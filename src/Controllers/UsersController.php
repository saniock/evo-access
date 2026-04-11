<?php

namespace Saniock\EvoAccess\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Saniock\EvoAccess\Models\Permission;
use Saniock\EvoAccess\Models\RolePermissionAction;
use Saniock\EvoAccess\Models\UserOverride;
use Saniock\EvoAccess\Models\UserRole;
use Saniock\EvoAccess\Services\AccessService;
use Saniock\EvoAccess\Services\AuditLogger;
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
        // All EVO managers (role > 0) — include users with NO evo-access role
        // so admins can assign roles to new managers from the Users page.
        $managers = collect();
        if (Schema::hasTable('user_attributes')) {
            $managers = DB::table('user_attributes')
                ->where('role', '>', 0)
                ->orderBy('fullname')
                ->get(['internalKey as user_id', 'fullname as user_name'])
                ->keyBy('user_id');
        }

        $userRoles = UserRole::with('role')->get()->keyBy('user_id');
        $userIds = $managers->keys()->all();

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
        foreach ($managers as $userId => $mgr) {
            $ur = $userRoles->get($userId);
            $role = $ur?->role;

            $roleGrants = $role ? ($roleGrantCounts[$role->id] ?? 0) : 0;
            $oGrants = $overrideGrants[$userId] ?? 0;
            $oRevokes = $overrideRevokes[$userId] ?? 0;
            $effectiveCount = max(0, $roleGrants + $oGrants - $oRevokes);

            // Modules where user has effective grants (only if user has a role)
            $modules = [];
            if ($role) {
                $permMap = $resolver->loadForUser($userId);
                if (!isset($permMap['__is_system'])) {
                    foreach ($permMap as $permName => $actions) {
                        $module = explode('.', $permName)[0];
                        if (!in_array($module, $modules) && collect($actions)->contains(true)) {
                            $modules[] = $module;
                        }
                    }
                }
            }

            $result[] = [
                'user_id' => (int) $userId,
                'user_name' => $mgr->user_name ?: 'Manager #' . $userId,
                'role_id' => $role?->id,
                'role_name' => $role?->name,
                'role_label' => $role?->label,
                'is_system' => $role?->is_system ?? false,
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

    public function matrix(int $userId): array
    {
        $userRole = UserRole::with('role')->find($userId);
        $role = $userRole?->role;

        // All active permissions, grouped by module
        $permissions = Permission::active()->orderBy('module')->orderBy('name')->get();

        // Role grants for this user's role
        $roleGrants = [];
        if ($role && !$role->is_system) {
            $roleGrants = RolePermissionAction::query()
                ->where('role_id', $role->id)
                ->get()
                ->groupBy('permission_id')
                ->map(fn ($rows) => $rows->pluck('action')->all())
                ->all();
        }

        // User overrides
        $overrides = UserOverride::query()
            ->where('user_id', $userId)
            ->get()
            ->groupBy('permission_id')
            ->map(fn ($rows) => $rows->map(fn ($o) => [
                'action' => $o->action,
                'mode'   => $o->mode,
                'reason' => $o->reason,
            ])->all())
            ->all();

        // Group by module
        $modules = [];
        foreach ($permissions->groupBy('module') as $moduleName => $modulePerms) {
            $permsData = [];
            foreach ($modulePerms as $perm) {
                $permsData[] = [
                    'id'          => $perm->id,
                    'name'        => $perm->name,
                    'label'       => $perm->label,
                    'actions'     => $perm->actions,
                    'role_grants' => $roleGrants[$perm->id] ?? [],
                    'overrides'   => $overrides[$perm->id] ?? [],
                ];
            }
            $modules[] = [
                'module'      => $moduleName,
                'permissions' => $permsData,
            ];
        }

        return [
            'user_id' => $userId,
            'role'    => $role ? [
                'id'        => $role->id,
                'name'      => $role->name,
                'label'     => $role->label,
                'is_system' => $role->is_system,
            ] : null,
            'modules' => $modules,
        ];
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

    /**
     * Batch-replace all overrides for a user in a single DB transaction.
     *
     * Optionally reassigns role if role_id differs from current.
     * Logs each individual add/remove to audit.
     * Used by the Save button in the Webix user matrix popup.
     */
    public function batchOverrides(Request $request, int $userId): array
    {
        $roleId = (int) $request->input('role_id');
        $overrides = $request->input('overrides', []);
        $actorId = $this->currentUserId();
        $audit = app(AuditLogger::class);

        DB::transaction(function () use ($userId, $roleId, $overrides, $actorId, $audit) {
            // 1. Reassign role if changed
            $currentRole = UserRole::find($userId);
            if ($currentRole && $currentRole->role_id !== $roleId) {
                $audit->logUserRoleChanged($actorId, $userId, $currentRole->role_id, $roleId);
                $currentRole->update([
                    'role_id'     => $roleId,
                    'assigned_by' => $actorId,
                    'assigned_at' => now(),
                ]);
            } elseif (!$currentRole && $roleId) {
                UserRole::create([
                    'user_id'     => $userId,
                    'role_id'     => $roleId,
                    'assigned_by' => $actorId,
                    'assigned_at' => now(),
                ]);
                $audit->logUserAssigned($actorId, $userId, $roleId);
            }

            // 2. Existing overrides for diff
            $existing = UserOverride::where('user_id', $userId)->get();

            // 3. Delete all existing
            UserOverride::where('user_id', $userId)->delete();

            // 4. Insert new
            foreach ($overrides as $o) {
                UserOverride::create([
                    'user_id'       => $userId,
                    'permission_id' => (int) $o['permission_id'],
                    'action'        => $o['action'],
                    'mode'          => $o['mode'],
                    'reason'        => $o['reason'] ?? null,
                    'created_by'    => $actorId,
                ]);
            }

            // 5. Audit diff
            $existingKeys = $existing->map(fn ($e) => "{$e->permission_id}:{$e->action}:{$e->mode}")->all();
            $newKeys = collect($overrides)->map(fn ($o) => "{$o['permission_id']}:{$o['action']}:{$o['mode']}")->all();

            foreach (array_diff($newKeys, $existingKeys) as $added) {
                [$permId, $action, $mode] = explode(':', $added);
                $audit->logOverrideAdded($actorId, $userId, (int) $permId, $action, $mode, null);
            }
            foreach (array_diff($existingKeys, $newKeys) as $removed) {
                [$permId, $action] = explode(':', $removed);
                $audit->logOverrideRemoved($actorId, $userId, (int) $permId, $action);
            }
        });

        $this->access->getResolver()->forgetUser($userId);

        return ['success' => true];
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
