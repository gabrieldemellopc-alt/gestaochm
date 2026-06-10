<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('location_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained('locations')
                ->nullOnDelete();

            $table->index(
                ['tenant_id', 'location_id', 'created_at'],
                'stock_movements_tenant_location_created_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('stock_movements_tenant_location_created_index');
            $table->dropForeign(['location_id']);
            $table->dropColumn('location_id');
        });
    }
};
