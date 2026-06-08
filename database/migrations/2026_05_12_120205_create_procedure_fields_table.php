<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procedure_fields', function (Blueprint $table) {

            $table->id();

            $table->foreignId('procedure_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('label');

            $table->string('slug');

            $table->enum('field_type', [

                'text',
                'textarea',
                'number',
                'money',
                'boolean',
                'date',
                'select',
                'stock_item'

            ]);

            $table->boolean('required')
                ->default(false);

            $table->json('options')
                ->nullable();

            $table->integer('sort_order')
                ->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedure_fields');
    }
};