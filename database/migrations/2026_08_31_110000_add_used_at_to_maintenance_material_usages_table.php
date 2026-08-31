<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_material_usages', function (Blueprint $table) {
            $table->timestamp('used_at')->nullable()->after('total_cost');
        });

        DB::table('maintenance_material_usages')
            ->whereNull('used_at')
            ->update(['used_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('maintenance_material_usages', function (Blueprint $table) {
            $table->dropColumn('used_at');
        });
    }
};
