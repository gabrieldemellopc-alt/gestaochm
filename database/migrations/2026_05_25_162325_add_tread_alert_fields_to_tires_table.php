<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tires', function (Blueprint $table) {
            if (! Schema::hasColumn('tires', 'warning_tread_depth')) {
                $table
                    ->decimal('warning_tread_depth', 5, 2)
                    ->nullable()
                    ->after('initial_tread_depth');
            }

            if (! Schema::hasColumn('tires', 'critical_tread_depth')) {
                $table
                    ->decimal('critical_tread_depth', 5, 2)
                    ->nullable()
                    ->after('warning_tread_depth');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tires', function (Blueprint $table) {
            if (Schema::hasColumn('tires', 'critical_tread_depth')) {
                $table->dropColumn('critical_tread_depth');
            }

            if (Schema::hasColumn('tires', 'warning_tread_depth')) {
                $table->dropColumn('warning_tread_depth');
            }
        });
    }
};