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
        Schema::table(
            'user_division_accesses',
            function (Blueprint $table)
            {
                $table
                    ->foreignId('location_id')
                    ->nullable()
                    ->after('division_id')
                    ->constrained()
                    ->nullOnDelete();
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_division_accesses', function (Blueprint $table) {
            //
        });
    }
};
