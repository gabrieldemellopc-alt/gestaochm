<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::table('data_consistency_alerts', fn(Blueprint $table) => $table->string('context_type')->default('current')->after('severity')->index()); } public function down(): void { Schema::table('data_consistency_alerts', fn(Blueprint $table) => $table->dropColumn('context_type')); } };
