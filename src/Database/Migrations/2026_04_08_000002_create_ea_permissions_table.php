<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ea_permissions', function (Blueprint $table) {
            $table->unsignedInteger('id')->autoIncrement();
            $table->string('name', 128);
            $table->string('label', 255);
            $table->string('module', 64);
            $table->json('actions');
            $table->boolean('is_orphaned')->default(false);
            $table->timestamps();

            $table->unique('name', 'ea_permissions_name_unique');
            $table->index('module', 'ea_permissions_module_idx');
            $table->index('is_orphaned', 'ea_permissions_orphaned_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ea_permissions');
    }
};
