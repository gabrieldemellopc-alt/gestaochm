<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procedures', function (Blueprint $table) {

            $table->id();

            $table->foreignId('tenant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');

            $table->enum('validity_type', [

                'none',
                'km',
                'hours',
                'date'

            ])->default('none');

            $table->integer('interval_km')
                ->nullable();

            $table->integer('interval_hours')
                ->nullable();

            $table->integer('interval_days')
                ->nullable();

            $table->boolean('can_be_internal')
                ->default(false);

            $table->string('color')
                ->nullable();

            $table->string('icon')
                ->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedures');
    }
};