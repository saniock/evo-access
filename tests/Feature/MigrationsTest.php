<?php

namespace Saniock\EvoAccess\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Saniock\EvoAccess\Tests\TestCase;

class MigrationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ea_roles_table_exists_with_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('ea_roles'));
        $this->assertTrue(Schema::hasColumns('ea_roles', [
            'id', 'name', 'label', 'description', 'is_system',
            'created_by', 'created_at', 'updated_at',
        ]));
    }

    public function test_superadmin_role_is_seeded(): void
    {
        $row = DB::table('ea_roles')->where('name', 'superadmin')->first();

        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row->is_system);
    }

    public function test_ea_permissions_table_exists_with_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('ea_permissions'));
        $this->assertTrue(Schema::hasColumns('ea_permissions', [
            'id', 'name', 'label', 'module', 'actions',
            'is_orphaned', 'created_at', 'updated_at',
        ]));
    }

    public function test_ea_role_permission_actions_table_exists_with_composite_pk(): void
    {
        $this->assertTrue(Schema::hasTable('ea_role_permission_actions'));
        $this->assertTrue(Schema::hasColumns('ea_role_permission_actions', [
            'role_id', 'permission_id', 'action', 'granted_by', 'granted_at',
        ]));
    }

    public function test_ea_user_roles_table_exists_with_user_id_pk(): void
    {
        $this->assertTrue(Schema::hasTable('ea_user_roles'));
        $this->assertTrue(Schema::hasColumns('ea_user_roles', [
            'user_id', 'role_id', 'assigned_by', 'assigned_at',
        ]));
    }

    public function test_ea_user_overrides_table_exists_with_mode_column(): void
    {
        $this->assertTrue(Schema::hasTable('ea_user_overrides'));
        $this->assertTrue(Schema::hasColumns('ea_user_overrides', [
            'user_id', 'permission_id', 'action', 'mode',
            'reason', 'created_by', 'created_at',
        ]));
    }

    public function test_ea_audit_log_table_exists_with_no_foreign_keys(): void
    {
        $this->assertTrue(Schema::hasTable('ea_audit_log'));
        $this->assertTrue(Schema::hasColumns('ea_audit_log', [
            'id', 'actor_user_id', 'action', 'target_role_id', 'target_user_id',
            'permission_id', 'old_value', 'new_value', 'details', 'created_at',
        ]));
    }
}
