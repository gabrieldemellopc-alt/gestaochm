@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pages/workshop-financial.css') }}?v=3">
@endpush

<div class="chm-wf">
    <section class="chm-wf-section">
        <header class="chm-wf-header">
            <div>
                <span>CUSTOS DA OFICINA</span>
                <h2>Controle operacional do mês</h2>
            </div>
            <div class="chm-wf-actions">
                <button type="button" class="chm-wf-button-primary" onclick="openWorkshopExpenseModal()">
                    Registrar despesa
                </button>
                <button type="button" class="chm-wf-button-secondary" onclick="openWorkshopConsumptionModal()">
                    Registrar consumo
                </button>
            </div>
        </header>

        <div class="chm-wf-kpi-grid">
            <article class="chm-wf-kpi">
                <i data-lucide="wallet-cards"></i>
                <div>
                    <span>Custo operacional do mês</span>
                    <strong>R$ {{ number_format($workshopOperationalCostMonth, 2, ',', '.') }}</strong>
                    <small>Despesas e consumo interno.</small>
                </div>
            </article>
            <article class="chm-wf-kpi">
                <i data-lucide="receipt-text"></i>
                <div>
                    <span>Despesas da oficina</span>
                    <strong>R$ {{ number_format($workshopExpenseMonthTotal, 2, ',', '.') }}</strong>
                    <small>{{ $workshopExpenseRecent->count() }} registro(s) recente(s)</small>
                </div>
            </article>
            <article class="chm-wf-kpi">
                <i data-lucide="package-minus"></i>
                <div>
                    <span>Consumo de estoque</span>
                    <strong>R$ {{ number_format($workshopConsumptionMonthTotal, 2, ',', '.') }}</strong>
                    <small>{{ $workshopConsumptionRecent->count() }} lançamento(s) recente(s)</small>
                </div>
            </article>
        </div>

        <div class="chm-wf-recent-grid">
            <section class="chm-wf-recent-card">
                <header>
                    <span>Oficina</span>
                    <h3>Despesas recentes</h3>
                </header>
                @forelse ($workshopExpenseRecent as $expense)
                    <div class="chm-wf-row">
                        <div>
                            <strong>{{ $expense->categoryLabel() }}</strong>
                            <small>{{ $expense->expense_date?->format('d/m/Y') }} · {{ $expense->description }}</small>
                        </div>
                        <b>R$ {{ number_format((float) $expense->amount, 2, ',', '.') }}</b>
                    </div>
                @empty
                    <p class="chm-wf-empty">Nenhuma despesa registrada.</p>
                @endforelse
            </section>
            <section class="chm-wf-recent-card">
                <header>
                    <span>Estoque</span>
                    <h3>Consumos recentes</h3>
                </header>
                @forelse ($workshopConsumptionRecent as $movement)
                    <div class="chm-wf-row">
                        <div>
                            <strong>{{ $movement->stockItem?->name ?? 'Item de estoque' }}</strong>
                            <small>{{ $movement->moved_at?->format('d/m/Y') }} · {{ number_format((float) $movement->quantity, 2, ',', '.') }} {{ $movement->stockItem?->unit }}</small>
                        </div>
                        <b>R$ {{ number_format((float) $movement->total_cost, 2, ',', '.') }}</b>
                    </div>
                @empty
                    <p class="chm-wf-empty">Nenhum consumo registrado.</p>
                @endforelse
            </section>
        </div>
    </section>

    <div class="chm-wf-modal-overlay" id="workshopExpenseModal" style="display:none;">
        <div class="chm-wf-modal">
            <header class="chm-wf-modal-header">
                <div><span>Oficina</span><h2>Registrar despesa</h2></div>
                <button type="button" onclick="closeWorkshopFinancialModal('workshopExpenseModal')">×</button>
            </header>
            <form method="POST" action="{{ route('workshop.expenses.store') }}">
                @csrf
                <div class="chm-wf-form-grid">
                    <label class="chm-wf-field">Data<input class="chm-wf-input" type="date" name="expense_date" value="{{ now()->format('Y-m-d') }}" required></label>
                    <label class="chm-wf-field">
                        Categoria
                        <select class="chm-wf-select" name="category" required>
                            @foreach ($workshopExpenseCategories as $value => $label)
                                <option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="chm-wf-field chm-wf-wide">Descrição<input class="chm-wf-input" name="description" required></label>
                    <label class="chm-wf-field">Fornecedor<input class="chm-wf-input" name="supplier_name"></label>
                    <label class="chm-wf-field">NF/documento<input class="chm-wf-input" name="invoice_number"></label>
                    <label class="chm-wf-field">Valor<input class="chm-wf-input" type="number" name="amount" step="0.01" min="0.01" required></label>
                    <label class="chm-wf-field chm-wf-wide">Observação<textarea class="chm-wf-textarea" name="notes" rows="3"></textarea></label>
                </div>
                <footer class="chm-wf-modal-footer">
                    <button type="button" class="chm-wf-button-secondary" onclick="closeWorkshopFinancialModal('workshopExpenseModal')">Cancelar</button>
                    <button class="chm-wf-button-primary">Salvar despesa</button>
                </footer>
            </form>
        </div>
    </div>

    <div class="chm-wf-modal-overlay" id="workshopConsumptionModal" style="display:none;">
        <div class="chm-wf-modal">
            <header class="chm-wf-modal-header">
                <div><span>Oficina</span><h2>Registrar consumo</h2></div>
                <button type="button" onclick="closeWorkshopFinancialModal('workshopConsumptionModal')">×</button>
            </header>

            @if ($workshopConsumableStockItems->isNotEmpty())
                <form method="POST" action="{{ route('workshop.consumption.store') }}">
                    @csrf
                    <div class="chm-wf-form-grid">
                        <label class="chm-wf-field chm-wf-wide">
                            Item do estoque
                            <select class="chm-wf-select" name="stock_item_id" id="workshopConsumptionItem" required>
                                <option value="">Selecione</option>
                                @foreach ($workshopConsumableStockItems as $item)
                                    <option value="{{ $item->id }}" data-quantity="{{ $item->quantity }}" data-unit="{{ $item->unit }}" data-cost="{{ $item->unit_cost }}">
                                        {{ $item->name }} · saldo {{ number_format((float) $item->quantity, 2, ',', '.') }} {{ $item->unit }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="chm-wf-help" id="workshopConsumptionPreview">Selecione um item para visualizar saldo e custo unitário.</small>
                        </label>
                        <label class="chm-wf-field">Quantidade consumida<input class="chm-wf-input" id="workshopConsumptionQuantity" type="number" name="quantity" step="0.01" min="0.01" required></label>
                        <label class="chm-wf-field">Data<input class="chm-wf-input" type="date" name="moved_at" value="{{ now()->format('Y-m-d') }}" required></label>
                        <label class="chm-wf-field chm-wf-wide">Observação<textarea class="chm-wf-textarea" name="notes" rows="3"></textarea></label>
                    </div>
                    <footer class="chm-wf-modal-footer">
                        <button type="button" class="chm-wf-button-secondary" onclick="closeWorkshopFinancialModal('workshopConsumptionModal')">Cancelar</button>
                        <button class="chm-wf-button-primary">Registrar consumo</button>
                    </footer>
                </form>
            @else
                <div class="chm-wf-empty">
                    <p>Nenhum consumível disponível no estoque.</p>
                    @if (app(\App\Services\Permissions\ProfilePermissionService::class)->allows(auth()->user(), 'stock.view'))
                        <a class="chm-wf-button-primary" href="{{ route('stock.index') }}">Gerenciar estoque</a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
    window.openWorkshopExpenseModal = () => document.getElementById('workshopExpenseModal').style.display = 'grid';
    window.openWorkshopConsumptionModal = () => document.getElementById('workshopConsumptionModal').style.display = 'grid';
    window.closeWorkshopFinancialModal = id => document.getElementById(id).style.display = 'none';

    (() => {
        const item = document.getElementById('workshopConsumptionItem');
        const quantity = document.getElementById('workshopConsumptionQuantity');
        const preview = document.getElementById('workshopConsumptionPreview');

        if (!item) return;

        const renderPreview = () => {
            const option = item.options[item.selectedIndex];
            if (!option?.value) {
                preview.textContent = 'Selecione um item para visualizar saldo e custo unitário.';
                return;
            }
            const balance = Number(option.dataset.quantity);
            const unitCost = Number(option.dataset.cost);
            const amount = Number(quantity.value || 0);
            preview.textContent = `Saldo disponível: ${balance.toLocaleString('pt-BR', { minimumFractionDigits: 2 })} ${option.dataset.unit} · Custo unitário: R$ ${unitCost.toLocaleString('pt-BR', { minimumFractionDigits: 2 })} · Custo estimado: R$ ${(amount * unitCost).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;
        };

        item.addEventListener('change', renderPreview);
        quantity.addEventListener('input', renderPreview);
    })();
</script>
@endpush
