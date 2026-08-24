<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Location;
use App\Models\Procedure;
use App\Models\ProcedureField;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\Tenant;
use App\Models\Vehicle;
use App\Services\MaintenanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProcedureStockItemsTest extends TestCase
{
    use RefreshDatabase;

    public function test_specific_items_override_category_and_other_locations_are_rejected(): void
    {
        [$procedure, $field, $allowed, $sameCategory, $outside, $vehicle] = $this->context();
        $procedure->stockItems()->attach($allowed);

        $this->assertSame([$allowed->id], $procedure->fresh()->stockItems->pluck('id')->all());
        $this->assertAllowed($procedure, $field, $allowed, $vehicle);
        $this->assertRejected($procedure, $field, $sameCategory, $vehicle);
        $this->assertRejected($procedure, $field, $outside, $vehicle);
    }

    public function test_legacy_category_fallback_and_pivot_uniqueness_are_preserved(): void
    {
        [$procedure, $field, $allowed, $sameCategory, $outside, $vehicle] = $this->context();
        $this->assertAllowed($procedure, $field, $sameCategory, $vehicle);
        $this->assertRejected($procedure, $field, $outside, $vehicle);

        $procedure->stockItems()->sync([$allowed->id, $allowed->id]);
        $this->assertDatabaseCount('procedure_stock_items', 1);
        $procedure->stockItems()->sync([$sameCategory->id]);
        $this->assertSame([$sameCategory->id], $procedure->fresh()->stockItems->pluck('id')->all());
    }

    private function assertAllowed(Procedure $procedure, ProcedureField $field, StockItem $item, Vehicle $vehicle): void
    {
        $method = new \ReflectionMethod(MaintenanceService::class, 'lockAndValidateStockItems');
        $result = $method->invoke(null, new Collection([['field' => $field, 'item_id' => $item->id, 'quantity' => 1]]), $vehicle, false, $procedure->fresh('stockItems'));
        $this->assertTrue($result->has($item->id));
    }

    private function assertRejected(Procedure $procedure, ProcedureField $field, StockItem $item, Vehicle $vehicle): void
    {
        $this->expectException(ValidationException::class);
        $this->assertAllowed($procedure, $field, $item, $vehicle);
    }

    private function context(): array
    {
        $tenant = Tenant::create(['name' => 'Teste']);
        $division = Division::create(['tenant_id' => $tenant->id, 'name' => 'Divisão']);
        $location = Location::create(['tenant_id' => $tenant->id, 'division_id' => $division->id, 'name' => 'A', 'active' => true]);
        $other = Location::create(['tenant_id' => $tenant->id, 'division_id' => $division->id, 'name' => 'B', 'active' => true]);
        $category = StockCategory::create(['tenant_id' => $tenant->id, 'name' => 'Óleos']);
        $procedure = Procedure::create(['tenant_id' => $tenant->id, 'location_id' => $location->id, 'name' => 'Procedimento', 'can_be_internal' => true]);
        $field = ProcedureField::create(['procedure_id' => $procedure->id, 'label' => 'Material', 'slug' => 'material', 'field_type' => 'stock_item', 'stock_category_id' => $category->id]);
        $item = fn (string $name, int $locationId) => StockItem::create(['tenant_id' => $tenant->id, 'location_id' => $locationId, 'stock_category_id' => $category->id, 'name' => $name, 'unit' => 'UNID', 'quantity' => 5, 'active' => true]);
        return [$procedure, $field, $item('Permitido', $location->id), $item('Mesma categoria', $location->id), $item('Outra unidade', $other->id), new Vehicle(['tenant_id' => $tenant->id, 'location_id' => $location->id])];
    }
}
