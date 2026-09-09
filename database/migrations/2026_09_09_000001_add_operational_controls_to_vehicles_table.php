<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->boolean('km_control_enabled')->default(true)->after('current_km');
            $table->boolean('hours_control_enabled')->default(false)->after('current_hours');
            $table->boolean('tire_control_enabled')->default(true)->after('tire_layout');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['km_control_enabled', 'hours_control_enabled', 'tire_control_enabled']);
        });
    }
};
