<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tire_entries', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('notes');
            $table->unsignedBigInteger('cancelled_by')->nullable()->after('cancelled_at');
            $table->text('cancel_reason')->nullable()->after('cancelled_by');

            $table->index('cancelled_at', 'tire_entries_cancelled_at_index');
        });

        Schema::table('tire_entry_items', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('tire_id');
            $table->unsignedBigInteger('cancelled_by')->nullable()->after('cancelled_at');
            $table->text('cancel_reason')->nullable()->after('cancelled_by');

            $table->index('cancelled_at', 'tire_entry_items_cancelled_at_index');
        });

        Schema::table('tires', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('notes');
            $table->unsignedBigInteger('cancelled_by')->nullable()->after('cancelled_at');
            $table->text('cancel_reason')->nullable()->after('cancelled_by');

            $table->index('cancelled_at', 'tires_cancelled_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('tires', function (Blueprint $table) {
            $table->dropIndex('tires_cancelled_at_index');
            $table->dropColumn([
                'cancelled_at',
                'cancelled_by',
                'cancel_reason',
            ]);
        });

        Schema::table('tire_entry_items', function (Blueprint $table) {
            $table->dropIndex('tire_entry_items_cancelled_at_index');
            $table->dropColumn([
                'cancelled_at',
                'cancelled_by',
                'cancel_reason',
            ]);
        });

        Schema::table('tire_entries', function (Blueprint $table) {
            $table->dropIndex('tire_entries_cancelled_at_index');
            $table->dropColumn([
                'cancelled_at',
                'cancelled_by',
                'cancel_reason',
            ]);
        });
    }
};
