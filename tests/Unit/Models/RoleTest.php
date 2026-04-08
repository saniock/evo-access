<?php

namespace Saniock\EvoAccess\Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Saniock\EvoAccess\Models\Role;
use Saniock\EvoAccess\Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_a_role(): void
    {
        $role = Role::create([
            'name'        => 'manager',
            'label'       => 'Менеджер',
            'description' => 'Тестова роль',
        ]);

        $this->assertNotNull($role->id);
        $this->assertSame('manager', $role->name);
        $this->assertFalse($role->is_system);
    }

    public function test_superadmin_role_is_seeded_and_flagged(): void
    {
        $superadmin = Role::where('name', 'superadmin')->firstOrFail();
        $this->assertTrue($superadmin->is_system);
    }

    public function test_grants_relationship(): void
    {
        $role = Role::create(['name' => 'r1', 'label' => 'R1']);
        $this->assertCount(0, $role->grants);
    }
}
