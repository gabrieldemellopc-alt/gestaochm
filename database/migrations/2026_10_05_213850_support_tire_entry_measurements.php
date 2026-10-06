<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tires', function (Blueprint $table) {
            $table
                ->string('acquisition_condition', 30)
                ->nullable()
                ->after('tread_grooves_count');
        });

        Schema::table('tire_measurements', function (Blueprint $table) {
            $table
                ->string('measurement_type', 30)
                ->default('operation')
                ->after('position_code');

            $table->index(
                ['tenant_id', 'measurement_type', 'measured_at'],
                'tire_measurements_type_date_index'
            );
        });

        DB::statement(
            'ALTER TABLE tire_measurements
             MODIFY vehicle_id BIGINT UNSIGNED NULL'
        );

        DB::statement(
            'ALTER TABLE tire_measurements
             MODIFY position_code VARCHAR(255) NULL'
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE tire_measurements
             MODIFY position_code VARCHAR(255) NOT NULL'
        );

        DB::statement(
            'ALTER TABLE tire_measurements
             MODIFY vehicle_id BIGINT UNSIGNED NOT NULL'
        );

        Schema::table('tire_measurements', function (Blueprint $table) {
            $table->dropIndex(
                'tire_measurements_type_date_index'
            );

            $table->dropColumn('measurement_type');
        });

        Schema::table('tires', function (Blueprint $table) {
            $table->dropColumn('acquisition_condition');
        });
    }
};
