<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('description');
            $table->unsignedBigInteger('cancelled_by')->nullable()->after('cancelled_at');
            $table->text('cancel_reason')->nullable()->after('cancelled_by');
            $table->unsignedBigInteger('reversal_movement_id')->nullable()->after('cancel_reason');
            $table->unsignedBigInteger('reversed_from_movement_id')->nullable()->after('reversal_movement_id');

            $table->index('cancelled_at', 'stock_movements_cancelled_at_index');
            $table->index('reversal_movement_id', 'stock_movements_reversal_index');
            $table->index('reversed_from_movement_id', 'stock_movements_reversed_from_index');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('stock_movements_cancelled_at_index');
            $table->dropIndex('stock_movements_reversal_index');
            $table->dropIndex('stock_movements_reversed_from_index');
            $table->dropColumn([
                'cancelled_at',
                'cancelled_by',
                'cancel_reason',
                'reversal_movement_id',
                'reversed_from_movement_id',
            ]);
        });
    }
};
