<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::table('vehicle_update_logs', function (Blueprint $table) {

            $table->dropForeign(['vehicle_id']);
            $table->dropForeign(['user_id']);
            $table->dropForeign(['division_id']);
            $table->dropForeign(['location_id']);

            $table->dropColumn([

                'vehicle_id',
                'user_id',
                'division_id',
                'location_id',
                'type',
                'old_value',
                'new_value',
                'observation',

            ]);

        });
    }
};