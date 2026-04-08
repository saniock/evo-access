<?php

namespace Saniock\EvoAccess\Tests\Unit\Services;

use InvalidArgumentException;
use Saniock\EvoAccess\Services\PermissionCatalog;
use Saniock\EvoAccess\Tests\TestCase;

class PermissionCatalogTest extends TestCase
{
    public function test_can_register_a_valid_batch(): void
    {
        $catalog = new PermissionCatalog();

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

        (new PermissionCatalog())->registerPermissions('Orders', [
            ['name' => 'orders.x', 'label' => 'X', 'actions' => ['view']],
        ]);
    }

    public function test_validates_name_format(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/Permission name must match/");

        (new PermissionCatalog())->registerPermissions('orders', [
            ['name' => 'NoDot', 'label' => 'X', 'actions' => ['view']],
        ]);
    }

    public function test_validates_name_starts_with_module_prefix(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/must start with module slug/");

        (new PermissionCatalog())->registerPermissions('orders', [
            ['name' => 'finances.x', 'label' => 'X', 'actions' => ['view']],
        ]);
    }

    public function test_validates_label_required(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PermissionCatalog())->registerPermissions('orders', [
            ['name' => 'orders.x', 'label' => '', 'actions' => ['view']],
        ]);
    }

    public function test_validates_actions_non_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PermissionCatalog())->registerPermissions('orders', [
            ['name' => 'orders.x', 'label' => 'X', 'actions' => []],
        ]);
    }

    public function test_validates_action_format(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PermissionCatalog())->registerPermissions('orders', [
            ['name' => 'orders.x', 'label' => 'X', 'actions' => ['VIEW']],
        ]);
    }

    public function test_validates_no_duplicate_actions(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/duplicate action/');

        (new PermissionCatalog())->registerPermissions('orders', [
            ['name' => 'orders.x', 'label' => 'X', 'actions' => ['view', 'view']],
        ]);
    }

    public function test_duplicate_name_overwrites_with_warning(): void
    {
        $catalog = new PermissionCatalog();

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
}
