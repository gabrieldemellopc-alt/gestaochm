<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->foreignId('procedure_id')
                ->nullable()
                ->change();
    
            $table->enum('maintenance_type', ['internal', 'external'])
                ->nullable()
                ->change();
        });
    }
    
    public function down(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->foreignId('procedure_id')
                ->nullable(false)
                ->change();
    
            $table->enum('maintenance_type', ['internal', 'external'])
                ->nullable(false)
                ->change();
        });
    }
};
