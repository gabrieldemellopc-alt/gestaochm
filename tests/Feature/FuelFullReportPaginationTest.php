<?php

namespace Tests\Feature;

use App\Exports\FuelReportExport;
use App\Models\Division;
use App\Models\FuelFilling;
use App\Models\FuelProduct;
use App\Models\FuelTank;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Vehicle;
use App\Services\Reports\FuelReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class FuelFullReportPaginationTest extends TestCase
{
    use RefreshDatabase;

    private array $context;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::create(['name' => 'Teste']);
        $division = Division::create(['tenant_id' => $tenant->id, 'name' => 'Divisão']);
        $location = Location::create(['tenant_id' => $tenant->id, 'division_id' => $division->id, 'name' => 'Unidade', 'active' => true]);
        $product = FuelProduct::create(['tenant_id' => $tenant->id, 'name' => 'Diesel', 'slug' => 'diesel', 'unit' => 'L', 'active' => true]);
        $tank = FuelTank::create(['tenant_id' => $tenant->id, 'division_id' => $division->id, 'location_id' => $location->id, 'fuel_product_id' => $product->id, 'name' => 'Tanque', 'capacity_liters' => 1000, 'current_balance_liters' => 500, 'minimum_balance_liters' => 0, 'active' => true]);
        $vehicle = Vehicle::create(['tenant_id' => $tenant->id, 'division_id' => $division->id, 'location_id' => $location->id, 'name' => 'Veículo', 'plate' => 'ABC1234', 'type' => 'lixo', 'operational_status' => 'operational']);

        for ($number = 1; $number <= 30; $number++) {
            FuelFilling::create(['tenant_id' => $tenant->id, 'division_id' => $division->id, 'location_id' => $location->id, 'fuel_tank_id' => $tank->id, 'fuel_product_id' => $product->id, 'vehicle_id' => $vehicle->id, 'source' => FuelFilling::SOURCE_INTERNAL_TANK, 'filled_at' => now()->subMinutes($number), 'quantity_liters' => 10, 'unit_cost' => 5, 'total_cost' => 50]);
        }

        $this->context = ['tenant_id' => $tenant->id, 'division' => $division, 'location' => $location, 'can_view_costs' => true, 'can_view_cancelled' => false];
    }

    public function test_full_report_paginates_fillings_with_twenty_five_records_by_default(): void
    {
        $data = $this->build(['start_date' => now()->subDay()->toDateString(), 'end_date' => now()->toDateString()]);

        $this->assertSame(25, $data['fillings_page']->perPage());
        $this->assertCount(25, $data['fillings_page']->items());
        $this->assertSame(30, $data['fillings_page']->total());
    }

    public function test_pagination_links_preserve_the_active_filter_query_string(): void
    {
        $this->app['request']->replace(['start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'vehicle_id' => 9, 'fuel_product_id' => 4, 'fuel_tank_id' => 3, 'include_cancelled' => '1', 'per_page' => 25]);
        $data = $this->build($this->app['request']->query());

        $url = $data['fillings_page']->url(2);
        $this->assertStringContainsString('start_date=2026-01-01', $url);
        $this->assertStringContainsString('vehicle_id=9', $url);
        $this->assertStringContainsString('fuel_product_id=4', $url);
        $this->assertStringContainsString('fuel_tank_id=3', $url);
        $this->assertStringContainsString('include_cancelled=1', $url);
    }

    public function test_summary_and_exports_use_all_filtered_fillings_not_the_current_page(): void
    {
        $filters = ['start_date' => now()->subDay()->toDateString(), 'end_date' => now()->toDateString(), 'page' => 2];
        $this->app['request']->replace($filters);
        $data = $this->build($filters);

        $this->assertCount(5, $data['fillings_page']->items());
        $this->assertCount(30, $data['fillings_period']);
        $this->assertSame(300.0, $data['total_filled_liters']);

        $rows = (new ReflectionMethod(FuelReportExport::class, 'fillingRows'))->invoke(new FuelReportExport($data));
        $this->assertCount(31, $rows); // header plus every filtered filling

        $pdf = view('reports.pdf.fuel', $data)->render();
        $this->assertStringContainsString(now()->subMinutes(30)->format('d/m/Y H:i'), $pdf);
    }

    private function build(array $filters): array
    {
        return app(FuelReportService::class)->build($filters, $this->context, 25);
    }
}
