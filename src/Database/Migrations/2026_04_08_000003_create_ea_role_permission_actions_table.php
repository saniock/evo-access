<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ea_role_permission_actions', function (Blueprint $table) {
            $table->unsignedInteger('role_id');
            $table->unsignedInteger('permission_id');
            $table->string('action', 32);
            $table->unsignedInteger('granted_by')->nullable();
            $table->timestamp('granted_at')->useCurrent();

            $table->primary(['role_id', 'permission_id', 'action']);
            $table->index('permission_id', 'ea_rpa_permission_idx');

            $table->foreign('role_id', 'ea_rpa_role_fk')
                ->references('id')->on('ea_roles')
                ->cascadeOnDelete();

            $table->foreign('permission_id', 'ea_rpa_permission_fk')
                ->references('id')->on('ea_permissions')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ea_role_permission_actions');
    }
};
