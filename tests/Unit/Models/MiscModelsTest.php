<?php

namespace Saniock\EvoAccess\Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Saniock\EvoAccess\Models\AuditLog;
use Saniock\EvoAccess\Models\Permission;
use Saniock\EvoAccess\Models\Role;
use Saniock\EvoAccess\Models\RolePermissionAction;
use Saniock\EvoAccess\Models\UserOverride;
use Saniock\EvoAccess\Models\UserRole;
use Saniock\EvoAccess\Tests\TestCase;

class MiscModelsTest extends TestCase
{
    use RefreshDatabase;

    private function makePermission(string $name = 'orders.orders'): Permission
    {
        return Permission::create([
            'name'    => $name,
            'label'   => 'L',
            'module'  => 'orders',
            'actions' => ['view', 'update'],
        ]);
    }

    public function test_role_permission_action_uses_composite_pk(): void
    {
        $role = Role::create(['name' => 'r1', 'label' => 'R1']);
        $perm = $this->makePermission();

        RolePermissionAction::create([
            'role_id'       => $role->id,
            'permission_id' => $perm->id,
            'action'        => 'view',
        ]);

        $this->assertSame(1, RolePermissionAction::where('role_id', $role->id)->count());
    }

    public function test_user_role_one_per_user(): void
    {
        $role = Role::where('name', 'superadmin')->firstOrFail();

        UserRole::create([
            'user_id' => 7,
            'role_id' => $role->id,
        ]);

        $row = UserRole::where('user_id', 7)->first();
        $this->assertSame($role->id, $row->role_id);
    }

    public function test_user_override_grant_mode(): void
    {
        $perm = $this->makePermission();

        UserOverride::create([
            'user_id'       => 42,
            'permission_id' => $perm->id,
            'action'        => 'export',
            'mode'          => 'grant',
            'reason'        => 'Test',
        ]);

        $row = UserOverride::where('user_id', 42)->first();
        $this->assertSame('grant', $row->mode);
    }

    public function test_audit_log_records_with_json_details(): void
    {
        AuditLog::create([
            'actor_user_id' => 7,
            'action'        => 'create_role',
            'target_role_id'=> 1,
            'details'       => ['old' => null, 'new' => 'manager'],
        ]);

        $row = AuditLog::first();
        $this->assertIsArray($row->details);
        $this->assertSame('manager', $row->details['new']);
    }
}
