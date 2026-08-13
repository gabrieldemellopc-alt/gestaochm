@php
    $materialCount = $maintenance->materialUsages->count();
    $materialQuantity = (float) $maintenance->materialUsages->sum('quantity');
    $materialTotal = (float) $maintenance->materialUsages->sum('total_cost');
@endphp

<section
    class="maintenance-materials-panel maintenance-tab-grid"
    data-maintenance-panel="materials"
    x-data="maintenanceMaterialsManager(@js([
        'searchUrl' => route('vehicles.maintenance.materials.search', [$vehicle->id, $maintenance->id]),
        'count' => $materialCount,
        'totalQuantity' => $materialQuantity,
        'materialsTotal' => $materialTotal,
        'maintenanceTotal' => (float) $maintenance->total_cost,
    ]))"
>
    <section class="maintenance-materials-column maintenance-form-panel">
        <header class="maintenance-materials-column-header">
            <span>Consumo direto de estoque</span>
            <h3>Adicionar material</h3>
            <p>Busque itens do estoque e registre materiais usados diretamente nesta manutenção.</p>
        </header>

        <div class="maintenance-materials-feedback" x-show="message" x-text="message" :class="messageType" role="status"></div>

        @if($canUseMaterials)
            <div class="maintenance-materials-search materials-search-panel">
                <label for="maintenance-material-search">Buscar item do estoque</label>
                <input id="maintenance-material-search" type="search" x-model="query" @input.debounce.300ms="search()" placeholder="Nome, marca ou categoria" autocomplete="off">
                <div class="maintenance-materials-results materials-search-results" x-show="loading || results.length" x-cloak>
                    <span class="maintenance-materials-loading" x-show="loading">Buscando itens...</span>
                    <template x-for="item in results" :key="item.id">
                        <button type="button" class="materials-result-item" :class="{'is-selected': selected?.id === item.id}" @click="selectItem(item)">
                            <span><strong x-text="item.name"></strong><small x-text="item.category || 'Sem categoria'"></small></span>
                            <span><small x-text="'Saldo: ' + item.available_quantity + ' ' + (item.unit || '')"></small>@if($canViewCosts)<small x-text="money(item.unit_cost)"></small>@endif</span>
                        </button>
                    </template>
                </div>
            </div>

            <form method="POST" action="{{ route('vehicles.maintenance.materials.store', [$vehicle->id, $maintenance->id]) }}" @submit.prevent="addMaterial($event)" class="maintenance-materials-form maintenance-materials-entry-form">
                @csrf
                <div class="materials-selected-empty" x-show="!selected"><strong>Nenhum item selecionado</strong><span>Selecione um item do estoque para habilitar o lançamento.</span></div>
                <div class="maintenance-materials-selected materials-selected-card" x-show="selected" x-cloak>
                    <strong x-text="selected?.name"></strong>
                    <div class="materials-meta-row">
                        <span class="materials-meta-pill" x-text="'Categoria: ' + (selected?.category || 'Sem categoria')"></span>
                        <span class="materials-meta-pill" x-text="'Saldo: ' + selected?.available_quantity + ' ' + (selected?.unit || '')"></span>
                        @if($canViewCosts)<span class="materials-meta-pill" x-text="'Custo unitário: ' + money(selected?.unit_cost)"></span>@endif
                    </div>
                </div>
                <input type="hidden" name="stock_item_id" :value="selected?.id || ''">
                <div class="materials-entry-grid">
                    <label>Quantidade utilizada<input type="number" name="quantity" x-model="quantity" min="1" step="1" :max="selected?.available_quantity" :disabled="!selected" placeholder="Ex.: 1" required></label>
                    <label>Observação (opcional)
                        <input type="text" name="notes" x-model="notes" maxlength="2000" :disabled="!selected" placeholder="Onde ou como o material foi utilizado">
                    </input></label>
                </div>
                <div class="materials-submit-row">
                    <small x-show="selected && (!quantity || !Number.isInteger(Number(quantity)) || Number(quantity) < 1)">Informe uma quantidade igual ou maior que 1.</small>
                    <button type="submit" class="maintenance-materials-primary" :disabled="!selected || !quantity || !Number.isInteger(Number(quantity)) || Number(quantity) < 1 || submitting"><span x-text="submitting ? 'Adicionando...' : 'Adicionar material'"></span></button>
                </div>
            </form>
        @else
            <div class="maintenance-materials-empty">Você não possui permissão para adicionar materiais.</div>
        @endif
    </section>

    <section class="maintenance-materials-column maintenance-scroll-panel">
        <header class="maintenance-materials-column-header maintenance-materials-list-header">
            <div><span>Materiais da ordem</span><h3>Materiais utilizados</h3></div>
            <div class="maintenance-materials-stats">
                <div><span>Registros</span><strong x-text="count"></strong></div>
                @if($canViewCosts)<div><span>Total</span><strong x-text="money(materialsTotal)"></strong></div>@endif
            </div>
        </header>
        <div class="maintenance-scroll-body maintenance-materials-list" x-ref="materialsList">
            @include('vehicle.partials.maintenance-materials-list', [
                'vehicle' => $vehicle,
                'maintenance' => $maintenance,
                'canCancelMaterials' => $canCancelMaterials,
                'canViewCosts' => $canViewCosts,
            ])
        </div>
    </section>
</section>
