<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Location;
use App\Models\Procedure;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserDivisionAccess;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleProcedurePersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_route_persists_and_removes_the_exact_procedure_set_for_the_same_location(): void
    {
        [$user, $vehicle, $procedure] = $this->context();
        $this->actingAs($user)->withSession(['active_division_id' => $vehicle->division_id, 'active_location_id' => $vehicle->location_id]);
        session(['active_division_id' => $vehicle->division_id, 'active_location_id' => $vehicle->location_id]);
        $response = $this->put(route('vehicles.update', $vehicle), $this->payload($vehicle, [$procedure->id]));
        $response->assertRedirect();
        $this->assertDatabaseHas('procedure_vehicle', ['vehicle_id' => $vehicle->id, 'procedure_id' => $procedure->id]);
        $this->assertTrue($vehicle->fresh()->load('procedures')->procedures->contains('id', $procedure->id));

        $edit = $this->get(route('vehicles.edit', $vehicle))->assertOk();
        $edit->assertSee('Selecionar todos')->assertSee('Desmarcar todos');
        $this->assertMatchesRegularExpression('/name="procedures\[\]"\s+value="'.$procedure->id.'"[\s\S]{0,100}checked/', $edit->getContent());

        $this->put(route('vehicles.update', $vehicle), $this->payload($vehicle, []))->assertRedirect(route('vehicles.index'));
        $this->assertDatabaseMissing('procedure_vehicle', ['vehicle_id' => $vehicle->id, 'procedure_id' => $procedure->id]);
        $this->assertFalse($vehicle->fresh()->load('procedures')->procedures->contains('id', $procedure->id));
    }

    private function payload(Vehicle $vehicle, array $procedures): array
    {
        return ['name' => $vehicle->name, 'plate' => $vehicle->plate, 'brand' => $vehicle->brand, 'model' => $vehicle->model, 'year' => $vehicle->year, 'current_km' => $vehicle->current_km, 'current_hours' => $vehicle->current_hours, 'status' => 'active', 'operational_status' => 'operational', 'type' => 'automovel', 'division_id' => $vehicle->division_id, 'location_id' => $vehicle->location_id, 'tire_layout' => 'truck_6_mixed', 'procedures' => $procedures];
    }

    private function context(): array
    {
        $tenant = Tenant::create(['name' => 'Tenant Imperatriz']);
        $division = Division::create(['tenant_id' => $tenant->id, 'name' => 'Imperatriz']);
        $location = Location::create(['tenant_id' => $tenant->id, 'division_id' => $division->id, 'name' => 'Imperatriz', 'active' => true]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        UserDivisionAccess::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'division_id' => $division->id, 'location_id' => $location->id, 'module' => 'fleet', 'profile' => 'manager', 'active' => true]);
        $vehicle = Vehicle::create(['tenant_id' => $tenant->id, 'division_id' => $division->id, 'location_id' => $location->id, 'name' => 'VCA001', 'plate' => 'ABC-1D23', 'type' => 'automovel', 'status' => 'active', 'operational_status' => 'operational', 'current_km' => 0, 'current_hours' => 0]);
        $procedure = Procedure::create(['tenant_id' => $tenant->id, 'location_id' => $location->id, 'name' => 'Troca / reposição de óleo hidráulico', 'can_be_internal' => true]);
        return [$user, $vehicle, $procedure];
    }
}
