@php
    $materialCount = $maintenance->materialUsages->count();
    $materialQuantity = (float) $maintenance->materialUsages->sum('quantity');
    $materialTotal = (float) $maintenance->materialUsages->sum('total_cost');
@endphp

<section class="maintenance-services-card maintenance-materials-summary"
    x-data="maintenanceMaterialsManager(@js([
        'searchUrl' => route('vehicles.maintenance.materials.search', [$vehicle->id, $maintenance->id]),
        'count' => $materialCount,
        'totalQuantity' => $materialQuantity,
        'materialsTotal' => $materialTotal,
        'maintenanceTotal' => (float) $maintenance->total_cost,
    ]))">
    <div class="maintenance-materials-summary-copy">
        <span>Consumo direto de estoque</span>
        <h3>Materiais utilizados</h3>
        <p>Peças, insumos e materiais usados diretamente nesta manutenção.</p>
        <small x-show="count === 0">Nenhum material lançado ainda.</small>
    </div>
    <div class="maintenance-materials-stats">
        <div><span>Itens</span><strong x-text="count + ' item(ns)'"></strong></div>
        <div><span>Quantidade total</span><strong x-text="Number(totalQuantity).toLocaleString('pt-BR', {maximumFractionDigits:2})"></strong></div>
        @if($canViewCosts)
            <div>
                <span>Total em materiais</span>
                <strong x-text="money(materialsTotal)"></strong>
            </div>
        @endif
    </div>
    <button type="button" class="maintenance-materials-manage" @click="openModal()">Gerenciar materiais</button>

    @include('vehicle.partials.maintenance-materials-modal', [
        'vehicle' => $vehicle,
        'maintenance' => $maintenance,
        'canUseMaterials' => $canUseMaterials,
        'canCancelMaterials' => $canCancelMaterials,
        'canViewCosts' => $canViewCosts,
    ])
</section>
