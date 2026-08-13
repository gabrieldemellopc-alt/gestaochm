<div class="maintenance-materials-modal-backdrop" x-show="modalOpen" x-cloak @keydown.escape.window="closeModal()" @click.self="closeModal()">
    <section class="maintenance-materials-modal" role="dialog" aria-modal="true" aria-labelledby="maintenance-materials-title">
        <header class="maintenance-materials-modal-header">
            <div><span>Consumo direto de estoque</span><h3 id="maintenance-materials-title">Materiais utilizados</h3><p>Busque itens do estoque e registre os materiais usados diretamente nesta ordem.</p></div>
            <button type="button" @click="closeModal()" aria-label="Fechar modal">×</button>
        </header>

        <div class="maintenance-materials-modal-body maintenance-materials-body">
            <div class="maintenance-materials-feedback" x-show="message" x-text="message" :class="messageType" role="status"></div>

            @if($canUseMaterials)
                <section class="materials-workspace">
                    <div class="materials-panel materials-search-panel maintenance-materials-search">
                        <div class="materials-panel-heading"><span>1. Buscar no estoque</span><p>Localize o material por nome, marca ou categoria.</p></div>
                        <label for="maintenance-material-search">Item do estoque</label>
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

                    <form method="POST" action="{{ route('vehicles.maintenance.materials.store', [$vehicle->id, $maintenance->id]) }}" @submit.prevent="addMaterial($event)" class="materials-panel materials-entry-panel maintenance-materials-entry-form">
                        @csrf
                        <div class="materials-panel-heading"><span>2. Item selecionado e lançamento</span><p>Confira o saldo e informe quanto foi utilizado.</p></div>
                        <div class="materials-selected-empty" x-show="!selected"><strong>Nenhum item selecionado</strong><span>Selecione um item do estoque para lançar o material.</span></div>
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
                            <label>Quantidade utilizada<input type="number" name="quantity" x-model="quantity" min="0.01" step="0.01" :max="selected?.available_quantity" :disabled="!selected" placeholder="Ex.: 2" required></label>
                            <label>Observação (opcional)<textarea name="notes" x-model="notes" rows="3" maxlength="2000" :disabled="!selected" placeholder="Informe onde ou como o material foi utilizado"></textarea></label>
                        </div>
                        <div class="materials-submit-row">
                            <small x-show="selected && (!quantity || Number(quantity) <= 0)">Informe uma quantidade maior que zero.</small>
                            <button type="submit" class="maintenance-materials-primary" :disabled="!selected || !quantity || Number(quantity) <= 0 || submitting"><span x-text="submitting ? 'Adicionando...' : 'Adicionar material'"></span></button>
                        </div>
                    </form>
                </section>
            @endif

            <section class="maintenance-materials-current materials-list-section">
                <div class="maintenance-materials-current-header"><div><span>Materiais da ordem</span><h4>Lançamentos ativos</h4></div><strong x-text="count + ' item(ns)'"></strong></div>
                <div x-ref="materialsList">
                    @include('vehicle.partials.maintenance-materials-list', ['maintenance' => $maintenance, 'canViewCosts' => $canViewCosts, 'canCancelMaterials' => $canCancelMaterials])
                </div>
            </section>
        </div>
        <footer class="maintenance-materials-modal-footer"><button type="button" @click="closeModal()">Fechar</button></footer>
    </section>
</div>
