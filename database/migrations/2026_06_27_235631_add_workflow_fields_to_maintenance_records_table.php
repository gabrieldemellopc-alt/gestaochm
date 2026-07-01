<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->string('workflow_status')->default('closed')->after('reason');
            $table->string('service_status')->nullable()->after('workflow_status');
    
            $table->timestamp('started_at')->nullable()->after('performed_at');
            $table->timestamp('finished_at')->nullable()->after('started_at');
    
            $table->unsignedBigInteger('opened_by')->nullable()->after('finished_at');
            $table->unsignedBigInteger('closed_by')->nullable()->after('opened_by');
    
            $table->text('closure_notes')->nullable()->after('notes');
        });
    }
    
    public function down(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->dropColumn([
                'workflow_status',
                'service_status',
                'started_at',
                'finished_at',
                'opened_by',
                'closed_by',
                'closure_notes',
            ]);
        });
    }
};
