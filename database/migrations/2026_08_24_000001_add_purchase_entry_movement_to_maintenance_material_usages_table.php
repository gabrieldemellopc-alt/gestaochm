<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_material_usages', function (Blueprint $table) {
            $table->foreignId('purchase_entry_movement_id')
                ->nullable()
                ->after('stock_movement_id')
                ->constrained('stock_movements')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_material_usages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_entry_movement_id');
        });
    }
};
