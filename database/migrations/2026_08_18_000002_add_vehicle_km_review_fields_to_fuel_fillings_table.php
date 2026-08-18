<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel_fillings', function (Blueprint $table) {
            $table->string('vehicle_km_status')->nullable()->after('vehicle_km');
            $table->text('vehicle_km_issue')->nullable()->after('vehicle_km_status');
            $table->foreignId('vehicle_km_reviewed_by')->nullable()->after('vehicle_km_issue')->constrained('users')->nullOnDelete();
            $table->timestamp('vehicle_km_reviewed_at')->nullable()->after('vehicle_km_reviewed_by');
            $table->index(['vehicle_id', 'vehicle_km_status', 'filled_at'], 'fuel_fillings_vehicle_km_status_filled_index');
        });
    }

    public function down(): void
    {
        Schema::table('fuel_fillings', function (Blueprint $table) {
            $table->dropIndex('fuel_fillings_vehicle_km_status_filled_index');
            $table->dropForeign(['vehicle_km_reviewed_by']);
            $table->dropColumn(['vehicle_km_status', 'vehicle_km_issue', 'vehicle_km_reviewed_by', 'vehicle_km_reviewed_at']);
        });
    }
};
