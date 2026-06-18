<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tire_measurements', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('notes');
            $table->unsignedBigInteger('cancelled_by')->nullable()->after('cancelled_at');
            $table->text('cancel_reason')->nullable()->after('cancelled_by');

            $table->index('cancelled_at', 'tire_measurements_cancelled_at_index');
        });

        Schema::table('tire_retreads', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('notes');
            $table->unsignedBigInteger('cancelled_by')->nullable()->after('cancelled_at');
            $table->text('cancel_reason')->nullable()->after('cancelled_by');

            $table->index('cancelled_at', 'tire_retreads_cancelled_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('tire_retreads', function (Blueprint $table) {
            $table->dropIndex('tire_retreads_cancelled_at_index');
            $table->dropColumn([
                'cancelled_at',
                'cancelled_by',
                'cancel_reason',
            ]);
        });

        Schema::table('tire_measurements', function (Blueprint $table) {
            $table->dropIndex('tire_measurements_cancelled_at_index');
            $table->dropColumn([
                'cancelled_at',
                'cancelled_by',
                'cancel_reason',
            ]);
        });
    }
};
