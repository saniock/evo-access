<?php

namespace Saniock\EvoAccess\Services;

use Illuminate\Support\Facades\DB;
use Saniock\EvoAccess\Contracts\PermissionCatalogInterface;
use Saniock\EvoAccess\Models\Permission;

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

    public function __construct(
        private readonly PermissionResolver $resolver,
    ) {}

    public function registerPermissions(string $module, array $permissions): void
    {
        $this->validateModuleSlug($module);

        foreach ($permissions as $row) {
            $this->validatePermissionRow($module, $row);

            // Last-write-wins: remove any existing entry with the same name
            $this->permissions = array_values(array_filter(
                $this->permissions,
                fn($p) => $p['name'] !== $row['name']
            ));

            $this->permissions[] = [
                'name'    => $row['name'],
                'label'   => $row['label'],
                'module'  => $module,
                'actions' => array_values($row['actions']),
            ];
        }
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

    public function byModule(string $module): array
    {
        return array_values(array_filter(
            $this->permissions,
            fn($p) => $p['module'] === $module
        ));
    }

    public function modules(): array
    {
        $modules = array_unique(array_column($this->permissions, 'module'));
        sort($modules);
        return array_values($modules);
    }

    public function syncToDatabase(): array
    {
        $result = DB::transaction(function () {
            $created = 0;
            $updated = 0;

            $catalogNames = array_column($this->permissions, 'name');

            foreach ($this->permissions as $row) {
                $existing = Permission::where('name', $row['name'])->first();

                if ($existing) {
                    $existing->fill([
                        'label'       => $row['label'],
                        'module'      => $row['module'],
                        'actions'     => $row['actions'],
                        'is_orphaned' => false,
                    ]);

                    if ($existing->isDirty()) {
                        $existing->save();
                        $updated++;
                    }
                } else {
                    Permission::create([
                        'name'        => $row['name'],
                        'label'       => $row['label'],
                        'module'      => $row['module'],
                        'actions'     => $row['actions'],
                        'is_orphaned' => false,
                    ]);
                    $created++;
                }
            }

            // Mark anything in DB but not in catalog as orphaned
            $orphaned = Permission::query()
                ->whereNotIn('name', $catalogNames)
                ->where('is_orphaned', false)
                ->update(['is_orphaned' => true]);

            return [
                'created'  => $created,
                'updated'  => $updated,
                'orphaned' => $orphaned,
            ];
        });

        $this->resolver->forgetAll();

        return $result;
    }

    private function validateModuleSlug(string $module): void
    {
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $module) || strlen($module) > 64) {
            throw new \InvalidArgumentException(
                "Invalid module slug '$module' — must match ^[a-z][a-z0-9_]*$ and be ≤64 chars"
            );
        }
    }

    private function validatePermissionRow(string $module, array $row): void
    {
        $name = $row['name'] ?? '';
        if (!is_string($name) || $name === '' || strlen($name) > 128) {
            throw new \InvalidArgumentException("Permission name is required and must be ≤128 chars");
        }
        if (!preg_match('/^[a-z][a-z0-9_\-]*(\.[a-z][a-z0-9_\-]*)+$/', $name)) {
            throw new \InvalidArgumentException(
                "Permission name must match 'module.section[.subsection]' (got '$name')"
            );
        }
        if (!str_starts_with($name, $module . '.')) {
            throw new \InvalidArgumentException(
                "Permission '$name' must start with module slug '$module.'"
            );
        }

        $label = $row['label'] ?? '';
        if (!is_string($label) || $label === '' || strlen($label) > 255) {
            throw new \InvalidArgumentException(
                "Permission '$name' label is required and must be ≤255 chars"
            );
        }

        $actions = $row['actions'] ?? null;
        if (!is_array($actions) || empty($actions)) {
            throw new \InvalidArgumentException(
                "Permission '$name' must have at least one action"
            );
        }

        $seen = [];
        foreach ($actions as $action) {
            if (!is_string($action) || !preg_match('/^[a-z][a-z0-9_]*$/', $action) || strlen($action) > 32) {
                throw new \InvalidArgumentException(
                    "Permission '$name' has invalid action '$action' (must be lowercase snake_case, ≤32 chars)"
                );
            }
            if (isset($seen[$action])) {
                throw new \InvalidArgumentException(
                    "Permission '$name' has duplicate action '$action'"
                );
            }
            $seen[$action] = true;
        }
    }
}
