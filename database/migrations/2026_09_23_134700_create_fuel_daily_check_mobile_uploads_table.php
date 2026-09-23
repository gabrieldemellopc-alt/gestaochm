<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'fuel_daily_check_mobile_uploads',
            function (Blueprint $table) {
                $table->id();

                $table
                    ->unsignedBigInteger(
                        'fuel_daily_check_upload_token_id'
                    );

                $table->string('disk', 30)->default('local');
                $table->string('path');
                $table->string('original_name');
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();

                $table->timestamps();

                $table
                    ->foreign(
                        'fuel_daily_check_upload_token_id',
                        'fdcmu_token_fk'
                    )
                    ->references('id')
                    ->on('fuel_daily_check_upload_tokens')
                    ->cascadeOnDelete();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'fuel_daily_check_mobile_uploads'
        );
    }
};
