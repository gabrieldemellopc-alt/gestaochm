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
        Schema::create('maintenance_record_items', function (Blueprint $table) {
        $table->id();
    
        $table->foreignId('maintenance_record_id')
            ->constrained()
            ->cascadeOnDelete();
    
        $table->foreignId('procedure_id')
            ->constrained();
    
        $table->enum('maintenance_type', ['internal', 'external'])
            ->default('external');
    
        $table->integer('performed_km')->nullable();
        $table->integer('performed_hours')->nullable();
        $table->date('performed_at')->nullable();
    
        $table->decimal('total_cost', 12, 2)->default(0);
        $table->decimal('extra_cost', 12, 2)->default(0);
    
        $table->string('provider_name')->nullable();
        $table->text('notes')->nullable();
    
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_record_items');
    }
};
