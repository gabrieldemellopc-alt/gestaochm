<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_operations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();

            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();

            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status')->default('open');
            // open, closed, canceled

            $table->decimal('start_vehicle_km', 12, 2)->nullable();
            $table->decimal('start_vehicle_hours', 12, 2)->nullable();

            $table->dateTime('start_datetime_reported');
            $table->dateTime('start_datetime_system');

            $table->integer('start_clock_difference_minutes')->default(0);

            $table->text('start_observation')->nullable();
            $table->string('start_delay_reason')->nullable();
            $table->text('start_delay_justification')->nullable();

            $table->decimal('end_vehicle_km', 12, 2)->nullable();
            $table->decimal('end_vehicle_hours', 12, 2)->nullable();

            $table->dateTime('end_datetime_reported')->nullable();
            $table->dateTime('end_datetime_system')->nullable();

            $table->integer('end_clock_difference_minutes')->nullable();

            $table->text('end_observation')->nullable();
            $table->string('end_delay_reason')->nullable();
            $table->text('end_delay_justification')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['vehicle_id', 'status']);
            $table->index(['tenant_id', 'status']);
            $table->index(['driver_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_operations');
    }
};