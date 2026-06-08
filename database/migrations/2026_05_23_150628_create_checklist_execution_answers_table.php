<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'checklist_execution_answers',
            function (Blueprint $table)
            {
                $table->id();

                $table->foreignId(
                    'checklist_execution_id'
                );

                $table->foreignId(
                    'checklist_template_item_id'
                );

                $table->text('answer')
                    ->nullable();

                $table->string('photo_path')
                    ->nullable();

                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'checklist_execution_answers'
        );
    }
};