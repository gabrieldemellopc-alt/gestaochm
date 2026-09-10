<?php

namespace Tests\Feature;

use App\Models\FuelProduct;
use App\Models\Tenant;
use App\Services\VehicleFuelPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VehicleFuelPolicySelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_single_primary_fuels_or_flex_alcohol_and_gasoline_are_valid(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant combustíveis']);
        $products = collect(['diesel-s10', 'diesel-s500', 'alcool', 'gasolina'])
            ->mapWithKeys(fn ($slug) => [$slug => FuelProduct::create([
                'tenant_id' => $tenant->id,
                'name' => $slug,
                'slug' => $slug,
                'unit' => 'litros',
                'active' => true,
            ])]);
        $policy = app(VehicleFuelPolicy::class);

        foreach (['diesel-s10', 'diesel-s500', 'alcool', 'gasolina'] as $slug) {
            $this->assertSame([$products[$slug]->id], $policy->validateIds($tenant->id, [$products[$slug]->id]));
        }

        $this->assertSame(
            [$products['alcool']->id, $products['gasolina']->id],
            $policy->validateIds($tenant->id, [$products['alcool']->id, $products['gasolina']->id])
        );

        $this->expectException(ValidationException::class);
        $policy->validateIds($tenant->id, [$products['diesel-s10']->id, $products['alcool']->id]);
    }
}
