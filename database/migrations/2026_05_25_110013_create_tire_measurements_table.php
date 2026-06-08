<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tire_measurements', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table
                ->foreignId('tire_id')
                ->constrained('tires')
                ->cascadeOnDelete();

            $table
                ->foreignId('vehicle_id')
                ->constrained('vehicles')
                ->cascadeOnDelete();

            $table
                ->string('position_code');

            $table
                ->date('measured_at');

            $table
                ->unsignedInteger('vehicle_km')
                ->nullable();

            $table
                ->decimal('outer_tread', 5, 2)
                ->nullable()
                ->comment('Sulco externo');

            $table
                ->decimal('center_outer_tread', 5, 2)
                ->nullable()
                ->comment('Sulco centro externo');

            $table
                ->decimal('center_inner_tread', 5, 2)
                ->nullable()
                ->comment('Sulco centro interno');

            $table
                ->decimal('inner_tread', 5, 2)
                ->nullable()
                ->comment('Sulco interno');

            $table
                ->decimal('average_tread', 5, 2)
                ->nullable()
                ->comment('Média dos sulcos');

            $table
                ->decimal('minimum_tread', 5, 2)
                ->nullable()
                ->comment('Menor sulco medido');

            $table
                ->text('notes')
                ->nullable();

            $table
                ->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index([
                'tenant_id',
                'vehicle_id',
                'measured_at',
            ], 'tire_measurements_vehicle_date_index');

            $table->index([
                'tenant_id',
                'tire_id',
                'measured_at',
            ], 'tire_measurements_tire_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tire_measurements');
    }
};