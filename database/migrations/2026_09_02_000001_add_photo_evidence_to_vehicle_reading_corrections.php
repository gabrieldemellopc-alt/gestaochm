<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vehicle_reading_corrections', function (Blueprint $table) {
            $table->string('verification_mode', 20)->default('video')->after('reason');
        });
        Schema::table('vehicle_reading_correction_evidences', function (Blueprint $table) {
            $table->string('evidence_type', 30)->default('video')->after('status');
            $table->index(['correction_id', 'evidence_type'], 'vrc_evid_correction_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_reading_correction_evidences', function (Blueprint $table) {
            $table->dropIndex('vrc_evid_correction_type_idx');
            $table->dropColumn('evidence_type');
        });
        Schema::table('vehicle_reading_corrections', fn (Blueprint $table) => $table->dropColumn('verification_mode'));
    }
};
