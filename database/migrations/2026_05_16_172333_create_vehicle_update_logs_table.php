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
        Schema::create('vehicle_update_logs', function (Blueprint $table) {

        $table->id();
    
        $table->foreignId('vehicle_id')
            ->constrained()
            ->cascadeOnDelete();
    
        $table->foreignId('user_id')
            ->nullable()
            ->constrained()
            ->nullOnDelete();
    
        $table->foreignId('division_id')
            ->nullable()
            ->constrained()
            ->nullOnDelete();
    
        $table->foreignId('location_id')
            ->nullable()
            ->constrained()
            ->nullOnDelete();
    
        /*
        |--------------------------------------------------------------------------
        | TYPE
        |--------------------------------------------------------------------------
        |
        | km
        | hours
        | location
        | division
        | status
        | operational_status
        |
        */
    
        $table->string('type');
    
        $table->string('old_value')->nullable();
    
        $table->string('new_value')->nullable();
    
        $table->text('observation')->nullable();
    
        $table->timestamps();
    
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_update_logs');
    }
};
