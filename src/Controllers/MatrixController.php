<?php

namespace Saniock\EvoAccess\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Saniock\EvoAccess\Models\Permission;
use Saniock\EvoAccess\Models\Role;
use Saniock\EvoAccess\Models\RolePermissionAction;
use Saniock\EvoAccess\Services\AccessService;

class MatrixController extends BaseController
{
    public function __construct(AccessService $access)
    {
        parent::__construct($access);
        $this->ensureAccess('access.roles');
    }

    public function index()
    {
        return view('evoAccess::matrix');
    }

    /**
     * Return the full permission matrix payload for a single role:
     * every active (non-orphaned) permission with its module/label/
     * available actions, plus the subset of grants currently on this
     * role keyed by permission_id.
     *
     * Permissions are pulled from the DB (not the in-memory catalog)
     * so each row carries an `id` that the JS can match against the
     * `grants` map and use as the `permission_id` payload for grant/
     * revoke calls.
     */
    public function data(int $role_id): JsonResponse
    {
        $role = Role::findOrFail($role_id);

        $permissions = Permission::active()
            ->orderBy('module')
            ->orderBy('name')
            ->get(['id', 'name', 'label', 'module', 'actions']);

        $grants = RolePermissionAction::where('role_id', $role_id)
            ->get(['permission_id', 'action'])
            ->groupBy('permission_id');

        // Build a module_slug => module_label map from the top-level
        // "{module}.{module}" permissions so the UI can display
        // human-readable module names in the matrix grid.
        $moduleLabels = [];
        foreach ($permissions as $perm) {
            if ($perm->name === $perm->module . '.' . $perm->module) {
                $moduleLabels[$perm->module] = $perm->label;
            }
        }

        return response()->json([
            'role'          => [
                'id'        => $role->id,
                'name'      => $role->name,
                'label'     => $role->label,
                'is_system' => (bool) $role->is_system,
            ],
            'permissions'   => $permissions,
            'grants'        => $grants,
            'module_labels' => (object) $moduleLabels,
        ]);
    }

    public function grant(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role_id'       => 'required|integer|exists:ea_roles,id',
            'permission_id' => 'required|integer|exists:ea_permissions,id',
            'action'        => 'required|string|max:32',
        ]);

        // firstOrCreate args: [search] then [extra values applied on
        // CREATE only, not on existing rows] — keeps granted_by/at on
        // the original grant if the row already exists.
        RolePermissionAction::firstOrCreate(
            [
                'role_id'       => $data['role_id'],
                'permission_id' => $data['permission_id'],
                'action'        => $data['action'],
            ],
            [
                'granted_by' => $this->currentUserId(),
                'granted_at' => now(),
            ],
        );

        return response()->json(['ok' => true]);
    }

    public function revoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role_id'       => 'required|integer',
            'permission_id' => 'required|integer',
            'action'        => 'required|string|max:32',
        ]);

        RolePermissionAction::where($data)->delete();

        return response()->json(['ok' => true]);
    }
}
