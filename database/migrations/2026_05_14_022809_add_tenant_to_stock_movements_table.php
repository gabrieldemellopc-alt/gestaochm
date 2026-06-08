<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(

            'stock_movements',

            function (Blueprint $table) {

                $table->foreignId('tenant_id')
                    ->nullable()
                    ->after('id');

            }

        );
    }

    public function down(): void
    {
        Schema::table(

            'stock_movements',

            function (Blueprint $table) {

                $table->dropColumn(
                    'tenant_id'
                );

            }

        );
    }
};