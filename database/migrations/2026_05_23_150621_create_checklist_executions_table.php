<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'checklist_executions',
            function (Blueprint $table)
            {
                $table->id();

                $table->foreignId('division_id');

                $table->foreignId('vehicle_id');

                $table->foreignId('checklist_template_id');

                $table->foreignId('user_id');

                $table->enum('status', [

                    'completed',
                    'pending',
                    'critical'

                ])->default('completed');

                $table->text('notes')
                    ->nullable();

                $table->timestamp('executed_at')
                    ->nullable();

                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'checklist_executions'
        );
    }
};