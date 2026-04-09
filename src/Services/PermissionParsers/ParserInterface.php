<?php

namespace Saniock\EvoAccess\Services\PermissionParsers;

/**
 * Contract for permission discovery parsers used by
 * SyncPermissionsCommand. Each parser knows how to walk a specific
 * config-file shape and emit a flat list of permission rows ready to
 * be fed into PermissionCatalog::registerPermissions().
 */
interface ParserInterface
{
    /**
     * Extract permissions from a parsed module config.
     *
     * @param  array  $config      The full array returned by the module's
     *                             config file (result of `require`).
     * @param  string $moduleSlug  Lowercased module folder name, e.g.
     *                             'orders'. Used as the first
     *                             segment of every generated permission
     *                             slug.
     * @return array<int, array{name: string, label: string, actions: array<int, string>}>
     */
    public function extract(array $config, string $moduleSlug): array;
}
