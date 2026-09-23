<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'fuel_daily_check_upload_tokens',
            function (Blueprint $table) {
                $table
                    ->string('document_type', 30)
                    ->nullable()
                    ->after('fuel_daily_check_id');
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'fuel_daily_check_upload_tokens',
            function (Blueprint $table) {
                $table->dropColumn('document_type');
            }
        );
    }
};
