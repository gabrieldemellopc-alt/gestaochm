<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The preceding create migration already introduced these columns.
        // Keep this historical "fix" migration idempotent for clean SQLite
        // databases and installations where only a partial legacy schema exists.
        if (! Schema::hasColumn('vehicle_update_logs', 'vehicle_id')) {
            Schema::table('vehicle_update_logs', function (Blueprint $table) {

            $table->foreignId('vehicle_id')
                ->after('id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->after('vehicle_id')
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('division_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('location_id')
                ->nullable()
                ->after('division_id')
                ->constrained()
                ->nullOnDelete();

            $table->string('type')
                ->after('location_id');

            $table->string('old_value')
                ->nullable()
                ->after('type');

            $table->string('new_value')
                ->nullable()
                ->after('old_value');

            $table->text('observation')
                ->nullable()
                ->after('new_value');

            });
        }
    }

    public function down(): void
    {
        // No-op: all columns belong to the canonical create migration.
    }
};
