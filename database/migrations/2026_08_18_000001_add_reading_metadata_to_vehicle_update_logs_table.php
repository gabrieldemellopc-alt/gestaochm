<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_update_logs', function (Blueprint $table) {
            $table->dateTime('read_at')->nullable()->after('source');
            $table->foreignId('fuel_filling_id')
                ->nullable()
                ->after('read_at')
                ->constrained('fuel_fillings')
                ->nullOnDelete();

            // A filling can produce one KM and one hours log, but never two logs of the same type.
            $table->unique(['fuel_filling_id', 'type'], 'vehicle_update_logs_filling_type_unique');
            $table->index(['vehicle_id', 'type', 'read_at'], 'vehicle_update_logs_vehicle_type_read_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_update_logs', function (Blueprint $table) {
            $table->dropUnique('vehicle_update_logs_filling_type_unique');
            $table->dropIndex('vehicle_update_logs_vehicle_type_read_at_index');
            $table->dropForeign(['fuel_filling_id']);
            $table->dropColumn(['fuel_filling_id', 'read_at']);
        });
    }
};
