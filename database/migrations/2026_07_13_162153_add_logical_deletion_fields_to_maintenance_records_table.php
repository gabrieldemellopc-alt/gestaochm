<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table
                ->timestamp('deleted_at')
                ->nullable()
                ->after('cancel_reason');

            $table
                ->foreignId('deleted_by')
                ->nullable()
                ->after('deleted_at')
                ->constrained('users')
                ->nullOnDelete();

            $table
                ->text('delete_reason')
                ->nullable()
                ->after('deleted_by');

            $table->index(
                ['tenant_id', 'deleted_at'],
                'maintenance_records_tenant_deleted_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->dropIndex(
                'maintenance_records_tenant_deleted_index'
            );

            $table->dropForeign([
                'deleted_by',
            ]);

            $table->dropColumn([
                'deleted_at',
                'deleted_by',
                'delete_reason',
            ]);
        });
    }
};