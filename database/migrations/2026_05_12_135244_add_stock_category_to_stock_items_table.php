<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_items', function (Blueprint $table) {

            $table->foreignId('stock_category_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_items', function (Blueprint $table) {

            $table->dropForeign([
                'stock_category_id'
            ]);

            $table->dropColumn(
                'stock_category_id'
            );
        });
    }
};