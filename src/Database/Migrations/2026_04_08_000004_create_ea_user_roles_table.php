<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ea_user_roles', function (Blueprint $table) {
            $table->unsignedInteger('user_id')->primary();
            $table->unsignedInteger('role_id');
            $table->unsignedInteger('assigned_by')->nullable();
            $table->timestamp('assigned_at')->useCurrent();

            $table->index('role_id', 'ea_user_roles_role_idx');

            $table->foreign('role_id', 'ea_user_roles_role_fk')
                ->references('id')->on('ea_roles')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ea_user_roles');
    }
};
