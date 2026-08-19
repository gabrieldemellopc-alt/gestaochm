<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_division_accesses', function (Blueprint $table) {
            $table
                ->foreignId('tenant_id')
                ->nullable()
                ->after('id')
                ->constrained('tenants')
                ->cascadeOnDelete();
        });

        // Backfill the tenant from each access record's division. Query Builder
        // keeps this historical migration compatible with both MySQL and SQLite.
        DB::table('user_division_accesses as uda')
            ->join('divisions as d', 'd.id', '=', 'uda.division_id')
            ->whereNull('uda.tenant_id')
            ->orderBy('uda.id')
            ->select('uda.id', 'd.tenant_id')
            ->each(function (object $access): void {
                DB::table('user_division_accesses')
                    ->where('id', $access->id)
                    ->whereNull('tenant_id')
                    ->update(['tenant_id' => $access->tenant_id]);
            });

        Schema::table('user_division_accesses', function (Blueprint $table) {
            $table
                ->foreignId('tenant_id')
                ->nullable(false)
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('user_division_accesses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });
    }
};
