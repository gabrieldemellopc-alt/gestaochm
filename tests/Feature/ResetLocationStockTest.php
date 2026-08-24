<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Location;
use App\Models\Procedure;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResetLocationStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_does_not_write_and_commit_only_removes_target_location_data(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant']);
        $division = Division::create(['tenant_id' => $tenant->id, 'name' => 'Divisão']);
        $target = Location::create(['tenant_id' => $tenant->id, 'division_id' => $division->id, 'name' => 'Imperatriz', 'active' => true]);
        $other = Location::create(['tenant_id' => $tenant->id, 'division_id' => $division->id, 'name' => 'Barreiras', 'active' => true]);
        $category = StockCategory::create(['tenant_id' => $tenant->id, 'name' => 'Global']);
        $targetItem = StockItem::create(['tenant_id' => $tenant->id, 'location_id' => $target->id, 'stock_category_id' => $category->id, 'name' => 'Local', 'unit' => 'UN']);
        $otherItem = StockItem::create(['tenant_id' => $tenant->id, 'location_id' => $other->id, 'stock_category_id' => $category->id, 'name' => 'Externo', 'unit' => 'UN']);
        $targetMovement = StockMovement::create(['tenant_id' => $tenant->id, 'location_id' => $target->id, 'stock_item_id' => $targetItem->id, 'movement_type' => 'in', 'quantity' => 1]);
        $localProcedure = Procedure::create(['tenant_id' => $tenant->id, 'location_id' => $target->id, 'name' => 'Procedimento local']);
        $sharedProcedure = Procedure::create(['tenant_id' => $tenant->id, 'location_id' => $other->id, 'name' => 'Procedimento externo']);

        $arguments = ['--tenant' => $tenant->id, '--division' => $division->id, '--location' => $target->id];
        $this->artisan('chm:reset-location-stock', $arguments)->expectsOutputToContain('SAFE YES')->assertSuccessful();
        $this->assertDatabaseHas('stock_items', ['id' => $targetItem->id]);
        $this->assertDatabaseHas('stock_movements', ['id' => $targetMovement->id]);
        $this->assertDatabaseHas('procedures', ['id' => $localProcedure->id]);

        $this->artisan('chm:reset-location-stock', [...$arguments, '--commit' => true, '--confirm-location' => $target->id])->assertSuccessful();
        $this->assertDatabaseMissing('stock_items', ['id' => $targetItem->id]);
        $this->assertDatabaseMissing('stock_movements', ['id' => $targetMovement->id]);
        $this->assertDatabaseMissing('procedures', ['id' => $localProcedure->id]);
        $this->assertDatabaseHas('stock_items', ['id' => $otherItem->id]);
        $this->assertDatabaseHas('procedures', ['id' => $sharedProcedure->id]);
        $this->assertDatabaseHas('stock_categories', ['id' => $category->id]);
    }
}
