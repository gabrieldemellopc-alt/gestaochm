<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tire_installations', function (Blueprint $table) {
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
                ->string('position_code')
                ->comment('Ex: 1E, 1D, 2EI, 2EE, 2DI, 2DE');

            $table
                ->date('installed_at')
                ->nullable();

            $table
                ->unsignedInteger('installed_km')
                ->nullable();

            $table
                ->date('removed_at')
                ->nullable();

            $table
                ->unsignedInteger('removed_km')
                ->nullable();

            $table
                ->string('removal_reason')
                ->nullable();

            $table
                ->boolean('active')
                ->default(true);

            $table
                ->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index([
                'tenant_id',
                'vehicle_id',
                'position_code',
                'active',
            ], 'tire_installations_vehicle_position_index');

            $table->index([
                'tenant_id',
                'tire_id',
                'active',
            ], 'tire_installations_tire_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tire_installations');
    }
};