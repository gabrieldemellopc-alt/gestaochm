<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Location;
use App\Models\Procedure;
use App\Models\SystemAuditLog;
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

    public function test_update_normalizes_renavam_and_serial_number_without_losing_leading_zeroes(): void
    {
        [$user, $vehicle] = $this->context();
        $this->actingAs($user)->withSession(['active_division_id'=>$vehicle->division_id,'active_location_id'=>$vehicle->location_id]);
        $data = $this->payload($vehicle, []);
        $data['renavam'] = ' 001.234.567-89 '; $data['serial_number'] = '  CAT-123/ABC  ';
        $this->put(route('vehicles.update', $vehicle), $data)->assertRedirect();
        $vehicle->refresh();
        $this->assertSame('00123456789', $vehicle->renavam);
        $this->assertSame('CAT-123/ABC', $vehicle->serial_number);
    }

    public function test_update_allows_empty_identifiers_and_persists_null(): void
    {
        [$user, $vehicle] = $this->context();
        $vehicle->update(['renavam'=>'01234567890','serial_number'=>'CAT-1']);
        $this->actingAs($user)->withSession(['active_division_id'=>$vehicle->division_id,'active_location_id'=>$vehicle->location_id]);
        $data = $this->payload($vehicle, []); $data['renavam'] = ''; $data['serial_number'] = '   ';
        $this->put(route('vehicles.update', $vehicle), $data)->assertRedirect();
        $this->assertNull($vehicle->fresh()->renavam); $this->assertNull($vehicle->fresh()->serial_number);
    }

    public function test_identifier_lengths_are_validated(): void
    {
        [$user, $vehicle] = $this->context();
        $this->actingAs($user)->withSession(['active_division_id'=>$vehicle->division_id,'active_location_id'=>$vehicle->location_id]);
        $data = $this->payload($vehicle, []); $data['renavam'] = str_repeat('1', 41); $data['serial_number'] = str_repeat('A', 121);
        $this->put(route('vehicles.update', $vehicle), $data)->assertSessionHasErrors(['renavam','serial_number']);
    }

    public function test_store_creates_a_vehicle_with_operational_controls_and_no_meter_change_audit(): void
    {
        [$user, $existingVehicle] = $this->context();
        $this->actingAs($user)->withSession(['active_division_id' => $existingVehicle->division_id, 'active_location_id' => $existingVehicle->location_id]);

        $response = $this->post(route('vehicles.store'), [
            'division_id' => $existingVehicle->division_id,
            'location_id' => $existingVehicle->location_id,
            'type' => 'automovel',
            'name' => 'Veículo criado pelo fluxo real',
            'plate' => 'NEW-1A23',
            'current_km' => 1234,
            'current_hours' => 56,
            'operational_status' => 'operational',
            'status' => 'active',
            'km_control_enabled' => 1,
            'hours_control_enabled' => 1,
            'tire_control_enabled' => 1,
            'km_meter_status' => Vehicle::METER_STATUS_NORMAL,
            'hours_meter_status' => Vehicle::METER_STATUS_NORMAL,
            'asset_code' => 'VEIC-001',
            'renavam' => '012.345.678-90',
            'serial_number' => '  CAT-123/ABC  ',
        ]);

        $response->assertRedirect(route('vehicles.index'));

        $vehicle = Vehicle::query()->where('plate', 'NEW-1A23')->firstOrFail();
        $this->assertSame('Veículo criado pelo fluxo real', $vehicle->name);
        $this->assertSame('VEIC-001', $vehicle->asset_code);
        $this->assertSame('01234567890', $vehicle->renavam);
        $this->assertSame('CAT-123/ABC', $vehicle->serial_number);
        $this->assertTrue($vehicle->km_control_enabled);
        $this->assertTrue($vehicle->hours_control_enabled);
        $this->assertTrue($vehicle->tire_control_enabled);
        $this->assertSame(Vehicle::METER_STATUS_NORMAL, $vehicle->km_meter_status);
        $this->assertSame(Vehicle::METER_STATUS_NORMAL, $vehicle->hours_meter_status);
        $this->assertSame(0, SystemAuditLog::query()->where('auditable_type', Vehicle::class)->where('auditable_id', $vehicle->id)->where('action', 'vehicle_meter_status_updated')->count());
    }

    public function test_update_audits_meter_status_only_when_it_changes(): void
    {
        [$user, $vehicle] = $this->context();
        $this->actingAs($user)->withSession(['active_division_id' => $vehicle->division_id, 'active_location_id' => $vehicle->location_id]);

        $payload = $this->payload($vehicle, []);
        $payload['km_meter_status'] = Vehicle::METER_STATUS_FAULTY;
        $payload['hours_meter_status'] = Vehicle::METER_STATUS_NORMAL;

        $this->put(route('vehicles.update', $vehicle), $payload)->assertRedirect();

        $audit = SystemAuditLog::query()
            ->where('auditable_type', Vehicle::class)
            ->where('auditable_id', $vehicle->id)
            ->where('action', 'vehicle_meter_status_updated')
            ->sole();

        $this->assertSame(Vehicle::METER_STATUS_NORMAL, $audit->before_data['km_meter_status']);
        $this->assertSame(Vehicle::METER_STATUS_FAULTY, $audit->after_data['km_meter_status']);

        $this->put(route('vehicles.update', $vehicle->fresh()), $payload)->assertRedirect();

        $this->assertSame(1, SystemAuditLog::query()
            ->where('auditable_type', Vehicle::class)
            ->where('auditable_id', $vehicle->id)
            ->where('action', 'vehicle_meter_status_updated')
            ->count());
    }

    public function test_store_allows_omitted_renavam_and_serial_number(): void
    {
        [$user, $existingVehicle] = $this->context();
        $this->actingAs($user)->withSession(['active_division_id' => $existingVehicle->division_id, 'active_location_id' => $existingVehicle->location_id]);

        $this->post(route('vehicles.store'), [
            'division_id' => $existingVehicle->division_id,
            'location_id' => $existingVehicle->location_id,
            'type' => 'automovel',
            'name' => 'Veículo sem identificadores opcionais',
            'plate' => 'NUL-1A23',
            'km_control_enabled' => 1,
            'hours_control_enabled' => 0,
            'tire_control_enabled' => 1,
        ])->assertRedirect(route('vehicles.index'));

        $vehicle = Vehicle::query()->where('plate', 'NUL-1A23')->firstOrFail();
        $this->assertNull($vehicle->renavam);
        $this->assertNull($vehicle->serial_number);
    }

    public function test_create_and_edit_forms_identify_only_required_fields(): void
    {
        [$user, $vehicle] = $this->context();
        $this->actingAs($user)->withSession(['active_division_id' => $vehicle->division_id, 'active_location_id' => $vehicle->location_id]);

        $create = $this->get(route('vehicles.create'))->assertOk()->getContent();
        $edit = $this->get(route('vehicles.edit', $vehicle))->assertOk()->getContent();

        foreach ([$create, $edit] as $form) {
            $this->assertStringContainsString('class="form-required-note"', $form);
            $this->assertStringContainsString('Campos obrigatórios', $form);
            $this->assertStringContainsString('Nome <span class="required-mark">*</span>', $form);
            $this->assertStringContainsString('RENAVAM</label>', $form);
            $this->assertStringContainsString('Nº de série</label>', $form);
            $this->assertStringNotContainsString('RENAVAM <span class="required-mark">*</span>', $form);
            $this->assertStringNotContainsString('Nº de série <span class="required-mark">*</span>', $form);
        }
    }

    private function payload(Vehicle $vehicle, array $procedures): array
    {
        return ['name' => $vehicle->name, 'plate' => $vehicle->plate, 'brand' => $vehicle->brand, 'model' => $vehicle->model, 'year' => $vehicle->year, 'current_km' => $vehicle->current_km, 'current_hours' => $vehicle->current_hours, 'status' => 'active', 'operational_status' => 'operational', 'type' => 'automovel', 'division_id' => $vehicle->division_id, 'location_id' => $vehicle->location_id, 'tire_layout' => 'truck_6_mixed', 'km_control_enabled' => true, 'hours_control_enabled' => false, 'tire_control_enabled' => true, 'procedures' => $procedures];
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
