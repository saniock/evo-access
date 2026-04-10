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

class UsersMatrixEndpointTest extends TestCase
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

    public function test_returns_grouped_permissions_with_role_grants_and_overrides(): void
    {
        $role = Role::create(['name' => 'viewer', 'label' => 'Viewer', 'is_system' => false]);
        $p1 = Permission::create([
            'name' => 'orders.orders', 'label' => 'Orders', 'module' => 'orders',
            'actions' => ['view', 'edit'],
        ]);
        $p2 = Permission::create([
            'name' => 'orders.payments', 'label' => 'Payments', 'module' => 'orders',
            'actions' => ['view', 'edit', 'delete'],
        ]);
        RolePermissionAction::create([
            'role_id' => $role->id, 'permission_id' => $p1->id,
            'action' => 'view', 'granted_at' => now(),
        ]);
        UserRole::create(['user_id' => 10, 'role_id' => $role->id, 'assigned_at' => now()]);
        UserOverride::create([
            'user_id' => 10, 'permission_id' => $p1->id,
            'action' => 'edit', 'mode' => 'grant',
        ]);
        UserOverride::create([
            'user_id' => 10, 'permission_id' => $p2->id,
            'action' => 'view', 'mode' => 'revoke',
        ]);

        $controller = $this->app->make(UsersController::class);
        $response = $controller->matrix(10);

        $this->assertEquals(10, $response['user_id']);
        $this->assertEquals($role->id, $response['role']['id']);

        // Both permissions in 'orders' module — but there may also be
        // auto-registered 'access' module permissions from the SP
        $ordersModule = collect($response['modules'])->firstWhere('module', 'orders');
        $this->assertNotNull($ordersModule);
        $this->assertCount(2, $ordersModule['permissions']);

        $perm1 = collect($ordersModule['permissions'])->firstWhere('id', $p1->id);
        $this->assertEquals(['view'], $perm1['role_grants']);
        $this->assertCount(1, $perm1['overrides']);
        $this->assertEquals('edit', $perm1['overrides'][0]['action']);
        $this->assertEquals('grant', $perm1['overrides'][0]['mode']);

        $perm2 = collect($ordersModule['permissions'])->firstWhere('id', $p2->id);
        $this->assertEmpty($perm2['role_grants']);
        $this->assertEquals('revoke', $perm2['overrides'][0]['mode']);
    }

    public function test_returns_null_role_for_unassigned_user(): void
    {
        $controller = $this->app->make(UsersController::class);
        $response = $controller->matrix(999);

        $this->assertEquals(999, $response['user_id']);
        $this->assertNull($response['role']);
    }
}
