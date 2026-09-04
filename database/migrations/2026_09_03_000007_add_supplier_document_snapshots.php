<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { foreach (['stock_movements','workshop_expenses','fuel_receipts','fuel_fillings','tire_entries','tire_retreads','maintenance_records'] as $table) Schema::table($table, fn (Blueprint $t) => $t->string('supplier_document', 14)->nullable()->after('supplier_id')); }
    public function down(): void { foreach (['stock_movements','workshop_expenses','fuel_receipts','fuel_fillings','tire_entries','tire_retreads','maintenance_records'] as $table) Schema::table($table, fn (Blueprint $t) => $t->dropColumn('supplier_document')); }
};
