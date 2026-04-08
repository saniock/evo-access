<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ea_user_overrides', function (Blueprint $table) {
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('permission_id');
            $table->string('action', 32);
            $table->enum('mode', ['grant', 'revoke']);
            $table->string('reason', 255)->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['user_id', 'permission_id', 'action']);
            $table->index('user_id', 'ea_uo_user_idx');
            $table->index('permission_id', 'ea_uo_permission_idx');

            $table->foreign('permission_id', 'ea_uo_permission_fk')
                ->references('id')->on('ea_permissions')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ea_user_overrides');
    }
};
