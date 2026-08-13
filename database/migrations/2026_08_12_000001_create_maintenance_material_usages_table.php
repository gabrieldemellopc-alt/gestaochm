<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_material_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('maintenance_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_movement_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancel_reason')->nullable();
            $table->foreignId('reversal_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->foreignId('replaced_by_usage_id')->nullable()->constrained('maintenance_material_usages')->nullOnDelete();
            $table->foreignId('replaces_usage_id')->nullable()->constrained('maintenance_material_usages')->nullOnDelete();
            $table->timestamps();
            $table->index(['maintenance_record_id', 'cancelled_at'], 'maintenance_material_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_material_usages');
    }
};
