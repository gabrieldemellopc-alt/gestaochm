<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('km_meter_status', 20)->default('normal')->after('km_control_enabled');
            $table->string('hours_meter_status', 20)->default('normal')->after('hours_control_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', fn (Blueprint $table) => $table->dropColumn(['km_meter_status', 'hours_meter_status']));
    }
};
