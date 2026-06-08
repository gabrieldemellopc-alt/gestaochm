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
            'maintenance_record_values',
            function (Blueprint $table) {
        
                $table->decimal(
                    'quantity',
                    12,
                    2
                )->nullable();
        
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenance_record_values', function (Blueprint $table) {
            //
        });
    }
};
