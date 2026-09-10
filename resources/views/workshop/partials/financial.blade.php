@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pages/workshop-financial.css') }}?v=3">
@endpush

<div class="chm-wf">
    <section class="chm-wf-section">
        <header class="chm-wf-header">
            <div>
                <span>CUSTOS DA OFICINA</span>
                <h2>Controle operacional do mês</h2>
                <small>{{ now()->locale('pt_BR')->translatedFormat('F/Y') }}</small>
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
                <i class="bi bi-wallet2"></i>
                <div>
                    <span>Custo operacional do mês</span>
                    <strong>R$ {{ number_format($workshopOperationalCostMonth, 2, ',', '.') }}</strong>
                    <small>Despesas e consumo interno.</small>
                </div>
            </article>
            <article class="chm-wf-kpi">
                <i class="bi bi-receipt"></i>
                <div>
                    <span>Despesas da oficina</span>
                    <strong>R$ {{ number_format($workshopExpenseMonthTotal, 2, ',', '.') }}</strong>
                    <small>{{ $workshopExpenseRecent->count() }} registro(s) recente(s)</small>
                </div>
            </article>
            <article class="chm-wf-kpi">
                <i class="bi bi-box-seam"></i>
                <div>
                    <span>Consumo de estoque</span>
                    <strong>R$ {{ number_format($workshopConsumptionMonthTotal, 2, ',', '.') }}</strong>
                    <small>{{ $workshopConsumptionRecent->count() }} lançamento(s) recente(s)</small>
                </div>
            </article>
        </div>

        <div class="chm-wf-recent-grid">
            <section class="chm-wf-recent-card chm-wf-expenses-card">
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
                        @if ($workshopFinancialPermissions['expenses_update'] || $workshopFinancialPermissions['expenses_delete'])
                            <div class="chm-wf-row-actions">
                                @if ($workshopFinancialPermissions['expenses_update'])
                                    @php($expenseEdit = $expense->only(['id', 'expense_date', 'category', 'description', 'supplier_name', 'supplier_id', 'supplier_document', 'invoice_number', 'amount', 'notes']))
                                    <button type="button" class="chm-wf-icon-button" title="Editar despesa" aria-label="Editar despesa" onclick='openWorkshopExpenseEdit(@json($expenseEdit))'><i class="bi bi-pencil"></i></button>
                                @endif
                                @if ($workshopFinancialPermissions['expenses_delete'])
                                    <form method="POST" action="{{ route('workshop.expenses.destroy', $expense) }}" onsubmit="return confirm('Excluir esta despesa? O lançamento ficará preservado na auditoria.');">@csrf @method('DELETE')<button class="chm-wf-icon-button chm-wf-icon-button--danger" title="Excluir despesa" aria-label="Excluir despesa"><i class="bi bi-trash3"></i></button></form>
                                @endif
                            </div>
                        @endif
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
                        @if ($workshopFinancialPermissions['consumptions_update'] || $workshopFinancialPermissions['consumptions_delete'])
                            <div class="chm-wf-row-actions">
                                @if ($workshopFinancialPermissions['consumptions_update'])
                                    @php($consumptionEdit = ['id' => $movement->id, 'stock_item_id' => $movement->stock_item_id, 'quantity' => $movement->quantity, 'moved_at' => optional($movement->moved_at)->format('Y-m-d'), 'notes' => trim(str_replace(\App\Models\StockMovement::WORKSHOP_CONSUMPTION_PREFIX, '', $movement->description))])
                                    <button type="button" class="chm-wf-icon-button" title="Corrigir consumo" aria-label="Corrigir consumo" onclick='openWorkshopConsumptionEdit(@json($consumptionEdit))'><i class="bi bi-pencil"></i></button>
                                @endif
                                @if ($workshopFinancialPermissions['consumptions_delete'])
                                    <form method="POST" action="{{ route('workshop.consumption.destroy', $movement) }}" onsubmit="return confirm('Reverter este consumo? A quantidade será devolvida ao estoque e a operação não poderá ser repetida.');">@csrf @method('DELETE')<button class="chm-wf-icon-button chm-wf-icon-button--danger" title="Reverter consumo" aria-label="Reverter consumo"><i class="bi bi-arrow-counterclockwise"></i></button></form>
                                @endif
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="chm-wf-empty">Nenhum consumo registrado.</p>
                @endforelse
            </section>
        </div>
    </section>

    <div class="chm-wf-modal-overlay is-hidden" id="workshopExpenseModal">
        <div class="chm-wf-modal">
            <header class="chm-wf-modal-header">
                <div><span>Oficina</span><h2>Registrar despesa</h2></div>
                <button type="button" onclick="closeWorkshopFinancialModal('workshopExpenseModal')">×</button>
            </header>
            <form method="POST" action="{{ route('workshop.expenses.store') }}" id="workshopExpenseForm">
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
                    <x-supplier-autocomplete class="chm-wf-wide chm-supplier-picker--split" document-name="supplier_document" :show-label="true" />
                    <label class="chm-wf-field">Valor<input class="chm-wf-input" type="number" name="amount" step="0.01" min="0.01" required></label>
                    <label class="chm-wf-field">NF/documento<input class="chm-wf-input" name="invoice_number"></label>
                    <label class="chm-wf-field chm-wf-wide">Observação<textarea class="chm-wf-textarea" name="notes" rows="3"></textarea></label>
                </div>
                <footer class="chm-wf-modal-footer">
                    <small class="chm-wf-help" id="workshopExpenseAddAnotherNotice" aria-live="polite"></small>
                    <button type="button" class="chm-wf-button-secondary" onclick="closeWorkshopFinancialModal('workshopExpenseModal')">Cancelar</button>
                    <button class="chm-wf-button-secondary" type="submit" name="add_another" value="1">Salvar e adicionar outra</button>
                    <button class="chm-wf-button-primary">Salvar despesa</button>
                </footer>
            </form>
        </div>
    </div>

    <div class="chm-wf-modal-overlay is-hidden" id="workshopConsumptionModal">
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

    <div class="chm-wf-modal-overlay is-hidden" id="workshopExpenseEditModal">
        <div class="chm-wf-modal"><header class="chm-wf-modal-header"><div><span>Oficina</span><h2>Editar despesa</h2></div><button type="button" onclick="closeWorkshopFinancialModal('workshopExpenseEditModal')">×</button></header>
            <form method="POST" id="workshopExpenseEditForm">@csrf @method('PUT')
                <div class="chm-wf-form-grid"><label class="chm-wf-field">Data<input class="chm-wf-input" type="date" name="expense_date" required></label><label class="chm-wf-field">Categoria<select class="chm-wf-select" name="category" required>@foreach ($workshopExpenseCategories as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label><label class="chm-wf-field chm-wf-wide">Descrição<input class="chm-wf-input" name="description" required></label><label class="chm-wf-field">Fornecedor<input class="chm-wf-input" name="supplier_name"></label><label class="chm-wf-field">CPF/CNPJ<input class="chm-wf-input" name="supplier_document"></label><input type="hidden" name="supplier_id"><label class="chm-wf-field">Valor<input class="chm-wf-input" type="number" step="0.01" min="0.01" name="amount" required></label><label class="chm-wf-field">NF/documento<input class="chm-wf-input" name="invoice_number"></label><label class="chm-wf-field chm-wf-wide">Observação<textarea class="chm-wf-textarea" name="notes" rows="3"></textarea></label></div>
                <footer class="chm-wf-modal-footer"><button type="button" class="chm-wf-button-secondary" onclick="closeWorkshopFinancialModal('workshopExpenseEditModal')">Cancelar</button><button class="chm-wf-button-primary">Salvar alteração</button></footer>
            </form>
        </div>
    </div>

    <div class="chm-wf-modal-overlay is-hidden" id="workshopConsumptionEditModal">
        <div class="chm-wf-modal"><header class="chm-wf-modal-header"><div><span>Oficina</span><h2>Corrigir consumo</h2></div><button type="button" onclick="closeWorkshopFinancialModal('workshopConsumptionEditModal')">×</button></header>
            <form method="POST" id="workshopConsumptionEditForm">@csrf @method('PUT')
                <div class="chm-wf-form-grid"><label class="chm-wf-field chm-wf-wide">Item do estoque<select class="chm-wf-select" name="stock_item_id" required>@foreach ($workshopConsumableStockItems as $item)<option value="{{ $item->id }}">{{ $item->name }} · saldo {{ number_format((float) $item->quantity, 2, ',', '.') }} {{ $item->unit }}</option>@endforeach</select></label><label class="chm-wf-field">Quantidade<input class="chm-wf-input" type="number" step="0.01" min="0.01" name="quantity" required></label><label class="chm-wf-field">Data<input class="chm-wf-input" type="date" name="moved_at" required></label><label class="chm-wf-field chm-wf-wide">Observação<textarea class="chm-wf-textarea" name="notes" rows="3"></textarea></label></div>
                <footer class="chm-wf-modal-footer"><button type="button" class="chm-wf-button-secondary" onclick="closeWorkshopFinancialModal('workshopConsumptionEditModal')">Cancelar</button><button class="chm-wf-button-primary">Corrigir consumo</button></footer>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    window.openWorkshopExpenseModal = () => document.getElementById('workshopExpenseModal').classList.remove('is-hidden');
    window.openWorkshopConsumptionModal = () => document.getElementById('workshopConsumptionModal').classList.remove('is-hidden');
    window.closeWorkshopFinancialModal = id => document.getElementById(id).classList.add('is-hidden');
    window.openWorkshopExpenseEdit = expense => { const form=document.getElementById('workshopExpenseEditForm'); form.action=`/workshop/expenses/${expense.id}`; Object.entries(expense).forEach(([key, value]) => { const field=form.elements[key]; if (field) field.value=value ?? ''; }); document.getElementById('workshopExpenseEditModal').classList.remove('is-hidden'); };
    window.openWorkshopConsumptionEdit = consumption => { const form=document.getElementById('workshopConsumptionEditForm'); form.action=`/workshop/consumption/${consumption.id}`; Object.entries(consumption).forEach(([key, value]) => { const field=form.elements[key]; if (field) field.value=value ?? ''; }); document.getElementById('workshopConsumptionEditModal').classList.remove('is-hidden'); };

    (() => {
        const expenseForm = document.getElementById('workshopExpenseForm');
        expenseForm?.addEventListener('submit', async event => {
            const submitter = event.submitter;
            if (submitter?.name !== 'add_another') return;
            event.preventDefault();
            if (expenseForm.dataset.saving === '1') return;
            expenseForm.dataset.saving = '1'; submitter.disabled = true;
            try {
                const response = await fetch(expenseForm.action, { method: 'POST', body: new FormData(expenseForm), headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                if (!response.ok) throw new Error('Não foi possível salvar a despesa.');
                const saved = await response.json();
                expenseForm.querySelectorAll('input[name="description"], input[name="supplier_name"], input[name="supplier_document"], input[name="supplier_id"], input[name="invoice_number"], input[name="amount"], textarea[name="notes"]').forEach(field => field.value = '');
                expenseForm.querySelector('input[name="description"]')?.focus();
                const notice = document.getElementById('workshopExpenseAddAnotherNotice');
                if (notice) notice.textContent = 'Despesa salva. Você pode registrar outra.';
                const list = document.querySelector('.chm-wf-expenses-card');
                const empty = list?.querySelector('.chm-wf-empty');
                empty?.remove();
                if (list && saved.expense) { const row=document.createElement('div'); row.className='chm-wf-row'; const details=document.createElement('div'); const title=document.createElement('strong'); title.textContent=saved.expense.category; const detail=document.createElement('small'); detail.textContent=`${saved.expense.date} · ${saved.expense.description}`; const amount=document.createElement('b'); amount.textContent=saved.expense.amount.toLocaleString('pt-BR', { style:'currency', currency:'BRL' }); details.append(title, detail); row.append(details, amount); list.querySelector('header')?.after(row); }
            } catch (error) { alert(error.message); } finally { expenseForm.dataset.saving = ''; submitter.disabled = false; }
        });

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
