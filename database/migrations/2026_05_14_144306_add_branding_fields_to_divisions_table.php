<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('divisions', function (Blueprint $table) {

            $table->string('logo')
                ->nullable()
                ->after('name');

            $table->string('logo_theme')
                ->default('dark')
                ->after('logo');

            $table->string('primary_color')
                ->nullable()
                ->after('logo_theme');

            $table->string('secondary_color')
                ->nullable()
                ->after('primary_color');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('divisions', function (Blueprint $table) {

            $table->dropColumn([
                'logo',
                'logo_theme',
                'primary_color',
                'secondary_color'
            ]);

        });
    }
};