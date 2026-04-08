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
}
