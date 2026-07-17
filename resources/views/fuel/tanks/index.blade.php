@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pages/fuel.css') }}?v=2">
@endpush

@section('content')
    @php
        $openFuelModal = $openFuelModal ?? null;
        $selectedFuelVehicleId = $selectedFuelVehicleId ?? null;
    @endphp

    <div class="fuel-page">
        <header class="fuel-header">
            <div>
                <span class="fuel-kicker">Abastecimentos</span>
                <h1>Tanques da unidade</h1>
                <p>
                    Controle as bases de combustível de {{ $activeLocation->name ?? 'unidade ativa' }}.
                    Recebimentos aumentam o saldo e abastecimentos reduzem o tanque selecionado.
                </p>
            </div>

            <div class="fuel-header-actions">
                <button type="button" class="fuel-secondary-action" onclick="openFuelModal('filling')">
                    <i data-lucide="truck"></i>
                    Registrar abastecimento
                </button>

                <button type="button" class="fuel-primary-action" onclick="openFuelModal('tank')">
                    <i data-lucide="plus"></i>
                    Novo tanque
                </button>
            </div>
        </header>

        <section class="fuel-summary-grid" aria-label="Resumo dos tanques">
            @forelse($tanks as $tank)
                <article class="fuel-tank-card {{ $tank->balance_status }}">
                    <div class="fuel-tank-card-top">
                        <div>
                            <span class="fuel-product-label">{{ $tank->product?->name ?? 'Produto' }}</span>
                            <h2>{{ $tank->name }}</h2>
                        </div>

                        <span class="fuel-status-badge {{ $tank->balance_status }}">
                            @if(! $tank->active)
                                Inativo
                            @elseif($tank->balance_status === 'low')
                                Saldo baixo
                            @else
                                Normal
                            @endif
                        </span>
                    </div>

                    <div class="fuel-balance-row">
                        <strong>{{ number_format((float) $tank->current_balance_liters, 3, ',', '.') }} L</strong>
                        <span>de {{ number_format((float) $tank->capacity_liters, 3, ',', '.') }} L</span>
                    </div>

                    <div class="fuel-progress-track">
                        <span style="width: {{ $tank->balance_percentage }}%"></span>
                    </div>

                    <dl class="fuel-tank-meta">
                        <div>
                            <dt>Saldo mínimo</dt>
                            <dd>{{ number_format((float) $tank->minimum_balance_liters, 3, ',', '.') }} L</dd>
                        </div>
                    
                        <div>
                            <dt>Ocupação</dt>
                            <dd>{{ number_format((float) $tank->balance_percentage, 1, ',', '.') }}%</dd>
                        </div>
                    
                        <div>
                            <dt>Custo médio</dt>
                            <dd>
                                R$ {{ number_format((float) ($tank->average_unit_cost ?? 0), 4, ',', '.') }}/L
                            </dd>
                        </div>
                    </dl>

                    <div class="fuel-card-actions">
                        @if($tank->active)
                            <button type="button" class="fuel-secondary-action" onclick="openFuelModal('receipt-{{ $tank->id }}')">
                                <i data-lucide="plus-circle"></i>
                                Recebimento
                            </button>
                        @endif

                        <button type="button" class="fuel-secondary-action" onclick="openFuelModal('edit-{{ $tank->id }}')">
                            <i data-lucide="pencil"></i>
                            Editar
                        </button>
                    </div>
                </article>
            @empty
                <article class="fuel-empty-card">
                    <i data-lucide="fuel"></i>
                    <h2>Nenhum tanque cadastrado</h2>
                    <p>Cadastre o primeiro tanque da unidade para iniciar o controle de abastecimentos.</p>
                </article>
            @endforelse
        </section>

        <section class="fuel-panel">
            <div class="fuel-panel-header">
                <div>
                    <span class="fuel-kicker">Recebimentos</span>
                    <h2>Últimas entradas</h2>
                </div>
            
                <div class="fuel-panel-actions">
                    <p>Exibindo os 8 registros mais recentes.</p>
            
                    <a href="#" class="fuel-secondary-action disabled">
                        Histórico completo
                    </a>
                </div>
            </div>

            <div class="fuel-receipt-list">
                @forelse($latestReceipts as $receipt)
                    <article class="fuel-receipt-item">
                        <div>
                            <strong>{{ $receipt->tank?->name ?? 'Tanque' }}</strong>
                            <span>{{ $receipt->product?->name ?? $receipt->tank?->product?->name ?? 'Produto' }} · {{ $receipt->received_at?->format('d/m/Y H:i') }}</span>
                        </div>

                        <div>
                            <strong>{{ number_format((float) $receipt->quantity_liters, 3, ',', '.') }} L</strong>
                            <span>
                                @if($receipt->total_cost !== null)
                                    R$ {{ number_format((float) $receipt->total_cost, 2, ',', '.') }}
                                @else
                                    Sem custo informado
                                @endif
                            </span>
                        </div>

                        <div>
                            <span>{{ $receipt->supplier_name ?: 'Fornecedor não informado' }}</span>
                            <small>Recebido por: {{ $receipt->responsible?->name ?: 'Não informado' }}</small>
                        </div>
                    </article>
                @empty
                    <div class="fuel-table-empty">Nenhum recebimento registrado nesta unidade.</div>
                @endforelse
            </div>
        </section>

        <section class="fuel-panel">
            <div class="fuel-panel-header">
                <div>
                    <span class="fuel-kicker">Abastecimentos</span>
                    <h2>Últimas saídas</h2>
                </div>
            
                <div class="fuel-panel-actions">
                    <p>Exibindo os 8 registros mais recentes.</p>
            
                    <a href="#" class="fuel-secondary-action disabled">
                        Histórico completo
                    </a>
                </div>
            </div>

            <div class="fuel-receipt-list">
                @forelse($latestFillings as $filling)
                    <article class="fuel-receipt-item">
                        <div>
                            <strong>{{ $filling->vehicle?->name ?? 'Veículo' }}</strong>
                            <span>{{ $filling->vehicle?->plate ?: 'Sem placa' }} · {{ $filling->filled_at?->format('d/m/Y H:i') }}</span>
                        </div>

                        <div>
                            <strong>{{ number_format((float) $filling->quantity_liters, 3, ',', '.') }} L</strong>
                            <span>{{ $filling->source_label }} · {{ $filling->location_label }} · {{ $filling->product?->name ?? $filling->tank?->product?->name ?? 'Produto' }}</span>
                        </div>

                        <div>
                            <span>
                                @if($filling->total_cost !== null)
                                    R$ {{ number_format((float) $filling->total_cost, 2, ',', '.') }}
                                @else
                                    Sem custo informado
                                @endif
                            </span>
                            <small>Motorista/Condutor: {{ $filling->driver?->name ?: 'Não informado' }}</small>
                            <small>Registrado por: {{ $filling->responsible?->name ?: 'Não informado' }}</small>
                        </div>
                    </article>
                @empty
                    <div class="fuel-table-empty">Nenhum abastecimento registrado nesta unidade.</div>
                @endforelse
            </div>
        </section>

        <section class="fuel-panel">
            <div class="fuel-panel-header">
                <div>
                    <span class="fuel-kicker">Listagem</span>
                    <h2>Tanques cadastrados</h2>
                </div>
                <p>{{ $tanks->count() }} tanque(s) na unidade ativa.</p>
            </div>

            <div class="fuel-table-wrap">
                <table class="fuel-table">
                    <thead>
                        <tr>
                            <th>Tanque</th>
                            <th>Produto</th>
                            <th>Capacidade</th>
                            <th>Saldo atual</th>
                            <th>Saldo mínimo</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tanks as $tank)
                            <tr>
                                <td>
                                    <strong>{{ $tank->name }}</strong>
                                    <span>{{ $activeLocation->name }}</span>
                                </td>
                                <td>{{ $tank->product?->name ?? '-' }}</td>
                                <td>{{ number_format((float) $tank->capacity_liters, 3, ',', '.') }} L</td>
                                <td>{{ number_format((float) $tank->current_balance_liters, 3, ',', '.') }} L</td>
                                <td>{{ number_format((float) $tank->minimum_balance_liters, 3, ',', '.') }} L</td>
                                <td>
                                    <span class="fuel-status-badge {{ $tank->balance_status }}">
                                        @if(! $tank->active)
                                            Inativo
                                        @elseif($tank->balance_status === 'low')
                                            Saldo baixo
                                        @else
                                            Normal
                                        @endif
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="fuel-secondary-action" onclick="openFuelModal('edit-{{ $tank->id }}')">
                                        Editar
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="fuel-table-empty">Nenhum tanque cadastrado para esta unidade.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div id="fuel-modal-tank" class="fuel-modal-overlay {{ $errors->fuelTank->any() || $openFuelModal === 'tank' ? 'is-open' : '' }}">
            <div class="fuel-modal-card">
                <div class="fuel-modal-header">
                    <div>
                        <span class="fuel-kicker">Cadastro</span>
                        <h2>Novo tanque</h2>
                    </div>
                    <button type="button" class="fuel-modal-close" onclick="closeFuelModals()">
                        <i data-lucide="x"></i>
                    </button>
                </div>

                @if($errors->fuelTank->any())
                    <div class="fuel-form-error">
                        @foreach($errors->fuelTank->all() as $message)
                            <span>{{ $message }}</span>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('fuel.tanks.store') }}" class="fuel-form">
                    @csrf
                    <div class="fuel-form-grid">
                        <label>
                            Produto
                            <select name="fuel_product_id" required>
                                <option value="">Selecione</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}" @selected(old('fuel_product_id') == $product->id)>{{ $product->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            Nome do tanque
                            <input type="text" name="name" value="{{ old('name') }}" required maxlength="255" placeholder="Ex.: Tanque Diesel 01">
                        </label>
                        <label>
                            Capacidade em litros
                            <input type="number" name="capacity_liters" value="{{ old('capacity_liters') }}" min="0.001" step="0.001" required>
                        </label>
                        <label>
                            Saldo mínimo
                            <input type="number" name="minimum_balance_liters" value="{{ old('minimum_balance_liters', 0) }}" min="0" step="0.001">
                        </label>
                    </div>
                    <label class="fuel-check">
                        <input type="checkbox" name="active" value="1" checked>
                        Tanque ativo
                    </label>
                    <div class="fuel-form-actions">
                        <button type="button" class="fuel-secondary-action" onclick="closeFuelModals()">Cancelar</button>
                        <button type="submit" class="fuel-primary-action">Salvar tanque</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="fuel-modal-filling" class="fuel-modal-overlay {{ $errors->fuelFilling->any() || $openFuelModal === 'filling' ? 'is-open' : '' }}">
            <div class="fuel-modal-card wide">
                <div class="fuel-modal-header">
                    <div>
                        <span class="fuel-kicker">Saída</span>
                        <h2>Registrar abastecimento</h2>
                    </div>
                    <button type="button" class="fuel-modal-close" onclick="closeFuelModals()">
                        <i data-lucide="x"></i>
                    </button>
                </div>

                @if($errors->fuelFilling->any())
                    <div class="fuel-form-error">
                        @foreach($errors->fuelFilling->all() as $message)
                            <span>{{ $message }}</span>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('fuel.fillings.store') }}" class="fuel-form fuel-filling-form"     onsubmit="return validateFuelFillingCounters(this);">
                    @csrf
                    <div class="fuel-form-grid fuel-filling-layout">
                    <input type="hidden" name="confirm_high_vehicle_km" value="0">
                    <input type="hidden" name="confirm_high_vehicle_hours" value="0">
                        <div class="fuel-span-12 fuel-source-toggle" data-fuel-source-toggle>
                            <div class="fuel-source-head">
                                <span>Tipo de abastecimento</span>
                                <small data-fuel-source-help>Baixa o saldo do tanque selecionado e registra movimentação interna.</small>
                            </div>

                            <div class="fuel-source-segment" role="radiogroup" aria-label="Tipo de abastecimento">
                                <label class="fuel-source-option">
                                    <input type="radio" name="source" value="internal_tank" @checked(old('source', 'internal_tank') !== 'external_station')>
                                    <span>Tanque da unidade</span>
                                </label>

                                <label class="fuel-source-option">
                                    <input type="radio" name="source" value="external_station" @checked(old('source') === 'external_station')>
                                    <span>Posto externo</span>
                                </label>
                            </div>
                        </div>

                        <label class="fuel-span-6">
                            Veículo
                            <select name="vehicle_id" required>
                                <option value="">Selecione</option>
                                @foreach($vehicles as $vehicle)
                                    <option
                                        value="{{ $vehicle->id }}"
                                        data-current-km="{{ $vehicle->current_km ?? 0 }}"
                                        data-current-hours="{{ $vehicle->current_hours ?? 0 }}"
                                        @selected((string) old('vehicle_id', $selectedFuelVehicleId) === (string) $vehicle->id)
                                    >
                                        {{ $vehicle->name }} @if($vehicle->plate) · {{ $vehicle->plate }} @endif
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    
                        <label class="fuel-span-6" data-source-field="internal">
                            Tanque/produto
                            <select name="fuel_tank_id">
                                <option value="">Selecione</option>
                                @foreach($tanks->where('active', true) as $tank)
                                    <option
                                        value="{{ $tank->id }}"
                                        data-unit-cost="{{ $tank->average_unit_cost ?? 0 }}"
                                        @selected(old('fuel_tank_id') == $tank->id)
                                    >
                                        {{ $tank->name }} · {{ $tank->product?->name }} · {{ number_format((float) $tank->current_balance_liters, 3, ',', '.') }} L
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    
                        <label class="fuel-span-6 is-hidden" data-source-field="external">
                            Produto
                            <select name="fuel_product_id">
                                <option value="">Selecione</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}" @selected(old('fuel_product_id') == $product->id)>{{ $product->name }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="fuel-span-4">
                            Motorista
                            <select name="driver_id">
                                <option value="">Não informado</option>
                                @foreach($drivers as $driver)
                                    <option value="{{ $driver->id }}" @selected(old('driver_id') == $driver->id)>{{ $driver->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    
                        <label class="fuel-span-4">
                            Data/hora
                            <input type="datetime-local" name="filled_at" value="{{ old('filled_at', now()->format('Y-m-d\TH:i')) }}" required>
                        </label>
                    
                    
                        <label class="fuel-span-4">
                            Litros
                            <input
                                type="number"
                                name="quantity_liters"
                                min="0.001"
                                step="0.001"
                                required
                                data-fuel-liters
                            >
                        </label>
                        <label class="fuel-span-6">
                            Horas informadas
                            <input
                                type="number"
                                name="vehicle_hours"
                                min="0"
                                step="0.01"
                                data-vehicle-hours-input
                            >
                        </label>
                        
                        <label class="fuel-span-6">
                            KM informado
                            <input
                                type="number"
                                name="vehicle_km"
                                min="0"
                                step="0.01"
                                data-vehicle-km-input
                            >
                        </label>

                        <label class="fuel-span-6 is-hidden" data-source-field="external">
                            Fornecedor/posto
                            <input type="text" name="supplier_name" value="{{ old('supplier_name') }}" maxlength="255" placeholder="Ex.: Posto Central">
                        </label>

                        <label class="fuel-span-6 is-hidden" data-source-field="external">
                            Documento/NF/cupom
                            <input type="text" name="document_number" value="{{ old('document_number') }}" maxlength="255" placeholder="Opcional">
                        </label>

                        <label class="fuel-span-6 is-hidden" data-source-field="external">
                            Custo unitario
                            <input type="number" name="unit_cost" min="0" step="0.0001" value="{{ old('unit_cost') }}" data-external-unit-cost>
                        </label>

                        <label class="fuel-span-6 is-hidden" data-source-field="external">
                            Custo total
                            <input type="number" name="total_cost" min="0" step="0.01" value="{{ old('total_cost') }}" data-external-total-cost>
                        </label>
                        <div class="fuel-cost-preview fuel-span-12">
                            <span data-filling-cost-title>Custo estimado automático</span>
                    
                            <strong data-filling-total-preview>
                                R$ 0,00
                            </strong>
                    
                            <small data-filling-unit-preview>
                                Selecione o tanque e informe os litros.
                            </small>
                        </div>
                    
                        <label class="fuel-span-12">
                            Observação
                            <textarea name="notes" rows="3"></textarea>
                        </label>
                    
                    </div>
            <div class="fuel-form-actions">
                        <button type="button" class="fuel-secondary-action" onclick="closeFuelModals()">Cancelar</button>
                        <button type="submit" class="fuel-primary-action">Salvar abastecimento</button>
                    </div>
                </form>
            </div>
        </div>

        @foreach($tanks as $tank)
            <div id="fuel-modal-receipt-{{ $tank->id }}" class="fuel-modal-overlay {{ $errors->fuelReceipt->any() && $openFuelModal === 'receipt-'.$tank->id ? 'is-open' : '' }}">
                <div class="fuel-modal-card wide">
                    <div class="fuel-modal-header">
                        <div>
                            <span class="fuel-kicker">Entrada</span>
                            <h2>Recebimento em {{ $tank->name }}</h2>
                        </div>
                        <button type="button" class="fuel-modal-close" onclick="closeFuelModals()">
                            <i data-lucide="x"></i>
                        </button>
                    </div>

                    @if($errors->fuelReceipt->any() && $openFuelModal === 'receipt-'.$tank->id)
                        <div class="fuel-form-error">
                            @foreach($errors->fuelReceipt->all() as $message)
                                <span>{{ $message }}</span>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('fuel.receipts.store') }}" class="fuel-form">
                        @csrf
                        <input type="hidden" name="fuel_tank_id" value="{{ $tank->id }}">
                        <input type="hidden" name="fuel_product_id" value="{{ $tank->fuel_product_id }}">
                        <div class="fuel-form-grid receipt-grid">
                        
                            <label>
                                Data do recebimento
                                <input
                                    type="datetime-local"
                                    name="received_at"
                                    value="{{ old('received_at', now()->format('Y-m-d\TH:i')) }}"
                                    required
                                >
                            </label>
                        
                            <label>
                                Quantidade em litros
                                <input
                                    type="number"
                                    name="quantity_liters"
                                    min="0.001"
                                    step="0.001"
                                    required
                                    data-fuel-liters
                                >
                            </label>
                        
                            <label>
                                Custo total
                                <input
                                    type="number"
                                    name="total_cost"
                                    min="0"
                                    step="0.01"
                                    data-fuel-total-cost
                                >
                            </label>
                        
                            <label>
                                Custo unitário calculado
                                <input
                                    type="number"
                                    name="unit_cost"
                                    min="0"
                                    step="0.0001"
                                    readonly
                                    data-fuel-unit-cost
                                >
                            </label>
                        
                            <label>
                                Fornecedor
                                <input
                                    type="text"
                                    name="supplier_name"
                                    maxlength="255"
                                    placeholder="Nome do fornecedor"
                                >
                            </label>
                        
                            <label>
                                Nota fiscal
                                <div class="input-with-badge">
                                    <span>NF</span>
                        
                                    <input
                                        type="text"
                                        name="invoice_number"
                                        maxlength="255"
                                        placeholder="12403"
                                    >
                                </div>
                            </label>
                        
                            <label class="fuel-form-wide">
                                Observação
                                <textarea name="notes" rows="3"></textarea>
                            </label>
                        
                        </div>
<div class="fuel-form-actions">
                            <button type="button" class="fuel-secondary-action" onclick="closeFuelModals()">Cancelar</button>
                            <button type="submit" class="fuel-primary-action">Registrar entrada</button>
                        </div>
                    </form>
                </div>
            </div>

            <div id="fuel-modal-edit-{{ $tank->id }}" class="fuel-modal-overlay {{ $errors->{'fuelTankEdit'.$tank->id}->any() ? 'is-open' : '' }}">
                <div class="fuel-modal-card">
                    <div class="fuel-modal-header">
                        <div>
                            <span class="fuel-kicker">Edição</span>
                            <h2>Editar {{ $tank->name }}</h2>
                        </div>
                        <button type="button" class="fuel-modal-close" onclick="closeFuelModals()">
                            <i data-lucide="x"></i>
                        </button>
                    </div>

                    @if($errors->{'fuelTankEdit'.$tank->id}->any())
                        <div class="fuel-form-error">
                            @foreach($errors->{'fuelTankEdit'.$tank->id}->all() as $message)
                                <span>{{ $message }}</span>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('fuel.tanks.update', $tank) }}" class="fuel-form">
                        @csrf
                        @method('PUT')
                        <div class="fuel-form-grid">
                            <label>
                                Produto
                                <select name="fuel_product_id" required>
                                    @foreach($products as $product)
                                        <option value="{{ $product->id }}" @selected((int) $tank->fuel_product_id === (int) $product->id)>{{ $product->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>
                                Nome
                                <input type="text" name="name" value="{{ $tank->name }}" required maxlength="255">
                            </label>
                            <label>
                                Capacidade
                                <input type="number" name="capacity_liters" value="{{ $tank->capacity_liters }}" min="0.001" step="0.001" required>
                            </label>
                            <label>
                                Saldo mínimo
                                <input type="number" name="minimum_balance_liters" value="{{ $tank->minimum_balance_liters }}" min="0" step="0.001">
                            </label>
                        </div>
                        <label class="fuel-check">
                            <input type="checkbox" name="active" value="1" @checked($tank->active)>
                            Ativo
                        </label>
                        <div class="fuel-form-actions">
                            <button type="button" class="fuel-secondary-action" onclick="closeFuelModals()">Cancelar</button>
                            <button type="submit" class="fuel-primary-action">Atualizar tanque</button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
@endsection

@push('scripts')
    <script>
        function openFuelModal(id) {
            closeFuelModals();

            const modal = document.getElementById(`fuel-modal-${id}`);

            if (modal) {
                modal.classList.add('is-open');
                document.body.classList.add('fuel-modal-open');
            }
        }

        function closeFuelModals() {
            document
                .querySelectorAll('.fuel-modal-overlay')
                .forEach((modal) => modal.classList.remove('is-open'));

            document.body.classList.remove('fuel-modal-open');
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeFuelModals();
            }
        });
        
        function calculateFuelUnitCost(form) {
            const litersInput = form.querySelector('[data-fuel-liters]');
            const totalInput = form.querySelector('[data-fuel-total-cost]');
            const unitInput = form.querySelector('[data-fuel-unit-cost]');
    
            if (!litersInput || !totalInput || !unitInput) {
                return;
            }
    
            const liters = Number(litersInput.value || 0);
            const total = Number(totalInput.value || 0);
    
            if (liters <= 0 || total <= 0) {
                unitInput.value = '';
                return;
            }
    
            const unit = total / liters;
    
            unitInput.value = unit.toFixed(4);
        }
    
        document.addEventListener('input', function (event) {
            if (
                event.target.matches('[data-fuel-liters]')
                ||
                event.target.matches('[data-fuel-total-cost]')
            ) {
                const form = event.target.closest('form');
    
                if (form) {
                    calculateFuelUnitCost(form);
                }
            }
        });

    function fuelFillingSource(form) {
        return form.querySelector('input[name="source"]:checked')?.value || 'internal_tank';
    }

    function syncFuelFillingSource(form) {
        const source = fuelFillingSource(form);
        const isExternal = source === 'external_station';

        form.querySelectorAll('[data-source-field]').forEach(function (field) {
            const shouldShow = field.dataset.sourceField === (isExternal ? 'external' : 'internal');
            field.classList.toggle('is-hidden', !shouldShow);
            field.querySelectorAll('input, select, textarea').forEach(function (input) {
                input.disabled = !shouldShow;
            });
        });

        const help = form.querySelector('[data-fuel-source-help]');
        const tankSelect = form.querySelector('select[name="fuel_tank_id"]');
        const productSelect = form.querySelector('select[name="fuel_product_id"]');

        if (help) {
            help.textContent = isExternal
                ? 'Registra custo e consumo do veículo sem movimentar o saldo dos tanques.'
                : 'Baixa o saldo do tanque selecionado e registra movimentação interna.';
        }

        if (tankSelect) {
            tankSelect.required = !isExternal;
        }

        if (productSelect) {
            productSelect.required = isExternal;
        }

        updateFillingCostPreview(form);
    }

    function updateFillingCostPreview(form) {
        const tankSelect = form.querySelector('select[name="fuel_tank_id"]');
        const litersInput = form.querySelector('input[name="quantity_liters"]');
        const totalPreview = form.querySelector('[data-filling-total-preview]');
        const unitPreview = form.querySelector('[data-filling-unit-preview]');
        const title = form.querySelector('[data-filling-cost-title]');
    
        if (!litersInput || !totalPreview || !unitPreview) {
            return;
        }

        const liters = Number(litersInput.value || 0);

        if (fuelFillingSource(form) === 'external_station') {
            const totalInput = form.querySelector('input[name="total_cost"]');
            const unitInput = form.querySelector('input[name="unit_cost"]');
            const informedTotal = Number(totalInput?.value || 0);
            const informedUnit = Number(unitInput?.value || 0);
            const calculatedTotal = informedTotal || (liters && informedUnit ? liters * informedUnit : 0);

            if (title) {
                title.textContent = 'Custo informado do posto externo';
            }

            totalPreview.textContent = calculatedTotal.toLocaleString('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            });

            unitPreview.textContent = informedUnit
                ? `Custo unitario informado: ${informedUnit.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}/L`
                : 'Informe custo unitario ou custo total, se houver.';
            return;
        }
    
        if (title) {
            title.textContent = 'Custo estimado automatico';
        }

        if (!tankSelect) {
            return;
        }

        const selected = tankSelect.options[tankSelect.selectedIndex];
        const unitCost = Number(selected?.dataset?.unitCost || 0);
    
        if (!unitCost || !liters) {
            totalPreview.textContent = 'R$ 0,00';
            unitPreview.textContent = 'Selecione o tanque e informe os litros.';
            return;
        }
    
        const total = unitCost * liters;
    
        totalPreview.textContent = total.toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        });
    
        unitPreview.textContent = `Custo medio atual: ${unitCost.toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        })}/L`;
    }
    
    document.addEventListener('input', function (event) {
        if (
            event.target.matches('input[name="quantity_liters"]')
            ||
            event.target.matches('select[name="fuel_tank_id"]')
            ||
            event.target.matches('input[name="unit_cost"]')
            ||
            event.target.matches('input[name="total_cost"]')
        ) {
            const form = event.target.closest('form');
    
            if (form && form.classList.contains('fuel-filling-form')) {
                updateFillingCostPreview(form);
            }
        }
    });
    
    document.addEventListener('change', function (event) {
        if (event.target.matches('select[name="fuel_tank_id"]') || event.target.matches('input[name="source"]')) {
            const form = event.target.closest('form');
    
            if (form && form.classList.contains('fuel-filling-form')) {
                if (event.target.matches('input[name="source"]')) {
                    syncFuelFillingSource(form);
                } else {
                    updateFillingCostPreview(form);
                }
            }
        }
    });
    function syncVehicleCounters(form) {
        const vehicleSelect = form.querySelector('select[name="vehicle_id"]');
        const kmInput = form.querySelector('[data-vehicle-km-input]');
        const hoursInput = form.querySelector('[data-vehicle-hours-input]');
    
        if (!vehicleSelect || !kmInput || !hoursInput) {
            return;
        }
    
        const selected = vehicleSelect.options[vehicleSelect.selectedIndex];
    
        if (!selected || !selected.value) {
            kmInput.value = '';
            hoursInput.value = '';
            kmInput.removeAttribute('min');
            hoursInput.removeAttribute('min');
            return;
        }
    
        const currentKm = Number(selected.dataset.currentKm || 0);
        const currentHours = Number(selected.dataset.currentHours || 0);
    
        kmInput.value = currentKm;
        kmInput.min = currentKm;
    
        hoursInput.value = currentHours;
        hoursInput.min = currentHours;
    }
    
    function validateFuelFillingCounters(form) {
        const vehicleSelect = form.querySelector('select[name="vehicle_id"]');
        const kmInput = form.querySelector('[data-vehicle-km-input]');
        const hoursInput = form.querySelector('[data-vehicle-hours-input]');
        const confirmKmInput = form.querySelector('[name="confirm_high_vehicle_km"]');
        const confirmHoursInput = form.querySelector('[name="confirm_high_vehicle_hours"]');
    
        confirmKmInput.value = '0';
        confirmHoursInput.value = '0';
    
        if (!vehicleSelect || !vehicleSelect.value) {
            return true;
        }
    
        const selected = vehicleSelect.options[vehicleSelect.selectedIndex];
    
        const currentKm = Number(selected.dataset.currentKm || 0);
        const currentHours = Number(selected.dataset.currentHours || 0);
    
        const informedKm = kmInput.value !== '' ? Number(kmInput.value) : null;
        const informedHours = hoursInput.value !== '' ? Number(hoursInput.value) : null;
    
        if (informedKm !== null && informedKm < currentKm) {
            alert(
                `O KM informado não pode ser menor que o KM atual do veículo.\n\n` +
                `KM atual: ${currentKm.toLocaleString('pt-BR')}\n` +
                `KM informado: ${informedKm.toLocaleString('pt-BR')}`
            );
    
            kmInput.focus();
            return false;
        }
    
        if (informedHours !== null && informedHours < currentHours) {
            alert(
                `O horímetro informado não pode ser menor que o horímetro atual do veículo.\n\n` +
                `Horímetro atual: ${currentHours.toLocaleString('pt-BR')}\n` +
                `Horímetro informado: ${informedHours.toLocaleString('pt-BR')}`
            );
    
            hoursInput.focus();
            return false;
        }
        
        const newKm = Number(kmInput.value || 0);
        
        if (currentKm && newKm && newKm - currentKm > 500) {
            if (!confirm(`O KM informado está ${newKm - currentKm} km acima do atual. Deseja continuar?`)) {
                kmInput.focus();
                return false;
            }
        
            form.querySelector('[name="confirm_high_vehicle_km"]').value = 1;
        }
        
        const newHours = Number(hoursInput.value || 0);
        
        if (currentHours && newHours && newHours - currentHours > 24) {
            if (!confirm(`O horímetro informado está ${newHours - currentHours} horas acima do atual. Deseja continuar?`)) {
                hoursInput.focus();
                return false;
            }
        
            form.querySelector('[name="confirm_high_vehicle_hours"]').value = 1;
        }
    
        return true;
    }
    
    document.addEventListener('change', function (event) {
        if (event.target.matches('select[name="vehicle_id"]')) {
            const form = event.target.closest('form');
    
            if (form && form.classList.contains('fuel-filling-form')) {
                syncVehicleCounters(form);
            }
        }
    });
    
    function hydrateFuelFillingForms() {
        document
            .querySelectorAll('.fuel-filling-form')
            .forEach(function (form) {
                const vehicleSelect = form.querySelector('select[name="vehicle_id"]');
    
                if (vehicleSelect && vehicleSelect.value) {
                    syncVehicleCounters(form);
                }
    
                syncFuelFillingSource(form);
            });
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', hydrateFuelFillingForms);
    } else {
        hydrateFuelFillingForms();
    }
    </script>
@endpush
