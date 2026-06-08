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

        DB::statement("
            UPDATE user_division_accesses uda
            JOIN divisions d ON d.id = uda.division_id
            SET uda.tenant_id = d.tenant_id
            WHERE uda.tenant_id IS NULL
        ");

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