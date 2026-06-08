<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {

            $table->id();

            $table->string('name');

            $table->string('plate')->nullable();

            $table->string('brand')->nullable();

            $table->string('model')->nullable();

            $table->string('year')->nullable();

            $table->integer('current_km')->default(0);

            $table->integer('current_hours')->default(0);

            $table->enum('status', [
                'active',
                'maintenance',
                'inactive'
            ])->default('active');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};