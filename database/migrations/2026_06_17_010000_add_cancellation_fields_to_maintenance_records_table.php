<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('notes');
            $table->unsignedBigInteger('cancelled_by')->nullable()->after('cancelled_at');
            $table->text('cancel_reason')->nullable()->after('cancelled_by');
            $table->index('cancelled_at', 'maintenance_records_cancelled_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->dropIndex('maintenance_records_cancelled_at_index');
            $table->dropColumn([
                'cancelled_at',
                'cancelled_by',
                'cancel_reason',
            ]);
        });
    }
};
