<?php

namespace Saniock\EvoAccess\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Saniock\EvoAccess\Models\Role;
use Saniock\EvoAccess\Models\RolePermissionAction;
use Saniock\EvoAccess\Models\UserRole;
use Saniock\EvoAccess\Services\AccessService;
use Saniock\EvoAccess\Services\AuditLogger;

class RolesController extends BaseController
{
    public function __construct(AccessService $access)
    {
        parent::__construct($access);
        $this->ensureAccess('access.roles');
    }

    /**
     * Render the Roles admin page (HTML). The page itself is empty
     * markup + a small JS bootstrapper that fetches data() below.
     *
     * Mirrors the Matrix/Users/Audit controllers, where index() always
     * returns a Blade view and a sibling data()/search() endpoint
     * returns the JSON payload.
     */
    public function index()
    {
        return view('evoAccess::roles');
    }

    /**
     * JSON endpoint backing the roles list — consumed by roles.blade.php
     * via eaFetch('/roles/data'). Kept separate from index() so the
     * page URL serves HTML and never returns raw JSON.
     */
    public function data(): JsonResponse
    {
        $roles = Role::query()
            ->withCount(['userAssignments as user_count'])
            ->orderBy('name')
            ->get(['id', 'name', 'label', 'description', 'is_system', 'created_at', 'updated_at']);

        return response()->json($roles);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:64|regex:/^[a-z][a-z0-9_]*$/|unique:ea_roles,name',
            'label'       => 'required|string|max:128',
            'description' => 'nullable|string|max:255',
        ]);

        $role = Role::create($data + [
            'is_system'  => false,
            'created_by' => $this->currentUserId(),
        ]);

        return response()->json($role, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        if ($role->is_system) {
            return response()->json(['error' => 'System role cannot be modified'], 403);
        }

        $data = $request->validate([
            'label'       => 'sometimes|required|string|max:128',
            'description' => 'sometimes|nullable|string|max:255',
        ]);

        $role->update($data);

        return response()->json($role);
    }

    public function destroy(int $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        if ($role->is_system) {
            return response()->json(['error' => 'System role cannot be deleted'], 403);
        }

        try {
            $role->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json(['error' => 'Cannot delete role with assigned users — reassign them first'], 409);
        }

        return response()->json(['ok' => true]);
    }

    public function reassignAndDelete(Request $request, int $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        if ($role->is_system) {
            return response()->json(['error' => 'System role cannot be deleted'], 403);
        }

        $newRoleId = (int) $request->input('new_role_id');
        $newRole = Role::findOrFail($newRoleId);
        $actorId = $this->currentUserId();
        $audit = app(AuditLogger::class);

        DB::transaction(function () use ($role, $newRole, $actorId, $audit) {
            $assignments = UserRole::where('role_id', $role->id)->get();

            foreach ($assignments as $assignment) {
                $audit->logUserRoleChanged($actorId, $assignment->user_id, $role->id, $newRole->id);
                $assignment->update([
                    'role_id' => $newRole->id,
                    'assigned_by' => $actorId,
                    'assigned_at' => now(),
                ]);
            }

            $role->delete();
        });

        return response()->json(['ok' => true]);
    }

    public function clone(Request $request, int $id): JsonResponse
    {
        $source = Role::findOrFail($id);
        $actor = $this->currentUserId();

        // Accept caller-provided name/label; fall back to auto-generated
        // "{source}_copy[_N]" if not supplied, so API stays backwards-compatible.
        $providedName = $request->input('name');
        $providedLabel = $request->input('label');

        if (is_string($providedName) && $providedName !== '') {
            $data = $request->validate([
                'name'  => 'required|string|max:64|regex:/^[a-z][a-z0-9_]*$/|unique:ea_roles,name',
                'label' => 'nullable|string|max:128',
            ]);
            $newName = $data['name'];
            $newLabel = $data['label'] ?? $source->label . ' (copy)';
        } else {
            $newName = $source->name . '_copy';
            $i = 1;
            while (Role::where('name', $newName)->exists()) {
                $i++;
                $newName = $source->name . '_copy_' . $i;
            }
            $newLabel = is_string($providedLabel) && $providedLabel !== ''
                ? $providedLabel
                : $source->label . ' (copy)';
        }

        $newRole = Role::create([
            'name'        => $newName,
            'label'       => $newLabel,
            'description' => $source->description,
            'is_system'   => false,
            'created_by'  => $actor,
        ]);

        // Copy all grants. The cloned grants are conceptually a NEW
        // grant action by the current actor (rather than a passive
        // mirror of the source), so granted_by + granted_at are set
        // to the cloning actor / now — keeps the audit trail honest.
        $sourceGrants = RolePermissionAction::where('role_id', $source->id)->get();
        foreach ($sourceGrants as $grant) {
            RolePermissionAction::create([
                'role_id'       => $newRole->id,
                'permission_id' => $grant->permission_id,
                'action'        => $grant->action,
                'granted_by'    => $actor,
                'granted_at'    => now(),
            ]);
        }

        return response()->json($newRole, 201);
    }
}
