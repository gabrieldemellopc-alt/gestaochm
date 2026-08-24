<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('vehicles', function (Blueprint $table) { $table->string('fleet_relation', 20)->default('internal')->after('type'); $table->index('fleet_relation'); }); Schema::table('locations', function (Blueprint $table) { $table->boolean('allow_aggregated_fuel')->default(false)->after('active'); $table->boolean('allow_aggregated_maintenance')->default(false)->after('allow_aggregated_fuel'); }); }
    public function down(): void { Schema::table('vehicles', function (Blueprint $table) { $table->dropIndex(['fleet_relation']); $table->dropColumn('fleet_relation'); }); Schema::table('locations', function (Blueprint $table) { $table->dropColumn(['allow_aggregated_fuel', 'allow_aggregated_maintenance']); }); }
};
