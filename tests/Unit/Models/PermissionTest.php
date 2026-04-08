<?php

namespace Saniock\EvoAccess\Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Saniock\EvoAccess\Models\Permission;
use Saniock\EvoAccess\Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_actions_are_cast_to_array(): void
    {
        $perm = Permission::create([
            'name'    => 'orders.orders',
            'label'   => 'Order list',
            'module'  => 'orders',
            'actions' => ['view', 'update', 'export'],
        ]);

        $perm->refresh();

        $this->assertIsArray($perm->actions);
        $this->assertSame(['view', 'update', 'export'], $perm->actions);
    }

    public function test_is_orphaned_defaults_to_false(): void
    {
        $perm = Permission::create([
            'name'    => 'orders.payments',
            'label'   => 'Payments',
            'module'  => 'orders',
            'actions' => ['view'],
        ]);

        $this->assertFalse($perm->is_orphaned);
    }

    public function test_only_active_scope(): void
    {
        Permission::create([
            'name' => 'a.x', 'label' => 'A', 'module' => 'a', 'actions' => ['view'],
            'is_orphaned' => false,
        ]);
        Permission::create([
            'name' => 'a.y', 'label' => 'B', 'module' => 'a', 'actions' => ['view'],
            'is_orphaned' => true,
        ]);

        $this->assertCount(1, Permission::active()->get());
    }
}
