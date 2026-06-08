<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_tire_positions', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table
                ->foreignId('vehicle_id')
                ->constrained('vehicles')
                ->cascadeOnDelete();

            $table
                ->string('code')
                ->comment('Ex: 1E, 1D, 2EI, 2EE, 2DI, 2DE');

            $table
                ->string('label')
                ->comment('Ex: Dianteiro esquerdo');

            $table
                ->unsignedInteger('sort_order')
                ->default(0);

            $table
                ->boolean('active')
                ->default(true);

            $table->timestamps();

            $table->unique([
                'vehicle_id',
                'code',
            ], 'vehicle_tire_positions_vehicle_code_unique');

            $table->index([
                'tenant_id',
                'vehicle_id',
                'active',
            ], 'vehicle_tire_positions_context_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_tire_positions');
    }
};