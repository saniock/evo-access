<?php

namespace Saniock\EvoAccess\Console;

use Illuminate\Console\Command;
use Saniock\EvoAccess\Services\PermissionCatalog;

/**
 * Persist the in-memory permission catalog to the ea_permissions table.
 *
 * Run after a deploy that adds or renames permissions in any host
 * module. Idempotent — upserts by name, never destructively deletes.
 *
 *     php artisan evoaccess:sync-permissions
 */
class SyncPermissionsCommand extends Command
{
    protected $signature = 'evoaccess:sync-permissions';

    protected $description = 'Sync the in-memory permission catalog to the ea_permissions table.';

    public function handle(PermissionCatalog $catalog): int
    {
        $result = $catalog->syncToDatabase();

        $this->info(sprintf(
            'Permission sync complete — created: %d, updated: %d, orphaned: %d',
            $result['created'],
            $result['updated'],
            $result['orphaned'],
        ));

        return self::SUCCESS;
    }
}
