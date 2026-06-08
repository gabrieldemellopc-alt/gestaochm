<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tire_entries', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table
                ->date('entry_date');

            $table
                ->string('supplier_name')
                ->nullable();

            $table
                ->string('invoice_number')
                ->nullable();

            $table
                ->unsignedInteger('quantity')
                ->default(0);

            $table
                ->decimal('unit_cost', 12, 2)
                ->nullable();

            $table
                ->decimal('total_cost', 12, 2)
                ->nullable();

            $table
                ->string('brand')
                ->nullable();

            $table
                ->string('model')
                ->nullable();

            $table
                ->string('size')
                ->nullable()
                ->comment('Ex: 275/80 R22.5');

            $table
                ->decimal('initial_tread_depth', 5, 2)
                ->nullable();

            $table
                ->string('code_prefix')
                ->nullable();

            $table
                ->text('notes')
                ->nullable();

            $table
                ->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index([
                'tenant_id',
                'entry_date',
            ], 'tire_entries_tenant_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tire_entries');
    }
};