<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_items', function (Blueprint $table) {
            $table->string('normalized_name')->nullable()->after('name');
            $table->index(
                ['tenant_id', 'location_id', 'normalized_name'],
                'stock_items_tenant_location_normalized_name_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('stock_items', function (Blueprint $table) {
            $table->dropIndex('stock_items_tenant_location_normalized_name_index');
            $table->dropColumn('normalized_name');
        });
    }
};
