<?php

namespace Saniock\EvoAccess\Tests\Feature;

use Saniock\EvoAccess\Services\PermissionCatalog;
use Saniock\EvoAccess\Tests\TestCase;

class PermissionSplitTest extends TestCase
{
    public function test_registers_access_roles_permission(): void
    {
        $catalog = $this->app->make(PermissionCatalog::class);
        $perm = $catalog->find('access.roles');

        $this->assertNotNull($perm);
        $this->assertEquals('access', $perm['module']);
        $this->assertEquals(['view', 'edit'], $perm['actions']);
    }

    public function test_registers_access_users_permission(): void
    {
        $catalog = $this->app->make(PermissionCatalog::class);
        $perm = $catalog->find('access.users');

        $this->assertNotNull($perm);
        $this->assertEquals('access', $perm['module']);
        $this->assertEquals(['view', 'edit'], $perm['actions']);
    }

    public function test_registers_access_audit_permission(): void
    {
        $catalog = $this->app->make(PermissionCatalog::class);
        $perm = $catalog->find('access.audit');

        $this->assertNotNull($perm);
        $this->assertEquals('access', $perm['module']);
        $this->assertEquals(['view'], $perm['actions']);
    }

    public function test_old_access_admin_no_longer_registered(): void
    {
        $catalog = $this->app->make(PermissionCatalog::class);
        $this->assertNull($catalog->find('access.admin'));
    }
}
