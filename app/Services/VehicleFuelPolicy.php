<?php
namespace App\Services;
use App\Models\FuelProduct;
use App\Models\Vehicle;
use Illuminate\Validation\ValidationException;

class VehicleFuelPolicy
{
    public const CANONICAL = ['diesel-s10', 'diesel-s500', 'alcool', 'gasolina'];
    public function products(int $tenantId) { $order=array_flip(self::CANONICAL); return FuelProduct::where('tenant_id',$tenantId)->whereIn('slug',self::CANONICAL)->where('active',true)->get()->sortBy(fn($product)=>$order[$product->slug])->values(); }
    public function validateIds(int $tenantId, array $ids): array { $products=FuelProduct::where('tenant_id',$tenantId)->whereIn('id',$ids)->get(); if($products->count()!==count(array_unique($ids)) || $products->contains(fn($p)=>!in_array($p->slug,self::CANONICAL,true))) throw ValidationException::withMessages(['fuel_product_ids'=>'Selecione apenas combustíveis canônicos deste tenant.']); $slugs=$products->pluck('slug')->all(); if((in_array('diesel-s10',$slugs,true)||in_array('diesel-s500',$slugs,true))&&count($slugs)>1) throw ValidationException::withMessages(['fuel_product_ids'=>'Diesel S10 e Diesel S500 são exclusivos e não podem ser combinados.']); return $products->pluck('id')->all(); }
    public function allowedProductsForVehicle(Vehicle $vehicle, $availableProducts)
    {
        $primary = $vehicle->relationLoaded('fuelProducts') ? $vehicle->fuelProducts : $vehicle->fuelProducts()->get();
        if ($primary->isEmpty()) return $availableProducts;
        $allowed = $primary->pluck('id')->all();
        if ($primary->contains('slug', 'diesel-s10')) {
            $allowed = array_merge($allowed, $availableProducts->where('slug', 'arla')->pluck('id')->all());
        }
        return $availableProducts->whereIn('id', $allowed)->values();
    }
    public function compatibilityForVehicle(Vehicle $vehicle, $availableProducts): array
    {
        $configured = ($vehicle->relationLoaded('fuelProducts') ? $vehicle->fuelProducts : $vehicle->fuelProducts()->get())->isNotEmpty();
        $allowed = $this->allowedProductsForVehicle($vehicle, $availableProducts);
        return ['configured' => $configured, 'allowed_ids' => $allowed->pluck('id')->values()->all(), 'label' => $configured ? 'Este veículo aceita: '.$allowed->pluck('name')->implode(' e ').'.' : 'Combustível permitido ainda não configurado para este veículo.'];
    }
    public function ensureAllowed(Vehicle $vehicle, FuelProduct $product): void { $available=FuelProduct::where('tenant_id',$vehicle->tenant_id)->where('active',true)->get(); $compatibility=$this->compatibilityForVehicle($vehicle,$available); if(!$compatibility['configured'])return; if(!in_array($product->id,$compatibility['allowed_ids'],true)) throw ValidationException::withMessages(['fuel_product_id'=>'Este veículo não está configurado para utilizar '.$product->name.'. '.$compatibility['label']]); }
}
