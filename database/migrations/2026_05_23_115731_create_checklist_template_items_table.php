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
        Schema::create('checklist_template_items', function (Blueprint $table) {
        
            $table->id();
        
            $table->foreignId('checklist_template_id')
                ->constrained()
                ->cascadeOnDelete();
        
            $table->string('label');
        
            $table->string('field_type')
                ->default('boolean');
        
            // boolean
            // text
            // number
            // photo
        
            $table->boolean('required')
                ->default(true);
        
            $table->integer('sort_order')
                ->default(0);
        
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checklist_template_items');
    }
};
