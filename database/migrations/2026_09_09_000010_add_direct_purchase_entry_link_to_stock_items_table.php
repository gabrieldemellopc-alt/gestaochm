<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_items', function (Blueprint $table) {
            $table->unsignedBigInteger('direct_purchase_entry_movement_id')
                ->nullable()
                ->index()
                ->after('stock_category_id');
        });
    }

    public function down(): void
    {
        Schema::table('stock_items', function (Blueprint $table) {
            $table->dropIndex(['direct_purchase_entry_movement_id']);
            $table->dropColumn('direct_purchase_entry_movement_id');
        });
    }
};
