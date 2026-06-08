<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {

            /*
            |--------------------------------------------------------------------------
            | TIPO DO VEÍCULO
            |--------------------------------------------------------------------------
            */

            $table->string('type')
                ->default('lixo')
                ->after('name');

            /*
            |--------------------------------------------------------------------------
            | DIVISÃO
            |--------------------------------------------------------------------------
            */

            $table->foreignId('division_id')
                ->nullable()
                ->after('type')
                ->constrained()
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | LOCATION / CIDADE
            |--------------------------------------------------------------------------
            */

            $table->foreignId('location_id')
                ->nullable()
                ->after('division_id')
                ->constrained()
                ->nullOnDelete();

        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {

            $table->dropForeign(['division_id']);

            $table->dropForeign(['location_id']);

            $table->dropColumn([
                'type',
                'division_id',
                'location_id'
            ]);

        });
    }
};