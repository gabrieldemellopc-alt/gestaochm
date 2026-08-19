<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::table('vehicle_update_logs', function (Blueprint $t) {
        $t->string('reading_status')->nullable()->after('fuel_filling_id');
        $t->text('reading_issue')->nullable()->after('reading_status');
        $t->foreignId('reviewed_by')->nullable()->after('reading_issue')->constrained('users')->nullOnDelete();
        $t->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        $t->index(['vehicle_id', 'type', 'reading_status', 'read_at'], 'vehicle_logs_reading_status_index');
    }); }
    public function down(): void { Schema::table('vehicle_update_logs', function (Blueprint $t) {
        $t->dropIndex('vehicle_logs_reading_status_index'); $t->dropForeign(['reviewed_by']);
        $t->dropColumn(['reading_status','reading_issue','reviewed_by','reviewed_at']);
    }); }
};
