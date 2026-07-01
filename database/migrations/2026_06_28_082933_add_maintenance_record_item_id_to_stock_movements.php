<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->unsignedBigInteger('maintenance_record_item_id')
                ->nullable()
                ->after('maintenance_record_id');
    
            $table->foreign(
                'maintenance_record_item_id',
                'fk_stock_movements_mr_item'
            )
            ->references('id')
            ->on('maintenance_record_items')
            ->nullOnDelete();
        });
    }
    
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign('fk_stock_movements_mr_item');
            $table->dropColumn('maintenance_record_item_id');
        });
    }
};
