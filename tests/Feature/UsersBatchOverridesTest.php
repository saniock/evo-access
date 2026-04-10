<?php

namespace Saniock\EvoAccess\Tests\Feature;

use Saniock\EvoAccess\Models\Permission;
use Saniock\EvoAccess\Models\Role;
use Saniock\EvoAccess\Models\UserOverride;
use Saniock\EvoAccess\Models\UserRole;
use Saniock\EvoAccess\Controllers\UsersController;
use Saniock\EvoAccess\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

class UsersBatchOverridesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (!function_exists('evo')) {
            eval('function evo() {
                return new class {
                    public function getLoginUserID($context = "mgr") { return 1; }
                };
            }');
        }

        $systemRole = Role::where('name', 'superadmin')->firstOrFail();
        UserRole::create(['user_id' => 1, 'role_id' => $systemRole->id, 'assigned_at' => now()]);
    }

    public function test_replaces_all_overrides_in_single_transaction(): void
    {
        $role = Role::create(['name' => 'editor', 'label' => 'Editor', 'is_system' => false]);
        $perm = Permission::create([
            'name' => 'orders.orders', 'label' => 'Orders',
            'module' => 'orders', 'actions' => ['view', 'edit', 'delete'],
        ]);
        UserRole::create(['user_id' => 10, 'role_id' => $role->id, 'assigned_at' => now()]);

        // Pre-existing override that should be removed
        UserOverride::create([
            'user_id' => 10, 'permission_id' => $perm->id,
            'action' => 'delete', 'mode' => 'grant',
        ]);

        $controller = $this->app->make(UsersController::class);
        $request = Request::create('/access/users/10/overrides/batch', 'POST', [
            'role_id' => $role->id,
            'overrides' => [
                ['permission_id' => $perm->id, 'action' => 'edit', 'mode' => 'grant'],
                ['permission_id' => $perm->id, 'action' => 'view', 'mode' => 'revoke'],
            ],
        ]);

        $response = $controller->batchOverrides($request, 10);

        $this->assertTrue($response['success']);

        // Old override (delete/grant) should be gone
        $this->assertDatabaseMissing('ea_user_overrides', [
            'user_id' => 10, 'action' => 'delete',
        ]);

        // New overrides should exist
        $this->assertCount(2, UserOverride::where('user_id', 10)->get());
        $this->assertDatabaseHas('ea_user_overrides', [
            'user_id' => 10, 'action' => 'edit', 'mode' => 'grant',
        ]);
        $this->assertDatabaseHas('ea_user_overrides', [
            'user_id' => 10, 'action' => 'view', 'mode' => 'revoke',
        ]);
    }

    public function test_reassigns_role_when_role_id_differs(): void
    {
        $role1 = Role::create(['name' => 'viewer', 'label' => 'Viewer', 'is_system' => false]);
        $role2 = Role::create(['name' => 'editor', 'label' => 'Editor', 'is_system' => false]);
        UserRole::create(['user_id' => 10, 'role_id' => $role1->id, 'assigned_at' => now()]);

        $controller = $this->app->make(UsersController::class);
        $request = Request::create('/access/users/10/overrides/batch', 'POST', [
            'role_id' => $role2->id,
            'overrides' => [],
        ]);

        $response = $controller->batchOverrides($request, 10);
        $this->assertTrue($response['success']);
        $this->assertEquals($role2->id, UserRole::find(10)->role_id);
    }

    public function test_creates_role_assignment_if_none_exists(): void
    {
        $role = Role::create(['name' => 'viewer', 'label' => 'Viewer', 'is_system' => false]);

        $controller = $this->app->make(UsersController::class);
        $request = Request::create('/access/users/20/overrides/batch', 'POST', [
            'role_id' => $role->id,
            'overrides' => [],
        ]);

        $response = $controller->batchOverrides($request, 20);
        $this->assertTrue($response['success']);
        $this->assertNotNull(UserRole::find(20));
        $this->assertEquals($role->id, UserRole::find(20)->role_id);
    }

    public function test_audits_role_change(): void
    {
        $role1 = Role::create(['name' => 'viewer', 'label' => 'Viewer', 'is_system' => false]);
        $role2 = Role::create(['name' => 'editor', 'label' => 'Editor', 'is_system' => false]);
        UserRole::create(['user_id' => 10, 'role_id' => $role1->id, 'assigned_at' => now()]);

        $controller = $this->app->make(UsersController::class);
        $request = Request::create('/access/users/10/overrides/batch', 'POST', [
            'role_id' => $role2->id,
            'overrides' => [],
        ]);

        $controller->batchOverrides($request, 10);

        $this->assertDatabaseHas('ea_audit_log', [
            'action' => 'user_role_changed',
            'target_user_id' => 10,
            'target_role_id' => $role2->id,
        ]);
    }

    public function test_audits_override_diff(): void
    {
        $role = Role::create(['name' => 'editor', 'label' => 'Editor', 'is_system' => false]);
        $perm = Permission::create([
            'name' => 'orders.orders', 'label' => 'Orders',
            'module' => 'orders', 'actions' => ['view', 'edit', 'delete'],
        ]);
        UserRole::create(['user_id' => 10, 'role_id' => $role->id, 'assigned_at' => now()]);

        // Pre-existing override that will be removed
        UserOverride::create([
            'user_id' => 10, 'permission_id' => $perm->id,
            'action' => 'delete', 'mode' => 'grant',
        ]);

        $controller = $this->app->make(UsersController::class);
        $request = Request::create('/access/users/10/overrides/batch', 'POST', [
            'role_id' => $role->id,
            'overrides' => [
                ['permission_id' => $perm->id, 'action' => 'edit', 'mode' => 'grant'],
            ],
        ]);

        $controller->batchOverrides($request, 10);

        // Added: edit/grant
        $this->assertDatabaseHas('ea_audit_log', [
            'action' => 'override_grant',
            'target_user_id' => 10,
            'permission_id' => $perm->id,
            'new_value' => 'edit',
        ]);

        // Removed: delete/grant
        $this->assertDatabaseHas('ea_audit_log', [
            'action' => 'override_removed',
            'target_user_id' => 10,
            'permission_id' => $perm->id,
            'old_value' => 'delete',
        ]);
    }
}
