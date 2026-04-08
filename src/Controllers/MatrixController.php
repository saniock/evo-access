<?php

namespace Saniock\EvoAccess\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Saniock\EvoAccess\Models\RolePermissionAction;
use Saniock\EvoAccess\Services\PermissionCatalog;

class MatrixController extends BaseController
{
    public function index()
    {
        return view('evoAccess::matrix');
    }

    public function data(int $role_id, PermissionCatalog $catalog): JsonResponse
    {
        $grants = RolePermissionAction::where('role_id', $role_id)
            ->get()
            ->groupBy('permission_id');

        return response()->json([
            'permissions' => $catalog->all(),
            'grants'      => $grants,
        ]);
    }

    public function grant(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role_id'       => 'required|integer|exists:ea_roles,id',
            'permission_id' => 'required|integer|exists:ea_permissions,id',
            'action'        => 'required|string|max:32',
        ]);

        RolePermissionAction::firstOrCreate([
            'role_id'       => $data['role_id'],
            'permission_id' => $data['permission_id'],
            'action'        => $data['action'],
        ]);

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
