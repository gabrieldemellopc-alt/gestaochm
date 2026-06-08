<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tires', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table
                ->string('code')
                ->comment('Número ou identificação do pneu');

            $table
                ->string('brand')
                ->nullable();

            $table
                ->string('model')
                ->nullable();

            $table
                ->decimal('initial_tread_depth', 5, 2)
                ->nullable()
                ->comment('Sulco inicial em mm');

            $table
                ->date('purchase_date')
                ->nullable();

            $table
                ->string('status')
                ->default('available')
                ->comment('available, installed, maintenance, discarded');

            $table
                ->text('notes')
                ->nullable();

            $table->timestamps();

            $table->unique([
                'tenant_id',
                'code',
            ], 'tires_tenant_code_unique');

            $table->index([
                'tenant_id',
                'status',
            ], 'tires_tenant_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tires');
    }
};