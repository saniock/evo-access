<?php

namespace Saniock\EvoAccess\Tests\Unit\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Saniock\EvoAccess\Models\Permission;
use Saniock\EvoAccess\Services\PermissionCatalog;
use Saniock\EvoAccess\Tests\TestCase;

class PermissionCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_register_a_valid_batch(): void
    {
        $catalog = new PermissionCatalog($this->app->make(\Saniock\EvoAccess\Services\PermissionResolver::class));

        $catalog->registerPermissions('orders', [
            ['name' => 'orders.orders',   'label' => 'Order list', 'actions' => ['view', 'update']],
            ['name' => 'orders.payments', 'label' => 'Payments',   'actions' => ['view']],
        ]);

        $this->assertCount(2, $catalog->all());
    }

    public function test_validates_module_slug_format(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/module slug/i');

        new PermissionCatalog($this->app->make(\Saniock\EvoAccess\Services\PermissionResolver::class))->registerPermissions('Orders', [
            ['name' => 'orders.x', 'label' => 'X', 'actions' => ['view']],
        ]);
    }

    public function test_validates_name_format(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/Permission name must match/");

        new PermissionCatalog($this->app->make(\Saniock\EvoAccess\Services\PermissionResolver::class))->registerPermissions('orders', [
            ['name' => 'NoDot', 'label' => 'X', 'actions' => ['view']],
        ]);
    }

    public function test_validates_name_starts_with_module_prefix(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/must start with module slug/");

        new PermissionCatalog($this->app->make(\Saniock\EvoAccess\Services\PermissionResolver::class))->registerPermissions('orders', [
            ['name' => 'finances.x', 'label' => 'X', 'actions' => ['view']],
        ]);
    }

    public function test_validates_label_required(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PermissionCatalog($this->app->make(\Saniock\EvoAccess\Services\PermissionResolver::class))->registerPermissions('orders', [
            ['name' => 'orders.x', 'label' => '', 'actions' => ['view']],
        ]);
    }

    public function test_validates_actions_non_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PermissionCatalog($this->app->make(\Saniock\EvoAccess\Services\PermissionResolver::class))->registerPermissions('orders', [
            ['name' => 'orders.x', 'label' => 'X', 'actions' => []],
        ]);
    }

    public function test_validates_action_format(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PermissionCatalog($this->app->make(\Saniock\EvoAccess\Services\PermissionResolver::class))->registerPermissions('orders', [
            ['name' => 'orders.x', 'label' => 'X', 'actions' => ['VIEW']],
        ]);
    }

    public function test_validates_no_duplicate_actions(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/duplicate action/');

        new PermissionCatalog($this->app->make(\Saniock\EvoAccess\Services\PermissionResolver::class))->registerPermissions('orders', [
            ['name' => 'orders.x', 'label' => 'X', 'actions' => ['view', 'view']],
        ]);
    }

    public function test_duplicate_name_overwrites_with_warning(): void
    {
        $catalog = new PermissionCatalog($this->app->make(\Saniock\EvoAccess\Services\PermissionResolver::class));

        $catalog->registerPermissions('orders', [
            ['name' => 'orders.x', 'label' => 'A', 'actions' => ['view']],
        ]);

        $catalog->registerPermissions('orders', [
            ['name' => 'orders.x', 'label' => 'B', 'actions' => ['view', 'update']],
        ]);

        $found = $catalog->find('orders.x');
        $this->assertSame('B', $found['label']);
        $this->assertSame(['view', 'update'], $found['actions']);
    }

    public function test_find_returns_null_for_unknown(): void
    {
        $catalog = new PermissionCatalog($this->app->make(\Saniock\EvoAccess\Services\PermissionResolver::class));
        $this->assertNull($catalog->find('nope.never'));
    }

    public function test_by_module_returns_only_module_rows(): void
    {
        $catalog = new PermissionCatalog($this->app->make(\Saniock\EvoAccess\Services\PermissionResolver::class));
        $catalog->registerPermissions('orders', [
            ['name' => 'orders.x', 'label' => 'X', 'actions' => ['view']],
            ['name' => 'orders.y', 'label' => 'Y', 'actions' => ['view']],
        ]);
        $catalog->registerPermissions('finances', [
            ['name' => 'finances.z', 'label' => 'Z', 'actions' => ['view']],
        ]);

        $orders = $catalog->byModule('orders');
        $this->assertCount(2, $orders);
    }

    public function test_modules_returns_unique_sorted_list(): void
    {
        $catalog = new PermissionCatalog($this->app->make(\Saniock\EvoAccess\Services\PermissionResolver::class));
        $catalog->registerPermissions('orders', [
            ['name' => 'orders.x', 'label' => 'X', 'actions' => ['view']],
        ]);
        $catalog->registerPermissions('finances', [
            ['name' => 'finances.x', 'label' => 'X', 'actions' => ['view']],
        ]);
        $catalog->registerPermissions('analytics', [
            ['name' => 'analytics.x', 'label' => 'X', 'actions' => ['view']],
        ]);

        $this->assertSame(['analytics', 'finances', 'orders'], $catalog->modules());
    }

    public function test_sync_creates_new_permissions(): void
    {
        $catalog = new PermissionCatalog($this->app->make(\Saniock\EvoAccess\Services\PermissionResolver::class));
        $catalog->registerPermissions('orders', [
            ['name' => 'orders.orders',   'label' => 'Order list', 'actions' => ['view', 'update']],
            ['name' => 'orders.payments', 'label' => 'Payments',   'actions' => ['view']],
        ]);

        $result = $catalog->syncToDatabase();

        $this->assertSame(2, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(0, $result['orphaned']);
        $this->assertSame(2, Permission::count());
    }

    public function test_sync_updates_existing_permissions(): void
    {
        Permission::create([
            'name'    => 'orders.orders',
            'label'   => 'Old label',
            'module'  => 'orders',
            'actions' => ['view'],
        ]);

        $catalog = new PermissionCatalog($this->app->make(\Saniock\EvoAccess\Services\PermissionResolver::class));
        $catalog->registerPermissions('orders', [
            ['name' => 'orders.orders', 'label' => 'New label', 'actions' => ['view', 'update']],
        ]);

        $result = $catalog->syncToDatabase();

        $this->assertSame(1, $result['updated']);
        $perm = Permission::where('name', 'orders.orders')->first();
        $this->assertSame('New label', $perm->label);
        $this->assertSame(['view', 'update'], $perm->actions);
    }

    public function test_sync_marks_orphans(): void
    {
        Permission::create([
            'name'    => 'orders.removed',
            'label'   => 'Removed',
            'module'  => 'orders',
            'actions' => ['view'],
        ]);

        $catalog = new PermissionCatalog($this->app->make(\Saniock\EvoAccess\Services\PermissionResolver::class));
        $catalog->registerPermissions('orders', [
            ['name' => 'orders.orders', 'label' => 'Order list', 'actions' => ['view']],
        ]);

        $result = $catalog->syncToDatabase();

        $this->assertSame(1, $result['orphaned']);
        $orphaned = Permission::where('name', 'orders.removed')->first();
        $this->assertTrue($orphaned->is_orphaned);
    }

    public function test_sync_unflags_orphan_when_re_registered(): void
    {
        Permission::create([
            'name'        => 'orders.x',
            'label'       => 'X',
            'module'      => 'orders',
            'actions'     => ['view'],
            'is_orphaned' => true,
        ]);

        $catalog = new PermissionCatalog($this->app->make(\Saniock\EvoAccess\Services\PermissionResolver::class));
        $catalog->registerPermissions('orders', [
            ['name' => 'orders.x', 'label' => 'X', 'actions' => ['view']],
        ]);

        $catalog->syncToDatabase();

        $perm = Permission::where('name', 'orders.x')->first();
        $this->assertFalse($perm->is_orphaned);
    }

    public function test_validates_name_rejects_consecutive_dots(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/Permission name must match/");

        new PermissionCatalog($this->app->make(\Saniock\EvoAccess\Services\PermissionResolver::class))->registerPermissions('orders', [
            ['name' => 'orders..double', 'label' => 'X', 'actions' => ['view']],
        ]);
    }

    public function test_validates_name_rejects_digit_leading_segment(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/Permission name must match/");

        new PermissionCatalog($this->app->make(\Saniock\EvoAccess\Services\PermissionResolver::class))->registerPermissions('orders', [
            ['name' => 'orders.1invalid', 'label' => 'X', 'actions' => ['view']],
        ]);
    }

    public function test_validates_name_rejects_underscore_leading_segment(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/Permission name must match/");

        new PermissionCatalog($this->app->make(\Saniock\EvoAccess\Services\PermissionResolver::class))->registerPermissions('orders', [
            ['name' => 'orders._invalid', 'label' => 'X', 'actions' => ['view']],
        ]);
    }

    public function test_register_permissions_accepts_dashes_in_name_segments(): void
    {
        $catalog = new PermissionCatalog($this->app->make(\Saniock\EvoAccess\Services\PermissionResolver::class));

        $catalog->registerPermissions('orders', [
            [
                'name'    => 'orders.sales.gift-cards',
                'label'   => 'Orders — Sales gift cards',
                'actions' => ['view', 'edit'],
            ],
        ]);

        $this->assertNotNull($catalog->find('orders.sales.gift-cards'));
    }

    public function test_register_permissions_rejects_leading_dash_in_segment(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Permission name must match/');

        $catalog = new PermissionCatalog($this->app->make(\Saniock\EvoAccess\Services\PermissionResolver::class));

        $catalog->registerPermissions('foo', [
            [
                'name'    => 'foo.-bar',
                'label'   => 'Invalid leading dash',
                'actions' => ['view'],
            ],
        ]);
    }
}
