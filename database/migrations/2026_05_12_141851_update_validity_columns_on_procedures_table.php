<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procedures', function (Blueprint $table) {

            $table->boolean('validity_km')
                ->default(false)
                ->after('name');

            $table->boolean('validity_hours')
                ->default(false)
                ->after('validity_km');

            $table->boolean('validity_period')
                ->default(false)
                ->after('validity_hours');

            if (
                Schema::hasColumn(
                    'procedures',
                    'validity_type'
                )
            ) {

                $table->dropColumn('validity_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('procedures', function (Blueprint $table) {

            $table->string('validity_type')
                ->nullable();

            $table->dropColumn([
                'validity_km',
                'validity_hours',
                'validity_period'
            ]);
        });
    }
};