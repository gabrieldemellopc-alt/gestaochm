<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workshop_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('division_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->date('expense_date');
            $table->string('category', 32);
            $table->string('description');
            $table->string('supplier_name')->nullable();
            $table->string('invoice_number')->nullable();
            $table->decimal('amount', 12, 2);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'division_id', 'location_id', 'expense_date'], 'workshop_expenses_context_date_idx');
        });
    }
    public function down(): void { Schema::dropIfExists('workshop_expenses'); }
};
