<?php

namespace Saniock\EvoAccess\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Saniock\EvoAccess\Models\Role;
use Saniock\EvoAccess\Models\UserRole;
use Saniock\EvoAccess\Tests\TestCase;

class BootstrapCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_assigns_configured_users_to_superadmin(): void
    {
        config(['evoAccess.bootstrap_superadmin_user_ids' => [7, 42]]);

        $this->artisan('evoaccess:bootstrap')
            ->expectsOutputToContain('Bootstrap complete')
            ->assertSuccessful();

        $superadmin = Role::where('name', 'superadmin')->firstOrFail();
        $this->assertSame($superadmin->id, UserRole::where('user_id', 7)->value('role_id'));
        $this->assertSame($superadmin->id, UserRole::where('user_id', 42)->value('role_id'));
    }

    public function test_bootstrap_is_idempotent(): void
    {
        config(['evoAccess.bootstrap_superadmin_user_ids' => [7]]);

        $this->artisan('evoaccess:bootstrap')->assertSuccessful();
        $this->artisan('evoaccess:bootstrap')->assertSuccessful();

        $this->assertSame(1, UserRole::where('user_id', 7)->count());
    }

    public function test_bootstrap_warns_on_existing_non_superadmin(): void
    {
        $manager = Role::create(['name' => 'manager', 'label' => 'M']);
        UserRole::create(['user_id' => 7, 'role_id' => $manager->id]);

        config(['evoAccess.bootstrap_superadmin_user_ids' => [7]]);

        $this->artisan('evoaccess:bootstrap')
            ->expectsOutputToContain('NOT superadmin')
            ->assertSuccessful();

        $this->assertSame($manager->id, UserRole::where('user_id', 7)->value('role_id'));
    }
}
