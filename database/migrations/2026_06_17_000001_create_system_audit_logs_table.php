<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('division_id')->nullable();
            $table->unsignedBigInteger('location_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_profile')->nullable();
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('module')->nullable();
            $table->string('action');
            $table->string('summary')->nullable();
            $table->json('before_data')->nullable();
            $table->json('after_data')->nullable();
            $table->json('metadata')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'division_id', 'location_id'], 'system_audit_context_index');
            $table->index('user_id', 'system_audit_user_index');
            $table->index(['auditable_type', 'auditable_id'], 'system_audit_auditable_index');
            $table->index(['module', 'action'], 'system_audit_module_action_index');
            $table->index('created_at', 'system_audit_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_audit_logs');
    }
};
