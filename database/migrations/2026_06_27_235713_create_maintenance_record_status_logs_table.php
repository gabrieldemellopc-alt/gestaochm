<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_record_status_logs', function (Blueprint $table) {
            $table->id();
    
            $table->foreignId('maintenance_record_id')
                ->constrained('maintenance_records')
                ->cascadeOnDelete();
    
            $table->string('old_status')->nullable();
            $table->string('new_status');
    
            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
    
            $table->text('reason')->nullable();
    
            $table->timestamps();
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('maintenance_record_status_logs');
    }
};
