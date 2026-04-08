<?php

namespace Saniock\EvoAccess\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Saniock\EvoAccess\Models\Permission;
use Saniock\EvoAccess\Services\PermissionCatalog;
use Saniock\EvoAccess\Tests\TestCase;

class SyncPermissionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_persists_in_memory_catalog_to_db(): void
    {
        $catalog = $this->app->make(PermissionCatalog::class);
        $catalog->registerPermissions('orders', [
            ['name' => 'orders.orders', 'label' => 'L', 'actions' => ['view']],
            ['name' => 'orders.payments', 'label' => 'P', 'actions' => ['view', 'update']],
        ]);

        // Count all catalog entries (includes SP-registered permissions like access.admin).
        $expectedCount = count($catalog->all());

        $this->artisan('evoaccess:sync-permissions')
            ->expectsOutputToContain("created {$expectedCount}")
            ->assertSuccessful();

        $this->assertSame($expectedCount, Permission::count());
    }
}
