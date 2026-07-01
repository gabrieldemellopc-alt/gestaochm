<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel_tanks', function (Blueprint $table) {
            $table->decimal('average_unit_cost', 12, 4)->default(0)->after('current_balance_liters');
            $table->decimal('estimated_stock_value', 14, 2)->default(0)->after('average_unit_cost');
        });
    }
    
    public function down(): void
    {
        Schema::table('fuel_tanks', function (Blueprint $table) {
            $table->dropColumn([
                'average_unit_cost',
                'estimated_stock_value',
            ]);
        });
    }
};
