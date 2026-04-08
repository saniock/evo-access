<?php

namespace Saniock\EvoAccess\Services;

use Illuminate\Support\Facades\DB;
use Saniock\EvoAccess\Models\Role;
use Saniock\EvoAccess\Models\UserRole;

/**
 * Resolves the effective permission set for a given user.
 *
 * Resolution order for a (user, permission, action) question:
 *   1. Superadmin short-circuit  — if the user's role is flagged
 *      is_system=1, return true immediately.
 *   2. User override (revoke)    — explicit deny wins over everything
 *      role-granted.
 *   3. User override (grant)     — explicit grant on top of role.
 *   4. Role grant                — ea_role_permission_actions row.
 *   5. Default                   — deny.
 *
 * All lookups for a given user are cached per-request so a single
 * page load doesn't re-hit the database for each canView/canEdit
 * call across BaseControllers and blade views.
 */
class PermissionResolver
{
    /**
     * Per-request cache: userId => [permission_name => [action => bool]]
     *
     * @var array<int, array<string, array<string, bool>>>
     */
    private array $cache = [];

    /**
     * Return the Role assigned to the given user, or null if unassigned.
     */
    public function roleOf(int $userId): ?Role
    {
        $userRole = UserRole::where('user_id', $userId)->first();
        if (!$userRole) {
            return null;
        }
        return Role::find($userRole->role_id);
    }

    /**
     * Is the given user assigned to a system (superadmin) role?
     */
    public function isSuperadmin(int $userId): bool
    {
        $role = $this->roleOf($userId);
        return $role !== null && $role->is_system === true;
    }

    /**
     * Is the given (permission, action) granted to the user?
     *
     * Loads the full permission map for the user on first call and
     * reuses it for subsequent calls in the same request.
     */
    public function userHas(int $userId, string $permission, string $action): bool
    {
        $map = $this->loadForUser($userId);

        if (isset($map['__is_system']) && $map['__is_system'] === true) {
            return true;
        }

        return $map[$permission][$action] ?? false;
    }

    /**
     * Invalidate cached permissions for a user (call after an admin
     * changes role/permission assignments via the matrix UI).
     */
    public function forgetUser(int $userId): void
    {
        unset($this->cache[$userId]);
    }

    /**
     * Invalidate the full per-request cache (e.g. on sync).
     */
    public function forgetAll(): void
    {
        $this->cache = [];
    }

    /**
     * Load the full [permission => [action => bool]] map for a user.
     *
     * Resolution:
     *   1. Look up user's role in ea_user_roles
     *   2. Short-circuit if role is_system → ['__is_system' => true]
     *   3. Fetch role grants from ea_role_permission_actions (non-orphaned)
     *   4. Fetch user overrides from ea_user_overrides (non-orphaned)
     *   5. Merge: role grants + override grants first, then revokes (revoke wins)
     *   6. Cache and return
     *
     * @return array<string, array<string, bool>>
     */
    public function loadForUser(int $userId): array
    {
        if (isset($this->cache[$userId])) {
            return $this->cache[$userId];
        }

        $userRole = DB::table('ea_user_roles')->where('user_id', $userId)->first();
        if (!$userRole) {
            return $this->cache[$userId] = [];
        }

        $role = DB::table('ea_roles')->where('id', $userRole->role_id)->first();
        if ($role && (int) $role->is_system === 1) {
            return $this->cache[$userId] = ['__is_system' => true];
        }

        // Load role grants (skip orphaned permissions)
        $roleGrants = DB::table('ea_role_permission_actions as rpa')
            ->join('ea_permissions as p', 'p.id', '=', 'rpa.permission_id')
            ->where('rpa.role_id', $userRole->role_id)
            ->where('p.is_orphaned', false)
            ->select('p.name as permission', 'rpa.action')
            ->get();

        $map = [];
        foreach ($roleGrants as $row) {
            $map[$row->permission][$row->action] = true;
        }

        // Apply user overrides — grants first, then revokes (revoke always wins)
        $overrides = DB::table('ea_user_overrides as uo')
            ->join('ea_permissions as p', 'p.id', '=', 'uo.permission_id')
            ->where('uo.user_id', $userId)
            ->where('p.is_orphaned', false)
            ->select('p.name as permission', 'uo.action', 'uo.mode')
            ->get();

        foreach ($overrides as $row) {
            if ($row->mode === 'grant') {
                $map[$row->permission][$row->action] = true;
            }
        }
        foreach ($overrides as $row) {
            if ($row->mode === 'revoke') {
                $map[$row->permission][$row->action] = false;
            }
        }

        return $this->cache[$userId] = $map;
    }

    /**
     * Return the list of actions that are effectively granted for a given
     * (user, permission) pair.
     *
     * @return string[]
     */
    public function effectiveActions(int $userId, string $permission): array
    {
        $map = $this->loadForUser($userId);
        $perPermission = $map[$permission] ?? [];

        return array_keys(array_filter($perPermission, fn($v) => $v === true));
    }
}
