<?php

namespace Saniock\EvoAccess\Contracts;

/**
 * Public contract for the access-control service.
 *
 * Consumer modules depend on this interface, not on AccessService
 * directly, so the implementation can evolve without breaking
 * BaseController call-sites across the host project.
 */
interface AccessServiceInterface
{
    /**
     * Is the given user allowed to perform $action on $permission?
     *
     * Canonical access check. All other helpers (canView/canEdit)
     * are shorthand wrappers around this method.
     */
    public function can(string $permission, string $action, int $userId): bool;

    /**
     * Assert that the user can perform $action on $permission.
     *
     * Throws AccessDeniedException if not allowed.
     *
     * @throws \Saniock\EvoAccess\Exceptions\AccessDeniedException
     */
    public function authorize(string $permission, string $action, int $userId): void;

    /**
     * Shorthand: can the user view the section identified by $actionId
     * in the given (module-local) menu tree?
     */
    public function canView(array $menu, string $actionId, int $userId): bool;

    /**
     * Shorthand: can the user edit (i.e. perform 'update' action) the
     * section identified by $actionId in the given menu tree?
     */
    public function canEdit(array $menu, string $actionId, int $userId): bool;

    /**
     * Return the given menu tree with items the user cannot view
     * removed. Empty groups (whose children all got filtered out)
     * are removed as well.
     */
    public function filterMenu(array $menu, int $userId): array;

    /**
     * Return the map of per-action booleans for the given permission
     * and user, suitable for passing into Blade views as a canActions
     * payload:  ['view' => true, 'update' => false, 'export' => false].
     *
     * @return array<string, bool>
     */
    public function actionsFor(string $permission, int $userId): array;

    /**
     * Convenience: forwards to PermissionCatalog::registerPermissions().
     */
    public function registerPermissions(string $module, array $permissions): void;
}
