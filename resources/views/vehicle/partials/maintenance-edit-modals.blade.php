@if($canEditItems)
<div
    x-data="{ itemModal: false, itemAction: '', itemForm: {} }"
    @edit-maintenance-item.window="
        itemForm = $event.detail.item;
        itemAction = $event.detail.action;
        itemModal = true;
    "
    x-show="itemModal"
    x-cloak
    class="maintenance-modal-backdrop"
    @click.self="itemModal = false"
>
    <div class="maintenance-close-modal maintenance-edit-modal">
        <header class="maintenance-edit-modal-header">
            <h3>Editar serviço</h3>
            <p>Corrija os dados operacionais. Campos dinâmicos e consumo de estoque não serão alterados.</p>
        </header>

        <form method="POST" :action="itemAction" class="maintenance-edit-modal-form">
            @csrf
            @method('PATCH')

            <div class="maintenance-edit-modal-body">
            <template x-if="itemForm.has_stock_consumption">
                <div class="maintenance-stock-linked-notice">
                    <strong>Consumo de estoque vinculado</strong>
                    <p>Este serviço possui consumo de estoque. Para alterar itens ou quantidades, será necessário um fluxo específico de ajuste de estoque.</p>
                </div>
            </template>

            <div class="form-group">
                <label>Tipo de execução</label>
                <input type="hidden" name="maintenance_type" :value="itemForm.maintenance_type">
                <div class="maintenance-execution-toggle" role="group" aria-label="Tipo de execução">
                    <button type="button" class="maintenance-execution-option" :class="{ 'is-active': itemForm.maintenance_type === 'internal' }" :disabled="itemForm.has_stock_consumption || !itemForm.can_be_internal" @click="itemForm.maintenance_type = 'internal'">Oficina interna</button>
                    <button type="button" class="maintenance-execution-option" :class="{ 'is-active': itemForm.maintenance_type === 'external' }" :disabled="itemForm.has_stock_consumption" @click="itemForm.maintenance_type = 'external'">Terceirizado</button>
                </div>
                <small x-show="itemForm.has_stock_consumption" class="maintenance-field-help">O tipo de execução fica bloqueado enquanto houver consumo vinculado.</small>
                <small x-show="!itemForm.can_be_internal" class="maintenance-field-help">Este procedimento permite somente execução terceirizada.</small>
            </div>

            <div class="form-group">
                <label>Data de execução</label>
                <input type="date" name="performed_at" class="form-input" x-model="itemForm.performed_at" required>
            </div>

            <div class="form-group" x-show="itemForm.maintenance_type === 'external'">
                <label>Prestador</label>
                <input type="text" name="provider_name" class="form-input" maxlength="255" x-model="itemForm.provider_name">
            </div>

            <template x-if="itemForm.stock_consumptions && itemForm.stock_consumptions.length">
                <section class="maintenance-stock-readonly">
                    <div class="maintenance-stock-readonly-title">Itens consumidos — somente leitura</div>
                    <template x-for="(consumption, index) in itemForm.stock_consumptions" :key="index">
                        <div class="maintenance-stock-readonly-row">
                            <div>
                                <strong x-text="consumption.item"></strong>
                                <span x-text="`${consumption.quantity} ${consumption.unit || ''}`"></span>
                            </div>
                            @if($canViewCosts)
                                <span x-text="new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(consumption.total_cost || 0)"></span>
                            @endif
                        </div>
                    </template>
                </section>
            </template>

            @if($canViewCosts)
                <div class="form-group">
                    <label>Custo adicional do serviço</label>
                    <input type="number" name="extra_cost" class="form-input" min="0" step="0.01" x-model="itemForm.extra_cost" required>
                </div>
            @endif

            <div class="form-group">
                <label>Observação</label>
                <textarea name="notes" rows="3" class="form-input" maxlength="2000" x-model="itemForm.notes"></textarea>
            </div>

            <div class="form-group">
                <label>Motivo da alteração</label>
                <textarea name="change_reason" rows="3" class="form-input" minlength="10" maxlength="2000" required></textarea>
            </div>
            </div>

            <footer class="maintenance-modal-actions maintenance-edit-modal-actions">
                <button type="button" class="maintenance-cancel-btn" @click="itemModal = false">Cancelar</button>
                <button type="submit" class="chm-page-button">Salvar alteração</button>
            </footer>
        </form>
    </div>
</div>
@endif

@if($canEditExtraCosts && $canViewCosts)
    <div
        x-data="{ extraCostModal: false, extraCostAction: '', extraCostForm: {} }"
        @edit-maintenance-extra-cost.window="
            extraCostForm = $event.detail.cost;
            extraCostAction = $event.detail.action;
            extraCostModal = true;
        "
        x-show="extraCostModal"
        x-cloak
        class="maintenance-modal-backdrop"
        @click.self="extraCostModal = false"
    >
        <div class="maintenance-close-modal maintenance-edit-modal">
            <header class="maintenance-edit-modal-header">
                <h3>Editar custo avulso</h3>
            </header>

            <form method="POST" :action="extraCostAction" class="maintenance-edit-modal-form">
                @csrf
                @method('PATCH')

                <div class="maintenance-edit-modal-body">
                <div class="form-group">
                    <label>Descrição</label>
                    <input type="text" name="description" class="form-input" maxlength="255" x-model="extraCostForm.description" required>
                </div>

                <div class="form-group">
                    <label>Valor</label>
                    <input type="number" name="amount" class="form-input" min="0" step="0.01" x-model="extraCostForm.amount" required>
                </div>

                <div class="form-group">
                    <label>Data do custo</label>
                    <input type="date" name="cost_date" class="form-input" x-model="extraCostForm.cost_date" required>
                </div>

                <div class="form-group">
                    <label>Motivo da alteração</label>
                    <textarea name="change_reason" rows="3" class="form-input" minlength="10" maxlength="2000" required></textarea>
                </div>
                </div>

                <footer class="maintenance-modal-actions maintenance-edit-modal-actions">
                    <button type="button" class="maintenance-cancel-btn" @click="extraCostModal = false">Cancelar</button>
                    <button type="submit" class="chm-page-button">Salvar alteração</button>
                </footer>
            </form>
        </div>
    </div>
@endif
