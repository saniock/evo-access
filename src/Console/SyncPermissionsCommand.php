<?php

namespace Saniock\EvoAccess\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Saniock\EvoAccess\Services\AuditLogger;
use Saniock\EvoAccess\Services\PermissionCatalog;
use Saniock\EvoAccess\Services\PermissionParsers\ParserRegistry;

/**
 * Discover, parse, and persist the permission catalog.
 *
 * Workflow:
 *   1. Read `config('evoAccess.permission_discovery')` for a list of
 *      discovery rules. Each rule is an array with `path`, `glob`,
 *      and `parser` keys.
 *   2. For every matched config file, derive the module slug from
 *      the parent folder name, require the file, and feed the result
 *      through the named parser (resolved via ParserRegistry).
 *   3. Register each emitted permission row into the in-memory
 *      PermissionCatalog.
 *   4. Call PermissionCatalog::syncToDatabase() to UPSERT the catalog
 *      into ea_permissions, flag orphaned rows, and write an audit
 *      entry.
 *
 * The catalog is populated ONLY inside this command — the service
 * provider does not scan configs at runtime. Run after every deploy
 * that adds or renames permissions in any host module.
 *
 *     php artisan evoaccess:sync-permissions
 */
class SyncPermissionsCommand extends Command
{
    protected $signature = 'evoaccess:sync-permissions';

    protected $description = 'Discover and sync the permission catalog to the ea_permissions table.';

    public function handle(
        PermissionCatalog $catalog,
        ParserRegistry $parsers,
        AuditLogger $audit,
    ): int {
        $discovery = (array) config('evoAccess.permission_discovery', []);

        if (empty($discovery)) {
            $this->line('No permission_discovery rules configured. Nothing to discover.');
            return $this->finishSync($catalog, $audit);
        }

        foreach ($discovery as $index => $rule) {
            $this->discoverRule($catalog, $parsers, $rule, $index);
        }

        return $this->finishSync($catalog, $audit);
    }

    private function discoverRule(
        PermissionCatalog $catalog,
        ParserRegistry $parsers,
        array $rule,
        int $index,
    ): void {
        $path = $rule['path'] ?? null;
        $glob = $rule['glob'] ?? null;
        $parserName = $rule['parser'] ?? null;

        if (!$path || !$glob || !$parserName) {
            $this->warn("Discovery rule #{$index} is missing path/glob/parser; skipping.");
            return;
        }

        if (!is_dir($path)) {
            $this->warn("Discovery rule #{$index} path not found: {$path}");
            return;
        }

        $parser = $parsers->get($parserName);
        if ($parser === null) {
            $this->warn("Discovery rule #{$index} references unknown parser '{$parserName}'; skipping.");
            return;
        }

        $files = glob(rtrim($path, '/') . '/' . $glob) ?: [];

        $this->line("[{$parserName}] scanning {$path}/{$glob}");

        foreach ($files as $file) {
            // Str::kebab converts PascalCase directory names to the
            // slug format evo-access permissions use:
            //   ContentHub → content-hub
            //   NovaPoshta → nova-poshta
            //   Competitors → competitors  (already lowercase, no change)
            $moduleSlug = Str::kebab(basename(dirname($file, 2)));

            /** @noinspection PhpIncludeInspection */
            $config = require $file;

            if (!is_array($config)) {
                $this->warn("  " . basename(dirname($file, 2)) . '/config/' . basename($file) . ': not an array, skipped');
                continue;
            }

            $permissions = $parser->extract($config, $moduleSlug);

            if (empty($permissions)) {
                $this->line(sprintf(
                    '  %s/config/%s: 0 permissions',
                    basename(dirname($file, 2)),
                    basename($file),
                ));
                continue;
            }

            $catalog->registerPermissions($moduleSlug, $permissions);
            $this->line(sprintf(
                '  %s/config/%s: %d permissions extracted',
                basename(dirname($file, 2)),
                basename($file),
                count($permissions),
            ));
        }
    }

    private function finishSync(PermissionCatalog $catalog, AuditLogger $audit): int
    {
        $result = $catalog->syncToDatabase();

        $this->info(sprintf(
            'Sync complete: created %d, updated %d, orphaned %d',
            $result['created'],
            $result['updated'],
            $result['orphaned'],
        ));

        $audit->logPermissionsSync(0, $result['created'], $result['updated'], $result['orphaned']);

        return self::SUCCESS;
    }
}
