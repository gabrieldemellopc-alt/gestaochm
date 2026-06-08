<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_records', function (Blueprint $table) {

            $table->id();

            $table->foreignId('tenant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('vehicle_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('procedure_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->enum('maintenance_type', [

                'internal',
                'external'

            ]);

            $table->integer('performed_km')
                ->nullable();

            $table->integer('performed_hours')
                ->nullable();

            $table->date('performed_at');

            $table->integer('next_due_km')
                ->nullable();

            $table->integer('next_due_hours')
                ->nullable();

            $table->date('next_due_date')
                ->nullable();

            $table->decimal('total_cost', 12, 2)
                ->default(0);

            $table->text('notes')
                ->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_records');
    }
};