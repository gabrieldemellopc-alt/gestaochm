<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('vehicle_reading_corrections', function (Blueprint $t) {
            $t->id(); $t->foreignId('tenant_id')->constrained()->cascadeOnDelete(); $t->foreignId('division_id')->nullable()->constrained()->nullOnDelete(); $t->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('vehicle_id')->constrained()->cascadeOnDelete(); $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('original_log_id')->nullable()->constrained('vehicle_update_logs')->nullOnDelete(); $t->foreignId('original_fuel_filling_id')->nullable()->constrained('fuel_fillings')->nullOnDelete(); $t->foreignId('corrected_log_id')->nullable()->constrained('vehicle_update_logs')->nullOnDelete();
            $t->decimal('new_km', 14, 2)->nullable(); $t->decimal('new_hours', 14, 2)->nullable(); $t->text('reason'); $t->dateTime('effective_at'); $t->json('impacts')->nullable(); $t->string('ip_address', 45)->nullable(); $t->text('user_agent')->nullable(); $t->timestamps();
        });
        Schema::create('vehicle_reading_correction_evidences', function (Blueprint $t) {
            $t->id(); $t->foreignId('tenant_id')->constrained()->cascadeOnDelete(); $t->foreignId('vehicle_id')->constrained()->cascadeOnDelete(); $t->foreignId('correction_id')->nullable()->constrained('vehicle_reading_corrections')->nullOnDelete(); $t->foreignId('initiated_by')->constrained('users')->cascadeOnDelete();
            $t->char('token_hash', 64)->unique(); $t->dateTime('expires_at'); $t->dateTime('used_at')->nullable(); $t->string('status')->default('pending'); $t->string('disk')->nullable(); $t->string('path')->nullable(); $t->string('original_name')->nullable(); $t->string('mime_type')->nullable(); $t->unsignedBigInteger('size_bytes')->nullable(); $t->decimal('duration_seconds', 6, 2)->nullable(); $t->char('checksum', 64)->nullable(); $t->timestamps(); $t->index(['vehicle_id', 'status', 'expires_at'], 'vrc_evid_vehicle_status_exp_idx');
        });
    }
    public function down(): void { Schema::dropIfExists('vehicle_reading_correction_evidences'); Schema::dropIfExists('vehicle_reading_corrections'); }
};
