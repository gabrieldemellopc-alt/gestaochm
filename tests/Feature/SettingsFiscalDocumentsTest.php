<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Tenant;
use App\Models\TenantFiscalSetting;
use App\Models\User;
use App\Models\UserDivisionAccess;
use App\Services\TenantFiscalSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsFiscalDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): array
    {
        $tenant = Tenant::create(['name' => 'Tenant fiscal']);
        $division = Division::create(['tenant_id' => $tenant->id, 'name' => 'Divisão fiscal']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        UserDivisionAccess::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'division_id' => $division->id, 'module' => 'fleet', 'profile' => 'admin', 'active' => true]);
        return [$user, $tenant, $division];
    }

    public function test_authorized_user_can_open_fiscal_settings(): void
    {
        [$user, , $division] = $this->administrator();
        $this->actingAs($user)->withSession(['active_division_id' => $division->id])
            ->get(route('settings.index', ['tab' => 'fiscal-documents']))
            ->assertOk()
            ->assertSee('Obrigatoriedade de notas fiscais');
    }

    public function test_defaults_are_optional(): void
    {
        [$user, , $division] = $this->administrator();
        $this->actingAs($user)->withSession(['active_division_id' => $division->id]);
        $requirements = app(TenantFiscalSettingService::class)->requirements();
        $this->assertSame(['stock_entry' => false, 'external_fuel_filling' => false, 'fuel_receipt' => false], $requirements);
    }

    public function test_authorized_user_can_save_fiscal_requirements(): void
    {
        [$user, $tenant, $division] = $this->administrator();
        $this->actingAs($user)->withSession(['active_division_id' => $division->id])
            ->patch(route('settings.fiscal-documents.update'), ['stock_entry' => '1', 'external_fuel_filling' => '0', 'fuel_receipt' => '1'])
            ->assertRedirect(route('settings.index', ['tab' => 'fiscal-documents']));
        $this->assertDatabaseHas('tenant_fiscal_settings', ['tenant_id' => $tenant->id, 'division_id' => $division->id]);
        $this->assertTrue((bool) TenantFiscalSetting::first()->fiscal_document_requirements['stock_entry']);
    }

    public function test_unauthorized_user_cannot_save_fiscal_requirements(): void
    {
        [$admin, , $division] = $this->administrator();
        $user = User::factory()->create(['tenant_id' => $admin->tenant_id]);
        UserDivisionAccess::create(['tenant_id' => $admin->tenant_id, 'user_id' => $user->id, 'division_id' => $division->id, 'module' => 'fleet', 'profile' => 'supervisor', 'active' => true]);
        $this->actingAs($user)->withSession(['active_division_id' => $division->id])
            ->patch(route('settings.fiscal-documents.update'), ['stock_entry' => '1'])
            ->assertForbidden();
    }
}