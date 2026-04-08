<?php

namespace Saniock\EvoAccess\Services;

use Saniock\EvoAccess\Contracts\AccessServiceInterface;

/**
 * Main entry point for access checks.
 *
 * Coordinates the PermissionResolver (computes effective permissions
 * for a user) and the PermissionCatalog (knows what permissions/actions
 * exist). Stateless itself — all caching lives in the resolver.
 *
 * The old EvolutionCMS\Ddaudio\Services\ModuleAccess class will become
 * a thin facade delegating to this service once the package lands in
 * the host project.
 */
class AccessService implements AccessServiceInterface
{
    public function __construct(
        private readonly PermissionCatalog $catalog,
        private readonly PermissionResolver $resolver,
    ) {
    }

    public function can(string $permission, string $action, int $userId): bool
    {
        // TODO: delegate to $this->resolver->userHas($userId, $permission, $action);
        return false;
    }

    public function canView(array $menu, string $actionId, int $userId): bool
    {
        // TODO: resolve menu item → permission name → $this->can(..., 'view', ...)
        return false;
    }

    public function canEdit(array $menu, string $actionId, int $userId): bool
    {
        // TODO: resolve menu item → permission name → $this->can(..., 'update', ...)
        return false;
    }

    public function filterMenu(array $menu, int $userId): array
    {
        // TODO: walk tree, drop items with no 'view' grant, collapse empty groups.
        return [];
    }

    public function actionsFor(string $permission, int $userId): array
    {
        // TODO: load catalog entry, produce ['view'=>bool,'update'=>bool,...]
        return [];
    }
}
