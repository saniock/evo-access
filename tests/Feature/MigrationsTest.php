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
}
