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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UsersDataEndpointTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bootstrap admin user so ensureAccess() in the controller constructor passes.
     * The migration already seeds a 'superadmin' system role — we just assign user 1 to it.
     * Also creates a fake user_attributes table since data() queries EVO managers
     * from it directly (to support listing users without an evo-access role).
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

        // Fake EVO user_attributes table for data() endpoint
        Schema::create('user_attributes', function ($table) {
            $table->integer('internalKey');
            $table->integer('role')->default(0);
            $table->string('fullname')->nullable();
        });
    }

    protected function seedManager(int $userId, string $fullname): void
    {
        DB::table('user_attributes')->insert([
            'internalKey' => $userId,
            'role' => 1,
            'fullname' => $fullname,
        ]);
    }

    public function test_returns_user_summaries_with_grant_and_override_counts(): void
    {
        $this->seedManager(1, 'Admin');
        $this->seedManager(10, 'Jane Editor');

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
        $this->assertEquals('Jane Editor', $user['user_name']);
        $this->assertEquals('editor', $user['role_name']);
        $this->assertEquals(2, $user['effective_grant_count']); // 1 role grant + 1 override grant
        $this->assertEquals(1, $user['override_grant_count']);
        $this->assertEquals(0, $user['override_revoke_count']);

        // modules now returns array of {slug, label} pairs.
        // "orders.orders" is its own root permission so its label ("Orders")
        // is used as the module display name.
        $this->assertCount(1, $user['modules']);
        $this->assertEquals('orders', $user['modules'][0]['slug']);
        $this->assertEquals('Orders', $user['modules'][0]['label']);
    }

    public function test_modules_use_human_label_when_root_permission_exists(): void
    {
        $this->seedManager(1, 'Admin');
        $this->seedManager(20, 'Bob');

        $role = Role::create(['name' => 'viewer', 'label' => 'Viewer', 'is_system' => false]);

        // Root permission "competitors.competitors" with Ukrainian label
        $root = Permission::create([
            'name' => 'competitors.competitors', 'label' => 'Конкуренти',
            'module' => 'competitors', 'actions' => ['view'],
        ]);
        $child = Permission::create([
            'name' => 'competitors.dracar.products', 'label' => 'Dracar → Товари',
            'module' => 'competitors', 'actions' => ['view'],
        ]);

        RolePermissionAction::create([
            'role_id' => $role->id, 'permission_id' => $child->id,
            'action' => 'view', 'granted_at' => now(),
        ]);
        UserRole::create(['user_id' => 20, 'role_id' => $role->id, 'assigned_at' => now()]);

        $controller = $this->app->make(UsersController::class);
        $response = $controller->data();

        $user = collect($response)->firstWhere('user_id', 20);
        $this->assertNotNull($user);
        $this->assertCount(1, $user['modules']);
        $this->assertEquals('competitors', $user['modules'][0]['slug']);
        $this->assertEquals('Конкуренти', $user['modules'][0]['label']);
    }

    public function test_returns_all_managers_including_those_without_role(): void
    {
        $this->seedManager(1, 'Admin');
        $this->seedManager(42, 'New Manager');  // No evo-access role yet

        $controller = $this->app->make(UsersController::class);
        $response = $controller->data();

        $this->assertIsArray($response);
        $this->assertCount(2, $response);

        $newMgr = collect($response)->firstWhere('user_id', 42);
        $this->assertNotNull($newMgr);
        $this->assertEquals('New Manager', $newMgr['user_name']);
        $this->assertNull($newMgr['role_id']);
        $this->assertNull($newMgr['role_name']);
        $this->assertEquals(0, $newMgr['effective_grant_count']);
        $this->assertEmpty($newMgr['modules']);
    }

    public function test_returns_empty_when_no_managers_in_evo(): void
    {
        // No managers seeded — user_attributes is empty
        $controller = $this->app->make(UsersController::class);
        $response = $controller->data();

        $this->assertIsArray($response);
        $this->assertEmpty($response);
    }
}
