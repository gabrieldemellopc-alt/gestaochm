<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tires', function (Blueprint $table) {
            $table
                ->unsignedTinyInteger('tread_grooves_count')
                ->nullable()
                ->after('initial_tread_depth')
                ->comment('Quantidade de sulcos medidos no pneu: 3 ou 4');
        });
    }

    public function down(): void
    {
        Schema::table('tires', function (Blueprint $table) {
            $table->dropColumn('tread_grooves_count');
        });
    }
};
