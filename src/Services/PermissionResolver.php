<?php

namespace Saniock\EvoAccess\Services;

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
     * Is the given (permission, action) granted to the user?
     *
     * Loads the full permission map for the user on first call and
     * reuses it for subsequent calls in the same request.
     */
    public function userHas(int $userId, string $permission, string $action): bool
    {
        $map = $this->loadForUser($userId);

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
     * TODO: actual implementation will:
     *   1. Look up user's role in ea_user_roles
     *   2. Short-circuit if role is_system
     *   3. Fetch role grants from ea_role_permission_actions
     *   4. Fetch user overrides from ea_user_overrides
     *   5. Merge: role grants + override grants − override revokes
     *   6. Cache and return
     *
     * @return array<string, array<string, bool>>
     */
    private function loadForUser(int $userId): array
    {
        if (isset($this->cache[$userId])) {
            return $this->cache[$userId];
        }

        return $this->cache[$userId] = [];
    }
}
