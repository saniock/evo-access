<?php

namespace Saniock\EvoAccess\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Saniock\EvoAccess\Models\Role;
use Saniock\EvoAccess\Models\RolePermissionAction;

class RolesController extends BaseController
{
    public function index(): JsonResponse
    {
        $roles = Role::query()
            ->withCount('userAssignments')
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

        $role = Role::create($data + ['is_system' => false]);

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

    public function clone(int $id): JsonResponse
    {
        $source = Role::findOrFail($id);

        $newName = $source->name . '_copy';
        $i = 1;
        while (Role::where('name', $newName)->exists()) {
            $i++;
            $newName = $source->name . '_copy_' . $i;
        }

        $newRole = Role::create([
            'name'        => $newName,
            'label'       => $source->label . ' (copy)',
            'description' => $source->description,
            'is_system'   => false,
        ]);

        // Copy all grants
        $sourceGrants = RolePermissionAction::where('role_id', $source->id)->get();
        foreach ($sourceGrants as $grant) {
            RolePermissionAction::create([
                'role_id'       => $newRole->id,
                'permission_id' => $grant->permission_id,
                'action'        => $grant->action,
            ]);
        }

        return response()->json($newRole, 201);
    }
}
