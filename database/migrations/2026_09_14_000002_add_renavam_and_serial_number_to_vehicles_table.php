<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::table('vehicles', function (Blueprint $t) { $t->string('renavam', 40)->nullable()->after('plate'); $t->string('serial_number', 120)->nullable()->after('renavam'); }); } public function down(): void { Schema::table('vehicles', fn (Blueprint $t) => $t->dropColumn(['renavam','serial_number'])); } };
