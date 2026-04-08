<?php

namespace Saniock\EvoAccess\Services;

use Saniock\EvoAccess\Contracts\PermissionCatalogInterface;

/**
 * In-memory registry of permissions declared by consumer projects.
 *
 * Consumer projects call registerPermissions() from their own service
 * provider boot() method, typically reading from their module config
 * files. The catalog itself knows nothing about the host project's
 * file layout — it just collects rows.
 *
 * A separate console command (SyncPermissionsCommand) reads the
 * in-memory catalog and upserts it into the ea_permissions table.
 */
class PermissionCatalog implements PermissionCatalogInterface
{
    /**
     * @var array<int, array{name: string, label: string, module: string, actions: array<int, string>}>
     */
    private array $permissions = [];

    public function registerPermissions(string $module, array $permissions): void
    {
        // TODO: validate each row (name non-empty, actions array, etc),
        //       normalize and append to $this->permissions.
    }

    public function all(): array
    {
        return $this->permissions;
    }

    public function find(string $name): ?array
    {
        foreach ($this->permissions as $row) {
            if ($row['name'] === $name) {
                return $row;
            }
        }

        return null;
    }

    public function syncToDatabase(): array
    {
        // TODO: upsert into ea_permissions, mark orphans, return counts.
        return ['created' => 0, 'updated' => 0, 'orphaned' => 0];
    }
}
