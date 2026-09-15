<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('data_consistency_alerts', fn(Blueprint $table) => $table->timestamp('last_scanned_at')->nullable()->after('last_detected_at')->index()); }
    public function down(): void { Schema::table('data_consistency_alerts', fn(Blueprint $table) => $table->dropColumn('last_scanned_at')); }
};
