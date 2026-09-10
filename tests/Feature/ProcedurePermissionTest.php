<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Location;
use App\Models\Procedure;
use App\Models\ProfilePermissionOverride;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserDivisionAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcedurePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_can_view_procedures_and_default_can_create_and_update(): void
    {
        $context = $this->context('supervisor');
        $procedure = $this->procedure($context);

        $this->get(route('procedures.index'))->assertOk()->assertSee($procedure->name)->assertSee('Novo procedimento')->assertSee('Editar procedimento');
    }

    public function test_user_without_procedure_view_permission_receives_forbidden(): void
    {
        $context = $this->context('supervisor');
        $this->override($context, 'maintenance.procedures.view', false);

        $this->get(route('procedures.index'))->assertForbidden();
    }

    public function test_new_and_edit_actions_are_hidden_without_their_respective_permissions(): void
    {
        $context = $this->context('supervisor');
        $this->procedure($context);
        $this->override($context, 'maintenance.procedures.create', false);
        $this->override($context, 'maintenance.procedures.update', false);

        $this->get(route('procedures.index'))
            ->assertOk()
            ->assertDontSee('Novo procedimento')
            ->assertDontSee('Criar primeiro procedimento')
            ->assertDontSee('Editar procedimento');
    }

    public function test_store_and_update_require_their_own_permissions(): void
    {
        $context = $this->context('supervisor');
        $procedure = $this->procedure($context);
        $this->override($context, 'maintenance.procedures.create', false);
        $this->override($context, 'maintenance.procedures.update', false);

        $this->post(route('procedures.store'), ['name' => 'Não autorizado', 'can_be_internal' => 1])->assertForbidden();
        $this->put(route('procedures.update', $procedure), ['name' => 'Não autorizado', 'can_be_internal' => 1])->assertForbidden();
    }

    public function test_mechanic_is_blocked_by_default_and_can_receive_a_location_specific_view_override(): void
    {
        $context = $this->context('mechanic');

        $this->get(route('procedures.index'))->assertForbidden();

        $this->override($context, 'navigation.workshop', true);
        $this->override($context, 'maintenance.procedures.view', true);

        $this->get(route('procedures.index'))->assertOk();
    }

    private function context(string $profile): array
    {
        $tenant = Tenant::create(['name' => 'Tenant de permissões']);
        $division = Division::create(['tenant_id' => $tenant->id, 'name' => 'Divisão']);
        $location = Location::create(['tenant_id' => $tenant->id, 'division_id' => $division->id, 'name' => 'Unidade', 'active' => true]);
        User::factory()->create(['tenant_id' => $tenant->id]); // Reserva o superadministrador técnico (ID 1).
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        UserDivisionAccess::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'division_id' => $division->id,
            'location_id' => $location->id,
            'module' => 'fleet',
            'profile' => $profile,
            'active' => true,
        ]);

        $this->actingAs($user)->withSession([
            'active_division_id' => $division->id,
            'active_location_id' => $location->id,
        ]);

        return compact('tenant', 'division', 'location', 'user', 'profile');
    }

    private function procedure(array $context): Procedure
    {
        return Procedure::create([
            'tenant_id' => $context['tenant']->id,
            'location_id' => $context['location']->id,
            'name' => 'Troca de óleo',
            'can_be_internal' => true,
        ]);
    }

    private function override(array $context, string $permission, bool $allowed): void
    {
        ProfilePermissionOverride::create([
            'tenant_id' => $context['tenant']->id,
            'division_id' => $context['division']->id,
            'location_id' => $context['location']->id,
            'module' => 'fleet',
            'profile' => $context['profile'],
            'permission_key' => $permission,
            'allowed' => $allowed,
            'created_by' => $context['user']->id,
            'updated_by' => $context['user']->id,
        ]);
    }
}
