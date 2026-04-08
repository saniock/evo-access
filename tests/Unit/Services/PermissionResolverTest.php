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
}
