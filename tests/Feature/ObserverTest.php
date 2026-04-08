<?php

namespace Saniock\EvoAccess\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Saniock\EvoAccess\Models\AuditLog;
use Saniock\EvoAccess\Models\Permission;
use Saniock\EvoAccess\Models\Role;
use Saniock\EvoAccess\Models\RolePermissionAction;
use Saniock\EvoAccess\Models\UserOverride;
use Saniock\EvoAccess\Tests\TestCase;

class ObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_creation_writes_audit(): void
    {
        Role::create(['name' => 'manager', 'label' => 'M']);

        $entry = AuditLog::where('action', 'role_created')->first();
        $this->assertNotNull($entry);
    }

    public function test_grant_creation_writes_audit(): void
    {
        $role = Role::create(['name' => 'manager', 'label' => 'M']);
        $perm = Permission::create([
            'name' => 'orders.orders', 'label' => 'L',
            'module' => 'orders', 'actions' => ['view'],
        ]);

        RolePermissionAction::create([
            'role_id' => $role->id,
            'permission_id' => $perm->id,
            'action' => 'view',
        ]);

        $entry = AuditLog::where('action', 'grant')->first();
        $this->assertNotNull($entry);
        $this->assertSame('view', $entry->new_value);
    }

    public function test_override_creation_writes_audit(): void
    {
        $perm = Permission::create([
            'name' => 'orders.orders', 'label' => 'L',
            'module' => 'orders', 'actions' => ['view', 'export'],
        ]);

        UserOverride::create([
            'user_id' => 7,
            'permission_id' => $perm->id,
            'action' => 'export',
            'mode' => 'grant',
            'reason' => 'test',
        ]);

        $entry = AuditLog::where('action', 'override_grant')->first();
        $this->assertNotNull($entry);
    }
}
