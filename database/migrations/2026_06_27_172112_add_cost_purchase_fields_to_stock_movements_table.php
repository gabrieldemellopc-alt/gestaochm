<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->decimal('total_cost', 12, 2)->default(0)->after('unit_cost');
            $table->string('invoice_number')->nullable()->after('description');
            $table->string('supplier_name')->nullable()->after('invoice_number');
        });
    }
    
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn([
                'total_cost',
                'invoice_number',
                'supplier_name',
            ]);
        });
    }
};
