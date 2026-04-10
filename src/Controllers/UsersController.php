<?php

namespace Saniock\EvoAccess\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Saniock\EvoAccess\Models\RolePermissionAction;
use Saniock\EvoAccess\Models\UserOverride;
use Saniock\EvoAccess\Models\UserRole;
use Saniock\EvoAccess\Services\AccessService;
use Saniock\EvoAccess\Services\PermissionResolver;

class UsersController extends BaseController
{
    public function __construct(AccessService $access)
    {
        parent::__construct($access);
        $this->ensureAccess('access.users');
    }

    public function index()
    {
        return view('evoAccess::users');
    }

    public function data(): array
    {
        $userRoles = UserRole::with('role')->get()->keyBy('user_id');
        $userIds = $userRoles->keys()->all();

        // Role grant counts per role_id
        $roleGrantCounts = RolePermissionAction::query()
            ->join('ea_permissions', 'ea_permissions.id', '=', 'ea_role_permission_actions.permission_id')
            ->where('ea_permissions.is_orphaned', false)
            ->selectRaw('role_id, COUNT(*) as cnt')
            ->groupBy('role_id')
            ->pluck('cnt', 'role_id');

        // Override counts per user
        $overrideGrants = UserOverride::query()
            ->whereIn('user_id', $userIds)
            ->where('mode', 'grant')
            ->selectRaw('user_id, COUNT(*) as cnt')
            ->groupBy('user_id')
            ->pluck('cnt', 'user_id');

        $overrideRevokes = UserOverride::query()
            ->whereIn('user_id', $userIds)
            ->where('mode', 'revoke')
            ->selectRaw('user_id, COUNT(*) as cnt')
            ->groupBy('user_id')
            ->pluck('cnt', 'user_id');

        $resolver = $this->access->getResolver();

        $result = [];
        foreach ($userRoles as $userId => $ur) {
            $role = $ur->role;
            $roleGrants = $roleGrantCounts[$role->id] ?? 0;
            $oGrants = $overrideGrants[$userId] ?? 0;
            $oRevokes = $overrideRevokes[$userId] ?? 0;
            $effectiveCount = max(0, $roleGrants + $oGrants - $oRevokes);

            // Modules where user has effective grants
            $permMap = $resolver->loadForUser($userId);
            $modules = [];
            if (!isset($permMap['__is_system'])) {
                foreach ($permMap as $permName => $actions) {
                    $module = explode('.', $permName)[0];
                    if (!in_array($module, $modules) && collect($actions)->contains(true)) {
                        $modules[] = $module;
                    }
                }
            }

            $result[] = [
                'user_id' => $userId,
                'role_id' => $role->id,
                'role_name' => $role->name,
                'role_label' => $role->label,
                'is_system' => $role->is_system,
                'modules' => $modules,
                'effective_grant_count' => $effectiveCount,
                'override_grant_count' => $oGrants,
                'override_revoke_count' => $oRevokes,
            ];
        }

        return $result;
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

        // assigned_at is updated on every reassignment so the column
        // reflects when the user got their CURRENT role, not when the
        // row was first inserted (Schema::useCurrent() only fires on
        // insert, not on update).
        UserRole::updateOrCreate(
            ['user_id' => $user_id],
            [
                'role_id'     => $data['role_id'],
                'assigned_by' => $this->currentUserId(),
                'assigned_at' => now(),
            ],
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

        UserOverride::create($data + [
            'user_id'    => $user_id,
            'created_by' => $this->currentUserId(),
        ]);

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
