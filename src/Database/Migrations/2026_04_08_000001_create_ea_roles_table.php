<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ea_roles', function (Blueprint $table) {
            $table->unsignedInteger('id')->autoIncrement();
            $table->string('name', 64);
            $table->string('label', 128);
            $table->string('description', 255)->nullable();
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique('name', 'ea_roles_name_unique');
            $table->index('is_system', 'ea_roles_is_system_idx');
        });

        DB::table('ea_roles')->insert([
            'name'        => 'superadmin',
            'label'       => 'Суперадмін',
            'description' => 'System role with unconditional full access',
            'is_system'   => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ea_roles');
    }
};
