<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_items', function (Blueprint $table) {

            $table->id();

            $table->foreignId('tenant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');

            $table->string('unit');

            $table->decimal('quantity', 12, 2)
                ->default(0);

            $table->decimal('minimum_quantity', 12, 2)
                ->default(0);

            $table->decimal('unit_cost', 12, 2)
                ->default(0);

            $table->boolean('active')
                ->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_items');
    }
};