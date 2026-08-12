<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('maintenance_record_items', 'cancelled_at')) {
            Schema::table('maintenance_record_items', function (Blueprint $table) {
                $table->timestamp('cancelled_at')->nullable()->after('notes');
                $table->unsignedBigInteger('cancelled_by')->nullable()->after('cancelled_at');
                $table->text('cancel_reason')->nullable()->after('cancelled_by');
                $table->string('cancellation_type', 30)->nullable()->after('cancel_reason');
                $table->unsignedBigInteger('replaced_by_item_id')->nullable()->after('cancellation_type');
                $table->unsignedBigInteger('replacement_of_item_id')->nullable()->after('replaced_by_item_id');
            });
        }

        $indexExists = fn (string $name): bool => collect(Schema::getIndexes('maintenance_record_items'))
            ->contains(fn (array $index) => ($index['name'] ?? null) === $name);
        $foreignExists = fn (string $name): bool => collect(Schema::getForeignKeys('maintenance_record_items'))
            ->contains(fn (array $foreign) => ($foreign['name'] ?? null) === $name);

        if ($foreignExists('1')) {
            DB::statement('ALTER TABLE maintenance_record_items DROP FOREIGN KEY `1`');
        }
        if ($indexExists('1')) {
            DB::statement('ALTER TABLE maintenance_record_items DROP INDEX `1`');
        }

        Schema::table('maintenance_record_items', function (Blueprint $table) use ($indexExists, $foreignExists) {
            if (! $indexExists('maintenance_record_items_cancelled_at_index')) {
                $table->index('cancelled_at', 'maintenance_record_items_cancelled_at_index');
            }
            if (! $indexExists('maintenance_items_replaced_by_index')) {
                $table->index('replaced_by_item_id', 'maintenance_items_replaced_by_index');
            }
            if (! $indexExists('maintenance_items_replacement_of_index')) {
                $table->index('replacement_of_item_id', 'maintenance_items_replacement_of_index');
            }
            if (! $foreignExists('maintenance_record_items_cancelled_by_foreign')) {
                $table->foreign('cancelled_by', 'maintenance_record_items_cancelled_by_foreign')->references('id')->on('users')->nullOnDelete();
            }
            if (! $foreignExists('maintenance_items_replaced_by_foreign')) {
                $table->foreign('replaced_by_item_id', 'maintenance_items_replaced_by_foreign')->references('id')->on('maintenance_record_items')->nullOnDelete();
            }
            if (! $foreignExists('maintenance_items_replacement_of_foreign')) {
                $table->foreign('replacement_of_item_id', 'maintenance_items_replacement_of_foreign')->references('id')->on('maintenance_record_items')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_record_items', function (Blueprint $table) {
            $table->dropForeign('maintenance_record_items_cancelled_by_foreign');
            $table->dropForeign('maintenance_items_replaced_by_foreign');
            $table->dropForeign('maintenance_items_replacement_of_foreign');
            $table->dropColumn([
                'cancelled_at',
                'cancelled_by',
                'cancel_reason',
                'cancellation_type',
                'replaced_by_item_id',
                'replacement_of_item_id',
            ]);
        });
    }
};
