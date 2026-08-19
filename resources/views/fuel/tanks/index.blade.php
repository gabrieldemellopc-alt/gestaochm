@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pages/fuel.css') }}?v=2">
@endpush

@section('content')
    @php

        $openFuelModal = $openFuelModal ?? null;
        $selectedFuelVehicleId = $selectedFuelVehicleId ?? null;
        $fuelPermissions = array_merge([
            'view' => false,
            'receive' => false,
            'fill_internal' => false,
            'fill_external' => false,
            'cancel' => false,
            'view_costs' => false,
        ], $fuelPermissions ?? []);
        $canReceiveFuel = (bool) $fuelPermissions['receive'];
        $canFillInternal = (bool) $fuelPermissions['fill_internal'];
        $canFillExternal = (bool) $fuelPermissions['fill_external'];
        $canRegisterFilling = $canFillInternal || $canFillExternal;
        $canViewFuelCosts = (bool) $fuelPermissions['view_costs'];
        $defaultFuelFillingSource = old('source', $canFillInternal ? 'internal_tank' : 'external_station');

        if ($defaultFuelFillingSource === 'internal_tank' && ! $canFillInternal) {
            $defaultFuelFillingSource = $canFillExternal ? 'external_station' : 'internal_tank';
        }

        if ($defaultFuelFillingSource === 'external_station' && ! $canFillExternal) {
            $defaultFuelFillingSource = $canFillInternal ? 'internal_tank' : 'external_station';
        }
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
                
                @if($canViewFuelReport)
                    <a
                        href="{{ route('reports.fuel.index') }}"
                        class="fuel-secondary-action"
                    >
                        <i data-lucide="bar-chart-3"></i>
                        Relatório
                    </a>
                @endif
                <button type="button" class="fuel-secondary-action" onclick="openFuelConsumptionDashboard()"><i data-lucide="chart-column"></i>Painel de consumo</button>                @if($canRegisterFilling)
                    <button type="button" class="fuel-secondary-action" onclick="openFuelModal('filling')">
                        <i data-lucide="truck"></i>
                        Registrar abastecimento
                    </button>
                @endif

                <button type="button" class="fuel-primary-action" onclick="openFuelModal('tank')">
                    <i data-lucide="plus"></i>
                    Novo tanque
                </button>
            </div>
        </header>
        <section
            class="fuel-overview-strip"
            aria-label="Resumo de combustíveis da unidade"
        >
            <div class="fuel-overview-available">
                <div class="fuel-overview-heading">
                    <span class="fuel-overview-icon">
                        <i data-lucide="fuel"></i>
                    </span>

                    <span>
                        Disponível na unidade
                    </span>
                </div>

                <div class="fuel-overview-products">
                    @forelse($fuelBalanceByProduct as $productBalance)
                        <div class="fuel-overview-product">
                            <span class="fuel-overview-product-name">
                                {{ $productBalance['product_name'] }}
                            </span>

                            <strong>
                                {{ number_format(
                                    (float) $productBalance['available_liters'],
                                    3,
                                    ',',
                                    '.'
                                ) }} L
                            </strong>
                        </div>
                    @empty
                        <span class="fuel-overview-empty">
                            Nenhum combustível disponível
                        </span>
                    @endforelse
                </div>
            </div>

            <div
                class="fuel-overview-divider"
                aria-hidden="true"
            ></div>

            <div class="fuel-overview-period">
                <div class="fuel-overview-heading">
                    <span class="fuel-overview-icon">
                        <i data-lucide="calendar-days"></i>
                    </span>

                    <span>
                        Abastecidos nos últimos 30 dias
                    </span>
                </div>

                <div class="fuel-overview-period-value">
                    <strong>
                        {{ number_format(
                            (float) ($fuelLast30Days['liters'] ?? 0),
                            2,
                            ',',
                            '.'
                        ) }} L
                    </strong>

                    @if($canViewFuelCosts)
                        <span>
                            (R$ {{ number_format(
                                (float) ($fuelLast30Days['total_cost'] ?? 0),
                                2,
                                ',',
                                '.'
                            ) }})
                        </span>
                    @endif
                </div>
            </div>
        </section>
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
                    
                        @if($canViewFuelCosts)
                            <div>
                                <dt>Custo m&eacute;dio</dt>
                                <dd>
                                    R$ {{ number_format((float) ($tank->average_unit_cost ?? 0), 2, ',', '.') }}/L
                                </dd>
                            </div>
                        @endif
                    </dl>

                    <div class="fuel-card-actions">
                        @if($tank->active && $canReceiveFuel)
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
            
                    <a href="{{ route('fuel.receipts.history') }}" class="fuel-secondary-action">
                        Histórico completo
                    </a>
                </div>
            </div>

            <div class="fuel-receipt-list">
                @forelse($latestReceipts as $receipt)
                    <article class="fuel-receipt-item">
                        <div>
                            <strong>{{ $receipt->tank?->name ?? 'Tanque' }} @if($receipt->cancelled_at)<span class="fuel-status-badge low">Cancelado</span>@endif</strong>
                            <span>{{ $receipt->product?->name ?? $receipt->tank?->product?->name ?? 'Produto' }} · {{ $receipt->received_at?->format('d/m/Y H:i') }}</span>
                        </div>

                        <div>
                            <strong>{{ number_format((float) $receipt->quantity_liters, 3, ',', '.') }} L</strong>
                            <span>
                                @if($canViewFuelCosts)
                                    @if($receipt->total_cost !== null)
                                        R$ {{ number_format((float) $receipt->total_cost, 2, ',', '.') }}
                                    @else
                                        Sem custo informado
                                    @endif
                                @else
                                    Custo restrito
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
            
                    <a href="{{ route('fuel.fillings.history') }}" class="fuel-secondary-action">
                        Histórico completo
                    </a>
                </div>
            </div>

            <div class="fuel-receipt-list">
                @forelse($latestFillings as $filling)
                    <article class="fuel-receipt-item">
                        <div>
                            <strong>{{ $filling->vehicle?->name ?? 'Veículo' }} @if($filling->cancelled_at)<span class="fuel-status-badge low">Cancelado</span>@endif</strong>
                            <span>{{ $filling->vehicle?->plate ?: 'Sem placa' }} · {{ $filling->filled_at?->format('d/m/Y H:i') }}</span>
                        </div>

                        <div>
                            <strong>{{ number_format((float) $filling->quantity_liters, 3, ',', '.') }} L</strong>
                            <span>{{ $filling->source_label }} · {{ $filling->location_label }} · {{ $filling->product?->name ?? $filling->tank?->product?->name ?? 'Produto' }}</span>
                        </div>

                        <div>
                            <span>
                                @if($canViewFuelCosts)
                                    @if($filling->total_cost !== null)
                                        R$ {{ number_format((float) $filling->total_cost, 2, ',', '.') }}
                                    @else
                                        Sem custo informado
                                    @endif
                                @else
                                    Custo restrito
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

        @if($canRegisterFilling)
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
                    <input type="hidden" name="km_reading_confirmed" value="0">
                    <input type="hidden" name="hours_reading_confirmed" value="0">
                        @if($canFillInternal && $canFillExternal)
                        <div class="fuel-span-12 fuel-source-toggle" data-fuel-source-toggle>
                            <div class="fuel-source-head">
                                <span>Tipo de abastecimento</span>
                                <small data-fuel-source-help>Baixa o saldo do tanque selecionado e registra movimentação interna.</small>
                            </div>

                            <div class="fuel-source-segment" role="radiogroup" aria-label="Tipo de abastecimento">
                                @if($canFillInternal)
                                    <label class="fuel-source-option">
                                        <input type="radio" name="source" value="internal_tank" @checked($defaultFuelFillingSource === 'internal_tank')>
                                        <span>Tanque da unidade</span>
                                    </label>
                                @endif

                                @if($canFillExternal)
                                    <label class="fuel-source-option">
                                        <input type="radio" name="source" value="external_station" @checked($defaultFuelFillingSource === 'external_station')>
                                        <span>Posto externo</span>
                                    </label>
                                @endif
                            </div>
                        </div>
                        @else
                            <input type="hidden" name="source" value="{{ $defaultFuelFillingSource }}">
                            <p class="fuel-source-single-note fuel-span-12" data-fuel-source-help>
                                {{ $defaultFuelFillingSource === 'external_station'
                                    ? 'Registra custo e consumo do veículo sem movimentar o saldo dos tanques.'
                                    : 'Baixa o saldo do tanque selecionado e registra movimentação interna.' }}
                            </p>
                        @endif

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
                            Documento/NF/cupom @if($externalFuelDocumentRequired ?? false) (Obrigatório) @else (Opcional) @endif
                            <input type="text" name="document_number" @if($externalFuelDocumentRequired ?? false) required @endif value="{{ old('document_number') }}" maxlength="255">
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
        @endif

        @foreach($tanks as $tank)
            @if($canReceiveFuel)
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
                                Nota fiscal @if($fuelReceiptInvoiceRequired ?? false) (Obrigatório) @else (Opcional) @endif
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
            @endif

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
<div id="fuelConsumptionDashboard" class="fuel-dashboard-modal" hidden><div class="fuel-dashboard-card"><button type="button" class="fuel-detail-close" onclick="closeFuelConsumptionDashboard()">×</button><h2>Painel de consumo</h2><div class="fuel-dashboard-toolbar"><p id="fuelDashboardSubtitle">Indicadores e gráficos de abastecimento — Últimos 30 dias</p><label>Período<select id="fuelDashboardPeriod" onchange="openFuelConsumptionDashboard()"><option value="last_30_days">Últimos 30 dias</option><option value="current_month">Mês atual</option><option value="previous_month">Mês anterior</option><option value="all">Todo o período</option></select></label></div><div id="fuelDashboardContent">Carregando…</div></div></div>
<script>
window.openFuelConsumptionDashboard = async function(){
    const modal=document.getElementById('fuelConsumptionDashboard'),content=document.getElementById('fuelDashboardContent'),period=document.getElementById('fuelDashboardPeriod')?.value||'last_30_days';
    modal.hidden=false; content.textContent='Carregando…';
    try {
        const d=await (await fetch(@json(route('fuel.consumption-dashboard'))+'?period='+encodeURIComponent(period))).json(),num=v=>Number(v||0),lit=v=>num(v).toLocaleString('pt-BR')+' L',max=rows=>Math.max(...rows.map(r=>num(r.liters)),1),escape=v=>String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
        document.getElementById('fuelDashboardSubtitle').textContent='Indicadores e gráficos de abastecimento — '+(d.period_label||'Últimos 30 dias');
        const empty=title=>`<section class="fuel-dashboard-chart"><h3>${title}</h3><p class="fuel-chart-empty">Sem dados no período.</p></section>`;
        const barChart=(title,rows)=>!rows.length?empty(title):`<section class="fuel-dashboard-chart fuel-chart-month"><h3>${title}</h3><div class="fuel-chart-body fuel-month-chart">${rows.map(r=>`<div class="fuel-month-bar-item"><b class="fuel-month-value">${lit(r.liters)}</b><i class="fuel-month-bar" style="height:${Math.max(10,num(r.liters)/max(rows)*100)}%"></i><span class="fuel-month-label">${escape(r.label)}</span></div>`).join('')}</div></section>`;
        const dayLabel=value=>{const [year,month,day]=String(value).split('-'),names=['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];return {day,month:`${names[Number(month)-1]||month}/${String(year).slice(-2)}`};};
        const lineChart=(title,rows)=>{if(!rows.length)return empty(title);const w=Math.max(620,rows.length*42),h=220,p={l:45,r:18,t:20,b:50},highest=max(rows),x=i=>p.l+i*(w-p.l-p.r)/Math.max(rows.length-1,1),y=v=>h-p.b-num(v)/highest*(h-p.t-p.b),points=rows.map((r,i)=>`${x(i)},${y(r.liters)}`).join(' '),step=Math.max(1,Math.ceil(rows.length/8));return `<section class="fuel-dashboard-chart fuel-chart-line"><h3>${title}</h3><div class="fuel-chart-svg-scroll"><svg viewBox="0 0 ${w} ${h}" role="img" aria-label="${title}">${[.25,.5,.75,1].map(v=>`<g><line class="fuel-svg-grid" x1="${p.l}" x2="${w-p.r}" y1="${h-p.b-(h-p.t-p.b)*v}" y2="${h-p.b-(h-p.t-p.b)*v}"/><text class="fuel-svg-axis" x="3" y="${h-p.b-(h-p.t-p.b)*v+4}">${lit(highest*v)}</text></g>`).join('')}<polyline class="fuel-svg-line" points="${points}"/>${rows.map((r,i)=>{const label=dayLabel(r.label);return `<g><title>${escape(r.label)}: ${lit(r.liters)}</title><circle class="fuel-svg-point" cx="${x(i)}" cy="${y(r.liters)}" r="4"/>${i%step===0||i===rows.length-1?`<text class="fuel-svg-day" x="${x(i)}" y="${h-27}">${escape(label.day)}</text><text class="fuel-svg-month" x="${x(i)}" y="${h-13}">${escape(label.month)}</text>`:''}</g>`}).join('')}</svg></div></section>`};
        const weekdayOrder={SEG:1,TER:2,QUA:3,QUI:4,SEX:5,SAB:6,DOM:7};
        const horizontal=(title,rows,rank=false)=>!rows.length?empty(title):`<section class="fuel-dashboard-chart fuel-chart-horizontal ${rank?'fuel-chart-rank':''}"><h3>${title}</h3>${rank?'<p class="fuel-chart-caption">Top 10 dos veículos com maior volume no período.</p>':''}<div class="fuel-chart-scroll">${rows.map((r,i)=>`<div class="fuel-chart-horizontal-row"><em>${rank?'#'+(i+1):escape(r.label)}</em><span>${rank?escape(r.label):''}</span><i style="width:${Math.max(4,num(r.liters)/max(rows)*100)}%"></i><b>${lit(r.liters)}</b></div>`).join('')}</div></section>`;
        const weekdayChart=(title,rows)=>{
            if(!rows.length)return empty(title);
            const body=rows.map(r=>{
                const width=num(r.liters)>0?Math.max(4,num(r.liters)/max(rows)*100):0;
                return '<div class="fuel-weekday-row"><span class="fuel-weekday-label">'+escape(r.label)+'</span><div class="fuel-weekday-track"><span class="fuel-weekday-bar" style="width:'+width+'%"></span></div><b class="fuel-weekday-value">'+lit(r.liters)+'</b></div>';
            }).join('');
            return '<section class="fuel-dashboard-chart fuel-chart-weekday"><h3>'+title+'</h3><div class="fuel-chart-body fuel-weekday-chart">'+body+'</div></section>';
        };        const efficiencyChart=rows=>!rows.length?empty('KM/L por veículo'):`<section class="fuel-dashboard-chart fuel-chart-efficiency"><h3>KM/L por veículo</h3><p class="fuel-chart-caption">Somente lançamentos com KM válido.</p><div class="fuel-chart-scroll">${rows.map((r,i)=>`<div class="fuel-chart-horizontal-row"><em>#${i+1}</em><span>${escape(r.label)} <small>${r.total_km.toLocaleString('pt-BR')} km · ${r.total_liters.toLocaleString('pt-BR')} L</small></span><i style="width:${Math.max(4,num(r.km_per_liter)/Math.max(...rows.map(x=>num(x.km_per_liter)),1)*100)}%"></i><b>${Number(r.km_per_liter).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2})} KM/L</b></div>`).join('')}</div></section>`;        const card=(label,value,help)=>`<div class="fuel-dashboard-kpi"><span>${label}</span><strong>${value}</strong><small>${help}</small></div>`;
        const weekdays=['SEG','TER','QUA','QUI','SEX','SAB','DOM'].map(label=>(d.by_weekday||[]).find(row=>String(row.label).slice(0,3).toUpperCase()===label)||{label,liters:0});
        content.innerHTML=`<div class="fuel-dashboard-kpis">${card('Total litros',lit(d.summary.total_liters),'Últimos 30 dias')}${card('Abastecimentos',d.summary.fillings_count,'Lançamentos válidos')}${card('Total gasto',d.summary.total_cost===null?'Restrito':'R$ '+num(d.summary.total_cost).toLocaleString('pt-BR',{minimumFractionDigits:2}),'No período')}${card('Média Km/L',d.summary.average_km_per_liter===null?'N/D':Number(d.summary.average_km_per_liter).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2})+' KM/L',d.summary.average_km_per_liter_entries_count?'Com base em '+d.summary.average_km_per_liter_entries_count+' lançamentos válidos':'Sem base suficiente')}</div><div class="fuel-dashboard-charts">${barChart('Abastecimento por mês',d.by_month)}${weekdayChart('Consumo por dia da semana',weekdays)}${lineChart('Abastecimento por dia',d.by_day)}${efficiencyChart(d.vehicle_efficiency||[])}${horizontal('Top 10 por volume abastecido',d.top_vehicles_by_liters||[],true)}</div>`;
    } catch(e) { content.innerHTML='<p class="fuel-chart-empty">Não foi possível carregar o painel.</p>'; }
};
window.closeFuelConsumptionDashboard = function(){document.getElementById('fuelConsumptionDashboard').hidden=true};
</script>@endsection

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
        return form.querySelector('input[name="source"]:checked')?.value
            || form.querySelector('input[type="hidden"][name="source"]')?.value
            || 'internal_tank';
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
        const confirmKmInput = form.querySelector('[name="km_reading_confirmed"]');
        const confirmHoursInput = form.querySelector('[name="hours_reading_confirmed"]');
    
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
        
        if (informedKm !== null && informedKm - currentKm > 500) {
            if (!confirm(`O KM informado está ${newKm - currentKm} km acima do atual. Deseja continuar?`)) {
                kmInput.focus();
                return false;
            }
        
            confirmKmInput.value = 1;
        }
        
        const newHours = Number(hoursInput.value || 0);
        
        if (informedHours !== null && informedHours - currentHours > 24) {
            if (!confirm(`O horímetro informado está ${newHours - currentHours} horas acima do atual. Deseja continuar?`)) {
                hoursInput.focus();
                return false;
            }
        
            confirmHoursInput.value = 1;
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
