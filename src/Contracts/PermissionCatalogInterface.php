<?php

namespace Saniock\EvoAccess\Contracts;

/**
 * Catalog of permissions known to the application.
 *
 * Consumer projects register their permissions during service-provider
 * boot via registerPermissions(). The catalog is the single source of
 * truth for "what actions on what sections exist" and drives both the
 * admin matrix UI and the db sync command.
 */
interface PermissionCatalogInterface
{
    /**
     * Register a batch of permissions for a module.
     *
     * $module is a free-form grouping key used only for UI accordion
     * sectioning (e.g. 'orders', 'finances', 'analytics').
     *
     * $permissions is a list of rows shaped like:
     * [
     *     ['name'    => 'orders.orders',
     *      'label'   => 'Order list',
     *      'actions' => ['view', 'create', 'update', 'delete', 'refund', 'export']],
     *     ...
     * ]
     */
    public function registerPermissions(string $module, array $permissions): void;

    /**
     * Flat list of every registered permission across all modules.
     *
     * @return array<int, array{name: string, label: string, module: string, actions: array<int, string>}>
     */
    public function all(): array;

    /**
     * Look up one permission by name. Returns null if not registered.
     *
     * @return array{name: string, label: string, module: string, actions: array<int, string>}|null
     */
    public function find(string $name): ?array;

    /**
     * Permissions for one specific module, in the order they were
     * registered. Used by the admin matrix UI to render an accordion
     * section per module.
     *
     * @return array<int, array{name: string, label: string, module: string, actions: array<int, string>}>
     */
    public function byModule(string $module): array;

    /**
     * Distinct list of registered module slugs, sorted alphabetically.
     *
     * @return array<int, string>
     */
    public function modules(): array;

    /**
     * Persist the in-memory catalog to the `ea_permissions` table.
     *
     * Upserts by name, marks missing rows as orphaned (never deletes
     * outright — audit log needs historic names to stay resolvable).
     *
     * @return array{created: int, updated: int, orphaned: int}
     */
    public function syncToDatabase(): array;
}
