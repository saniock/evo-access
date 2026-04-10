<?php

namespace Saniock\EvoAccess\Tests\Feature;

use Saniock\EvoAccess\Models\Permission;
use Saniock\EvoAccess\Models\Role;
use Saniock\EvoAccess\Models\RolePermissionAction;
use Saniock\EvoAccess\Models\UserOverride;
use Saniock\EvoAccess\Models\UserRole;
use Saniock\EvoAccess\Controllers\UsersController;
use Saniock\EvoAccess\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UsersDataEndpointTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bootstrap admin user so ensureAccess() in the controller constructor passes.
     * The migration already seeds a 'superadmin' system role — we just assign user 1 to it.
     */
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

    public function test_returns_user_summaries_with_grant_and_override_counts(): void
    {
        $role = Role::create(['name' => 'editor', 'label' => 'Editor', 'is_system' => false]);
        $perm = Permission::create([
            'name' => 'orders.orders', 'label' => 'Orders',
            'module' => 'orders', 'actions' => ['view', 'edit', 'delete'],
        ]);
        RolePermissionAction::create([
            'role_id' => $role->id, 'permission_id' => $perm->id,
            'action' => 'view', 'granted_at' => now(),
        ]);
        UserRole::create(['user_id' => 10, 'role_id' => $role->id, 'assigned_at' => now()]);
        UserOverride::create([
            'user_id' => 10, 'permission_id' => $perm->id,
            'action' => 'edit', 'mode' => 'grant',
        ]);

        $controller = $this->app->make(UsersController::class);
        $response = $controller->data();

        $this->assertIsArray($response);
        $user = collect($response)->firstWhere('user_id', 10);
        $this->assertNotNull($user);
        $this->assertEquals('editor', $user['role_name']);
        $this->assertEquals(2, $user['effective_grant_count']); // 1 role grant + 1 override grant
        $this->assertEquals(1, $user['override_grant_count']);
        $this->assertEquals(0, $user['override_revoke_count']);
        $this->assertContains('orders', $user['modules']);
    }

    public function test_returns_only_admin_when_no_other_users_assigned(): void
    {
        // Only the admin user from setUp has a role assignment
        $controller = $this->app->make(UsersController::class);
        $response = $controller->data();

        $this->assertIsArray($response);
        $this->assertCount(1, $response);
        $this->assertEquals(1, $response[0]['user_id']);
        $this->assertTrue($response[0]['is_system']);
    }
}
