<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tire_entry_items', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('tire_entry_id')
                ->constrained('tire_entries')
                ->cascadeOnDelete();

            $table
                ->foreignId('tire_id')
                ->constrained('tires')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique([
                'tire_entry_id',
                'tire_id',
            ], 'tire_entry_items_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tire_entry_items');
    }
};