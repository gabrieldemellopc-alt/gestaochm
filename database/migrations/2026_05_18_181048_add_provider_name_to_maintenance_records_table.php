<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(

            'maintenance_records',

            function (Blueprint $table) {

                $table->string('provider_name')
                    ->nullable()
                    ->after('notes');
            }
        );
    }

    public function down(): void
    {
        Schema::table(

            'maintenance_records',

            function (Blueprint $table) {

                $table->dropColumn(
                    'provider_name'
                );
            }
        );
    }
};