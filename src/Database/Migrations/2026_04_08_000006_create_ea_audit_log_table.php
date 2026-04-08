<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ea_audit_log', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->unsignedInteger('actor_user_id');
            $table->string('action', 32);
            $table->unsignedInteger('target_role_id')->nullable();
            $table->unsignedInteger('target_user_id')->nullable();
            $table->unsignedInteger('permission_id')->nullable();
            $table->string('old_value', 255)->nullable();
            $table->string('new_value', 255)->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['actor_user_id', 'created_at'], 'ea_audit_actor_idx');
            $table->index(['target_role_id', 'created_at'], 'ea_audit_role_idx');
            $table->index(['target_user_id', 'created_at'], 'ea_audit_user_idx');
            $table->index('action', 'ea_audit_action_idx');
            $table->index('created_at', 'ea_audit_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ea_audit_log');
    }
};
