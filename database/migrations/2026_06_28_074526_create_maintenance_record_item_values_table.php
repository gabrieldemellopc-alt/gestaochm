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
        Schema::create('maintenance_record_item_values', function (Blueprint $table) {
            $table->id();
        
            $table->unsignedBigInteger('maintenance_record_item_id');
            
            $table->foreign(
                'maintenance_record_item_id',
                'fk_mr_item_values_item'
            )
            ->references('id')
            ->on('maintenance_record_items')
            ->cascadeOnDelete();
        
            $table->unsignedBigInteger('procedure_field_id');
                
                $table->foreign(
                    'procedure_field_id',
                    'fk_mr_item_values_field'
                )
                ->references('id')
                ->on('procedure_fields');
        
            $table->text('value')->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
        
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_record_item_values');
    }
};
