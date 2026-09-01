<?php

namespace Tests\Feature;

use App\Models\MaintenanceRecord;
use App\Models\StockMovement;
use App\Services\Reports\FinancialReportService;
use App\Services\Reports\ReportContextService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class FinancialReportMaintenanceTemporalCostTest extends TestCase
{
    public function test_maintenance_costs_follow_their_own_event_dates(): void
    {
        $original = DB::getDefaultConnection();
        Config::set('database.connections.financial_temporal_test', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        DB::setDefaultConnection('financial_temporal_test');

        try {
            $this->createSchema();
            DB::table('maintenance_records')->insert([
                'id' => 1, 'tenant_id' => 1, 'vehicle_id' => 1,
                'performed_at' => '2026-05-20', 'started_at' => '2026-05-20 08:00:00',
                'finished_at' => '2026-07-20 17:00:00', 'created_at' => '2026-05-20 08:00:00',
            ]);
            DB::table('maintenance_record_items')->insert([
                ['id' => 1, 'maintenance_record_id' => 1, 'performed_at' => '2026-06-05', 'total_cost' => 1000, 'extra_cost' => 1000, 'cancelled_at' => null],
                ['id' => 2, 'maintenance_record_id' => 1, 'performed_at' => '2026-07-10', 'total_cost' => 2000, 'extra_cost' => 2000, 'cancelled_at' => null],
                ['id' => 3, 'maintenance_record_id' => 1, 'performed_at' => '2026-06-06', 'total_cost' => 900, 'extra_cost' => 900, 'cancelled_at' => '2026-06-06 10:00:00'],
            ]);
            DB::table('stock_movements')->insert([
                'id' => 1, 'tenant_id' => 1, 'location_id' => 1, 'maintenance_record_id' => 1,
                'movement_type' => 'out', 'total_cost' => 300, 'moved_at' => '2026-06-15 10:00:00',
            ]);
            DB::table('maintenance_record_extra_costs')->insert([
                'maintenance_record_id' => 1, 'amount' => 400, 'cost_date' => '2026-07-12', 'created_at' => '2026-07-12 09:00:00',
            ]);

            $report = $this->reportService();
            $this->assertSame(0.0, $report->build(['start_date' => '2026-05-01', 'end_date' => '2026-05-31'], $this->context())['maintenance_total']);
            $this->assertSame(1300.0, $report->build(['start_date' => '2026-06-01', 'end_date' => '2026-06-30'], $this->context())['maintenance_total']);
            $this->assertSame(2400.0, $report->build(['start_date' => '2026-07-01', 'end_date' => '2026-07-31'], $this->context())['maintenance_total']);
        } finally {
            DB::purge('financial_temporal_test');
            DB::setDefaultConnection($original);
        }
    }

    public function test_stock_acquisitions_are_separate_from_operational_cost_and_ignore_reversals(): void
    {
        $original = DB::getDefaultConnection();
        Config::set('database.connections.financial_temporal_test', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        DB::setDefaultConnection('financial_temporal_test');

        try {
            $this->createSchema();
            DB::table('maintenance_records')->insert(['id' => 1, 'tenant_id' => 1, 'vehicle_id' => 1, 'created_at' => '2026-06-01 08:00:00']);
            $movements = [
                ['id' => 1, 'tenant_id' => 1, 'location_id' => 1, 'movement_type' => 'in', 'total_cost' => 300, 'description' => 'Compra de estoque', 'moved_at' => '2026-06-10 10:00:00'],
                ['id' => 2, 'tenant_id' => 1, 'location_id' => 1, 'maintenance_record_id' => 1, 'movement_type' => 'in', 'total_cost' => 200, 'description' => 'Compra direta para manutenção', 'moved_at' => '2026-06-15 10:00:00'],
                ['id' => 3, 'tenant_id' => 1, 'location_id' => 1, 'maintenance_record_id' => 1, 'movement_type' => 'out', 'total_cost' => 200, 'description' => 'Uso em manutenção', 'moved_at' => '2026-06-15 10:00:00'],
                ['id' => 4, 'tenant_id' => 1, 'location_id' => 1, 'movement_type' => 'in', 'total_cost' => 200, 'description' => 'Compra de estoque', 'moved_at' => '2026-05-10 10:00:00'],
                ['id' => 5, 'tenant_id' => 1, 'location_id' => 1, 'movement_type' => 'in', 'total_cost' => 50, 'description' => 'Compra cancelada', 'moved_at' => '2026-06-21 10:00:00', 'reversal_movement_id' => 6],
                ['id' => 6, 'tenant_id' => 1, 'location_id' => 1, 'movement_type' => 'in', 'total_cost' => 40, 'description' => 'Estorno', 'moved_at' => '2026-06-22 10:00:00', 'reversed_from_movement_id' => 5],
                ['id' => 7, 'tenant_id' => 1, 'location_id' => 1, 'movement_type' => 'in', 'total_cost' => 99, 'description' => 'Estoque inicial', 'moved_at' => '2026-06-23 10:00:00'],
                ['id' => 8, 'tenant_id' => 1, 'location_id' => 2, 'movement_type' => 'in', 'total_cost' => 500, 'description' => 'Outra unidade', 'moved_at' => '2026-06-24 10:00:00'],
            ];
            foreach ($movements as $movement) {
                DB::table('stock_movements')->insert($movement);
            }
            DB::table('maintenance_material_usages')->insert(['id' => 1, 'stock_movement_id' => 3, 'purchase_entry_movement_id' => 2]);

            $june = $this->reportService()->build(['start_date' => '2026-06-01', 'end_date' => '2026-06-30'], $this->context());
            $may = $this->reportService()->build(['start_date' => '2026-05-01', 'end_date' => '2026-05-31'], $this->context());

            $this->assertSame(300.0, $june['stock_purchases_total']);
            $this->assertSame(1, $june['stock_purchase_entries_count']);
            $this->assertSame(200.0, $june['maintenance_total']);
            $this->assertSame(200.0, $june['total']);
            $this->assertSame(200.0, $may['stock_purchases_total']);
            $this->assertSame(0.0, $may['total']);
        } finally {
            DB::purge('financial_temporal_test');
            DB::setDefaultConnection($original);
        }
    }

    private function reportService(): FinancialReportService
    {
        $context = Mockery::mock(ReportContextService::class);
        $context->shouldReceive('maintenanceQuery')->andReturnUsing(
            fn () => MaintenanceRecord::query()->where('tenant_id', 1)
        );
        $context->shouldReceive('stockMovementQuery')->andReturnUsing(
            fn () => StockMovement::query()->where('tenant_id', 1)->where('location_id', 1)
        );

        return new FinancialReportService($context);
    }

    private function context(): array
    {
        return ['tenant_id' => 1, 'division' => (object) ['id' => 1], 'location' => (object) ['id' => 1]];
    }

    private function createSchema(): void
    {
        Schema::create('maintenance_records', function ($table) {
            $table->id(); $table->unsignedBigInteger('tenant_id'); $table->unsignedBigInteger('vehicle_id');
            $table->date('performed_at')->nullable(); $table->timestamp('started_at')->nullable(); $table->timestamp('finished_at')->nullable();
            $table->timestamp('cancelled_at')->nullable(); $table->timestamp('deleted_at')->nullable(); $table->timestamps();
        });
        Schema::create('maintenance_record_items', function ($table) {
            $table->id(); $table->unsignedBigInteger('maintenance_record_id'); $table->date('performed_at')->nullable();
            $table->decimal('total_cost', 12, 2); $table->decimal('extra_cost', 12, 2); $table->timestamp('cancelled_at')->nullable();
        });
        Schema::create('stock_movements', function ($table) {
            $table->id(); $table->unsignedBigInteger('tenant_id'); $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('maintenance_record_id')->nullable(); $table->unsignedBigInteger('maintenance_record_item_id')->nullable();
            $table->string('movement_type'); $table->decimal('total_cost', 12, 2); $table->string('description')->nullable(); $table->timestamp('moved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable(); $table->unsignedBigInteger('reversal_movement_id')->nullable(); $table->unsignedBigInteger('reversed_from_movement_id')->nullable();
        });
        Schema::create('maintenance_material_usages', function ($table) {
            $table->id(); $table->unsignedBigInteger('stock_movement_id'); $table->unsignedBigInteger('purchase_entry_movement_id')->nullable(); $table->timestamp('cancelled_at')->nullable();
        });
        Schema::create('maintenance_record_extra_costs', function ($table) {
            $table->id(); $table->unsignedBigInteger('maintenance_record_id'); $table->decimal('amount', 12, 2);
            $table->date('cost_date')->nullable(); $table->timestamp('created_at')->nullable(); $table->timestamp('updated_at')->nullable();
        });
        Schema::create('fuel_fillings', function ($table) {
            $table->id(); $table->unsignedBigInteger('tenant_id'); $table->unsignedBigInteger('division_id'); $table->unsignedBigInteger('location_id');
            $table->timestamp('cancelled_at')->nullable(); $table->timestamp('filled_at')->nullable(); $table->decimal('total_cost', 12, 2);
        });
    }
}
