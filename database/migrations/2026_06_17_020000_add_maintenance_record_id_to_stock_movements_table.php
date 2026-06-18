<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table
                ->unsignedBigInteger('maintenance_record_id')
                ->nullable()
                ->after('stock_item_id');

            $table->index(
                'maintenance_record_id',
                'stock_movements_maintenance_record_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('stock_movements_maintenance_record_index');
            $table->dropColumn('maintenance_record_id');
        });
    }
};
