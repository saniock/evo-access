<?php

namespace Saniock\EvoAccess\Tests\Unit\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Saniock\EvoAccess\Exceptions\AccessDeniedException;
use Saniock\EvoAccess\Models\Permission;
use Saniock\EvoAccess\Models\Role;
use Saniock\EvoAccess\Models\RolePermissionAction;
use Saniock\EvoAccess\Models\UserRole;
use Saniock\EvoAccess\Services\AccessService;
use Saniock\EvoAccess\Tests\TestCase;

class AccessServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): AccessService
    {
        return $this->app->make(AccessService::class);
    }

    private function setupUserWithGrant(string $action = 'view'): void
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
            'action' => $action,
        ]);
    }

    public function test_can_returns_true_for_granted(): void
    {
        $this->setupUserWithGrant('view');
        $this->assertTrue($this->service()->can('orders.orders', 'view', 7));
    }

    public function test_can_returns_false_for_not_granted(): void
    {
        $this->setupUserWithGrant('view');
        $this->assertFalse($this->service()->can('orders.orders', 'export', 7));
    }

    public function test_authorize_throws_when_denied(): void
    {
        $this->setupUserWithGrant('view');

        $this->expectException(AccessDeniedException::class);
        $this->service()->authorize('orders.orders', 'export', 7);
    }

    public function test_authorize_silent_when_allowed(): void
    {
        $this->setupUserWithGrant('view');
        $this->service()->authorize('orders.orders', 'view', 7);
        $this->assertTrue(true);  // no exception thrown
    }

    private function ordersMenu(): array
    {
        return [
            [
                'id' => 'orders',
                'title' => 'Orders',
                'items' => [
                    ['id' => 'orders',   'title' => 'List',     'permission' => 'orders.orders'],
                    ['id' => 'payments', 'title' => 'Payments', 'permission' => 'orders.payments'],
                ],
            ],
        ];
    }

    public function test_can_view_resolves_menu_item_to_permission(): void
    {
        $this->setupUserWithGrant('view');
        $this->assertTrue($this->service()->canView($this->ordersMenu(), 'orders', 7));
    }

    public function test_can_view_false_when_action_id_unknown_in_menu_returns_true(): void
    {
        // Action IDs not present in menu (like AJAX endpoints) are not blocked
        $this->setupUserWithGrant('view');
        $this->assertTrue($this->service()->canView($this->ordersMenu(), 'unknown_ajax', 7));
    }

    public function test_can_edit_uses_update_action(): void
    {
        $this->setupUserWithGrant('update');
        $this->assertTrue($this->service()->canEdit($this->ordersMenu(), 'orders', 7));
    }

    public function test_filter_menu_keeps_visible_items(): void
    {
        $this->setupUserWithGrant('view');

        $filtered = $this->service()->filterMenu($this->ordersMenu(), 7);
        $this->assertCount(1, $filtered);
        $this->assertCount(1, $filtered[0]['items']);
        $this->assertSame('orders', $filtered[0]['items'][0]['id']);
    }

    public function test_filter_menu_drops_items_without_view(): void
    {
        // user has no role at all
        $filtered = $this->service()->filterMenu($this->ordersMenu(), 999);
        $this->assertEmpty($filtered);
    }

    public function test_filter_menu_collapses_empty_groups(): void
    {
        $this->setupUserWithGrant('view');

        $menu = [
            [
                'id' => 'finances',
                'title' => 'Finances',
                'items' => [
                    ['id' => 'banks', 'title' => 'Banks', 'permission' => 'finances.banks'],
                ],
            ],
            [
                'id' => 'orders',
                'title' => 'Orders',
                'items' => [
                    ['id' => 'orders', 'title' => 'List', 'permission' => 'orders.orders'],
                ],
            ],
        ];

        // User has 'orders.orders' grant but no 'finances.banks' — finances group should be collapsed
        $filtered = $this->service()->filterMenu($menu, 7);
        $this->assertCount(1, $filtered);
        $this->assertSame('orders', $filtered[0]['id']);
    }
}
