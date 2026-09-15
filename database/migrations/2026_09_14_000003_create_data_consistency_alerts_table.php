<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('data_consistency_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('division_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('module'); $table->string('rule_key'); $table->string('severity');
            $table->string('title'); $table->text('summary'); $table->json('details')->nullable();
            $table->string('entity_type')->nullable(); $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('related_entity_type')->nullable(); $table->unsignedBigInteger('related_entity_id')->nullable();
            $table->string('fingerprint', 128); $table->string('status')->default('new');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable(); $table->text('resolution_note')->nullable();
            $table->timestamp('first_detected_at'); $table->timestamp('last_detected_at');
            $table->timestamp('resolved_at')->nullable(); $table->timestamp('ignored_at')->nullable(); $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'fingerprint'], 'consistency_alert_tenant_fingerprint_unique');
            $table->index(['tenant_id', 'division_id', 'location_id', 'status'], 'consistency_alert_scope_status_index');
            $table->index(['tenant_id', 'module', 'severity'], 'consistency_alert_module_severity_index');
        });
    }
    public function down(): void { Schema::dropIfExists('data_consistency_alerts'); }
};
