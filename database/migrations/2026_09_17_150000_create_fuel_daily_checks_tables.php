<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_daily_checks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('division_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();

            $table->date('operation_date');

            $table->string('status', 30)->default('pending');

            $table->json('system_snapshot')->nullable();
            $table->string('system_signature', 64)->nullable();

            $table->decimal('system_total_liters', 14, 3)->default(0);
            $table->decimal('manual_total_liters', 14, 3)->nullable();
            $table->decimal('difference_liters', 14, 3)->nullable();

            $table->foreignId('checked_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('checked_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['tenant_id', 'division_id', 'location_id', 'operation_date'],
                'fuel_daily_checks_context_date_unique'
            );
        });

        Schema::create('fuel_daily_check_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('fuel_daily_check_id')
                ->constrained('fuel_daily_checks')
                ->cascadeOnDelete();

            $table->string('source', 50);

            $table->foreignId('fuel_product_id')
                ->nullable()
                ->constrained('fuel_products')
                ->nullOnDelete();

            $table->string('product_name', 255);

            $table->unsignedInteger('fillings_count')->default(0);

            $table->decimal('system_liters', 14, 3)->default(0);
            $table->decimal('manual_liters', 14, 3)->nullable();
            $table->decimal('difference_liters', 14, 3)->nullable();

            $table->timestamps();

            $table->unique(
                ['fuel_daily_check_id', 'source', 'fuel_product_id'],
                'fuel_daily_check_item_unique'
            );
        });

        Schema::create('fuel_daily_check_files', function (Blueprint $table) {
            $table->id();

            $table->foreignId('fuel_daily_check_id')
                ->constrained('fuel_daily_checks')
                ->cascadeOnDelete();

            $table->string('disk', 30)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();

            $table->string('source', 30)->default('web');

            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });

        Schema::create('fuel_daily_check_upload_tokens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('fuel_daily_check_id')
                ->constrained('fuel_daily_checks')
                ->cascadeOnDelete();

            $table->string('token_hash', 64)->unique();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('expires_at');
            $table->timestamp('last_used_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_daily_check_upload_tokens');
        Schema::dropIfExists('fuel_daily_check_files');
        Schema::dropIfExists('fuel_daily_check_items');
        Schema::dropIfExists('fuel_daily_checks');
    }
};
