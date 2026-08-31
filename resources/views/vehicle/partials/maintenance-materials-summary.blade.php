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
            <p>Use este fluxo quando o material já estiver disponível no estoque da unidade.</p>
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
                    <label>Data e hora do uso<input type="datetime-local" name="used_at" value="{{ old('used_at', now()->format('Y-m-d\TH:i')) }}" :disabled="!selected" required></label>
                    <label>Observação (opcional)
                        <input type="text" name="notes" x-model="notes" maxlength="2000" :disabled="!selected" placeholder="Onde ou como o material foi utilizado">
                    </input></label>
                </div>
                <div class="materials-submit-row">
                    <small x-show="selected && (!quantity || !Number.isInteger(Number(quantity)) || Number(quantity) < 1)">Informe uma quantidade igual ou maior que 1.</small>
                    <button type="submit" class="maintenance-materials-primary" :disabled="!selected || !quantity || !Number.isInteger(Number(quantity)) || Number(quantity) < 1 || submitting"><span x-text="submitting ? 'Adicionando...' : 'Adicionar material'"></span></button>
                </div>
            </form>
            @if(($maintenancePermissions['stock_entry'] ?? false))
                <div class="maintenance-direct-material__separator"><span>ou compre para esta OM</span></div>
                <details class="maintenance-direct-material" x-data="{
                    query: '', name: '', brand: '', unit: 'UNID', unitOther: '', categoryId: '', quantity: '', totalCost: '', selectedItem: null, suggestions: [], loading: false,
                    async search() {
                        if (this.query.trim().length < 2 || this.selectedItem) { this.suggestions = []; return; }
                        this.loading = true;
                        try {
                            const response = await fetch(@js(route('vehicles.maintenance.materials.search', [$vehicle->id, $maintenance->id])) + '?q=' + encodeURIComponent(this.query), { headers: { Accept: 'application/json' } });
                            this.suggestions = response.ok ? await response.json() : [];
                        } catch (_) { this.suggestions = []; } finally { this.loading = false; }
                    },
                    select(item) { this.selectedItem = item; this.name = item.name; this.brand = item.brand || ''; this.unit = item.unit || 'UNID'; this.categoryId = item.stock_category_id || ''; this.query = item.name; this.suggestions = []; },
                    clearSelection() { this.selectedItem = null; this.query = ''; this.name = ''; this.brand = ''; this.unit = 'UNID'; this.unitOther = ''; this.categoryId = ''; this.suggestions = []; }
                }">
                    <summary>Comprar / lançar material direto</summary>
                    <p class="maintenance-direct-material__intro">Registre uma nova compra vinculada a esta manutenção.</p>
                    <p class="maintenance-direct-material__description">Use este fluxo quando houve uma nova compra para esta manutenção. A entrada será registrada no estoque e o material será baixado imediatamente nesta OM.</p>
                    <div class="maintenance-direct-material__search">
                        <label for="maintenance-direct-material-search">Reutilizar item já cadastrado (opcional)</label>
                        <input id="maintenance-direct-material-search" type="search" x-model="query" x-on:input.debounce.300ms="search()" :readonly="selectedItem" placeholder="Nome, marca ou categoria" autocomplete="off">
                        <div class="maintenance-direct-material__suggestions" x-show="loading || suggestions.length" x-cloak>
                            <span x-show="loading">Buscando itens...</span>
                            <template x-for="item in suggestions" :key="item.id">
                                <button type="button" x-on:click="select(item)">
                                    <span><strong x-text="item.name"></strong><small x-text="[item.category || 'Sem categoria', item.unit, 'Saldo atual: ' + item.available_quantity].filter(Boolean).join(' · ')"></small></span>
                                </button>
                            </template>
                        </div>
                        <div class="maintenance-direct-material__selected" x-show="selectedItem" x-cloak>
                            <span><strong x-text="selectedItem?.name"></strong><small x-text="'Item existente selecionado · Saldo atual: ' + selectedItem?.available_quantity + ' ' + selectedItem?.unit"></small></span>
                            <button type="button" x-on:click="clearSelection()">Trocar item</button>
                        </div>
                        <div class="maintenance-direct-material__notice" x-show="selectedItem" x-cloak>
                            <strong>Item já cadastrado no estoque</strong>
                            <span>Você está registrando uma <b>nova compra</b> deste item. O saldo atual não será utilizado para esta operação.</span>
                            <small>Se deseja utilizar unidades que já estão no estoque, use a seção “Consumo direto de estoque” acima.</small>
                        </div>
                        <div class="maintenance-direct-material__new-item" x-show="!selectedItem">
                            <strong>Nenhum item selecionado</strong><span>Um novo cadastro poderá ser criado.</span>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('vehicles.maintenance.materials.direct.store', [$vehicle, $maintenance]) }}" class="maintenance-materials-form">
                        @csrf
                        <input type="hidden" name="stock_item_id" :value="selectedItem?.id || ''">
                        <div class="maintenance-direct-material__item-fields" x-show="!selectedItem">
                            <span class="maintenance-direct-material__form-title">Dados do novo item</span>
                            <div class="maintenance-direct-material__grid">
                                <label>Nome do item<input name="name" x-model="name" :disabled="selectedItem" required maxlength="255"></label>
                                <label>Marca<input name="brand" x-model="brand" :disabled="selectedItem" maxlength="255"></label>
                                <label>Unidade<select name="unit" x-model="unit" :disabled="selectedItem" required><option value="UNID">UNID</option><option value="L">L</option><option value="KG">KG</option><option value="G">G</option><option value="Outro">Outro</option></select></label>
                                <label>Categoria<select name="stock_category_id" x-model="categoryId" :disabled="selectedItem" required><option value="">Selecione a categoria</option>@foreach($stockCategories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></label>
                                <label x-show="unit === 'Outro'" x-cloak>Informe a unidade<input name="unit_other" x-model="unitOther" :required="unit === 'Outro'" maxlength="50" placeholder="Ex.: CX, M, PAR"></label>
                            </div>
                        </div>
                        <div class="maintenance-direct-material__item-summary" x-show="selectedItem" x-cloak>
                            <span class="maintenance-direct-material__form-title">Item selecionado</span>
                            <div><strong x-text="selectedItem?.name"></strong><span x-text="[selectedItem?.brand, selectedItem?.category, selectedItem?.unit && 'Unidade: ' + selectedItem.unit, 'Saldo atual: ' + selectedItem?.available_quantity + ' ' + selectedItem?.unit].filter(Boolean).join(' · ')"></span></div>
                            <input type="hidden" name="name" :value="name"><input type="hidden" name="brand" :value="brand"><input type="hidden" name="unit" :value="unit"><input type="hidden" name="stock_category_id" :value="categoryId">
                        </div>
                        <div class="maintenance-direct-material__purchase-fields">
                            <span class="maintenance-direct-material__form-title">Dados da compra</span>
                            <div class="maintenance-direct-material__grid">
                                <label>Quantidade<input name="quantity" type="number" min="1" x-model="quantity" required></label>
                                <label>Custo total<input name="total_cost" type="number" min="0" step="0.01" x-model="totalCost" required></label>
                                <label>Data e hora do uso<input name="used_at" type="datetime-local" value="{{ old('used_at', now()->format('Y-m-d\TH:i')) }}" required></label>
                                <label>Fornecedor<input name="supplier_name" maxlength="255"></label>
                                <label>Custo unitário calculado<input type="text" :value="quantity > 0 ? 'R$ ' + (Number(totalCost || 0) / Number(quantity)).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—'" readonly><small>Calculado automaticamente com base no custo total e na quantidade.</small></label>
                                <label>Nota fiscal @if(app(\App\Services\TenantFiscalSettingService::class)->requires('stock_entry'))<small>Obrigatória</small>@else<small>Opcional</small>@endif<div class="maintenance-direct-material__invoice"><span>NF</span><input name="invoice_number" @required(app(\App\Services\TenantFiscalSettingService::class)->requires('stock_entry')) maxlength="255"></div></label>
                                <label>Observação<input name="notes" maxlength="2000"></label>
                            </div>
                        </div>
                        <button class="maintenance-materials-primary" type="submit">Registrar compra e utilizar</button>
                    </form>
                </details>
            @endif
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
