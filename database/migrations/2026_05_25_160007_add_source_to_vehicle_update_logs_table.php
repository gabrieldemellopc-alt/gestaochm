<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_update_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('vehicle_update_logs', 'source')) {
                $table
                    ->string('source')
                    ->nullable()
                    ->after('type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_update_logs', function (Blueprint $table) {
            if (Schema::hasColumn('vehicle_update_logs', 'source')) {
                $table->dropColumn('source');
            }
        });
    }
};