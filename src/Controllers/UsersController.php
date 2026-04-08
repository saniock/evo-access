<?php

namespace Saniock\EvoAccess\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Saniock\EvoAccess\Models\UserOverride;
use Saniock\EvoAccess\Models\UserRole;
use Saniock\EvoAccess\Services\PermissionResolver;

class UsersController extends BaseController
{
    public function index()
    {
        return view('evoAccess::users');
    }

    public function search(Request $request): JsonResponse
    {
        $q = $request->input('q', '');

        if (!Schema::hasTable('user_attributes')) {
            return response()->json([]);
        }

        $users = DB::table('user_attributes')
            ->where('role', '>', 0)
            ->where(function ($query) use ($q) {
                $query->where('fullname', 'like', "%{$q}%")
                    ->orWhere('internalKey', 'like', "%{$q}%");
            })
            ->limit(20)
            ->get(['internalKey as user_id', 'fullname']);

        return response()->json($users);
    }

    public function effective(int $user_id, PermissionResolver $resolver): JsonResponse
    {
        $userRole = UserRole::where('user_id', $user_id)->first();
        $map = $resolver->loadForUser($user_id);
        $overrides = UserOverride::where('user_id', $user_id)->get();

        return response()->json([
            'user_id'   => $user_id,
            'role_id'   => $userRole?->role_id,
            'effective' => $map,
            'overrides' => $overrides,
        ]);
    }

    public function assign(Request $request, int $user_id): JsonResponse
    {
        $data = $request->validate(['role_id' => 'required|integer|exists:ea_roles,id']);

        UserRole::updateOrCreate(
            ['user_id' => $user_id],
            ['role_id' => $data['role_id']],
        );

        return response()->json(['ok' => true]);
    }

    public function addOverride(Request $request, int $user_id): JsonResponse
    {
        $data = $request->validate([
            'permission_id' => 'required|integer|exists:ea_permissions,id',
            'action'        => 'required|string|max:32',
            'mode'          => 'required|in:grant,revoke',
            'reason'        => 'required|string|max:255',
        ]);

        // Remove conflicting override (PK doesn't include mode)
        UserOverride::where([
            'user_id'       => $user_id,
            'permission_id' => $data['permission_id'],
            'action'        => $data['action'],
        ])->delete();

        UserOverride::create($data + ['user_id' => $user_id]);

        return response()->json(['ok' => true], 201);
    }

    public function removeOverride(Request $request, int $user_id, int $override_id): JsonResponse
    {
        // Override has composite PK — the route parameter $override_id here is
        // a synthetic identifier passed back by the UI. The UI receives a hash
        // of (permission_id, action) and the controller decodes it. For the
        // initial implementation we accept permission_id + action via query
        // params and ignore $override_id.
        $permissionId = (int) $request->query('permission_id', 0);
        $action = (string) $request->query('action', '');

        if ($permissionId <= 0 || $action === '') {
            return response()->json(['error' => 'permission_id and action query params required'], 400);
        }

        UserOverride::where([
            'user_id'       => $user_id,
            'permission_id' => $permissionId,
            'action'        => $action,
        ])->delete();

        return response()->json(['ok' => true]);
    }
}
