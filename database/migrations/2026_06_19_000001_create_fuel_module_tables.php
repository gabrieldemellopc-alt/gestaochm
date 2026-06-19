<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('unit')->default('litros');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug'], 'fuel_products_tenant_slug_unique');
            $table->index(['tenant_id', 'active'], 'fuel_products_tenant_active_index');
        });

        Schema::create('fuel_tanks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('division_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('fuel_product_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->decimal('capacity_liters', 14, 3)->default(0);
            $table->decimal('current_balance_liters', 14, 3)->default(0);
            $table->decimal('minimum_balance_liters', 14, 3)->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'division_id', 'location_id'], 'fuel_tanks_context_index');
            $table->index(['tenant_id', 'location_id', 'fuel_product_id', 'active'], 'fuel_tanks_location_product_active_index');
        });

        Schema::create('fuel_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('division_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('fuel_tank_id')->constrained()->restrictOnDelete();
            $table->foreignId('fuel_product_id')->constrained()->restrictOnDelete();
            $table->dateTime('received_at');
            $table->decimal('quantity_liters', 14, 3);
            $table->decimal('unit_cost', 12, 4)->nullable();
            $table->decimal('total_cost', 14, 2)->nullable();
            $table->string('supplier_name')->nullable();
            $table->string('invoice_number')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'division_id', 'location_id'], 'fuel_receipts_context_index');
            $table->index(['fuel_tank_id', 'received_at'], 'fuel_receipts_tank_received_index');
            $table->index('cancelled_at', 'fuel_receipts_cancelled_at_index');
        });

        Schema::create('fuel_fillings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('division_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('fuel_tank_id')->constrained()->restrictOnDelete();
            $table->foreignId('fuel_product_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('filled_at');
            $table->decimal('vehicle_km', 12, 2)->nullable();
            $table->decimal('vehicle_hours', 12, 2)->nullable();
            $table->decimal('quantity_liters', 14, 3);
            $table->decimal('unit_cost', 12, 4)->nullable();
            $table->decimal('total_cost', 14, 2)->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'division_id', 'location_id'], 'fuel_fillings_context_index');
            $table->index(['vehicle_id', 'filled_at'], 'fuel_fillings_vehicle_filled_index');
            $table->index(['fuel_tank_id', 'filled_at'], 'fuel_fillings_tank_filled_index');
            $table->index('cancelled_at', 'fuel_fillings_cancelled_at_index');
        });

        Schema::create('fuel_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('division_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('fuel_tank_id')->constrained()->restrictOnDelete();
            $table->foreignId('fuel_product_id')->constrained()->restrictOnDelete();
            $table->string('movement_type');
            $table->decimal('quantity_liters', 14, 3);
            $table->decimal('balance_before', 14, 3);
            $table->decimal('balance_after', 14, 3);
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'division_id', 'location_id'], 'fuel_movements_context_index');
            $table->index(['fuel_tank_id', 'created_at'], 'fuel_movements_tank_created_index');
            $table->index(['source_type', 'source_id'], 'fuel_movements_source_index');
            $table->index(['movement_type', 'created_at'], 'fuel_movements_type_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_movements');
        Schema::dropIfExists('fuel_fillings');
        Schema::dropIfExists('fuel_receipts');
        Schema::dropIfExists('fuel_tanks');
        Schema::dropIfExists('fuel_products');
    }
};
