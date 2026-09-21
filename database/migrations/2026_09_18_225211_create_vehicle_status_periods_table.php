<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_status_periods', function (Blueprint $table) {
            $table->id();

            $table->foreignId('vehicle_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('status', 20);

            $table->timestamp('started_at');

            $table->timestamp('ended_at')
                ->nullable();

            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('reason')
                ->nullable();

            $table->timestamps();

            $table->index([
                'vehicle_id',
                'started_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_status_periods');
    }
};
