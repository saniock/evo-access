<?php

namespace Saniock\EvoAccess\Services;

use Saniock\EvoAccess\Contracts\AccessServiceInterface;
use Saniock\EvoAccess\Exceptions\AccessDeniedException;

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
        return $this->resolver->userHas($userId, $permission, $action);
    }

    public function authorize(string $permission, string $action, int $userId): void
    {
        if (!$this->can($permission, $action, $userId)) {
            throw new AccessDeniedException(
                "Access denied: user $userId cannot $action on $permission",
                permission: $permission,
                action: $action,
                userId: $userId,
            );
        }
    }

    public function canView(array $menu, string $actionId, int $userId): bool
    {
        $permission = $this->resolveMenuPermission($menu, $actionId);
        if ($permission === null) {
            return true;  // Action ID not described in menu — allow (e.g. AJAX endpoints)
        }
        return $this->can($permission, 'view', $userId);
    }

    public function canEdit(array $menu, string $actionId, int $userId): bool
    {
        $permission = $this->resolveMenuPermission($menu, $actionId);
        if ($permission === null) {
            return true;
        }
        return $this->can($permission, 'update', $userId);
    }

    private function resolveMenuPermission(array $menu, string $actionId): ?string
    {
        foreach ($menu as $item) {
            if (($item['id'] ?? null) === $actionId) {
                return $item['permission'] ?? null;
            }
            if (!empty($item['items'])) {
                $nested = $this->resolveMenuPermission($item['items'], $actionId);
                if ($nested !== null) {
                    return $nested;
                }
            }
        }
        return null;
    }

    public function filterMenu(array $menu, int $userId): array
    {
        $out = [];

        foreach ($menu as $item) {
            // Has children? Recurse first.
            if (!empty($item['items'])) {
                $children = $this->filterMenu($item['items'], $userId);
                if (empty($children)) {
                    continue;  // group is empty after filtering — drop it
                }
                $copy = $item;
                $copy['items'] = $children;
                $out[] = $copy;
                continue;
            }

            // Leaf item — check view permission
            $permission = $item['permission'] ?? null;
            if ($permission === null) {
                $out[] = $item;  // No permission tag → always visible
                continue;
            }

            if ($this->can($permission, 'view', $userId)) {
                $out[] = $item;
            }
        }

        return $out;
    }

    public function actionsFor(string $permission, int $userId): array
    {
        // TODO: load catalog entry, produce ['view'=>bool,'update'=>bool,...]
        return [];
    }
}
