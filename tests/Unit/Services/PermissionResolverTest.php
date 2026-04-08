<?php

namespace Saniock\EvoAccess\Tests\Unit\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Saniock\EvoAccess\Models\Permission;
use Saniock\EvoAccess\Models\Role;
use Saniock\EvoAccess\Models\RolePermissionAction;
use Saniock\EvoAccess\Models\UserRole;
use Saniock\EvoAccess\Services\PermissionResolver;
use Saniock\EvoAccess\Tests\TestCase;

class PermissionResolverTest extends TestCase
{
    use RefreshDatabase;

    private function resolver(): PermissionResolver
    {
        return $this->app->make(PermissionResolver::class);
    }

    // -----------------------------------------------------------------
    // Task 4.1: roleOf + isSuperadmin
    // -----------------------------------------------------------------

    public function test_role_of_returns_null_for_unassigned_user(): void
    {
        $this->assertNull($this->resolver()->roleOf(999));
    }

    public function test_role_of_returns_role_for_assigned_user(): void
    {
        $role = Role::create(['name' => 'manager', 'label' => 'M']);
        UserRole::create(['user_id' => 7, 'role_id' => $role->id]);

        $found = $this->resolver()->roleOf(7);
        $this->assertNotNull($found);
        $this->assertSame('manager', $found->name);
    }

    public function test_is_superadmin_true_for_system_role(): void
    {
        $superadmin = Role::where('name', 'superadmin')->firstOrFail();
        UserRole::create(['user_id' => 7, 'role_id' => $superadmin->id]);

        $this->assertTrue($this->resolver()->isSuperadmin(7));
    }

    public function test_is_superadmin_false_for_regular_role(): void
    {
        $role = Role::create(['name' => 'manager', 'label' => 'M']);
        UserRole::create(['user_id' => 7, 'role_id' => $role->id]);

        $this->assertFalse($this->resolver()->isSuperadmin(7));
    }

    public function test_is_superadmin_false_for_unassigned_user(): void
    {
        $this->assertFalse($this->resolver()->isSuperadmin(999));
    }

    // -----------------------------------------------------------------
    // Task 4.2: loadForUser
    // -----------------------------------------------------------------

    public function test_load_for_user_returns_empty_for_unassigned(): void
    {
        $this->assertSame([], $this->resolver()->loadForUser(999));
    }

    public function test_load_for_user_returns_is_system_marker_for_superadmin(): void
    {
        $superadmin = Role::where('name', 'superadmin')->firstOrFail();
        UserRole::create(['user_id' => 7, 'role_id' => $superadmin->id]);

        $map = $this->resolver()->loadForUser(7);
        $this->assertTrue($map['__is_system'] ?? false);
    }

    public function test_load_for_user_returns_role_grants(): void
    {
        $role = Role::create(['name' => 'manager', 'label' => 'M']);
        UserRole::create(['user_id' => 7, 'role_id' => $role->id]);

        $perm = Permission::create([
            'name' => 'orders.orders', 'label' => 'L',
            'module' => 'orders', 'actions' => ['view', 'update'],
        ]);

        RolePermissionAction::create([
            'role_id' => $role->id,
            'permission_id' => $perm->id,
            'action' => 'view',
        ]);
        RolePermissionAction::create([
            'role_id' => $role->id,
            'permission_id' => $perm->id,
            'action' => 'update',
        ]);

        $map = $this->resolver()->loadForUser(7);

        $this->assertTrue($map['orders.orders']['view']);
        $this->assertTrue($map['orders.orders']['update']);
    }

    public function test_load_for_user_skips_orphaned_permissions(): void
    {
        $role = Role::create(['name' => 'manager', 'label' => 'M']);
        UserRole::create(['user_id' => 7, 'role_id' => $role->id]);

        $perm = Permission::create([
            'name' => 'orders.deleted', 'label' => 'D',
            'module' => 'orders', 'actions' => ['view'],
            'is_orphaned' => true,
        ]);

        RolePermissionAction::create([
            'role_id' => $role->id,
            'permission_id' => $perm->id,
            'action' => 'view',
        ]);

        $map = $this->resolver()->loadForUser(7);
        $this->assertArrayNotHasKey('orders.deleted', $map);
    }
}
