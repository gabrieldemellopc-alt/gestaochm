<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {

            $table->id();

            $table->foreignId('stock_item_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->enum('movement_type', [

                'in',
                'out'

            ]);

            $table->decimal('quantity', 12, 2);

            $table->decimal('unit_cost', 12, 2)
                ->default(0);

            $table->text('description')
                ->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};