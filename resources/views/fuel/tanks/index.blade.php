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
        $canManageFuelTanks = (int) auth()->id() === 1 || userHasProfile('admin');
        $canUseFuelPhotoImport = $canManageFuelTanks || $canFillInternal;
        $defaultFuelFillingSource = old('source', $canFillInternal ? 'internal_tank' : 'external_station');

        if ($defaultFuelFillingSource === 'internal_tank' && ! $canFillInternal) {
            $defaultFuelFillingSource = $canFillExternal ? 'external_station' : 'internal_tank';
        }

        if ($defaultFuelFillingSource === 'external_station' && ! $canFillExternal) {
            $defaultFuelFillingSource = $canFillInternal ? 'internal_tank' : 'external_station';
        }
    @endphp

    <div
        class="fuel-page"
        x-data="{ manualSheetOpen: @js(request()->boolean('manual_sheet')) }"
    >
        <header class="fuel-header">
            <div>
                <span class="fuel-kicker">Abastecimentos</span>
                <h1>Tanques da unidade</h1>
                <p>
                    Controle as bases de combustível de {{ $activeLocation->name ?? 'unidade ativa' }}.
                    Recebimentos aumentam o saldo e abastecimentos reduzem o tanque selecionado.
                </p>
            </div>

            <div
                class="fuel-header-actions
                    {{ ! $canManageFuelTanks
                        ? 'fuel-header-actions--operator'
                            .($canUseFuelPhotoImport
                                ? ' fuel-header-actions--operator-photo'
                                : '')
                        : 'fuel-header-actions--manager' }}"
            >

                @if($canViewFuelReport)
                    <a
                        href="{{ route('reports.fuel.index') }}"
                        class="fuel-secondary-action fuel-manager-report-action"
                    >
                        <i class="bi bi-bar-chart"></i>
                        Relatório
                    </a>
                @endif
                <button
                    type="button"
                    class="fuel-secondary-action fuel-operator-consumption-action"
                    onclick="openFuelConsumptionDashboard()"
                ><i class="bi bi-bar-chart-line"></i>Painel de consumo</button>                <a
                    href="{{ route('fuel.daily-check.index') }}"
                    class="fuel-secondary-action fuel-operator-archive-action"
                    title="Consultar o arquivo diário de abastecimentos"
                >
                    <i class="bi bi-calendar3"></i>
                    Arquivo diário
                </a>

                <button
                    type="button"
                    class="fuel-secondary-action fuel-manual-sheet-trigger fuel-operator-manual-action"
                    x-on:click="manualSheetOpen = true"
                    title="Gerar ficha manual de abastecimento"
                >
                    <span class="fuel-operator-manual-icon">
                        <i class="bi bi-printer"></i>
                    </span>

                    <span class="fuel-operator-manual-content">
                        <strong>Ficha manual</strong>
                        <small>Emitir ficha operacional</small>
                    </span>
                </button>

                @if($canUseFuelPhotoImport)
                    <button
                        type="button"
                        class="fuel-secondary-action fuel-photo-import-trigger"
                        onclick="openFuelPhotoImport()"
                        title="Importar abastecimentos a partir de uma foto"
                    >
                        <span class="fuel-manager-photo-icon">
                            <i class="bi bi-stars"></i>
                        </span>

                        <span class="fuel-manager-photo-content">
                            <strong>Importar via foto (IA)</strong>
                            <small>Ler, revisar e lançar uma ficha</small>
                        </span>
                    </button>
                @endif


            </div>
        </header>

        <div
            class="fuel-manual-sheet-overlay"
            x-show="manualSheetOpen"
            x-cloak
            x-on:keydown.escape.window="manualSheetOpen = false"
            x-on:click.self="manualSheetOpen = false"
        >
            <div
                class="fuel-manual-sheet-modal"
                x-data="{
                    mode: 'blank',
                    selected: [],
                    blankRows: 28,

                    openGroups: {
                        internal: false,
                        aggregated: false,
                        rented: false,
                        unassigned: false
                    },

                    vehicles: @js(
                        $vehicles->map(fn ($vehicle) => [
                            'id' => $vehicle->id,
                            'code' => $vehicle->name,
                            'plate' => $vehicle->plate,
                            'km' => $vehicle->current_km,
                            'relation' => in_array(
                                $vehicle->fleet_relation,
                                [
                                    \App\Models\Vehicle::FLEET_RELATION_INTERNAL,
                                    \App\Models\Vehicle::FLEET_RELATION_AGGREGATED,
                                    \App\Models\Vehicle::FLEET_RELATION_RENTED,
                                ],
                                true
                            )
                                ? $vehicle->fleet_relation
                                : 'unassigned',
                        ])->values()
                    ),

                    groups: [
                        {
                            key: 'internal',
                            label: 'Internos'
                        },
                        {
                            key: 'aggregated',
                            label: 'Agregados'
                        },
                        {
                            key: 'rented',
                            label: 'Alugados'
                        },
                        {
                            key: 'unassigned',
                            label: 'Sem vínculo'
                        }
                    ],

                    groupVehicles(key) {
                        return this.vehicles.filter(
                            vehicle => vehicle.relation === key
                        );
                    },

                    groupCount(key) {
                        return this.groupVehicles(key).length;
                    },

                    selectedCount(key) {
                        const ids = this.groupVehicles(key)
                            .map(vehicle => String(vehicle.id));

                        return this.selected.filter(
                            id => ids.includes(String(id))
                        ).length;
                    },

                    selectGroup(key) {
                        const ids = this.groupVehicles(key)
                            .map(vehicle => String(vehicle.id));

                        this.selected = Array.from(
                            new Set([
                                ...this.selected.map(String),
                                ...ids
                            ])
                        );
                    },

                    clearGroup(key) {
                        const ids = this.groupVehicles(key)
                            .map(vehicle => String(vehicle.id));

                        this.selected = this.selected
                            .map(String)
                            .filter(id => ! ids.includes(id));
                    },

                    toggleGroup(key) {
                        this.openGroups[key] =
                            ! this.openGroups[key];
                    },

                    changeMode(value) {
                        this.mode = value;

                        if (value === 'blank') {
                            this.blankRows = 28;
                        } else if (this.blankRows === 28) {
                            this.blankRows = 5;
                        }
                    }
                }"
            >
                <form
                    method="POST"
                    action="{{ route('fuel.fillings.manual-sheet.pdf') }}"
                    target="_blank"
                >
                    @csrf


                    <div class="fuel-manual-sheet-modal-header">

                        <div>
                            <span>IMPRESSÃO OPERACIONAL</span>

                            <h2>
                                Ficha manual de abastecimento
                            </h2>

                            <p>
                                Monte uma folha em branco ou escolha
                                os veículos que já sairão impressos.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="fuel-manual-sheet-close"
                            x-on:click="manualSheetOpen = false"
                            aria-label="Fechar"
                        >
                            <i class="bi bi-x-lg"></i>
                        </button>

                    </div>


                    <div class="fuel-manual-sheet-body">

                        {{-- TIPO --}}
                        <section class="fuel-manual-sheet-block">

                            <div class="fuel-manual-sheet-block-title">
                                Tipo da ficha
                            </div>

                            <div class="fuel-manual-sheet-mode-grid">

                                <label
                                    class="fuel-manual-sheet-mode"
                                    :class="{ 'is-active': mode === 'blank' }"
                                >
                                    <input
                                        type="radio"
                                        name="sheet_mode"
                                        value="blank"
                                        x-model="mode"
                                        x-on:change="changeMode('blank')"
                                    >

                                    <i class="bi bi-file-earmark"></i>

                                    <span>
                                        <strong>Folha em branco</strong>

                                        <small>
                                            Tudo será preenchido à mão.
                                        </small>
                                    </span>
                                </label>


                                <label
                                    class="fuel-manual-sheet-mode"
                                    :class="{ 'is-active': mode === 'prefilled' }"
                                >
                                    <input
                                        type="radio"
                                        name="sheet_mode"
                                        value="prefilled"
                                        x-model="mode"
                                        x-on:change="changeMode('prefilled')"
                                    >

                                    <i class="bi bi-card-checklist"></i>

                                    <span>
                                        <strong>Pré-preenchida</strong>

                                        <small>
                                            Escolha os veículos que sairão impressos.
                                        </small>
                                    </span>
                                </label>

                            </div>

                        </section>


                        {{-- VEÍCULOS AGRUPADOS --}}
                        <section
                            class="fuel-manual-sheet-block"
                            x-show="mode === 'prefilled'"
                        >

                            <div class="fuel-manual-sheet-block-head">

                                <div>
                                    <div class="fuel-manual-sheet-block-title">
                                        Veículos
                                    </div>

                                    <small>
                                        <span
                                            x-text="selected.length"
                                        ></span>
                                        selecionado(s)
                                    </small>
                                </div>

                            </div>


                            <div class="fuel-manual-sheet-groups">

                                <template
                                    x-for="group in groups"
                                    :key="group.key"
                                >

                                    <div
                                        class="fuel-manual-sheet-group"
                                        x-show="groupCount(group.key) > 0"
                                    >

                                        <button
                                            type="button"
                                            class="fuel-manual-sheet-group-header"
                                            x-on:click="toggleGroup(group.key)"
                                        >

                                            <span class="fuel-manual-sheet-group-title">

                                                <span
                                                    class="fuel-manual-sheet-group-chevron"
                                                    :class="{ 'is-open': openGroups[group.key] }"
                                                >
                                                    <i class="bi bi-chevron-right"></i>
                                                </span>

                                                <strong
                                                    x-text="group.label"
                                                ></strong>

                                                <span
                                                    class="fuel-manual-sheet-group-count"
                                                    x-text="groupCount(group.key)"
                                                ></span>

                                            </span>


                                            <span
                                                class="fuel-manual-sheet-group-selected"
                                                x-show="selectedCount(group.key) > 0"
                                            >
                                                <span
                                                    x-text="selectedCount(group.key)"
                                                ></span>
                                                selecionado(s)
                                            </span>

                                        </button>


                                        <div
                                            class="fuel-manual-sheet-group-body"
                                            x-show="openGroups[group.key]"
                                            x-collapse
                                        >

                                            <div class="fuel-manual-sheet-group-actions">

                                                <button
                                                    type="button"
                                                    x-on:click="selectGroup(group.key)"
                                                >
                                                    <i class="bi bi-check2-square"></i>
                                                    Marcar todos
                                                </button>

                                                <button
                                                    type="button"
                                                    x-on:click="clearGroup(group.key)"
                                                >
                                                    <i class="bi bi-square"></i>
                                                    Limpar
                                                </button>

                                            </div>


                                            <div class="fuel-manual-sheet-group-vehicles">

                                                <template
                                                    x-for="vehicle in groupVehicles(group.key)"
                                                    :key="vehicle.id"
                                                >

                                                    <label class="fuel-manual-sheet-vehicle">

                                                        <input
                                                            type="checkbox"
                                                            name="vehicle_ids[]"
                                                            :value="String(vehicle.id)"
                                                            x-model="selected"
                                                        >

                                                        <span class="fuel-manual-sheet-check">
                                                            <i class="bi bi-check"></i>
                                                        </span>

                                                        <span class="fuel-manual-sheet-vehicle-info">

                                                            <strong
                                                                x-text="vehicle.code || 'Sem código'"
                                                            ></strong>

                                                            <small>

                                                                <span
                                                                    x-text="vehicle.plate || 'Sem placa'"
                                                                ></span>

                                                                <template
                                                                    x-if="vehicle.km !== null && vehicle.km !== ''"
                                                                >
                                                                    <span
                                                                        x-text="' · ' + Number(vehicle.km).toLocaleString('pt-BR') + ' km'"
                                                                    ></span>
                                                                </template>

                                                            </small>

                                                        </span>

                                                    </label>

                                                </template>

                                            </div>

                                        </div>

                                    </div>

                                </template>

                            </div>

                        </section>


                        {{-- CAMPOS --}}
                        <section class="fuel-manual-sheet-block">

                            <div class="fuel-manual-sheet-block-title">
                                Dados que podem sair impressos
                            </div>

                            <div class="fuel-manual-sheet-options">

                                <label>
                                    <input
                                        type="checkbox"
                                        name="show_code"
                                        value="1"
                                        checked
                                    >
                                    <span>Código</span>
                                </label>

                                <label>
                                    <input
                                        type="checkbox"
                                        name="show_plate"
                                        value="1"
                                        checked
                                    >
                                    <span>Placa</span>
                                </label>

                                <label>
                                    <input
                                        type="checkbox"
                                        name="show_km"
                                        value="1"
                                    >
                                    <span>Último KM registrado</span>
                                </label>

                                <label>
                                    <input
                                        type="checkbox"
                                        name="show_arla"
                                        value="1"
                                        checked
                                    >
                                    <span>Coluna ARLA</span>
                                </label>

                            </div>

                            <p class="fuel-manual-sheet-help">
                                Dados não impressos serão substituídos
                                por espaços separados para facilitar
                                a escrita manual.
                            </p>

                        </section>


                        {{-- LINHAS --}}
                        <section class="fuel-manual-sheet-block">

                            <div class="fuel-manual-sheet-row-setting">

                                <div>

                                    <strong>
                                        Linhas em branco
                                    </strong>

                                    <small x-show="mode === 'blank'">
                                        Quantidade total de linhas da ficha.
                                    </small>

                                    <small x-show="mode === 'prefilled'">
                                        Linhas extras depois dos veículos selecionados.
                                    </small>

                                </div>

                                <input
                                    type="number"
                                    name="blank_rows"
                                    min="0"
                                    max="40"
                                    x-model.number="blankRows"
                                    required
                                >

                            </div>

                        </section>

                    </div>


                    <div class="fuel-manual-sheet-footer">

                        <button
                            type="button"
                            class="fuel-secondary-action"
                            x-on:click="manualSheetOpen = false"
                        >
                            Cancelar
                        </button>

                        <button
                            type="submit"
                            class="fuel-manual-sheet-generate"
                            :disabled="
                                mode === 'prefilled'
                                && selected.length === 0
                            "
                        >
                            <i class="bi bi-file-earmark-pdf"></i>
                            Gerar ficha
                        </button>

                    </div>

                </form>
            </div>
        </div>


        <section
            class="fuel-overview-strip"
            aria-label="Resumo de combustíveis da unidade"
        >
            <div class="fuel-overview-available">
                <div class="fuel-overview-heading">
                    <span class="fuel-overview-icon">
                        <i class="bi bi-fuel-pump"></i>
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
                        <i class="bi bi-calendar-week"></i>
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

                    <div class="fuel-card-actions fuel-tank-actions">
                        <div class="fuel-tank-actions__primary">
                            @if($tank->active && $canReceiveFuel)
                                <button
                                    type="button"
                                    class="fuel-tank-receive-action"
                                    onclick="openFuelModal('receipt-{{ $tank->id }}')"
                                    title="Registrar a chegada de combustível e adicionar o volume ao tanque"
                                >
                                    <i class="bi bi-truck"></i>
                                    <span>
                                        <strong>Registrar recebimento</strong>
                                        <small>Entrada de produto no tanque</small>
                                    </span>
                                </button>
                            @endif
                        </div>

                        @if($canManageFuelTanks)
                            <button
                                type="button"
                                class="fuel-tank-edit-action"
                                onclick="openFuelModal('edit-{{ $tank->id }}')"
                                title="Editar configuração do tanque"
                            >
                                <i class="bi bi-pencil"></i>
                                Editar tanque
                            </button>
                        @endif
                    </div>
                </article>
            @empty
                <article class="fuel-empty-card">
                    <i class="bi bi-fuel-pump"></i>
                    <h2>Nenhum tanque cadastrado</h2>
                    <p>Cadastre o primeiro tanque da unidade para iniciar o controle de abastecimentos.</p>
                </article>
            @endforelse
        </section>

        @if($canRegisterFilling || $canManageFuelTanks)
            <div
                class="fuel-bottom-create-actions
                    {{ $canRegisterFilling && $canManageFuelTanks
                        ? 'has-two-actions'
                        : 'has-one-action' }}"
            >

                @if($canRegisterFilling)
                    <button
                        type="button"
                        class="fuel-bottom-create-action fuel-bottom-create-action--filling"
                        onclick="openFuelModal('filling')"
                    >
                        <span class="fuel-bottom-create-action__icon">
                            <i class="bi bi-fuel-pump"></i>
                        </span>

                        <span class="fuel-bottom-create-action__content">
                            <strong>Novo abastecimento</strong>
                            <small>
                                Registrar abastecimento de veículo
                            </small>
                        </span>
                    </button>
                @endif

                @if($canManageFuelTanks)
                    <button
                        type="button"
                        class="fuel-bottom-create-action fuel-bottom-create-action--tank"
                        onclick="openFuelModal('tank')"
                    >
                        <span class="fuel-bottom-create-action__icon">
                            <i class="bi bi-plus-lg"></i>
                        </span>

                        <span class="fuel-bottom-create-action__content">
                            <strong>Novo tanque</strong>
                            <small>
                                Cadastrar outro tanque nesta unidade
                            </small>
                        </span>
                    </button>
                @endif

            </div>
        @endif

        <section
            class="fuel-panel fuel-collapsible-panel"
            x-data="{ open: false }"
        >
            <div
                class="fuel-panel-header fuel-collapsible-header"
                @click="open = !open"
                :aria-expanded="open"
            >
                <div class="fuel-collapsible-title">
                    <span class="fuel-collapse-icon" :class="{ 'is-open': open }">
                        <i class="bi bi-chevron-right"></i>
                    </span>

                    <div>
                        <span class="fuel-kicker">Abastecimentos</span>
                        <h2>Últimos abastecimentos</h2>
                    </div>
                </div>

                <div class="fuel-panel-actions">
                    <p>Exibindo os 8 registros mais recentes.</p>

                    <a
                        href="{{ route('fuel.fillings.history') }}"
                        class="fuel-history-action"
                        @click.stop
                    >
                        <i class="bi bi-clock-history"></i>
                        Ver todos os abastecimentos
                        <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>

            <div
                class="fuel-collapsible-content"
                x-show="open"
                x-collapse
                x-cloak
            >
                <div class="fuel-receipt-list">
                    @forelse($latestFillings as $filling)
                        <article class="fuel-receipt-item">
                            <div>
                                <strong>
                                    {{ $filling->vehicle?->name ?? 'Veículo' }}
                                    @if($filling->cancelled_at)
                                        <span class="fuel-status-badge low">Cancelado</span>
                                    @endif
                                </strong>

                                <span>
                                    {{ $filling->vehicle?->plate ?: 'Sem placa' }}
                                    · {{ $filling->filled_at?->format('d/m/Y H:i') }}
                                </span>
                            </div>

                            <div>
                                <strong>
                                    {{ number_format((float) $filling->quantity_liters, 3, ',', '.') }} L
                                </strong>

                                <span>
                                    {{ $filling->source_label }}
                                    · {{ $filling->location_label }}
                                    · {{ $filling->product?->name ?? $filling->tank?->product?->name ?? 'Produto' }}
                                </span>
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

                                <small>
                                    Registrado por: {{ $filling->responsible?->name ?: 'Não informado' }}
                                </small>
                            </div>
                        </article>
                    @empty
                        <div class="fuel-table-empty">
                            Nenhum abastecimento registrado nesta unidade.
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

<section
            class="fuel-panel fuel-collapsible-panel"
            x-data="{ open: false }"
        >
            <div
                class="fuel-panel-header fuel-collapsible-header"
                @click="open = !open"
                :aria-expanded="open"
            >
                <div class="fuel-collapsible-title">
                    <span class="fuel-collapse-icon" :class="{ 'is-open': open }">
                        <i class="bi bi-chevron-right"></i>
                    </span>

                    <div>
                        <span class="fuel-kicker">Recebimentos</span>
                        <h2>Últimas entradas</h2>
                    </div>
                </div>

                <div class="fuel-panel-actions">
                    <p>Exibindo os 8 registros mais recentes.</p>

                    <a
                        href="{{ route('fuel.receipts.history') }}"
                        class="fuel-history-action"
                        @click.stop
                    >
                        <i class="bi bi-clock-history"></i>
                        Ver todos os recebimentos
                        <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>

            <div
                class="fuel-collapsible-content"
                x-show="open"
                x-collapse
                x-cloak
            >
                <div class="fuel-receipt-list">
                    @forelse($latestReceipts as $receipt)
                        <article class="fuel-receipt-item">
                            <div>
                                <strong>
                                    {{ $receipt->tank?->name ?? 'Tanque' }}
                                    @if($receipt->cancelled_at)
                                        <span class="fuel-status-badge low">Cancelado</span>
                                    @endif
                                </strong>

                                <span>
                                    {{ $receipt->product?->name ?? $receipt->tank?->product?->name ?? 'Produto' }}
                                    · {{ $receipt->received_at?->format('d/m/Y H:i') }}
                                </span>
                            </div>

                            <div>
                                <strong>
                                    {{ number_format((float) $receipt->quantity_liters, 3, ',', '.') }} L
                                </strong>

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
                                <small>
                                    Recebido por: {{ $receipt->responsible?->name ?: 'Não informado' }}
                                </small>
                            </div>
                        </article>
                    @empty
                        <div class="fuel-table-empty">
                            Nenhum recebimento registrado nesta unidade.
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        <section
            class="fuel-panel fuel-collapsible-panel"
            x-data="{ open: false }"
        >
            <div
                class="fuel-panel-header fuel-collapsible-header"
                @click="open = !open"
                :aria-expanded="open"
            >
                <div class="fuel-collapsible-title">
                    <span class="fuel-collapse-icon" :class="{ 'is-open': open }">
                        <i class="bi bi-chevron-right"></i>
                    </span>

                    <div>
                        <span class="fuel-kicker">Listagem</span>
                        <h2>Tanques cadastrados</h2>
                    </div>
                </div>

                <p>{{ $tanks->count() }} tanque(s) na unidade ativa.</p>
            </div>

            <div
                class="fuel-collapsible-content"
                x-show="open"
                x-collapse
                x-cloak
            >
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

                                @if($canManageFuelTanks)
                                    <th>Ações</th>
                                @endif
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

                                    <td>
                                        {{ number_format((float) $tank->capacity_liters, 3, ',', '.') }} L
                                    </td>

                                    <td>
                                        {{ number_format((float) $tank->current_balance_liters, 3, ',', '.') }} L
                                    </td>

                                    <td>
                                        {{ number_format((float) $tank->minimum_balance_liters, 3, ',', '.') }} L
                                    </td>

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

                                    @if($canManageFuelTanks)
                                        <td>
                                            <button
                                                type="button"
                                                class="fuel-secondary-action"
                                                onclick="openFuelModal('edit-{{ $tank->id }}')"
                                                @click.stop
                                            >
                                                <i class="bi bi-pencil"></i>
                                                Editar
                                            </button>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td
                                        colspan="{{ $canManageFuelTanks ? 7 : 6 }}"
                                        class="fuel-table-empty"
                                    >
                                        Nenhum tanque cadastrado para esta unidade.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        @if($canManageFuelTanks)
<div id="fuel-modal-tank" class="fuel-modal-overlay {{ $errors->fuelTank->any() || $openFuelModal === 'tank' ? 'is-open' : '' }}">
            <div class="fuel-modal-card">
                <div class="fuel-modal-header">
                    <div>
                        <span class="fuel-kicker">Cadastro</span>
                        <h2>Novo tanque</h2>
                    </div>
                    <button type="button" class="fuel-modal-close" onclick="closeFuelModals()">
                        <i class="bi bi-x-lg"></i>
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
@endif

        @if($canRegisterFilling)
        <div id="fuel-modal-filling" class="fuel-modal-overlay {{ $errors->fuelFilling->any() || $openFuelModal === 'filling' ? 'is-open' : '' }}">
            <div class="fuel-modal-card wide">
                <div class="fuel-modal-header">
                    <div>
                        <span class="fuel-kicker">Saída</span>
                        <h2>Registrar abastecimento</h2>
                    </div>
                    <button type="button" class="fuel-modal-close" onclick="closeFuelModals()">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                @if($errors->fuelFilling->any() && ! $errors->fuelFilling->has('duplicate'))
                    <div class="fuel-form-error">
                        @foreach($errors->fuelFilling->all() as $message)
                            <span>{{ $message }}</span>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('fuel.fillings.store') }}" class="fuel-form fuel-filling-form"     onsubmit="return validateFuelFillingCounters(this);">
                    @csrf
                    <input type="hidden" name="return_to" value="{{ $fuelReturnTo }}">
                    <input type="hidden" name="confirm_duplicate" value="{{ old('confirm_duplicate', 0) }}">
                    @if($errors->fuelFilling->has('duplicate'))
                        <div
                            class="fuel-span-12 fuel-alert danger fuel-filling-duplicate-alert"
                            role="alert"
                        >
                            <div class="fuel-filling-alert__content">

                                <div class="fuel-filling-alert__icon">
                                    <i class="bi bi-exclamation-triangle"></i>
                                </div>

                                <div class="fuel-filling-alert__text">
                                    <strong>
                                        Possível abastecimento duplicado
                                    </strong>

                                    <p>
                                        {{ $errors->fuelFilling->first('duplicate') }}
                                    </p>

                                    <small>
                                        Confira os dados antes de continuar.
                                    </small>
                                </div>

                            </div>

                            <div class="fuel-filling-alert__actions">

                                <button
                                    type="button"
                                    class="fuel-action-btn fuel-action-btn--secondary"
                                    onclick="this.closest('form').querySelector('[name=confirm_duplicate]').value='0'; this.closest('form').querySelector('[name=vehicle_km]').focus();"
                                >
                                    <i class="bi bi-arrow-left"></i>
                                    Voltar e revisar
                                </button>

                                <button
                                    type="button"
                                    class="fuel-action-btn fuel-action-btn--danger"
                                    onclick="this.closest('form').querySelector('[name=confirm_duplicate]').value='1'; this.closest('form').submit();"
                                >
                                    <i class="bi bi-exclamation-triangle"></i>
                                    Registrar mesmo assim
                                </button>

                            </div>
                        </div>
                    @endif
                    <div class="fuel-form-grid fuel-filling-layout">
                    <input type="hidden" name="km_reading_confirmed" value="0">
                    <input type="hidden" name="hours_reading_confirmed" value="0">
                        <div class="fuel-span-12 fuel-filling-top-grid">

                            <div class="fuel-filling-source-panel">

                                @if($canFillInternal && $canFillExternal)

                                    <div class="fuel-source-toggle" data-fuel-source-toggle>

                                        <div class="fuel-source-head">
                                            <span>Tipo de abastecimento</span>

                                            <small data-fuel-source-help>
                                                Baixa o saldo do tanque selecionado e registra movimentação interna.
                                            </small>
                                        </div>

                                        <div
                                            class="fuel-source-segment"
                                            role="radiogroup"
                                            aria-label="Tipo de abastecimento"
                                        >
                                            @if($canFillInternal)
                                                <label class="fuel-source-option">
                                                    <input
                                                        type="radio"
                                                        name="source"
                                                        value="internal_tank"
                                                        @checked($defaultFuelFillingSource === 'internal_tank')
                                                    >
                                                    <span>Tanque da unidade</span>
                                                </label>
                                            @endif

                                            @if($canFillExternal)
                                                <label class="fuel-source-option">
                                                    <input
                                                        type="radio"
                                                        name="source"
                                                        value="external_station"
                                                        @checked($defaultFuelFillingSource === 'external_station')
                                                    >
                                                    <span>Posto externo</span>
                                                </label>
                                            @endif
                                        </div>

                                    </div>

                                @else

                                    <div class="fuel-source-toggle fuel-source-toggle--single">
                                        <div class="fuel-source-head">
                                            <span>Tipo de abastecimento</span>

                                            <small data-fuel-source-help>
                                                {{ $defaultFuelFillingSource === 'external_station'
                                                    ? 'Registra custo e consumo do veículo sem movimentar o saldo dos tanques.'
                                                    : 'Baixa o saldo do tanque selecionado e registra movimentação interna.' }}
                                            </small>
                                        </div>

                                        <input
                                            type="hidden"
                                            name="source"
                                            value="{{ $defaultFuelFillingSource }}"
                                        >

                                        <div class="fuel-source-single-value">
                                            {{ $defaultFuelFillingSource === 'external_station'
                                                ? 'Posto externo'
                                                : 'Tanque da unidade' }}
                                        </div>
                                    </div>

                                @endif

                            </div>


                            <aside
                                class="fuel-last-filling-card"
                                id="fuelLastFillingCard"
                            >

                                <div class="fuel-last-filling-card__header">

                                    <div>
                                        <span>Último abastecimento</span>
                                        <small>do veículo selecionado</small>
                                    </div>

                                    <strong id="fuelLastFillingStatus">
                                        Sem histórico
                                    </strong>

                                </div>


                                <div
                                    class="fuel-last-filling-card__empty"
                                    id="fuelLastFillingEmpty"
                                >
                                    Selecione um veículo para consultar
                                    o último abastecimento registrado.
                                </div>


                                <div
                                    class="fuel-last-filling-card__content"
                                    id="fuelLastFillingContent"
                                    hidden
                                >

                                    <div class="fuel-last-filling-card__grid">

                                        <div>
                                            <small>Data</small>
                                            <strong id="fuelLastFillingDate">—</strong>
                                        </div>

                                        <div>
                                            <small>Litros</small>
                                            <strong id="fuelLastFillingLiters">—</strong>
                                        </div>

                                        <div>
                                            <small>Produto</small>
                                            <strong id="fuelLastFillingProduct">—</strong>
                                        </div>

                                        <div>
                                            <small>Origem</small>
                                            <strong id="fuelLastFillingSource">—</strong>
                                        </div>

                                        <div>
                                            <small>KM anterior</small>
                                            <strong id="fuelLastFillingPreviousKm">—</strong>
                                        </div>

                                        <div>
                                            <small>KM lançado</small>
                                            <strong id="fuelLastFillingVehicleKm">—</strong>
                                        </div>

                                    </div>

                                    <div class="fuel-last-filling-card__footer">

                                        <span>
                                            <i class="bi bi-person"></i>
                                            <strong id="fuelLastFillingResponsible">—</strong>
                                        </span>

                                        <span
                                            id="fuelLastFillingTankWrap"
                                        >
                                            <i class="bi bi-fuel-pump"></i>
                                            <strong id="fuelLastFillingTank">—</strong>
                                        </span>

                                    </div>

                                </div>

                            </aside>

                        </div>

                        <label class="fuel-span-4">
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
                            <small data-vehicle-fuel-help>Selecione o veículo para consultar os combustíveis permitidos.</small>
                        </label>

                        <label class="fuel-span-4" data-source-field="internal">
                            Tanque/produto
                            <select name="fuel_tank_id">
                                <option value="">Selecione</option>
                                @foreach($tanks->where('active', true) as $tank)
                                    <option
                                        value="{{ $tank->id }}"
                                        data-product-id="{{ $tank->fuel_product_id }}"
                                        data-unit-cost="{{ $tank->average_unit_cost ?? 0 }}"
                                        @selected(old('fuel_tank_id') == $tank->id)
                                    >
                                        {{ $tank->name }} · {{ $tank->product?->name }} · {{ number_format((float) $tank->current_balance_liters, 3, ',', '.') }} L
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label class="fuel-span-4 is-hidden" data-source-field="external">
                            Produto
                            <select name="fuel_product_id">
                                <option value="">Selecione</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}" data-product-id="{{ $product->id }}" @selected(old('fuel_product_id') == $product->id)>{{ $product->name }}</option>
                                @endforeach
                            </select>
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

                        <label class="fuel-span-4">
                            Data/hora
                            <input type="datetime-local" name="filled_at" value="{{ old('filled_at', now()->format('Y-m-d\TH:i')) }}" required>
                        </label>

                        <label class="fuel-span-4">
                            Horímetro
                            <input
                                type="number"
                                name="vehicle_hours"
                                min="0"
                                step="0.01"
                                data-vehicle-hours-input
                            >
                        </label>

                        <label class="fuel-span-4">
                            Hodômetro
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
                            <x-supplier-autocomplete value="{{ old('supplier_name') }}" document-name="supplier_document" document-value="{{ old('supplier_document') }}" placeholder="Ex.: Posto Central" />
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
                            <i class="bi bi-x-lg"></i>
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

                            {{-- LINHA 1 --}}
                            <label class="receipt-span-2">
                                Data do recebimento

                                <input
                                    type="datetime-local"
                                    name="received_at"
                                    value="{{ old('received_at', now()->format('Y-m-d\TH:i')) }}"
                                    required
                                >
                            </label>


                            <label class="receipt-span-2">
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


                            <label class="receipt-span-2">
                                Custo total

                                <input
                                    type="number"
                                    name="total_cost"
                                    min="0"
                                    step="0.01"
                                    data-fuel-total-cost
                                    value="{{ old('total_cost') }}"
                                >
                            </label>


                            {{-- LINHA 2 --}}
                            <label class="receipt-span-6 receipt-supplier-field">
                                Fornecedor

                                <x-supplier-autocomplete
                                    value="{{ old('supplier_name') }}"
                                    document-name="supplier_document"
                                    document-value="{{ old('supplier_document') }}"
                                    placeholder="Nome do fornecedor"
                                />
                            </label>


                            {{-- LINHA 3 --}}
                            <label class="receipt-span-3">
                                Nota fiscal

                                @if($fuelReceiptInvoiceRequired ?? false)
                                    <small class="receipt-field-hint">
                                        Pode ficar pendente
                                    </small>
                                @endif

                                <div class="input-with-badge">
                                    <span>NF</span>

                                    <input
                                        type="text"
                                        name="invoice_number"
                                        maxlength="255"
                                        placeholder="12403"
                                        value="{{ old('invoice_number') }}"
                                    >
                                </div>
                            </label>


                            <label class="receipt-span-3">
                                Data da NF

                                <input
                                    type="date"
                                    name="invoice_date"
                                    value="{{ old('invoice_date') }}"
                                >

                                @if($fuelReceiptInvoiceRequired ?? false)
                                    <small class="fuel-form-help">
                                        Se a nota ainda não chegou,
                                        deixe número e data em branco.
                                    </small>
                                @endif
                            </label>


                            {{-- CUSTO CALCULADO --}}
                            <label class="receipt-span-6 fuel-cost-preview receipt-unit-cost-preview">
                                <span>
                                    Custo unitário calculado
                                </span>

                                <strong data-fuel-unit-cost-display>
                                    R$ 0,00
                                </strong>

                                <input
                                    type="hidden"
                                    name="unit_cost"
                                    min="0"
                                    step="0.01"
                                    data-fuel-unit-cost
                                >
                            </label>


                            {{-- OBSERVAÇÃO --}}
                            <label class="receipt-span-6">
                                Observação

                                <textarea
                                    name="notes"
                                    rows="3"
                                >{{ old('notes') }}</textarea>
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
                            <i class="bi bi-x-lg"></i>
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

@if($canUseFuelPhotoImport)

<div
    id="fuelPhotoImportModal"
    class="fuel-photo-ai-overlay"
    hidden
>
    <div
        class="fuel-photo-ai-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="fuelPhotoAiTitle"
    >

        <div class="fuel-photo-ai-header">

            <div>
                <span class="fuel-photo-ai-kicker">
                    LEITURA ASSISTIDA
                </span>

                <h2 id="fuelPhotoAiTitle">
                    Importar abastecimentos via foto
                </h2>

                <p>
                    O Google Vision fará a leitura da ficha.
                    Revise todos os campos antes de qualquer lançamento.
                </p>
            </div>

            <button
                type="button"
                class="fuel-photo-ai-close"
                onclick="closeFuelPhotoImport()"
                aria-label="Fechar"
            >
                <i class="bi bi-x-lg"></i>
            </button>

        </div>


        <div class="fuel-photo-ai-body">

            <section class="fuel-photo-ai-upload">

                <div class="fuel-photo-ai-upload-left">

                    <label
                        for="fuelPhotoAiFile"
                        class="fuel-photo-ai-drop"
                    >
                        <i class="bi bi-image"></i>

                        <span>
                            <strong>
                                Selecionar foto da ficha
                            </strong>

                            <small>
                                JPG, PNG ou PDF · até 12 MB
                            </small>
                        </span>
                    </label>

                    <input
                        id="fuelPhotoAiFile"
                        type="file"
                        accept="image/jpeg,image/png,application/pdf,.pdf"
                        hidden
                    >

                    <div
                        id="fuelPhotoAiFileInfo"
                        class="fuel-photo-ai-file-info"
                        hidden
                    ></div>

                </div>


                <div class="fuel-photo-ai-preview-wrap">

                    <img
                        id="fuelPhotoAiPreview"
                        class="fuel-photo-ai-preview"
                        alt="Prévia da ficha"
                        hidden
                    >

                    <div
                        id="fuelPhotoAiPreviewEmpty"
                        class="fuel-photo-ai-preview-empty"
                    >
                        <i class="bi bi-file-earmark-image"></i>
                        <span>Prévia da folha</span>
                    </div>

                </div>

            </section>


            <div class="fuel-photo-ai-analysis-bar">

                <div>
                    <strong>
                        Unidade:
                        {{ $activeLocation->name ?? '—' }}
                    </strong>

                    <span>
                        A imagem não gera lançamentos automaticamente.
                    </span>
                </div>

                <button
                    id="fuelPhotoAiAnalyze"
                    type="button"
                    class="fuel-primary-action"
                    onclick="analyzeFuelPhoto()"
                    disabled
                >
                    <i class="bi bi-stars"></i>
                    Analisar imagem
                </button>

            </div>





            <div
                id="fuelPhotoAiLoading"
                class="fuel-photo-ai-loading"
                hidden
            >
                <div class="fuel-photo-ai-loading-head">
                    <div class="fuel-photo-ai-loading-icon">
                        <span></span>
                    </div>

                    <div>
                        <strong id="fuelPhotoAiLoadingTitle">
                            Analisando ficha...
                        </strong>

                        <span id="fuelPhotoAiLoadingMessage">
                            Preparando o arquivo para leitura.
                        </span>
                    </div>

                    <div
                        id="fuelPhotoAiLoadingTime"
                        class="fuel-photo-ai-loading-time"
                    >
                        00:00
                    </div>
                </div>

                <div class="fuel-photo-ai-loading-track">
                    <div class="fuel-photo-ai-loading-progress"></div>
                </div>

                <div class="fuel-photo-ai-loading-foot">
                    <span>
                        <i class="bi bi-stars"></i>
                        Leitura assistida em andamento
                    </span>

                    <small id="fuelPhotoAiLoadingHint">
                        Não feche esta janela enquanto a ficha estiver sendo analisada.
                    </small>
                </div>
            </div>


            <section
                id="fuelPhotoAiResult"
                class="fuel-photo-ai-result"
                hidden
            >

                <div class="fuel-photo-ai-operation-fields">

                    <label class="fuel-photo-ai-operation-field">
                        <span>Data dos abastecimentos</span>

                        <input
                            type="date"
                            id="fuelPhotoAiOperationDate"
                            class="fuel-photo-ai-meta-input"
                        >
                    </label>

                    <label class="fuel-photo-ai-operation-field">
                        <span>Início</span>

                        <input
                            type="time"
                            id="fuelPhotoAiStartTime"
                            class="fuel-photo-ai-meta-input"
                            onchange="recalculateFuelPhotoSchedule(true)"
                        >
                    </label>

                    <label class="fuel-photo-ai-operation-field">
                        <span>Fim</span>

                        <input
                            type="time"
                            id="fuelPhotoAiEndTime"
                            class="fuel-photo-ai-meta-input"
                            onchange="recalculateFuelPhotoSchedule(true)"
                        >
                    </label>

                </div>


                <div class="fuel-photo-ai-tank-grid">

                    <div class="fuel-photo-ai-tank-card">

                        <div class="fuel-photo-ai-tank-heading">
                            <div>
                                <span>Origem do combustível</span>
                                <strong>Tanque dos abastecimentos</strong>
                            </div>

                            <i class="bi bi-fuel-pump"></i>
                        </div>

                        <select
                            id="fuelPhotoAiFuelTank"
                            class="fuel-photo-ai-meta-input"
                            onchange="recalculateFuelPhotoStocks()"
                        >
                            <option value="">
                                Selecione o tanque...
                            </option>
                        </select>

                        <div
                            id="fuelPhotoAiFuelStock"
                            class="fuel-photo-ai-stock-summary"
                        >
                            <span>
                                Selecione o tanque de origem.
                            </span>
                        </div>

                    </div>


                    <div
                        id="fuelPhotoAiArlaTankCard"
                        class="fuel-photo-ai-tank-card"
                        hidden
                    >

                        <div class="fuel-photo-ai-tank-heading">
                            <div>
                                <span>ARLA informado na ficha</span>
                                <strong>Tanque de ARLA</strong>
                            </div>

                            <i class="bi bi-droplet"></i>
                        </div>

                        <select
                            id="fuelPhotoAiArlaTank"
                            class="fuel-photo-ai-meta-input"
                            onchange="recalculateFuelPhotoStocks()"
                        >
                            <option value="">
                                Selecione o tanque de ARLA...
                            </option>
                        </select>

                        <div
                            id="fuelPhotoAiArlaStock"
                            class="fuel-photo-ai-stock-summary"
                        >
                            <span>
                                Selecione o tanque de ARLA.
                            </span>
                        </div>

                    </div>

                </div>


                <div class="fuel-photo-ai-summary">

                    <div>
                        <span>Linhas identificadas</span>
                        <strong id="fuelPhotoAiRowCount">—</strong>
                    </div>

                    <div>
                        <span>Soma dos litros</span>
                        <strong id="fuelPhotoAiCalculated">—</strong>
                    </div>

                    <div>
                        <span>Duração do período</span>
                        <strong id="fuelPhotoAiPeriodDuration">—</strong>
                    </div>

                    <div>
                        <span>Intervalo médio</span>
                        <strong id="fuelPhotoAiAverageInterval">—</strong>
                    </div>

                </div>


                <div class="fuel-photo-ai-meta">

                    <span>
                        Operador lido:
                        <strong id="fuelPhotoAiOperator">—</strong>
                    </span>

                    <span>
                        Os horários abaixo são estimados e podem ser corrigidos.
                    </span>

                </div>


                <datalist id="fuelPhotoVehiclePlateList">
                    @foreach($vehicles as $vehicle)
                        @if($vehicle->plate)
                            <option
                                value="{{ $vehicle->plate }}"
                            >
                                {{ $vehicle->name }}
                            </option>
                        @endif
                    @endforeach
                </datalist>

                <div class="fuel-photo-ai-table-wrap">

                    <table class="fuel-photo-ai-table">

                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Horário</th>
                                <th>Veículo</th>
                                <th>Placa lida</th>
                                <th>Litros</th>
                                <th>Último KM</th>
                                <th>Novo KM</th>
                                <th>KM rodado</th>
                                <th>ARLA</th>
                                <th>Situação</th>
                            </tr>
                        </thead>

                        <tbody id="fuelPhotoAiRows"></tbody>

                    </table>

                </div>


                <div
                    id="fuelPhotoAiLaunchConfirmation"
                    class="fuel-photo-ai-launch-confirmation"
                    hidden
                >

                    <div class="fuel-photo-ai-launch-confirmation-head">
                        <div>
                            <span>CONFIRMAÇÃO FINAL</span>
                            <strong>
                                Revise o resumo antes de lançar
                            </strong>
                        </div>

                        <i class="bi bi-shield-check"></i>
                    </div>

                    <div
                        id="fuelPhotoAiLaunchSummary"
                        class="fuel-photo-ai-launch-summary"
                    ></div>

                    <div
                        id="fuelPhotoAiDuplicateWarning"
                        class="fuel-photo-ai-duplicate-warning"
                        hidden
                    ></div>

                    <div class="fuel-photo-ai-launch-warning">
                        <i class="bi bi-exclamation-triangle"></i>

                        <span>
                            Esta operação alterará estoque,
                            histórico de abastecimentos e
                            quilometragem dos veículos.
                        </span>
                    </div>

                    <div class="fuel-photo-ai-launch-actions">

                        <button
                            type="button"
                            class="fuel-secondary-action"
                            onclick="cancelFuelPhotoLaunchConfirmation()"
                        >
                            Voltar à revisão
                        </button>

                        <button
                            type="button"
                            id="fuelPhotoAiLaunchButton"
                            class="fuel-primary-action"
                            onclick="storeFuelPhotoImport()"
                        >
                            <i class="bi bi-check2-circle"></i>

                            <span id="fuelPhotoAiLaunchButtonText">
                                Confirmar e lançar
                            </span>
                        </button>

                    </div>

                </div>


                <div class="fuel-photo-ai-footer">

                    <div class="fuel-photo-ai-footer-feedback">
                    <div
                id="fuelPhotoAiFeedback"
                class="fuel-photo-ai-feedback"
                hidden
            ></div>
                </div>

                <div class="fuel-photo-ai-footer-actions">

                        <button
                            type="button"
                            class="fuel-secondary-action"
                            onclick="resetFuelPhotoImport()"
                        >
                            Limpar
                        </button>

                        <button
                            type="button"
                            class="fuel-primary-action"
                            onclick="validateFuelPhotoReading()"
                        >
                            <i class="bi bi-check2-circle"></i>
                            Validar leitura
                        </button>

                    </div>

                </div>

            </section>

        </div>

    </div>
</div>

@endif


<div id="fuelConsumptionDashboard" class="fuel-dashboard-modal" hidden><div class="fuel-dashboard-card"><button type="button" class="fuel-detail-close" onclick="closeFuelConsumptionDashboard()">×</button><h2>Painel de consumo</h2><div class="fuel-dashboard-toolbar"><p id="fuelDashboardSubtitle">Indicadores e gráficos de abastecimento — Últimos 30 dias</p><label>Período<select id="fuelDashboardPeriod" onchange="openFuelConsumptionDashboard()"><option value="last_30_days">Últimos 30 dias</option><option value="current_month">Mês atual</option><option value="previous_month">Mês anterior</option><option value="all">Todo o período</option></select></label></div><div id="fuelDashboardContent">Carregando…</div></div></div>
<script>
window.fuelVehicleCompatibility=@json($fuelCompatibility);
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


@if($canUseFuelPhotoImport)

<script>

@php
    $fuelPhotoVehiclesPayload = $vehicles
        ->map(function ($vehicle) {
            return [
                'id' => $vehicle->id,
                'name' => $vehicle->name,
                'plate' => $vehicle->plate,
                'current_km' => $vehicle->current_km,
                'current_hours' => $vehicle->current_hours,
                'km_control_enabled' => (bool) $vehicle->km_control_enabled,
                'hours_control_enabled' => (bool) $vehicle->hours_control_enabled,
            ];
        })
        ->values()
        ->all();
@endphp

const fuelPhotoVehicles = @json($fuelPhotoVehiclesPayload);

@php
    $fuelPhotoTanksPayload = $tanks
        ->where('active', true)
        ->map(function ($tank) {
            return [
                'id' => $tank->id,
                'name' => $tank->name,
                'fuel_product_id' => $tank->fuel_product_id,
                'product_name' => $tank->product?->name,
                'product_slug' => $tank->product?->slug,
                'balance_liters' => (float) $tank->current_balance_liters,
                'minimum_balance_liters' => (float) $tank->minimum_balance_liters,
            ];
        })
        ->values()
        ->all();
@endphp

const fuelPhotoTanks = @json($fuelPhotoTanksPayload);

const fuelPhotoAnalyzeUrl =
    @json(route('fuel.photo-import.analyze'));


const fuelPhotoStoreUrl =
    @json(route('fuel.photo-import.store'));


const fuelPhotoDuplicateCheckUrl =
    @json(route('fuel.photo-import.duplicates'));

let fuelPhotoDetectedDuplicates = [];

let fuelPhotoAnalysis = null;



let fuelPhotoLoadingTimer = null;
let fuelPhotoLoadingStartedAt = null;


function fuelPhotoLoadingText(seconds) {
    if (seconds < 8) {
        return {
            message: 'Preparando o arquivo para leitura.',
            hint: 'Aguarde enquanto a imagem é preparada.'
        };
    }

    if (seconds < 25) {
        return {
            message: 'Realizando a leitura assistida da ficha.',
            hint: 'O Google Vision está interpretando os dados da imagem.'
        };
    }

    if (seconds < 55) {
        return {
            message: 'Identificando linhas, veículos e valores.',
            hint: 'Estamos reconstruindo os registros encontrados na ficha.'
        };
    }

    if (seconds < 90) {
        return {
            message: 'Conferindo a estrutura e organizando os dados.',
            hint: 'Fichas maiores ou PDFs podem levar um pouco mais de tempo.'
        };
    }

    return {
        message: 'A leitura continua em andamento.',
        hint: 'PDFs podem levar cerca de 1 a 2 minutos.'
    };
}


function updateFuelPhotoLoading() {
    if (! fuelPhotoLoadingStartedAt) {
        return;
    }

    const elapsed =
        Math.max(
            0,
            Math.floor(
                (Date.now() - fuelPhotoLoadingStartedAt) / 1000
            )
        );

    const minutes =
        String(
            Math.floor(elapsed / 60)
        ).padStart(2, '0');

    const seconds =
        String(
            elapsed % 60
        ).padStart(2, '0');

    const time =
        document.getElementById(
            'fuelPhotoAiLoadingTime'
        );

    const message =
        document.getElementById(
            'fuelPhotoAiLoadingMessage'
        );

    const hint =
        document.getElementById(
            'fuelPhotoAiLoadingHint'
        );

    const copy =
        fuelPhotoLoadingText(elapsed);

    if (time) {
        time.textContent =
            `${minutes}:${seconds}`;
    }

    if (message) {
        message.textContent =
            copy.message;
    }

    if (hint) {
        hint.textContent =
            copy.hint;
    }
}


function startFuelPhotoLoading() {
    const box =
        document.getElementById(
            'fuelPhotoAiLoading'
        );

    if (! box) {
        return;
    }

    fuelPhotoLoadingStartedAt =
        Date.now();

    box.hidden = false;

    updateFuelPhotoLoading();

    if (fuelPhotoLoadingTimer) {
        clearInterval(
            fuelPhotoLoadingTimer
        );
    }

    fuelPhotoLoadingTimer =
        setInterval(
            updateFuelPhotoLoading,
            1000
        );
}


function stopFuelPhotoLoading() {
    const box =
        document.getElementById(
            'fuelPhotoAiLoading'
        );

    if (fuelPhotoLoadingTimer) {
        clearInterval(
            fuelPhotoLoadingTimer
        );

        fuelPhotoLoadingTimer =
            null;
    }

    fuelPhotoLoadingStartedAt =
        null;

    if (box) {
        box.hidden = true;
    }
}


function openFuelPhotoImport() {
    const modal =
        document.getElementById(
            'fuelPhotoImportModal'
        );

    if (!modal) return;

    /*
     * Coloca o overlay diretamente no body para não ficar
     * abaixo de sidebar/header ou preso em stacking contexts.
     */
    if (modal.parentElement !== document.body) {
        document.body.appendChild(
            modal
        );
    }

    modal.hidden = false;

    document.body.classList.add(
        'fuel-photo-ai-modal-open'
    );
}


function closeFuelPhotoImport() {
    const modal =
        document.getElementById(
            'fuelPhotoImportModal'
        );

    if (!modal) return;

    modal.hidden = true;

    document.body.classList.remove(
        'fuel-photo-ai-modal-open'
    );
}


function resetFuelPhotoImport() {
    fuelPhotoAnalysis = null;

    const operationDate =
        document.getElementById(
            'fuelPhotoAiOperationDate'
        );

    const startTime =
        document.getElementById(
            'fuelPhotoAiStartTime'
        );

    const endTime =
        document.getElementById(
            'fuelPhotoAiEndTime'
        );

    if (operationDate) {
        operationDate.value = '';
    }

    if (startTime) {
        startTime.value = '';
    }

    if (endTime) {
        endTime.value = '';
    }

    const file =
        document.getElementById(
            'fuelPhotoAiFile'
        );

    const preview =
        document.getElementById(
            'fuelPhotoAiPreview'
        );

    const empty =
        document.getElementById(
            'fuelPhotoAiPreviewEmpty'
        );

    const info =
        document.getElementById(
            'fuelPhotoAiFileInfo'
        );

    const result =
        document.getElementById(
            'fuelPhotoAiResult'
        );

    const feedback =
        document.getElementById(
            'fuelPhotoAiFeedback'
        );

    const button =
        document.getElementById(
            'fuelPhotoAiAnalyze'
        );

    file.value = '';

    preview.src = '';
    preview.hidden = true;

    empty.hidden = false;
    empty.innerHTML =
        '<i class="bi bi-file-earmark-image"></i>'
        + '<span>Prévia da folha</span>';

    info.hidden = true;
    info.innerHTML = '';

    result.hidden = true;

    feedback.hidden = true;
    feedback.innerHTML = '';

    button.disabled = true;
}


async function analyzeFuelPhoto() {
    const input =
        document.getElementById(
            'fuelPhotoAiFile'
        );

    const button =
        document.getElementById(
            'fuelPhotoAiAnalyze'
        );

    const feedback =
        document.getElementById(
            'fuelPhotoAiFeedback'
        );

    if (!input.files?.length) {
        showFuelPhotoFeedback(
            'Selecione uma imagem primeiro.',
            'error'
        );

        return;
    }

    const formData =
        new FormData();

    formData.append(
        'image',
        input.files[0]
    );

    const original =
        button.innerHTML;

    button.disabled = true;

    button.innerHTML =
        '<span class="fuel-photo-ai-spinner"></span>'
        + ' Analisando ficha...';

    startFuelPhotoLoading();

    feedback.hidden = false;
    feedback.className =
        'fuel-photo-ai-feedback is-loading';

    feedback.innerHTML =
        '<strong>Google Vision está lendo a ficha.</strong>'
        + '<span>Reconstruindo linhas e cruzando com os veículos da unidade...</span>';

    try {
        const response = await fetch(
            fuelPhotoAnalyzeUrl,
            {
                method: 'POST',

                headers: {
                    'Accept':
                        'application/json',

                    'X-CSRF-TOKEN':
                        @json(csrf_token())
                },

                body: formData
            }
        );

        const data =
            await response.json();

        if (
            !response.ok
            || !data.ok
        ) {
            throw new Error(
                data.message
                || 'Não foi possível analisar a imagem.'
            );
        }

        fuelPhotoAnalysis =
            data.analysis;

        renderFuelPhotoAnalysis(
            data.analysis
        );

        feedback.hidden = true;

    } catch (error) {
        showFuelPhotoFeedback(
            error.message,
            'error'
        );

    } finally {
        stopFuelPhotoLoading();

        button.disabled = false;
        button.innerHTML = original;
    }
}


function renderFuelPhotoAnalysis(data) {
    const result =
        document.getElementById(
            'fuelPhotoAiResult'
        );

    const rows =
        data.rows || [];

    const validation =
        data.validation || {};

    const header =
        data.header || {};

    document.getElementById(
        'fuelPhotoAiRowCount'
    ).textContent =
        rows.length;

    document.getElementById(
        'fuelPhotoAiCalculated'
    ).textContent =
        fuelPhotoLiters(
            validation.rows_liters_sum
        );

    document.getElementById(
        'fuelPhotoAiOperator'
    ).textContent =
        header.operator || '—';

    const tbody =
        document.getElementById(
            'fuelPhotoAiRows'
        );

    tbody.innerHTML = '';

    rows.forEach(
        (row, index) => {
            tbody.appendChild(
                buildFuelPhotoRow(
                    row,
                    index
                )
            );
        }
    );

    initializeFuelPhotoOperationMeta(
        data
    );

    initializeFuelPhotoTankOptions();

    recalculateFuelPhotoStocks();

    result.hidden = false;

    result.scrollIntoView({
        behavior: 'smooth',
        block: 'nearest'
    });
}


function buildFuelPhotoRow(
    row,
    index
) {
    const tr =
        document.createElement('tr');

    tr.dataset.index = index;

    const warnings =
        row.warnings || [];

    if (
        row.review_level === 'critical'
    ) {
        tr.classList.add(
            'has-critical'
        );

    } else if (
        row.review_level === 'warning'
        || warnings.length
    ) {
        tr.classList.add(
            'has-warning'
        );
    }

    const vehicleOptions =
        [
            '<option value="">Selecione...</option>',

            ...fuelPhotoVehicles.map(
                vehicle => {
                    const selected =
                        Number(
                            row.suggested_vehicle_id
                        ) === Number(
                            vehicle.id
                        );

                    const label =
                        `${vehicle.name}`
                        + (
                            vehicle.plate
                                ? ` · ${vehicle.plate}`
                                : ''
                        );

                    return `
                        <option
                            value="${vehicle.id}"
                            ${selected ? 'selected' : ''}
                        >
                            ${escapeFuelPhoto(label)}
                        </option>
                    `;
                }
            )
        ].join('');

    const confidence =
        Number(
            row.confidence || 0
        );

    const status =
        warnings.length
            ? '⚠ Revisar'
            : confidence >= .85
                ? '✓ OK'
                : '◌ Conferir';

    const plateDb =
        row.matched_vehicle?.plate
        || '—';

    const currentKmDb =
        row.matched_vehicle?.current_km
        ?? null;


    /*
     * Configuração operacional real do veículo.
     * Não presume que toda máquina utilize hodômetro.
     */
    const vehicleMeterConfig =
        fuelPhotoVehicles.find(
            vehicle =>
                Number(vehicle.id)
                === Number(
                    row.suggested_vehicle_id
                    ?? row.matched_vehicle?.id
                )
        )
        || null;

    const kmControlEnabled =
        vehicleMeterConfig
            ? Boolean(
                vehicleMeterConfig.km_control_enabled
            )
            : true;

    const hoursControlEnabled =
        vehicleMeterConfig
            ? Boolean(
                vehicleMeterConfig.hours_control_enabled
            )
            : false;

    const currentHoursDb =
        vehicleMeterConfig?.current_hours
        ?? null;

    /*
     * Comparação numérica.
     * 80032 e "80.032" exibido pelo locale representam
     * a mesma leitura e NÃO geram ação de correção.
     */
    const sheetPreviousKm =
        row.previous_km_printed === null
        || row.previous_km_printed === undefined
        || row.previous_km_printed === ''
            ? null
            : Number(
                row.previous_km_printed
            );

    const hasDifferentChmKm =
        kmControlEnabled
        && currentKmDb !== null
        && sheetPreviousKm !== null
        && Number.isFinite(
            Number(currentKmDb)
        )
        && Number.isFinite(
            Number(sheetPreviousKm)
        )
        && Number(currentKmDb)
            !== Number(sheetPreviousKm);

    tr.innerHTML = `
        <td class="fuel-photo-ai-line">
            ${row.line ?? index + 1}
        </td>

        <td class="fuel-photo-ai-time-cell">
            <input
                type="time"
                class="fuel-photo-ai-input fuel-photo-ai-time-input"
                data-field="estimated_time"
                data-auto-time="1"
                title="Horário estimado. Pode ser corrigido manualmente."
                onchange="this.dataset.autoTime='0'"
            >
        </td>

        <td>
            <select
                class="fuel-photo-ai-input fuel-photo-ai-vehicle"
                data-field="vehicle_id"
                onchange="fuelPhotoVehicleChanged(this)"
            >
                ${vehicleOptions}
            </select>

            <small class="fuel-photo-ai-cell-note">
                Lido:
                ${escapeFuelPhoto(
                    row.code_read || '—'
                )}
            </small>
        </td>

        <td>

            <div class="fuel-photo-ai-plate-confirm">

                <input
                    class="fuel-photo-ai-input fuel-photo-ai-plate ${
                        row.review_level === 'critical'
                            ? 'is-critical'
                            : row.review_level === 'warning'
                                ? 'is-warning'
                                : 'is-ok'
                    }"
                    data-field="plate_read"
                    data-ocr-plate="${escapeFuelPhoto(
                        row.plate_read || ''
                    )}"
                    list="fuelPhotoVehiclePlateList"
                    value="${escapeFuelPhoto(
                        row.plate_read || ''
                    )}"
                    oninput="fuelPhotoPlateChanged(this)"
                    autocomplete="off"
                >

                <button
                    type="button"
                    class="fuel-photo-ai-confirm-btn"
                    title="Confirmar que este é o veículo selecionado"
                    onclick="confirmFuelPhotoPlate(this)"
                >
                    <i class="bi bi-check-lg"></i>
                </button>

            </div>

            <small class="fuel-photo-ai-cell-note fuel-photo-ai-db-plate">
                CHM: ${escapeFuelPhoto(plateDb)}
            </small>

            ${
                row.plate_score != null
                    ? `
                        <small class="fuel-photo-ai-cell-note">
                            Similaridade:
                            ${Math.round(
                                Number(row.plate_score) * 100
                            )}%
                        </small>
                    `
                    : ''
            }
        </td>

        <td>
            <input
                type="number"
                step="0.001"
                min="0"
                class="fuel-photo-ai-input"
                data-field="liters"
                value="${
                    row.liters ?? ''
                }"

                oninput="recalculateFuelPhotoStocks()">
        </td>

        <td>
            <div class="fuel-photo-ai-km-confirm">
<input
                type="number"
                step="1"
                min="0"
                class="fuel-photo-ai-input"
                data-field="previous_km"
                value="${
                    row.previous_km_printed
                    ?? ''
                }"
                oninput="
                    const tr = this.closest('tr');

                    tr.dataset.previousKmConfirmed = '0';
                    this.dataset.kmConfirmed = '0';

                    tr.querySelector(
                        '.fuel-photo-ai-km-confirm-btn'
                    )?.classList.remove(
                        'is-confirmed'
                    );

                    recalculateFuelPhotoRow(tr);
                    refreshFuelPhotoHumanReview();
                    invalidateFuelPhotoLaunchConfirmation();
                "
            >
                <button
                    type="button"
                    class="fuel-photo-ai-confirm-btn fuel-photo-ai-km-confirm-btn"
                    title="Confirmar que este Último KM está correto"
                    onclick="confirmFuelPhotoPreviousKm(this)"
                >
                    <i class="bi bi-check-lg"></i>
                </button>
            </div>

            <small class="fuel-photo-ai-cell-note fuel-photo-ai-km-chm">

                ${
                    kmControlEnabled
                        ? `
                            <span>
                                CHM:
                                ${
                                    currentKmDb == null
                                        ? '—'
                                        : Number(
                                            currentKmDb
                                        ).toLocaleString(
                                            'pt-BR'
                                        ) + ' km'
                                }
                            </span>

                            ${
                                hasDifferentChmKm
                                    ? `
                                        <button
                                            type="button"
                                            class="fuel-photo-ai-use-chm-km"
                                            onclick="useFuelPhotoChmKm(
                                                this,
                                                ${Number(currentKmDb)}
                                            )"
                                            title="Substituir pela leitura atual registrada no CHM"
                                        >
                                            Usar este
                                        </button>
                                    `
                                    : ''
                            }
                        `
                        : (
                            hoursControlEnabled
                                ? `
                                    <span class="fuel-photo-ai-meter-hours">
                                        <i class="bi bi-clock"></i>
                                        Controle por horímetro
                                        ${
                                            currentHoursDb != null
                                                ? ' · CHM: '
                                                    + Number(
                                                        currentHoursDb
                                                    ).toLocaleString(
                                                        'pt-BR'
                                                    )
                                                    + ' h'
                                                : ''
                                        }
                                    </span>
                                `
                                : `
                                    <span class="fuel-photo-ai-meter-disabled">
                                        Controle de hodômetro desabilitado
                                    </span>
                                `
                        )
                }

            </small>

            ${
                kmControlEnabled
                && row.km_reference?.message
                    ? `
                        <small
                            class="fuel-photo-ai-km-reference is-${escapeFuelPhoto(
                                row.km_reference.level || 'info'
                            )}"
                        >
                            ${escapeFuelPhoto(
                                row.km_reference.message
                            )}
                        </small>
                    `
                    : ''
            }
        </td>

        <td>
            <div class="fuel-photo-ai-new-km-confirm">
<input
                type="number"
                step="1"
                min="0"
                class="fuel-photo-ai-input"
                data-field="new_km"
                value="${
                    row.km_at_filling
                    ?? ''
                }"
                oninput="
                    const tr = this.closest('tr');

                    tr.dataset.newKmConfirmed = '0';

                    const confirmButton =
                        tr.querySelector(
                            '.fuel-photo-ai-new-km-confirm-btn'
                        );

                    confirmButton?.classList.remove(
                        'is-confirmed'
                    );

                    if (confirmButton) {
                        delete confirmButton.dataset.confirmedValue;
                    }

                    recalculateFuelPhotoRow(tr);
                    refreshFuelPhotoHumanReview();
                    invalidateFuelPhotoLaunchConfirmation();
                "
            >
                <button
                    type="button"
                    class="fuel-photo-ai-confirm-btn fuel-photo-ai-new-km-confirm-btn"
                    title="Confirmar conscientemente este Novo KM"
                    onclick="confirmFuelPhotoNewKm(this)"
                >
                    <i class="bi bi-check-lg"></i>
                </button>
            </div>
        </td>

        <td class="fuel-photo-ai-distance">

            <strong class="fuel-photo-ai-distance-sheet">
                Folha:
                ${
                    row.distance_km == null
                        ? '—'
                        : Number(
                            row.distance_km
                        ).toLocaleString(
                            'pt-BR'
                        ) + ' km'
                }
            </strong>

            <span class="fuel-photo-ai-distance-db">
                CHM:
                ${
                    currentKmDb != null
                    && row.km_at_filling != null
                        ? Number(
                            Number(row.km_at_filling)
                            - Number(currentKmDb)
                        ).toLocaleString(
                            'pt-BR'
                        ) + ' km'
                        : '—'
                }
            </span>

            ${
                row.historical_km?.samples >= 3
                && row.historical_km?.average_km != null
                    ? `
                        <small>
                            Média:
                            ${Number(
                                row.historical_km.average_km
                            ).toLocaleString(
                                'pt-BR',
                                {
                                    maximumFractionDigits: 1
                                }
                            )} km
                            · ${row.historical_km.samples}
                            intervalos
                        </small>
                    `
                    : `
                        <small>
                            Histórico insuficiente
                        </small>
                    `
            }

        </td>

        <td>
            <input
                type="number"
                step="0.01"
                min="0"
                class="fuel-photo-ai-input"
                data-field="arla"
                value="${
                    row.arla_liters
                    ?? ''
                }"

                oninput="recalculateFuelPhotoStocks()">
        </td>

        <td class="fuel-photo-ai-status-cell">

            <strong>
                ${status}
            </strong>

            ${
                warnings.length
                    ? `
                        <div class="fuel-photo-ai-warnings">
                            ${warnings.map(
                                warning =>
                                    `<span>⚠ ${escapeFuelPhoto(
                                        warning
                                    )}</span>`
                            ).join('')}
                        </div>
                    `
                    : `
                        <small>
                            Confiança:
                            ${Math.round(
                                confidence * 100
                            )}%
                        </small>
                    `
            }

        </td>
    `;

    return tr;
}


function normalizeFuelPhotoPlate(value) {
    return String(value || '')
        .toUpperCase()
        .replace(/[^A-Z0-9]/g, '');
}


function fuelPhotoPlateChanged(input) {
    const tr = input.closest('tr');

    if (tr) {
        tr.dataset.vehicleConfirmed = '0';

        tr.querySelector(
            '.fuel-photo-ai-plate-confirm .fuel-photo-ai-confirm-btn'
        )?.classList.remove(
            'is-confirmed'
        );

        refreshFuelPhotoHumanReview();
        invalidateFuelPhotoLaunchConfirmation();
    }


    const typed =
        normalizeFuelPhotoPlate(
            input.value
        );

    const vehicle =
        fuelPhotoVehicles.find(
            item =>
                normalizeFuelPhotoPlate(
                    item.plate
                ) === typed
        );

    const select =
        tr.querySelector(
            '[data-field="vehicle_id"]'
        );

    const dbPlate =
        tr.querySelector(
            '.fuel-photo-ai-db-plate'
        );

    if (vehicle) {
        if (select) {
            select.value =
                String(vehicle.id);

            fuelPhotoVehicleChanged(
                select
            );
        }

        if (dbPlate) {
            dbPlate.textContent =
                'CHM: '
                + vehicle.plate;
        }

        input.classList.remove(
            'is-warning',
            'is-critical'
        );

        input.classList.add(
            'is-ok'
        );

        tr.classList.remove(
            'has-critical',
            'has-warning'
        );

        return;
    }

    /*
     * Ainda não encontramos placa exata.
     * Mantemos amarelo enquanto o usuário pesquisa.
     */
    input.classList.remove(
        'is-ok',
        'is-critical'
    );

    input.classList.add(
        'is-warning'
    );

    tr.classList.add(
        'has-warning'
    );

    refreshFuelPhotoDuplicatePreflight();

}


function confirmFuelPhotoNewKm(
    button
) {
    const tr =
        button.closest('tr');

    if (!tr) {
        return;
    }

    const input =
        tr.querySelector(
            '[data-field="new_km"]'
        );

    if (!input) {
        return;
    }

    const value =
        Number(
            input.value
        );

    if (
        !Number.isFinite(value)
        || value < 0
    ) {
        showFuelPhotoFeedback(
            'Informe um Novo KM válido antes de confirmar.',
            'error'
        );

        input.focus();
        return;
    }

    tr.dataset.newKmConfirmed =
        '1';

    button.dataset.confirmedValue =
        String(value);

    button.classList.add(
        'is-confirmed'
    );

    button.title =
        'Novo KM confirmado conscientemente';


    refreshFuelPhotoHumanReview();

    showFuelPhotoFeedback(
        'Novo KM confirmado manualmente para esta linha. '
        + 'A validação de segurança será registrada no lançamento.',
        'success'
    );

    invalidateFuelPhotoLaunchConfirmation();
}


function syncFuelPhotoRowMeterControl(
    tr
) {
    if (!tr) {
        return;
    }

    const vehicleSelect =
        tr.querySelector(
            '[data-field="vehicle_id"]'
        );

    const vehicle =
        fuelPhotoVehicles.find(
            item =>
                Number(item.id)
                === Number(
                    vehicleSelect?.value
                )
        )
        || null;

    const previousKmInput =
        tr.querySelector(
            '[data-field="previous_km"]'
        );

    if (!previousKmInput) {
        return;
    }

    /*
     * Algumas linhas nasceram sem veículo associado.
     * Nesses casos o bloco de referência CHM pode não ter
     * sido criado originalmente. Criamos sob demanda.
     */
    let reference =
        tr.querySelector(
            '.fuel-photo-ai-km-chm'
        );

    if (!reference) {

        reference =
            document.createElement(
                'small'
            );

        reference.className =
            'fuel-photo-ai-cell-note fuel-photo-ai-km-chm';

        previousKmInput.insertAdjacentElement(
            'afterend',
            reference
        );
    }


    /*
     * Nenhum veículo selecionado.
     */
    if (!vehicle) {

        reference.innerHTML =
            '<span>CHM: —</span>';

        tr.querySelectorAll(
            '.fuel-photo-ai-km-reference'
        ).forEach(
            element =>
                element.remove()
        );

        return;
    }


    const kmEnabled =
        Boolean(
            vehicle.km_control_enabled
        );

    const hoursEnabled =
        Boolean(
            vehicle.hours_control_enabled
        );

    const currentKm =
        fuelPhotoMeterNumber(
            vehicle.current_km
        );

    const currentHours =
        fuelPhotoMeterNumber(
            vehicle.current_hours
        );

    const sheetPreviousKm =
        fuelPhotoMeterNumber(
            previousKmInput.value
        );


    /*
     * =====================================================
     * CONTROLE POR HORÍMETRO
     * =====================================================
     */
    if (
        !kmEnabled
        && hoursEnabled
    ) {

        reference.innerHTML =
            `
                <span class="fuel-photo-ai-meter-hours">
                    <i class="bi bi-clock"></i>
                    Controle por horímetro
                    ${
                        currentHours !== null
                            ? ' · CHM: '
                                + Number(
                                    currentHours
                                ).toLocaleString(
                                    'pt-BR'
                                )
                                + ' h'
                            : ''
                    }
                </span>
            `;

        tr.querySelectorAll(
            '.fuel-photo-ai-km-reference'
        ).forEach(
            element =>
                element.remove()
        );

        previousKmInput.classList.remove(
            'is-warning',
            'is-critical'
        );

        return;
    }


    /*
     * =====================================================
     * CONTROLE POR KM
     * =====================================================
     */
    if (kmEnabled) {

        const different =
            currentKm !== null
            && sheetPreviousKm !== null
            && Number(currentKm)
                !== Number(sheetPreviousKm);

        reference.innerHTML =
            `
                <span>
                    CHM:
                    ${
                        currentKm === null
                            ? '—'
                            : Number(
                                currentKm
                            ).toLocaleString(
                                'pt-BR'
                            )
                                + ' km'
                    }
                </span>

                ${
                    different
                        ? `
                            <button
                                type="button"
                                class="fuel-photo-ai-use-chm-km"
                                onclick="useFuelPhotoChmKm(
                                    this,
                                    ${Number(currentKm)}
                                )"
                                title="Substituir pela leitura atual registrada no CHM"
                            >
                                Usar este
                            </button>
                        `
                        : ''
                }
            `;

        return;
    }


    /*
     * Nenhum controle habilitado.
     */
    reference.innerHTML =
        `
            <span class="fuel-photo-ai-meter-disabled">
                Medidor não controlado
            </span>
        `;

    tr.querySelectorAll(
        '.fuel-photo-ai-km-reference'
    ).forEach(
        element =>
            element.remove()
    );
}



/*
|--------------------------------------------------------------------------
| FOTO IA - GARANTIR AÇÃO "USAR ESTE" DO KM DO CHM
|--------------------------------------------------------------------------
|
| Algumas rotinas reconstruem a referência CHM depois da seleção
| manual do veículo. Esta função reaplica a ação no estado final
| da linha, sem alterar o valor OCR automaticamente.
|
*/

function ensureFuelPhotoUseChmAction(
    tr
) {
    if (!tr) {
        return;
    }

    const vehicleSelect =
        tr.querySelector(
            '[data-field="vehicle_id"]'
        );

    const previousInput =
        tr.querySelector(
            '[data-field="previous_km"]'
        );

    if (
        !vehicleSelect
        || !previousInput
    ) {
        return;
    }

    const vehicle =
        fuelPhotoVehicles.find(
            item =>
                Number(item.id)
                === Number(
                    vehicleSelect.value
                )
        )
        || null;

    if (!vehicle) {
        return;
    }

    /*
     * Para veículos controlados exclusivamente por horas,
     * não existe ação "Usar KM do CHM".
     */
    if (
        !Boolean(vehicle.km_control_enabled)
    ) {
        tr.querySelectorAll(
            '.fuel-photo-ai-use-chm-km'
        ).forEach(
            button =>
                button.remove()
        );

        return;
    }

    const currentKm =
        fuelPhotoMeterNumber(
            vehicle.current_km
        );

    const sheetKm =
        fuelPhotoMeterNumber(
            previousInput.value
        );

    /*
     * Localiza a referência CHM criada pela renderização
     * original ou pela sincronização posterior.
     */
    let reference =
        tr.querySelector(
            '.fuel-photo-ai-km-chm'
        );

    if (!reference) {
        const cell =
            previousInput.closest(
                'td'
            );

        reference =
            [
                ...(
                    cell?.querySelectorAll(
                        '.fuel-photo-ai-cell-note'
                    )
                    || []
                )
            ].find(
                element =>
                    element.textContent
                        ?.includes('CHM:')
            )
            || null;
    }

    if (!reference) {
        return;
    }

    const existingButton =
        reference.querySelector(
            '.fuel-photo-ai-use-chm-km'
        );

    /*
     * Mesmo valor:
     *
     * 80032 === 80.032 km
     *
     * portanto não deve existir botão.
     */
    if (
        currentKm === null
        || sheetKm === null
        || Number(currentKm)
            === Number(sheetKm)
    ) {
        existingButton?.remove();

        return;
    }

    /*
     * Já existe e está apontando para o mesmo KM.
     */
    if (existingButton) {
        existingButton.dataset.chmKm =
            String(currentKm);

        existingButton.onclick =
            function () {
                useFuelPhotoChmKm(
                    this,
                    currentKm
                );
            };

        return;
    }

    const button =
        document.createElement(
            'button'
        );

    button.type =
        'button';

    button.className =
        'fuel-photo-ai-use-chm-km';

    button.dataset.chmKm =
        String(currentKm);

    button.textContent =
        'Usar este';

    button.title =
        'Usar o KM atual registrado no CHM';

    button.onclick =
        function () {
            useFuelPhotoChmKm(
                this,
                currentKm
            );
        };

    reference.appendChild(
        button
    );
}


function ensureFuelPhotoUseChmActions() {
    document
        .querySelectorAll(
            '#fuelPhotoAiRows > tr'
        )
        .forEach(
            tr =>
                ensureFuelPhotoUseChmAction(
                    tr
                )
        );
}


/*
 * Seleção manual de veículo.
 */
document.addEventListener(
    'change',
    function (event) {

        if (
            !event.target.matches(
                '#fuelPhotoAiRows [data-field="vehicle_id"]'
            )
        ) {
            return;
        }

        const tr =
            event.target.closest(
                'tr'
            );

        /*
         * Aguarda as rotinas originais atualizarem placa,
         * CHM, similaridade e alertas.
         */
        requestAnimationFrame(
            () => {
                requestAnimationFrame(
                    () => {
                        ensureFuelPhotoUseChmAction(
                            tr
                        );
                    }
                );
            }
        );
    }
);


/*
 * Se o operador alterar o Último KM manualmente,
 * também recalcula se "Usar este" deve existir.
 */
document.addEventListener(
    'input',
    function (event) {

        if (
            !event.target.matches(
                '#fuelPhotoAiRows [data-field="previous_km"]'
            )
        ) {
            return;
        }

        ensureFuelPhotoUseChmAction(
            event.target.closest('tr')
        );
    }
);


/*
 * Algumas rotinas da Foto IA reescrevem partes da linha
 * depois do change. O observer garante que a ação continue
 * presente no estado FINAL do DOM.
 */
function setupFuelPhotoUseChmObserver() {

    const tbody =
        document.getElementById(
            'fuelPhotoAiRows'
        );

    if (
        !tbody
        || tbody.dataset.useChmObserver
            === '1'
    ) {
        return;
    }

    tbody.dataset.useChmObserver =
        '1';

    let scheduled =
        false;

    const observer =
        new MutationObserver(
            () => {

                if (scheduled) {
                    return;
                }

                scheduled =
                    true;

                requestAnimationFrame(
                    () => {
                        scheduled =
                            false;

                        ensureFuelPhotoUseChmActions();
                    }
                );
            }
        );

    observer.observe(
        tbody,
        {
            childList: true,
            subtree: true,
            characterData: true
        }
    );

    ensureFuelPhotoUseChmActions();
}


if (
    document.readyState
    === 'loading'
) {
    document.addEventListener(
        'DOMContentLoaded',
        setupFuelPhotoUseChmObserver
    );
} else {
    setupFuelPhotoUseChmObserver();
}


function useFuelPhotoChmKm(
    button,
    chmKm
) {
    const tr =
        button.closest('tr');

    if (!tr) {
        return;
    }

    const input =
        tr.querySelector(
            '[data-field="previous_km"]'
        );

    if (!input) {
        return;
    }

    const value =
        Number(chmKm);

    if (
        !Number.isFinite(value)
        || value < 0
    ) {
        showFuelPhotoFeedback(
            'O KM atual do CHM não é válido para esta linha.',
            'error'
        );

        return;
    }

    /*
     * O operador escolheu conscientemente
     * usar a leitura cadastrada no CHM.
     */
    input.value =
        String(
            Math.round(value)
        );

    /*
     * Como houve alteração de referência,
     * qualquer confirmação final anterior
     * deixa de ser válida.
     */
    invalidateFuelPhotoLaunchConfirmation();

    /*
     * Recalcula KM rodado, alertas e demais
     * informações dependentes da leitura.
     */
    recalculateFuelPhotoRow(
        tr
    );

    /*
     * Reaproveita a confirmação humana já
     * existente para o campo Último KM.
     */
    const confirmButton =
        tr.querySelector(
            '.fuel-photo-ai-km-confirm-btn'
        );

    if (confirmButton) {
        confirmFuelPhotoPreviousKm(
            confirmButton
        );
    }

    button.classList.add(
        'is-used'
    );

    button.textContent =
        'Usado';

    button.title =
        'KM do CHM aplicado nesta linha';

    refreshFuelPhotoHumanReview();
}


function confirmFuelPhotoPreviousKm(
    button
) {
    const tr =
        button.closest('tr');

    if (!tr) {
        return;
    }

    const input =
        tr.querySelector(
            '[data-field="previous_km"]'
        );

    if (!input) {
        return;
    }

    const value =
        Number(
            input.value
        );

    if (
        !Number.isFinite(value)
        || value <= 0
    ) {
        showFuelPhotoFeedback(
            'Informe um Último KM válido antes de confirmar.',
            'error'
        );

        input.focus();

        return;
    }

    /*
     * Confirmação humana:
     * o operador conferiu o KM na ficha.
     */
    input.dataset.kmConfirmed =
        '1';

    tr.dataset.previousKmConfirmed =
        '1';

    input.classList.remove(
        'is-warning',
        'is-critical'
    );

    input.classList.add(
        'is-ok'
    );

    button.classList.add(
        'is-confirmed'
    );

    button.title =
        'Último KM confirmado manualmente';


    refreshFuelPhotoHumanReview();

    /*
     * Remove somente a mensagem referente
     * à comparação Último KM da folha x CHM.
     */
    const kmReference =
        tr.querySelector(
            '.fuel-photo-ai-km-reference'
        );

    if (kmReference) {
        kmReference.textContent =
            '✓ KM conferido manualmente';

        kmReference.classList.remove(
            'is-warning',
            'is-info'
        );

        kmReference.classList.add(
            'is-confirmed'
        );
    }

    /*
     * Caso algum aviso desse tipo esteja também
     * na coluna Situação, removemos somente ele.
     */
    const statusCell =
        tr.querySelector(
            '.fuel-photo-ai-status-cell'
        )
        || tr.lastElementChild;

    if (statusCell) {
        const kmReferenceWarnings = [
            'último km da folha está abaixo',
            'último km da folha está acima',
            'folha abaixo do chm',
            'folha acima do chm',
            'revise se a folha foi preenchida com uma leitura antiga',
            'pode haver abastecimentos anteriores ainda não lançados'
        ];

        [
            ...statusCell.querySelectorAll(
                'li, p, small, span'
            )
        ].forEach(
            node => {
                const content =
                    (
                        node.textContent
                        || ''
                    )
                    .trim()
                    .toLowerCase();

                if (
                    kmReferenceWarnings.some(
                        warning =>
                            content.includes(
                                warning
                            )
                    )
                ) {
                    node.remove();
                }
            }
        );
    }
}


function confirmFuelPhotoPlate(
    button
) {
    const tr =
        button.closest('tr');

    if (!tr) {
        return;
    }

    const select =
        tr.querySelector(
            '[data-field="vehicle_id"]'
        );

    const input =
        tr.querySelector(
            '[data-field="plate_read"]'
        );

    if (!select || !input) {
        return;
    }

    const vehicle =
        fuelPhotoVehicles.find(
            item =>
                Number(item.id)
                === Number(
                    select.value
                )
        );

    if (!vehicle) {
        showFuelPhotoFeedback(
            'Selecione primeiro o veículo correto.',
            'error'
        );

        select.focus();
        return;
    }

    const confirmedValue =
        (
            vehicle.plate
            || vehicle.name
            || ''
        )
        .toUpperCase()
        .trim();

    if (!confirmedValue) {
        showFuelPhotoFeedback(
            'Este veículo não possui placa/código utilizável para confirmação.',
            'error'
        );

        return;
    }

    /*
     * O operador está confirmando manualmente
     * que o veículo selecionado é o correto.
     */
    input.value =
        confirmedValue;

    input.dataset.ocrPlate =
        confirmedValue;

    input.classList.remove(
        'is-warning',
        'is-critical'
    );

    input.classList.add(
        'is-ok'
    );

    /*
     * Executa a lógica normal da placa.
     */
    fuelPhotoPlateChanged(
        input
    );

    /*
     * Marca explicitamente como confirmação humana.
     */
    tr.dataset.vehicleConfirmed =
        '1';

    button.classList.add(
        'is-confirmed'
    );

    button.title =
        'Veículo confirmado manualmente';


    refreshFuelPhotoHumanReview();

    /*
     * Remove APENAS avisos relacionados
     * à identificação do veículo.
     *
     * KM, ARLA e demais avisos continuam.
     */
    const statusCell =
        tr.querySelector(
            '.fuel-photo-ai-status-cell'
        )
        || tr.lastElementChild;

    if (statusCell) {
        const identityWarnings = [
            'placa/identificador lido diverge',
            'veículo associado por aproximação',
            'nenhum veículo cadastrado foi identificado com segurança',
            'código e placa/identificador apontam para veículos diferentes',
            'placa lida na folha diverge',
            'confirme o cadastro'
        ];

        /*
         * Procura cada elemento textual da coluna Situação.
         */
        const candidates =
            [
                ...statusCell.querySelectorAll(
                    'li, p, small, span, div'
                )
            ];

        candidates
            .reverse()
            .forEach(
                node => {
                    const value =
                        (
                            node.textContent
                            || ''
                        )
                        .trim()
                        .toLowerCase();

                    if (!value) {
                        return;
                    }

                    const isIdentityWarning =
                        identityWarnings.some(
                            warning =>
                                value.includes(
                                    warning
                                )
                        );

                    if (!isIdentityWarning) {
                        return;
                    }

                    /*
                     * Não remove o container inteiro da situação.
                     */
                    if (
                        node === statusCell
                    ) {
                        return;
                    }

                    /*
                     * Se o elemento contém somente este aviso,
                     * removemos a linha inteira.
                     */
                    const childWarning =
                        [
                            ...node.children
                        ]
                        .some(
                            child => {
                                const childText =
                                    (
                                        child.textContent
                                        || ''
                                    )
                                    .trim()
                                    .toLowerCase();

                                return identityWarnings.some(
                                    warning =>
                                        childText.includes(
                                            warning
                                        )
                                );
                            }
                        );

                    if (
                        node.children.length === 0
                        || !childWarning
                    ) {
                        node.remove();
                    }
                }
            );

        /*
         * Caso o aviso esteja como texto direto dentro
         * de algum container, limpa especificamente
         * as frases conhecidas.
         */
        [
            ...statusCell.childNodes
        ].forEach(
            node => {
                if (
                    node.nodeType
                    !== Node.TEXT_NODE
                ) {
                    return;
                }

                const value =
                    (
                        node.textContent
                        || ''
                    )
                    .trim()
                    .toLowerCase();

                if (
                    identityWarnings.some(
                        warning =>
                            value.includes(
                                warning
                            )
                    )
                ) {
                    node.remove();
                }
            }
        );
    }

    /*
     * Recalcula KM/estado depois da confirmação.
     */
    recalculateFuelPhotoRow(
        tr
    );
}


function fuelPhotoVehicleChanged(
    select
) {
    const tr =
        select.closest('tr');

    if (tr) {
        tr.dataset.vehicleConfirmed = '0';

        tr.querySelector(
            '.fuel-photo-ai-plate-confirm .fuel-photo-ai-confirm-btn'
        )?.classList.remove(
            'is-confirmed'
        );

        refreshFuelPhotoHumanReview();
        invalidateFuelPhotoLaunchConfirmation();
    }


    if (!tr) {
        return;
    }

    const vehicle =
        fuelPhotoVehicles.find(
            item =>
                Number(item.id)
                === Number(
                    select.value
                )
        );

    const plate =
        tr.querySelector(
            '.fuel-photo-ai-db-plate'
        );

    const km =
        tr.querySelector(
            '.fuel-photo-ai-km-chm'
        );

    if (plate) {
        plate.textContent =
            'CHM: '
            + (
                vehicle?.plate
                || vehicle?.name
                || '—'
            );
    }

    if (km) {
        km.textContent =
            'CHM: '
            + (
                vehicle?.current_km == null
                    ? '—'
                    : Number(
                        vehicle.current_km
                    ).toLocaleString(
                        'pt-BR'
                    ) + ' km'
            );
    }

    /*
     * Se o usuário selecionou explicitamente um veículo,
     * resolvemos a pendência de identificação.
     */
    if (vehicle) {
        tr.classList.remove(
            'has-critical'
        );

        const plateInput =
            tr.querySelector(
                '[data-field="plate_read"]'
            );

        if (plateInput) {
            plateInput.classList.remove(
                'is-critical'
            );

            /*
             * Não pintamos automaticamente de verde:
             * pode continuar existindo divergência OCR x cadastro.
             */
            const typed =
                normalizeFuelPhotoPlate(
                    plateInput.value
                );

            const registered =
                normalizeFuelPhotoPlate(
                    vehicle.plate
                    || vehicle.name
                );

            plateInput.classList.toggle(
                'is-ok',
                typed !== ''
                && registered !== ''
                && typed === registered
            );

            plateInput.classList.toggle(
                'is-warning',
                typed === ''
                || registered === ''
                || typed !== registered
            );
        }
    }

    recalculateFuelPhotoRow(
        tr
    );

    refreshFuelPhotoDuplicatePreflight();

}


function recalculateFuelPhotoRow(
    tr
) {
    if (!tr) {
        return;
    }

    const previousInput =
        tr.querySelector(
            '[data-field="previous_km"]'
        );

    const newInput =
        tr.querySelector(
            '[data-field="new_km"]'
        );

    const select =
        tr.querySelector(
            '[data-field="vehicle_id"]'
        );

    const previous =
        previousInput?.value !== ''
            ? Number(previousInput.value)
            : null;

    const next =
        newInput?.value !== ''
            ? Number(newInput.value)
            : null;

    const vehicle =
        fuelPhotoVehicles.find(
            item =>
                Number(item.id)
                === Number(
                    select?.value
                )
        );

    const currentKm =
        vehicle?.current_km != null
            ? Number(vehicle.current_km)
            : null;

    const sheet =
        tr.querySelector(
            '.fuel-photo-ai-distance-sheet'
        );

    const database =
        tr.querySelector(
            '.fuel-photo-ai-distance-db'
        );

    const kmLabel =
        tr.querySelector(
            '.fuel-photo-ai-km-chm'
        );

    let kmReference =
        tr.querySelector(
            '.fuel-photo-ai-km-reference'
        );

    if (kmLabel) {
        kmLabel.textContent =
            currentKm == null
                ? 'CHM: —'
                : 'CHM: '
                    + currentKm.toLocaleString(
                        'pt-BR'
                    )
                    + ' km';
    }


    if (
        previousInput
        && currentKm !== null
        && previous !== null
        && Number.isFinite(previous)
    ) {
        let level = 'ok';
        let message = '';

        if (previous > currentKm) {
            level = 'info';
            message =
                'Folha acima do CHM: pode haver abastecimentos anteriores ainda não lançados.';

        } else if (previous < currentKm) {
            level = 'warning';
            message =
                'Folha abaixo do KM registrado no CHM: revisar leitura anterior.';
        }

        if (!kmReference) {
            kmReference =
                document.createElement(
                    'small'
                );

            kmReference.className =
                'fuel-photo-ai-km-reference';

            kmLabel?.insertAdjacentElement(
                'afterend',
                kmReference
            );
        }

        kmReference.className =
            'fuel-photo-ai-km-reference is-'
            + level;

        kmReference.textContent =
            message;

        kmReference.hidden =
            message === '';

    } else if (kmReference) {
        kmReference.hidden = true;
    }

    if (sheet) {
        if (
            previous !== null
            && next !== null
            && Number.isFinite(previous)
            && Number.isFinite(next)
        ) {
            const distance =
                next - previous;

            sheet.textContent =
                'Folha: '
                + distance.toLocaleString(
                    'pt-BR'
                )
                + ' km';

            sheet.classList.toggle(
                'is-invalid',
                distance < 0
            );
        } else {
            sheet.textContent =
                'Folha: —';

            sheet.classList.remove(
                'is-invalid'
            );
        }
    }

    if (database) {
        if (
            currentKm !== null
            && next !== null
            && Number.isFinite(currentKm)
            && Number.isFinite(next)
        ) {
            const distance =
                next - currentKm;

            database.textContent =
                'CHM: '
                + distance.toLocaleString(
                    'pt-BR'
                )
                + ' km';

            database.classList.toggle(
                'is-invalid',
                distance < 0
            );
        } else {
            database.textContent =
                'CHM: —';

            database.classList.remove(
                'is-invalid'
            );
        }
    }

    /*
     * Último KM da folha x último KM cadastrado.
     */
    if (
        previousInput
        && currentKm !== null
        && previous !== null
        && Number.isFinite(previous)
    ) {
        const differs =
            Number(previous)
            !== Number(currentKm);

        previousInput.classList.toggle(
            'is-warning',
            differs
        );

        previousInput.classList.toggle(
            'is-ok',
            !differs
        );
    }
}



function fuelPhotoTimeToMinutes(
    value
) {
    if (!value || !value.includes(':')) {
        return null;
    }

    const [
        hours,
        minutes
    ] = value
        .split(':')
        .map(Number);

    if (
        !Number.isFinite(hours)
        || !Number.isFinite(minutes)
    ) {
        return null;
    }

    return (
        hours * 60
        + minutes
    );
}


function fuelPhotoMinutesToTime(
    totalMinutes
) {
    /*
     * Mantém dentro de um dia.
     */
    totalMinutes =
        Math.round(
            totalMinutes
        );

    totalMinutes =
        (
            totalMinutes
            % 1440
            + 1440
        ) % 1440;

    const hours =
        Math.floor(
            totalMinutes / 60
        );

    const minutes =
        totalMinutes % 60;

    return String(hours)
        .padStart(2, '0')
        + ':'
        + String(minutes)
            .padStart(2, '0');
}


function recalculateFuelPhotoSchedule(
    forceAll = false
) {
    const startInput =
        document.getElementById(
            'fuelPhotoAiStartTime'
        );

    const endInput =
        document.getElementById(
            'fuelPhotoAiEndTime'
        );

    const durationEl =
        document.getElementById(
            'fuelPhotoAiPeriodDuration'
        );

    const averageEl =
        document.getElementById(
            'fuelPhotoAiAverageInterval'
        );

    const rows =
        [
            ...document.querySelectorAll(
                '#fuelPhotoAiRows tr'
            )
        ];

    const start =
        fuelPhotoTimeToMinutes(
            startInput?.value
        );

    let end =
        fuelPhotoTimeToMinutes(
            endInput?.value
        );

    if (
        start === null
        || end === null
        || !rows.length
    ) {
        if (durationEl) {
            durationEl.textContent = '—';
        }

        if (averageEl) {
            averageEl.textContent = '—';
        }

        return;
    }

    /*
     * Permite período atravessando meia-noite.
     * Ex.: 23:30 → 00:30 = 60 min.
     */
    if (end < start) {
        end += 1440;
    }

    const duration =
        end - start;

    /*
     * Conforme regra operacional:
     * 60 minutos / 20 abastecimentos = 3 min.
     *
     * Assim a primeira linha recebe o horário inicial;
     * a última ocorre antes do horário final.
     */
    const interval =
        duration / rows.length;

    if (durationEl) {
        const hours =
            Math.floor(
                duration / 60
            );

        const minutes =
            duration % 60;

        durationEl.textContent =
            hours > 0
                ? (
                    minutes > 0
                        ? `${hours}h ${String(minutes).padStart(2, '0')}min`
                        : `${hours}h`
                )
                : `${minutes} min`;
    }

    if (averageEl) {
        averageEl.textContent =
            interval >= 1
                ? `${interval.toLocaleString(
                    'pt-BR',
                    {
                        maximumFractionDigits: 1
                    }
                )} min`
                : `${Math.round(
                    interval * 60
                )} s`;
    }

    const stockTotals =
        fuelPhotoCurrentTotals();

    const fuelTankId =
        document.getElementById(
            'fuelPhotoAiFuelTank'
        )?.value;

    const arlaTankId =
        document.getElementById(
            'fuelPhotoAiArlaTank'
        )?.value;

    const selectedFuelTank =
        fuelPhotoTanks.find(
            tank =>
                Number(tank.id)
                === Number(fuelTankId)
        );

    const selectedArlaTank =
        fuelPhotoTanks.find(
            tank =>
                Number(tank.id)
                === Number(arlaTankId)
        );

    if (!selectedFuelTank) {
        problems.push(
            'Selecione o tanque de combustível.'
        );

    } else if (
        stockTotals.fuel_liters
        > Number(
            selectedFuelTank.balance_liters || 0
        )
    ) {
        problems.push(
            'O tanque de combustível não possui saldo suficiente para a soma dos abastecimentos.'
        );
    }

    if (
        stockTotals.arla_liters > 0
    ) {
        if (!selectedArlaTank) {
            problems.push(
                'Selecione o tanque de ARLA.'
            );

        } else if (
            stockTotals.arla_liters
            > Number(
                selectedArlaTank.balance_liters || 0
            )
        ) {
            problems.push(
                'O tanque de ARLA não possui saldo suficiente para a soma informada.'
            );
        }
    }

    rows.forEach(
        (tr, index) => {
            const input =
                tr.querySelector(
                    '[data-field="estimated_time"]'
                );

            if (!input) {
                return;
            }

            /*
             * Ao alterar início/fim, recalculamos tudo.
             * O usuário ainda pode depois corrigir uma linha.
             */
            if (
                forceAll
                || input.dataset.autoTime !== '0'
            ) {
                input.value =
                    fuelPhotoMinutesToTime(
                        start
                        + interval * index
                    );

                input.dataset.autoTime =
                    '1';
            }
        }
    );
}


function initializeFuelPhotoOperationMeta(
    data
) {
    const date =
        document.getElementById(
            'fuelPhotoAiOperationDate'
        );

    const start =
        document.getElementById(
            'fuelPhotoAiStartTime'
        );

    const end =
        document.getElementById(
            'fuelPhotoAiEndTime'
        );

    /*
     * Se o Vision reconheceu data, utiliza-a.
     * Caso contrário, usa a data local do navegador.
     */
    if (date) {
        let resolvedDate = '';

        const readDate =
            data?.header?.date
            || data?.date
            || data?.operation_date
            || '';

        const match =
            String(readDate).match(
                /^(\d{2})\/(\d{2})\/(\d{4})$/
            );

        if (match) {
            resolvedDate =
                `${match[3]}-${match[2]}-${match[1]}`;
        } else if (
            /^\d{4}-\d{2}-\d{2}$/.test(
                String(readDate)
            )
        ) {
            resolvedDate =
                String(readDate);
        }

        if (!resolvedDate) {
            const today =
                new Date();

            resolvedDate =
                [
                    today.getFullYear(),
                    String(
                        today.getMonth() + 1
                    ).padStart(2, '0'),
                    String(
                        today.getDate()
                    ).padStart(2, '0')
                ].join('-');
        }

        date.value =
            resolvedDate;
    }

    /*
     * Não inventamos início/fim.
     * O operador informa.
     */
    if (start) {
        start.value = '';
    }

    if (end) {
        end.value = '';
    }

    recalculateFuelPhotoSchedule(
        true
    );
}


function fuelPhotoNumber(
    value
) {
    if (
        value === null
        || value === undefined
        || value === ''
    ) {
        return 0;
    }

    if (typeof value === 'number') {
        return Number.isFinite(value)
            ? value
            : 0;
    }

    let normalized =
        String(value).trim();

    /*
     * Inputs HTML type=number retornam decimal com ponto:
     * 142.1 deve continuar 142.1.
     *
     * Se houver vírgula, tratamos como formato pt-BR:
     * 1.234,56 -> 1234.56
     * 23,66    -> 23.66
     */
    if (normalized.includes(',')) {
        normalized =
            normalized
                .replace(/\./g, '')
                .replace(',', '.');
    }

    const parsed =
        Number(normalized);

    return Number.isFinite(parsed)
        ? parsed
        : 0;
}


function fuelPhotoFormatLiters(
    value
) {
    return Number(value || 0)
        .toLocaleString(
            'pt-BR',
            {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }
        )
        + ' L';
}


function fuelPhotoTankIsArla(
    tank
) {
    const text =
        [
            tank?.product_name,
            tank?.product_slug,
            tank?.name
        ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase();

    return text.includes('arla');
}


function initializeFuelPhotoTankOptions() {
    const fuelSelect =
        document.getElementById(
            'fuelPhotoAiFuelTank'
        );

    const arlaSelect =
        document.getElementById(
            'fuelPhotoAiArlaTank'
        );

    if (!fuelSelect || !arlaSelect) {
        return;
    }

    const fuelTanks =
        fuelPhotoTanks.filter(
            tank =>
                !fuelPhotoTankIsArla(
                    tank
                )
        );

    const arlaTanks =
        fuelPhotoTanks.filter(
            tank =>
                fuelPhotoTankIsArla(
                    tank
                )
        );

    fuelSelect.innerHTML =
        '<option value="">Selecione o tanque...</option>'
        + fuelTanks.map(
            tank => `
                <option value="${tank.id}">
                    ${escapeFuelPhoto(
                        tank.name
                        + (
                            tank.product_name
                                ? ' · ' + tank.product_name
                                : ''
                        )
                    )}
                </option>
            `
        ).join('');

    arlaSelect.innerHTML =
        '<option value="">Selecione o tanque de ARLA...</option>'
        + arlaTanks.map(
            tank => `
                <option value="${tank.id}">
                    ${escapeFuelPhoto(
                        tank.name
                        + (
                            tank.product_name
                                ? ' · ' + tank.product_name
                                : ''
                        )
                    )}
                </option>
            `
        ).join('');

    /*
     * Se houver apenas uma opção possível,
     * pode pré-selecioná-la sem ambiguidade.
     */
    if (fuelTanks.length === 1) {
        fuelSelect.value =
            String(
                fuelTanks[0].id
            );
    }

    if (arlaTanks.length === 1) {
        arlaSelect.value =
            String(
                arlaTanks[0].id
            );
    }
}


function fuelPhotoCurrentTotals() {
    const rows =
        [
            ...document.querySelectorAll(
                '#fuelPhotoAiRows tr'
            )
        ];

    let fuelLiters = 0;
    let arlaLiters = 0;

    rows.forEach(
        tr => {
            fuelLiters +=
                fuelPhotoNumber(
                    tr.querySelector(
                        '[data-field="liters"]'
                    )?.value
                );

            arlaLiters +=
                fuelPhotoNumber(
                    tr.querySelector(
                        '[data-field="arla"]'
                    )?.value
                );
        }
    );

    return {
        fuel_liters:
            Math.max(
                0,
                fuelLiters
            ),

        arla_liters:
            Math.max(
                0,
                arlaLiters
            )
    };
}


function renderFuelPhotoTankStock(
    element,
    tank,
    required
) {
    if (!element) {
        return;
    }

    if (!tank) {
        element.className =
            'fuel-photo-ai-stock-summary';

        element.innerHTML =
            '<span>Selecione o tanque de origem.</span>';

        return;
    }

    const available =
        Number(
            tank.balance_liters || 0
        );

    const remaining =
        available - required;

    const sufficient =
        remaining >= 0;

    element.className =
        'fuel-photo-ai-stock-summary '
        + (
            sufficient
                ? 'is-ok'
                : 'is-critical'
        );

    element.innerHTML = `
        <div>
            <span>Saldo atual</span>
            <strong>
                ${fuelPhotoFormatLiters(
                    available
                )}
            </strong>
        </div>

        <div>
            <span>Necessário</span>
            <strong>
                ${fuelPhotoFormatLiters(
                    required
                )}
            </strong>
        </div>

        <div>
            <span>Saldo após</span>
            <strong>
                ${fuelPhotoFormatLiters(
                    remaining
                )}
            </strong>
        </div>

        <p>
            ${
                sufficient
                    ? '✓ Saldo suficiente para esta ficha.'
                    : '⚠ Saldo insuficiente para concluir estes abastecimentos.'
            }
        </p>
    `;
}


function recalculateFuelPhotoStocks() {
    const totals =
        fuelPhotoCurrentTotals();

    const fuelSelect =
        document.getElementById(
            'fuelPhotoAiFuelTank'
        );

    const arlaSelect =
        document.getElementById(
            'fuelPhotoAiArlaTank'
        );

    const arlaCard =
        document.getElementById(
            'fuelPhotoAiArlaTankCard'
        );

    const fuelTank =
        fuelPhotoTanks.find(
            tank =>
                Number(tank.id)
                === Number(
                    fuelSelect?.value
                )
        );

    const arlaTank =
        fuelPhotoTanks.find(
            tank =>
                Number(tank.id)
                === Number(
                    arlaSelect?.value
                )
        );

    renderFuelPhotoTankStock(
        document.getElementById(
            'fuelPhotoAiFuelStock'
        ),
        fuelTank,
        totals.fuel_liters
    );

    const hasArla =
        totals.arla_liters > 0.0001;

    if (arlaCard) {
        arlaCard.hidden =
            !hasArla;
    }

    if (hasArla) {
        renderFuelPhotoTankStock(
            document.getElementById(
                'fuelPhotoAiArlaStock'
            ),
            arlaTank,
            totals.arla_liters
        );
    }

    /*
     * Também mantém o KPI de litros coerente
     * com os valores efetivamente editados.
     */
    const calculated =
        document.getElementById(
            'fuelPhotoAiCalculated'
        );

    if (calculated) {
        calculated.textContent =
            fuelPhotoFormatLiters(
                totals.fuel_liters
            );
    }

    refreshFuelPhotoHumanReview();


    /*
     * Mantém o alerta de duplicidade atualizado
     * conforme veículo, litros ou tanque mudarem.
     */
    clearTimeout(
        window.fuelPhotoDuplicateTimer
    );

    window.fuelPhotoDuplicateTimer =
        setTimeout(
            refreshFuelPhotoDuplicatePreflight,
            350
        );

}


function invalidateFuelPhotoLaunchConfirmation() {
    const confirmation =
        document.getElementById(
            'fuelPhotoAiLaunchConfirmation'
        );

    if (
        confirmation
        && !confirmation.hidden
    ) {
        confirmation.hidden = true;

        showFuelPhotoFeedback(
            'A leitura foi alterada. Valide novamente antes de lançar.',
            'warning'
        );
    }
}



/*
|--------------------------------------------------------------------------
| FOTO IA - CORREÇÃO ESTRUTURAL DAS LINHAS
|--------------------------------------------------------------------------
|
| 1. Consolida automaticamente um caso seguro de linha quebrada pelo OCR.
| 2. Permite inserir linha acima/abaixo.
| 3. Permite excluir linha durante a revisão.
|
*/

let fuelPhotoStructuralToolsBusy = false;


function fuelPhotoMeterNumber(
    value
) {
    if (
        value === null
        || value === undefined
        || String(value).trim() === ''
    ) {
        return null;
    }

    let raw =
        String(value)
            .trim()
            .replace(/\s+/g, '');

    /*
     * Para medidores inteiros:
     * 51.117  -> 51117
     * 80.032  -> 80032
     */
    if (
        /^\d{1,3}(?:\.\d{3})+$/.test(raw)
    ) {
        raw =
            raw.replace(/\./g, '');
    }

    raw =
        raw.replace(',', '.');

    const number =
        Number(raw);

    return Number.isFinite(number)
        ? number
        : null;
}


function fuelPhotoVehicleForRow(
    tr
) {
    const id =
        tr.querySelector(
            '[data-field="vehicle_id"]'
        )?.value;

    if (!id) {
        return null;
    }

    return (
        fuelPhotoVehicles.find(
            vehicle =>
                Number(vehicle.id)
                === Number(id)
        )
        || null
    );
}


function fuelPhotoRowFieldValue(
    tr,
    field
) {
    return (
        tr.querySelector(
            `[data-field="${field}"]`
        )?.value
        ?? ''
    );
}


function fuelPhotoRowHasValue(
    tr,
    field
) {
    return (
        String(
            fuelPhotoRowFieldValue(
                tr,
                field
            )
        ).trim() !== ''
    );
}


function fuelPhotoIsMeterOnlyOrphanRow(
    tr
) {
    const vehicle =
        fuelPhotoRowFieldValue(
            tr,
            'vehicle_id'
        );

    const plate =
        fuelPhotoRowFieldValue(
            tr,
            'plate_read'
        );

    const liters =
        fuelPhotoMeterNumber(
            fuelPhotoRowFieldValue(
                tr,
                'liters'
            )
        );

    const previous =
        fuelPhotoMeterNumber(
            fuelPhotoRowFieldValue(
                tr,
                'previous_km'
            )
        );

    const next =
        fuelPhotoMeterNumber(
            fuelPhotoRowFieldValue(
                tr,
                'new_km'
            )
        );

    return (
        !String(vehicle).trim()
        && !String(plate).trim()
        && (
            liters === null
            || liters <= 0
        )
        && previous !== null
        && next !== null
        && previous > 0
        && next >= previous
    );
}


function fuelPhotoCanReceiveOrphanKm(
    tr
) {
    const vehicle =
        fuelPhotoVehicleForRow(
            tr
        );

    if (!vehicle) {
        return false;
    }

    /*
     * Esta fusão automática só vale para
     * veículos controlados por KM.
     *
     * Horímetro será tratado separadamente.
     */
    if (
        vehicle.km_control_enabled
        === false
    ) {
        return false;
    }

    const liters =
        fuelPhotoMeterNumber(
            fuelPhotoRowFieldValue(
                tr,
                'liters'
            )
        );

    return (
        liters !== null
        && liters > 0
        && !fuelPhotoRowHasValue(
            tr,
            'previous_km'
        )
        && !fuelPhotoRowHasValue(
            tr,
            'new_km'
        )
    );
}


function fuelPhotoAppendMergeNote(
    tr,
    sourceLine
) {
    const statusCell =
        tr.querySelector(
            '.fuel-photo-ai-status-cell'
        );

    if (
        !statusCell
        || statusCell.querySelector(
            '.fuel-photo-ai-merge-note'
        )
    ) {
        return;
    }

    const note =
        document.createElement(
            'small'
        );

    note.className =
        'fuel-photo-ai-merge-note';

    note.innerHTML =
        '<i class="bi bi-intersect"></i> '
        + 'KM recuperado automaticamente'
        + (
            sourceLine
                ? ` da linha ${sourceLine}.`
                : ' da linha anterior.'
        );

    statusCell.prepend(
        note
    );
}


function fuelPhotoAfterStructureChange() {

    if (
        typeof recalculateFuelPhotoStocks
        === 'function'
    ) {
        recalculateFuelPhotoStocks();
    }

    if (
        typeof refreshFuelPhotoHumanReview
        === 'function'
    ) {
        refreshFuelPhotoHumanReview();
    }

    if (
        typeof invalidateFuelPhotoLaunchConfirmation
        === 'function'
    ) {
        invalidateFuelPhotoLaunchConfirmation();
    }

    clearTimeout(
        window.fuelPhotoStructuralDuplicateTimer
    );

    window.fuelPhotoStructuralDuplicateTimer =
        setTimeout(
            () => {
                if (
                    typeof refreshFuelPhotoDuplicatePreflight
                    === 'function'
                ) {
                    refreshFuelPhotoDuplicatePreflight();
                }
            },
            250
        );
}


function fuelPhotoAutoMergeSplitRows() {

    if (fuelPhotoStructuralToolsBusy) {
        return 0;
    }

    const tbody =
        document.getElementById(
            'fuelPhotoAiRows'
        );

    if (!tbody) {
        return 0;
    }

    fuelPhotoStructuralToolsBusy =
        true;

    let merged =
        0;

    try {

        /*
         * Recomeça após cada fusão porque
         * a coleção de TRs muda.
         */
        let changed =
            true;

        while (changed) {

            changed =
                false;

            const rows =
                [
                    ...tbody.querySelectorAll(
                        ':scope > tr'
                    )
                ];

            for (
                let index = 0;
                index < rows.length - 1;
                index++
            ) {
                const orphan =
                    rows[index];

                const target =
                    rows[index + 1];

                if (
                    !fuelPhotoIsMeterOnlyOrphanRow(
                        orphan
                    )
                    || !fuelPhotoCanReceiveOrphanKm(
                        target
                    )
                ) {
                    continue;
                }

                const vehicle =
                    fuelPhotoVehicleForRow(
                        target
                    );

                const previous =
                    fuelPhotoMeterNumber(
                        fuelPhotoRowFieldValue(
                            orphan,
                            'previous_km'
                        )
                    );

                const next =
                    fuelPhotoMeterNumber(
                        fuelPhotoRowFieldValue(
                            orphan,
                            'new_km'
                        )
                    );

                const chm =
                    fuelPhotoMeterNumber(
                        vehicle?.current_km
                    );

                /*
                 * Critério forte:
                 * Último KM da linha órfã =
                 * KM atual cadastrado no CHM
                 * para o veículo da linha seguinte.
                 */
                if (
                    previous === null
                    || chm === null
                    || previous !== chm
                ) {
                    continue;
                }

                const targetPrevious =
                    target.querySelector(
                        '[data-field="previous_km"]'
                    );

                const targetNext =
                    target.querySelector(
                        '[data-field="new_km"]'
                    );

                if (
                    !targetPrevious
                    || !targetNext
                ) {
                    continue;
                }

                targetPrevious.value =
                    String(previous);

                targetNext.value =
                    String(next);

                target.dataset.previousKmConfirmed =
                    '0';

                target.dataset.newKmConfirmed =
                    '0';

                target.dataset.rowConfirmed =
                    '0';

                target.dataset.autoMerged =
                    '1';

                const sourceLine =
                    orphan.querySelector(
                        '.fuel-photo-ai-line'
                    )?.textContent?.trim();

                fuelPhotoAppendMergeNote(
                    target,
                    sourceLine
                );

                orphan.remove();

                if (
                    typeof recalculateFuelPhotoRow
                    === 'function'
                ) {
                    recalculateFuelPhotoRow(
                        target
                    );
                }

                merged++;
                changed = true;

                break;
            }
        }

    } finally {
        fuelPhotoStructuralToolsBusy =
            false;
    }

    if (merged > 0) {

        fuelPhotoAfterStructureChange();

        if (
            typeof showFuelPhotoFeedback
            === 'function'
        ) {
            showFuelPhotoFeedback(
                merged === 1
                    ? '1 linha dividida pelo OCR foi consolidada automaticamente.'
                    : `${merged} linhas divididas pelo OCR foram consolidadas automaticamente.`,
                'success'
            );
        }
    }

    return merged;
}


function fuelPhotoShiftVisibleLines(
    startRow,
    delta
) {
    let reached =
        false;

    document
        .querySelectorAll(
            '#fuelPhotoAiRows > tr'
        )
        .forEach(
            tr => {
                if (tr === startRow) {
                    reached = true;
                }

                if (!reached) {
                    return;
                }

                const cell =
                    tr.querySelector(
                        '.fuel-photo-ai-line'
                    );

                const number =
                    Number(
                        cell?.textContent
                    );

                if (
                    cell
                    && Number.isFinite(
                        number
                    )
                ) {
                    cell.textContent =
                        String(
                            Math.max(
                                1,
                                number + delta
                            )
                        );
                }
            }
        );
}


function fuelPhotoResetInsertedRow(
    tr
) {
    /*
     * cloneNode(true) também copia os data-* da linha original.
     * A linha nova precisa ser considerada estruturalmente nova,
     * para receber novamente + acima/abaixo e excluir.
     */
    delete tr.dataset.structureActions;
    delete tr.dataset.autoMerged;

    tr.classList.remove(
        'has-warning',
        'has-critical'
    );

    tr.dataset.rowConfirmed =
        '0';

    tr.dataset.vehicleConfirmed =
        '0';

    tr.dataset.previousKmConfirmed =
        '0';

    tr.dataset.newKmConfirmed =
        '0';

    tr.dataset.confirmingRow =
        '0';

    tr.dataset.manualRow =
        '1';

    [
        'estimated_time',
        'vehicle_id',
        'plate_read',
        'liters',
        'previous_km',
        'new_km',
        'arla'
    ].forEach(
        field => {
            const element =
                tr.querySelector(
                    `[data-field="${field}"]`
                );

            if (!element) {
                return;
            }

            element.value =
                '';

            element.classList.remove(
                'is-ok',
                'is-warning',
                'is-critical'
            );

            if (
                field === 'plate_read'
            ) {
                delete element.dataset
                    .ocrPlate;
            }
        }
    );

    tr.querySelectorAll(
        '.is-confirmed'
    ).forEach(
        element =>
            element.classList.remove(
                'is-confirmed'
            )
    );

    tr.querySelectorAll(
        '.fuel-photo-ai-km-reference,'
        + '.fuel-photo-ai-merge-note,'
        + '.fuel-photo-ai-duplicate-row-warning'
    ).forEach(
        element =>
            element.remove()
    );

    const distance =
        tr.querySelector(
            '.fuel-photo-ai-distance'
        );

    if (distance) {
        distance.innerHTML =
            '<strong class="fuel-photo-ai-distance-sheet">'
            + 'Folha: —'
            + '</strong>'
            + '<span class="fuel-photo-ai-distance-db">'
            + 'CHM: —'
            + '</span>'
            + '<small>Histórico insuficiente</small>';
    }

    const statusCell =
        tr.querySelector(
            '.fuel-photo-ai-status-cell'
        );

    if (statusCell) {
        statusCell.innerHTML =
            '<strong>⚠ Revisar</strong>'
            + '<small class="fuel-photo-ai-manual-row-note">'
            + 'Linha inserida manualmente.'
            + '</small>'
            + '<button'
            + ' type="button"'
            + ' class="fuel-photo-ai-row-confirm-btn"'
            + ' onclick="confirmFuelPhotoRow(this)"'
            + ' title="Confirmar todos os dados desta linha"'
            + '>'
            + '<i class="bi bi-check-lg"></i>'
            + '<span>Confirmar linha</span>'
            + '</button>';
    }
}


function fuelPhotoInsertReviewRow(
    button,
    position
) {
    const current =
        button.closest('tr');

    const tbody =
        current?.parentElement;

    if (
        !current
        || !tbody
    ) {
        return;
    }

    const clone =
        current.cloneNode(
            true
        );

    /*
     * Remove ações estruturais herdadas.
     * Elas serão recriadas após a inserção.
     */
    clone
        .querySelectorAll(
            '.fuel-photo-ai-structure-actions,'
            + '.fuel-photo-ai-row-delete-btn'
        )
        .forEach(
            element =>
                element.remove()
        );

    const currentLineCell =
        current.querySelector(
            '.fuel-photo-ai-line'
        );

    const currentLine =
        Number(
            currentLineCell?.textContent
        );

    if (
        position === 'before'
    ) {
        fuelPhotoShiftVisibleLines(
            current,
            1
        );

        tbody.insertBefore(
            clone,
            current
        );

        const line =
            clone.querySelector(
                '.fuel-photo-ai-line'
            );

        if (
            line
            && Number.isFinite(
                currentLine
            )
        ) {
            line.textContent =
                String(currentLine);
        }

    } else {

        const next =
            current.nextElementSibling;

        if (next) {
            fuelPhotoShiftVisibleLines(
                next,
                1
            );
        }

        tbody.insertBefore(
            clone,
            next
        );

        const line =
            clone.querySelector(
                '.fuel-photo-ai-line'
            );

        if (
            line
            && Number.isFinite(
                currentLine
            )
        ) {
            line.textContent =
                String(
                    currentLine + 1
                );
        }
    }

    fuelPhotoResetInsertedRow(
        clone
    );

    /*
     * O clone deve receber seus próprios controles:
     * + acima, + abaixo e excluir.
     */
    delete clone.dataset.structureActions;

    fuelPhotoEnhanceReviewRow(
        clone
    );

    fuelPhotoAfterStructureChange();

    clone.querySelector(
        '[data-field="vehicle_id"]'
    )?.focus();
}


function fuelPhotoDeleteReviewRow(
    button
) {
    const tr =
        button.closest('tr');

    if (!tr) {
        return;
    }

    const line =
        tr.querySelector(
            '.fuel-photo-ai-line'
        )?.textContent?.trim();

    if (
        !window.confirm(
            `Excluir a linha ${line || ''} desta revisão?`
        )
    ) {
        return;
    }

    const next =
        tr.nextElementSibling;

    tr.remove();

    if (next) {
        fuelPhotoShiftVisibleLines(
            next,
            -1
        );
    }

    fuelPhotoAfterStructureChange();
}


function fuelPhotoEnhanceReviewRow(
    tr
) {
    if (
        !tr
        || tr.dataset.structureActions
            === '1'
    ) {
        return;
    }

    const line =
        tr.querySelector(
            '.fuel-photo-ai-line'
        );

    const lineCell =
        line?.closest('td');

    const statusCell =
        tr.querySelector(
            '.fuel-photo-ai-status-cell'
        );

    if (
        !lineCell
        || !statusCell
    ) {
        return;
    }

    tr.dataset.structureActions =
        '1';

    lineCell.classList.add(
        'fuel-photo-ai-line-cell'
    );

    const actions =
        document.createElement(
            'div'
        );

    actions.className =
        'fuel-photo-ai-structure-actions';

    actions.innerHTML =
        `
            <button
                type="button"
                class="fuel-photo-ai-structure-btn"
                onclick="fuelPhotoInsertReviewRow(this, 'before')"
                title="Inserir linha acima"
                aria-label="Inserir linha acima"
            >
                <i class="bi bi-plus-lg"></i>
            </button>

            <button
                type="button"
                class="fuel-photo-ai-structure-btn"
                onclick="fuelPhotoInsertReviewRow(this, 'after')"
                title="Inserir linha abaixo"
                aria-label="Inserir linha abaixo"
            >
                <i class="bi bi-plus-lg"></i>
            </button>
        `;

    lineCell.appendChild(
        actions
    );

    if (
        !statusCell.querySelector(
            '.fuel-photo-ai-row-delete-btn'
        )
    ) {
        const remove =
            document.createElement(
                'button'
            );

        remove.type =
            'button';

        remove.className =
            'fuel-photo-ai-row-delete-btn';

        remove.title =
            'Excluir esta linha';

        remove.setAttribute(
            'aria-label',
            'Excluir esta linha'
        );

        remove.onclick =
            function () {
                fuelPhotoDeleteReviewRow(
                    this
                );
            };

        remove.innerHTML =
            '<i class="bi bi-trash3"></i>';

        statusCell.appendChild(
            remove
        );
    }
}


function fuelPhotoEnhanceReviewRows() {
    document
        .querySelectorAll(
            '#fuelPhotoAiRows > tr'
        )
        .forEach(
            tr => {
                fuelPhotoEnhanceReviewRow(
                    tr
                );

                syncFuelPhotoRowMeterControl(
                    tr
                );
            }
        );
}


/*
 * Quando o operador troca manualmente o veículo,
 * a regra KM/Horas deve acompanhar o cadastro
 * do veículo atualmente selecionado.
 */
if (
    !window.fuelPhotoMeterControlListenerInstalled
) {
    window.fuelPhotoMeterControlListenerInstalled =
        true;

    document.addEventListener(
        'change',
        function (event) {
            if (
                !event.target.matches(
                    '#fuelPhotoAiRows [data-field="vehicle_id"]'
                )
            ) {
                return;
            }

            const tr =
                event.target.closest(
                    'tr'
                );

            /*
             * Outras rotinas do sistema também
             * processam o change. Rodamos depois
             * delas para corrigir a apresentação
             * final do medidor.
             */
            /*
             * Primeiro deixa as rotinas de associação do
             * veículo atualizarem placa, confiança e demais
             * referências. Depois recalcula o medidor.
             */
            setTimeout(
                () => {
                    syncFuelPhotoRowMeterControl(
                        tr
                    );
                },
                30
            );

            /*
             * Segunda sincronização defensiva para linhas
             * que nasceram sem veículo e são reconstruídas
             * pela rotina de associação manual.
             */
            setTimeout(
                () => {
                    syncFuelPhotoRowMeterControl(
                        tr
                    );
                },
                120
            );
        }
    );
}


function setupFuelPhotoStructuralTools() {
    const tbody =
        document.getElementById(
            'fuelPhotoAiRows'
        );

    if (
        !tbody
        || tbody.dataset.structuralObserver
            === '1'
    ) {
        return;
    }

    tbody.dataset.structuralObserver =
        '1';

    let timer =
        null;

    const run =
        () => {
            clearTimeout(
                timer
            );

            timer =
                setTimeout(
                    () => {
                        if (
                            fuelPhotoStructuralToolsBusy
                        ) {
                            return;
                        }

                        fuelPhotoAutoMergeSplitRows();
                        fuelPhotoEnhanceReviewRows();
                    },
                    20
                );
        };

    const observer =
        new MutationObserver(
            run
        );

    observer.observe(
        tbody,
        {
            childList: true
        }
    );

    fuelPhotoEnhanceReviewRows();
    fuelPhotoAutoMergeSplitRows();
}


if (
    document.readyState
    === 'loading'
) {
    document.addEventListener(
        'DOMContentLoaded',
        setupFuelPhotoStructuralTools
    );
} else {
    setupFuelPhotoStructuralTools();
}


function fuelPhotoHumanReviewStatus() {
    const rows =
        [
            ...document.querySelectorAll(
                '#fuelPhotoAiRows tr'
            )
        ];

    return {
        total:
            rows.length,

        confirmed:
            rows.filter(
                tr =>
                    tr.dataset.rowConfirmed
                    === '1'
            ).length,

        rows:
            rows.map(
                (tr, index) => ({
                    tr,

                    line:
                        Number(
                            tr.querySelector(
                                '.fuel-photo-ai-line'
                            )?.textContent
                            || index + 1
                        ),

                    confirmed:
                        tr.dataset.rowConfirmed
                        === '1'
                })
            )
    };
}


function invalidateFuelPhotoRowConfirmation(
    tr
) {
    if (!tr) {
        return;
    }

    tr.dataset.rowConfirmed =
        '0';

    const button =
        tr.querySelector(
            '.fuel-photo-ai-row-confirm-btn'
        );

    if (button) {
        button.classList.remove(
            'is-confirmed'
        );

        button.innerHTML =
            '<i class="bi bi-check-lg"></i>'
            + '<span>Confirmar linha</span>';

        button.title =
            'Confirmar todos os dados desta linha';
    }

    refreshFuelPhotoHumanReview();
    invalidateFuelPhotoLaunchConfirmation();
}


function confirmFuelPhotoRow(
    button
) {
    const tr =
        button.closest('tr');

    if (!tr) {
        return;
    }

    tr.dataset.confirmingRow =
        '1';

    const line =
        Number(
            tr.querySelector(
                '.fuel-photo-ai-line'
            )?.textContent
            || 0
        );

    const vehicle =
        tr.querySelector(
            '[data-field="vehicle_id"]'
        );

    const plate =
        tr.querySelector(
            '[data-field="plate_read"]'
        );

    const liters =
        tr.querySelector(
            '[data-field="liters"]'
        );

    const previousKm =
        tr.querySelector(
            '[data-field="previous_km"]'
        );

    const newKm =
        tr.querySelector(
            '[data-field="new_km"]'
        );

    const arla =
        tr.querySelector(
            '[data-field="arla"]'
        );

    const problems = [];

    if (!vehicle?.value) {
        problems.push(
            'veículo'
        );
    }

    if (!plate?.value?.trim()) {
        problems.push(
            'placa'
        );
    }

    const litersValue =
        Number(
            liters?.value
        );

    if (
        !Number.isFinite(litersValue)
        || litersValue <= 0
    ) {
        problems.push(
            'litros'
        );
    }

    const previousKmValue =
        Number(
            previousKm?.value
        );

    if (
        previousKm?.value === ''
        || !Number.isFinite(
            previousKmValue
        )
        || previousKmValue < 0
    ) {
        problems.push(
            'Último KM'
        );
    }

    const newKmValue =
        Number(
            newKm?.value
        );

    if (
        newKm?.value === ''
        || !Number.isFinite(
            newKmValue
        )
        || newKmValue < 0
    ) {
        problems.push(
            'Novo KM'
        );
    }

    if (
        arla?.value !== ''
        && arla?.value != null
    ) {
        const arlaValue =
            Number(
                arla.value
            );

        if (
            !Number.isFinite(
                arlaValue
            )
            || arlaValue < 0
        ) {
            problems.push(
                'ARLA'
            );
        }
    }

    if (problems.length) {
        delete tr.dataset.confirmingRow;

        showFuelPhotoFeedback(
            `Linha ${line}: revise ${problems.join(', ')} antes de confirmar.`,
            'error'
        );

        return;
    }

    /*
     * Confirma internamente o veículo/placa.
     * Mantemos a rotina existente para usar a placa
     * canônica do cadastro do CHM.
     */
    const plateButton =
        tr.querySelector(
            '.fuel-photo-ai-plate-confirm .fuel-photo-ai-confirm-btn'
        );

    if (plateButton) {
        confirmFuelPhotoPlate(
            plateButton
        );
    }

    /*
     * Confirma Último KM.
     */
    const previousKmButton =
        tr.querySelector(
            '.fuel-photo-ai-km-confirm-btn'
        );

    if (previousKmButton) {
        confirmFuelPhotoPreviousKm(
            previousKmButton
        );
    }

    /*
     * Confirma conscientemente o Novo KM.
     * Isso é necessário para que um salto suspeito
     * possa ser aceito pelo VehicleReadingService.
     */
    const newKmButton =
        tr.querySelector(
            '.fuel-photo-ai-new-km-confirm-btn'
        );

    if (newKmButton) {
        confirmFuelPhotoNewKm(
            newKmButton
        );
    }

    /*
     * Depois das rotinas internas, a linha inteira
     * passa a ser considerada revisada pelo operador.
     */
    tr.dataset.rowConfirmed =
        '1';

    delete tr.dataset.confirmingRow;

    button.classList.add(
        'is-confirmed'
    );

    button.innerHTML =
        '<i class="bi bi-check-circle-fill"></i>'
        + '<span>Linha confirmada</span>';

    button.title =
        'Todos os dados desta linha foram conferidos';

    refreshFuelPhotoHumanReview();

    showFuelPhotoFeedback(
        `Linha ${line} confirmada pelo operador.`,
        'success'
    );
}


function ensureFuelPhotoRowConfirmButtons() {
    const rows =
        [
            ...document.querySelectorAll(
                '#fuelPhotoAiRows tr'
            )
        ];

    rows.forEach(
        tr => {
            if (
                tr.querySelector(
                    '.fuel-photo-ai-row-confirm-btn'
                )
            ) {
                return;
            }

            const statusCell =
                tr.querySelector(
                    '.fuel-photo-ai-status-cell'
                )
                || tr.lastElementChild;

            if (!statusCell) {
                return;
            }

            const button =
                document.createElement(
                    'button'
                );

            button.type =
                'button';

            button.className =
                'fuel-photo-ai-row-confirm-btn';

            button.title =
                'Confirmar todos os dados desta linha';

            button.innerHTML =
                '<i class="bi bi-check-lg"></i>'
                + '<span>Confirmar linha</span>';

            button.addEventListener(
                'click',
                () =>
                    confirmFuelPhotoRow(
                        button
                    )
            );

            statusCell.appendChild(
                button
            );
        }
    );
}


function refreshFuelPhotoHumanReview() {
    ensureFuelPhotoRowConfirmButtons();

    const review =
        fuelPhotoHumanReviewStatus();

    const tbody =
        document.getElementById(
            'fuelPhotoAiRows'
        );

    const table =
        tbody?.closest(
            'table'
        );

    if (!table) {
        return;
    }

    let box =
        document.getElementById(
            'fuelPhotoAiHumanReview'
        );

    if (!box) {
        box =
            document.createElement(
                'div'
            );

        box.id =
            'fuelPhotoAiHumanReview';

        box.className =
            'fuel-photo-ai-human-review';

        table.parentElement
            ?.insertBefore(
                box,
                table
            );
    }

    const complete =
        review.total > 0
        && review.confirmed
            === review.total;

    const percent =
        review.total
            ? Math.round(
                review.confirmed
                / review.total
                * 100
            )
            : 0;

    box.classList.toggle(
        'is-complete',
        complete
    );

    box.innerHTML = `
        <div class="fuel-photo-ai-human-review-head">

            <div>
                <span>REVISÃO HUMANA</span>

                <strong>
                    ${review.confirmed}
                    de
                    ${review.total}
                    linhas confirmadas
                </strong>
            </div>

            <div class="fuel-photo-ai-human-review-count">
                ${
                    complete
                        ? '✓ Revisão concluída'
                        : percent + '%'
                }
            </div>

        </div>

        <div class="fuel-photo-ai-human-review-track">
            <span
                style="width: ${percent}%"
            ></span>
        </div>

        <small>
            Revise todos os dados de cada abastecimento
            e use “Confirmar linha” antes de validar a ficha.
        </small>
    `;
}


function buildFuelPhotoLaunchPayload() {
    const operationDate =
        document.getElementById(
            'fuelPhotoAiOperationDate'
        )?.value;

    const fuelTankId =
        document.getElementById(
            'fuelPhotoAiFuelTank'
        )?.value;

    const arlaTankId =
        document.getElementById(
            'fuelPhotoAiArlaTank'
        )?.value;

    const rows =
        [
            ...document.querySelectorAll(
                '#fuelPhotoAiRows tr'
            )
        ];

    return {
        confirm_duplicates:
            Boolean(
                document.getElementById(
                    'fuelPhotoAiDuplicateConfirm'
                )?.checked
            ),

        fuel_tank_id:
            fuelTankId
                ? Number(fuelTankId)
                : null,

        arla_tank_id:
            arlaTankId
                ? Number(arlaTankId)
                : null,

        rows:
            rows.map(
                (tr, index) => {
                    const time =
                        tr.querySelector(
                            '[data-field="estimated_time"]'
                        )?.value;

                    const vehicle =
                        tr.querySelector(
                            '[data-field="vehicle_id"]'
                        )?.value;

                    const newKm =
                        tr.querySelector(
                            '[data-field="new_km"]'
                        )?.value;

                    const newKmConfirmButton =
                        tr.querySelector(
                            '.fuel-photo-ai-new-km-confirm-btn'
                        );

                    const newKmConfirmed =
                        Boolean(
                            newKmConfirmButton
                            && newKmConfirmButton.classList.contains(
                                'is-confirmed'
                            )
                            && newKm !== ''
                            && newKm != null
                            && Number(
                                newKmConfirmButton.dataset.confirmedValue
                            ) === Number(newKm)
                        );

                    const liters =
                        tr.querySelector(
                            '[data-field="liters"]'
                        )?.value;

                    const arla =
                        tr.querySelector(
                            '[data-field="arla"]'
                        )?.value;

                    const lineText =
                        tr.querySelector(
                            '.fuel-photo-ai-line'
                        )?.textContent;

                    return {
                        line:
                            Number(
                                String(
                                    lineText
                                    || index + 1
                                ).trim()
                            ),

                        vehicle_id:
                            vehicle
                                ? Number(vehicle)
                                : null,

                        filled_at:
                            (
                                operationDate
                                && time
                            )
                                ? `${operationDate} ${time}:00`
                                : null,

                        vehicle_km:
                            newKm === ''
                            || newKm == null
                                ? null
                                : Number(newKm),

                        liters:
                            liters === ''
                            || liters == null
                                ? null
                                : Number(liters),

                        arla_liters:
                            arla === ''
                            || arla == null
                                ? 0
                                : Number(arla),

                        /*
                         * O ✓ atual confirma o KM anterior
                         * impresso na folha, não o Novo KM.
                         *
                         * Portanto não usamos essa confirmação
                         * para ignorar validações do hodômetro.
                         */
                        km_reading_confirmed:
                            newKmConfirmed
                    };
                }
            )
    };
}


async function refreshFuelPhotoDuplicatePreflight() {
    const rows =
        [
            ...document.querySelectorAll(
                '#fuelPhotoAiRows tr'
            )
        ];

    if (!rows.length) {
        return;
    }

    const operationDate =
        document.getElementById(
            'fuelPhotoAiOperationDate'
        )?.value;

    const fuelTankId =
        document.getElementById(
            'fuelPhotoAiFuelTank'
        )?.value;

    if (
        !operationDate
        || !fuelTankId
    ) {
        return;
    }

    const arlaTankId =
        document.getElementById(
            'fuelPhotoAiArlaTank'
        )?.value;

    const payload = {
        fuel_tank_id:
            Number(fuelTankId),

        arla_tank_id:
            arlaTankId
                ? Number(arlaTankId)
                : null,

        rows:
            rows
                .map(
                    (tr, index) => {
                        const vehicle =
                            tr.querySelector(
                                '[data-field="vehicle_id"]'
                            )?.value;

                        const liters =
                            tr.querySelector(
                                '[data-field="liters"]'
                            )?.value;

                        const arla =
                            tr.querySelector(
                                '[data-field="arla"]'
                            )?.value;

                        const line =
                            Number(
                                tr.querySelector(
                                    '.fuel-photo-ai-line'
                                )?.textContent
                                || index + 1
                            );

                        if (
                            !vehicle
                            || liters === ''
                            || !Number.isFinite(
                                Number(liters)
                            )
                            || Number(liters) <= 0
                        ) {
                            return null;
                        }

                        return {
                            line,
                            vehicle_id:
                                Number(vehicle),

                            /*
                             * O backend usa apenas a data
                             * na busca de duplicidade.
                             */
                            filled_at:
                                `${operationDate} 00:00:00`,

                            liters:
                                Number(liters),

                            arla_liters:
                                arla === ''
                                || arla == null
                                    ? 0
                                    : Number(arla)
                        };
                    }
                )
                .filter(Boolean)
    };

    if (!payload.rows.length) {
        return;
    }

    try {
        const duplicates =
            await checkFuelPhotoDuplicates(
                payload
            );

        renderFuelPhotoDuplicates(
            duplicates
        );

    } catch (error) {
        /*
         * A pré-conferência não deve impedir
         * o restante da revisão da ficha.
         */
        console.warn(
            'Não foi possível pré-conferir duplicidades.',
            error
        );
    }
}


async function checkFuelPhotoDuplicates(
    payload
) {
    const csrf =
        document.querySelector(
            'meta[name="csrf-token"]'
        )?.content;

    const response =
        await fetch(
            fuelPhotoDuplicateCheckUrl,
            {
                method: 'POST',

                headers: {
                    'Accept':
                        'application/json',

                    'Content-Type':
                        'application/json',

                    'X-CSRF-TOKEN':
                        csrf || ''
                },

                body:
                    JSON.stringify(
                        payload
                    )
            }
        );

    const data =
        await response.json();

    if (!response.ok) {
        throw new Error(
            data.message
            || 'Não foi possível conferir duplicidades.'
        );
    }

    return data.duplicates || [];
}


function syncFuelPhotoDuplicateRowWarnings(
    duplicates
) {
    const rows =
        [
            ...document.querySelectorAll(
                '#fuelPhotoAiRows tr'
            )
        ];

    /*
     * Primeiro remove somente os avisos de
     * duplicidade inseridos por esta rotina.
     */
    rows.forEach(
        tr => {
            tr.querySelectorAll(
                '.fuel-photo-ai-duplicate-row-warning'
            ).forEach(
                element =>
                    element.remove()
            );

            const title =
                tr.querySelector(
                    '.fuel-photo-ai-status-cell strong'
                )
                || tr.lastElementChild
                    ?.querySelector(
                        'strong'
                    );

            if (
                title
                && title.dataset.duplicateStatus
                    === '1'
            ) {
                title.textContent =
                    '✓ OK';

                delete title.dataset
                    .duplicateStatus;
            }
        }
    );

    /*
     * Agrupa porque uma mesma linha pode,
     * em tese, possuir duplicidade de Diesel
     * e também de ARLA.
     */
    const grouped = {};

    (duplicates || []).forEach(
        duplicate => {
            const line =
                Number(
                    duplicate.line
                );

            if (!grouped[line]) {
                grouped[line] = [];
            }

            grouped[line].push(
                duplicate
            );
        }
    );

    Object.entries(
        grouped
    ).forEach(
        ([line, lineDuplicates]) => {
            const tr =
                rows.find(
                    row =>
                        Number(
                            row.querySelector(
                                '.fuel-photo-ai-line'
                            )?.textContent
                        ) === Number(line)
                );

            if (!tr) {
                return;
            }

            const statusCell =
                tr.querySelector(
                    '.fuel-photo-ai-status-cell'
                )
                || tr.lastElementChild;

            if (!statusCell) {
                return;
            }

            const title =
                statusCell.querySelector(
                    'strong'
                );

            if (title) {
                title.textContent =
                    '⚠ Possível duplicidade';

                title.dataset.duplicateStatus =
                    '1';
            }

            lineDuplicates.forEach(
                duplicate => {
                    const warning =
                        document.createElement(
                            'div'
                        );

                    warning.className =
                        'fuel-photo-ai-duplicate-row-warning';

                    const type =
                        duplicate.type === 'arla'
                            ? 'ARLA'
                            : 'Combustível';

                    const km =
                        duplicate.vehicle_km != null
                            ? ` · KM ${Number(
                                duplicate.vehicle_km
                            ).toLocaleString(
                                'pt-BR'
                            )}`
                            : '';

                    warning.innerHTML = `
                        <i class="bi bi-exclamation-triangle"></i>

                        <span>
                            ${type}: já existe neste dia
                            um lançamento de
                            <strong>
                                ${fuelPhotoFormatLiters(
                                    duplicate.quantity_liters
                                )}
                            </strong>
                            ${km}.
                        </span>
                    `;

                    statusCell.insertBefore(
                        warning,
                        statusCell.querySelector(
                            '.fuel-photo-ai-row-confirm-btn'
                        )
                        || null
                    );
                }
            );
        }
    );
}


function renderFuelPhotoDuplicates(
    duplicates
) {
    fuelPhotoDetectedDuplicates =
        duplicates || [];


    syncFuelPhotoDuplicateRowWarnings(
        fuelPhotoDetectedDuplicates
    );

    const box =
        document.getElementById(
            'fuelPhotoAiDuplicateWarning'
        );

    const launchButton =
        document.getElementById(
            'fuelPhotoAiLaunchButton'
        );

    if (!box || !launchButton) {
        return;
    }

    if (!fuelPhotoDetectedDuplicates.length) {
        box.hidden = true;
        box.innerHTML = '';
        launchButton.disabled = false;
        return;
    }

    const items =
        fuelPhotoDetectedDuplicates
            .map(
                duplicate => {
                    const type =
                        duplicate.type === 'arla'
                            ? 'ARLA'
                            : 'Combustível';

                    return `
                        <li>
                            <strong>
                                Linha ${duplicate.line} · ${type}
                            </strong>

                            <span>
                                Já existe o lançamento
                                #${duplicate.filling_id}
                                em ${escapeFuelPhoto(
                                    duplicate.filled_at
                                )}
                                com ${fuelPhotoFormatLiters(
                                    duplicate.quantity_liters
                                )}
                                ${
                                    duplicate.vehicle_km != null
                                        ? ` · KM ${Number(
                                            duplicate.vehicle_km
                                        ).toLocaleString('pt-BR')}`
                                        : ''
                                }.
                            </span>
                        </li>
                    `;
                }
            )
            .join('');

    box.hidden = false;

    box.innerHTML = `
        <div class="fuel-photo-ai-duplicate-title">
            <i class="bi bi-exclamation-triangle"></i>

            <div>
                <strong>
                    Possível duplicidade
                </strong>

                <span>
                    Mesmo veículo, mesma data e mesma quantidade.
                    Confira antes de continuar.
                </span>
            </div>
        </div>

        <ul>
            ${items}
        </ul>

        <label class="fuel-photo-ai-duplicate-confirm">
            <input
                type="checkbox"
                id="fuelPhotoAiDuplicateConfirm"
                onchange="syncFuelPhotoDuplicateConfirmation()"
            >

            <span>
                Estou ciente e desejo registrar
                mesmo assim.
            </span>
        </label>
    `;

    launchButton.disabled = true;
}


function syncFuelPhotoDuplicateConfirmation() {
    const checkbox =
        document.getElementById(
            'fuelPhotoAiDuplicateConfirm'
        );

    const button =
        document.getElementById(
            'fuelPhotoAiLaunchButton'
        );

    if (!button) {
        return;
    }

    button.disabled =
        fuelPhotoDetectedDuplicates.length > 0
        && !checkbox?.checked;
}


async function validateFuelPhotoReading() {
    const payload =
        buildFuelPhotoLaunchPayload();

    const rows =
        [
            ...document.querySelectorAll(
                '#fuelPhotoAiRows tr'
            )
        ];

    if (!rows.length) {
        showFuelPhotoFeedback(
            'Não há linhas para validar.',
            'error'
        );

        return;
    }

    const problems = [];

    /*
     * A IA apenas sugere os dados.
     * Cada linha precisa de confirmação humana explícita.
     */
    const humanReview =
        fuelPhotoHumanReviewStatus();

    humanReview.rows.forEach(
        row => {
            if (!row.confirmed) {
                problems.push(
                    `Linha ${row.line}: revise os dados e confirme a linha.`
                );
            }
        }
    );

    const operationDate =
        document.getElementById(
            'fuelPhotoAiOperationDate'
        )?.value;

    const startTime =
        document.getElementById(
            'fuelPhotoAiStartTime'
        )?.value;

    const endTime =
        document.getElementById(
            'fuelPhotoAiEndTime'
        )?.value;

    if (!operationDate) {
        problems.push(
            'Informe a data dos abastecimentos.'
        );
    }

    if (!startTime) {
        problems.push(
            'Informe a hora de início.'
        );
    }

    if (!endTime) {
        problems.push(
            'Informe a hora de fim.'
        );
    }

    const fuelTank =
        fuelPhotoTanks.find(
            tank =>
                Number(tank.id)
                === Number(
                    payload.fuel_tank_id
                )
        );

    if (!fuelTank) {
        problems.push(
            'Selecione o tanque de combustível.'
        );
    }

    const totals =
        fuelPhotoCurrentTotals();

    if (
        fuelTank
        && totals.fuel_liters
            > Number(
                fuelTank.balance_liters
                || 0
            )
    ) {
        problems.push(
            'Saldo insuficiente no tanque de combustível.'
        );
    }

    const hasArla =
        totals.arla_liters > 0.0001;

    let arlaTank = null;

    if (hasArla) {
        arlaTank =
            fuelPhotoTanks.find(
                tank =>
                    Number(tank.id)
                    === Number(
                        payload.arla_tank_id
                    )
            );

        if (!arlaTank) {
            problems.push(
                'Selecione o tanque de ARLA.'
            );

        } else if (
            totals.arla_liters
            > Number(
                arlaTank.balance_liters
                || 0
            )
        ) {
            problems.push(
                'Saldo insuficiente no tanque de ARLA.'
            );
        }
    }

    payload.rows.forEach(
        (row, index) => {
            if (!row.vehicle_id) {
                problems.push(
                    `Linha ${index + 1}: selecione o veículo.`
                );
            }

            if (
                !Number.isFinite(
                    row.liters
                )
                || row.liters <= 0
            ) {
                problems.push(
                    `Linha ${index + 1}: informe os litros.`
                );
            }

            if (!row.filled_at) {
                problems.push(
                    `Linha ${index + 1}: informe o horário.`
                );
            }

            if (
                row.vehicle_km !== null
                && (
                    !Number.isFinite(
                        row.vehicle_km
                    )
                    || row.vehicle_km < 0
                )
            ) {
                problems.push(
                    `Linha ${index + 1}: Novo KM inválido.`
                );
            }

            if (
                !Number.isFinite(
                    row.arla_liters
                )
                || row.arla_liters < 0
            ) {
                problems.push(
                    `Linha ${index + 1}: ARLA inválido.`
                );
            }
        }
    );

    if (problems.length) {
        cancelFuelPhotoLaunchConfirmation();

        showFuelPhotoFeedback(
            problems.join('<br>'),
            'error',
            true
        );

        return;
    }

    try {
        const duplicates =
            await checkFuelPhotoDuplicates(
                payload
            );

        renderFuelPhotoDuplicates(
            duplicates
        );

    } catch (error) {
        showFuelPhotoFeedback(
            error.message
            || 'Não foi possível conferir duplicidades.',
            'error'
        );

        return;
    }


    const confirmation =
        document.getElementById(
            'fuelPhotoAiLaunchConfirmation'
        );

    const summary =
        document.getElementById(
            'fuelPhotoAiLaunchSummary'
        );

    const buttonText =
        document.getElementById(
            'fuelPhotoAiLaunchButtonText'
        );

    const firstTime =
        payload.rows[0]?.filled_at
            ?.slice(11, 16)
        || '—';

    const lastTime =
        payload.rows[
            payload.rows.length - 1
        ]?.filled_at
            ?.slice(11, 16)
        || '—';

    const fuelName =
        fuelTank
            ? fuelTank.name
                + (
                    fuelTank.product_name
                        ? ' · '
                            + fuelTank.product_name
                        : ''
                )
            : '—';

    const arlaBlock =
        hasArla
            ? `
                <div>
                    <span>ARLA</span>
                    <strong>
                        ${fuelPhotoFormatLiters(
                            totals.arla_liters
                        )}
                    </strong>
                    <small>
                        ${escapeFuelPhoto(
                            arlaTank?.name
                            || 'Tanque não informado'
                        )}
                    </small>
                </div>
            `
            : `
                <div>
                    <span>ARLA</span>
                    <strong>0,00 L</strong>
                    <small>
                        Nenhum ARLA nesta ficha
                    </small>
                </div>
            `;

    summary.innerHTML = `
        <div>
            <span>Abastecimentos</span>
            <strong>
                ${payload.rows.length}
            </strong>
            <small>
                linhas da ficha
            </small>
        </div>

        <div>
            <span>Combustível</span>
            <strong>
                ${fuelPhotoFormatLiters(
                    totals.fuel_liters
                )}
            </strong>
            <small>
                ${escapeFuelPhoto(
                    fuelName
                )}
            </small>
        </div>

        ${arlaBlock}

        <div>
            <span>Data</span>
            <strong>
                ${operationDate
                    .split('-')
                    .reverse()
                    .join('/')}
            </strong>
            <small>
                ${firstTime}
                → ${lastTime}
            </small>
        </div>
    `;

    if (buttonText) {
        buttonText.textContent =
            `Confirmar e lançar ${payload.rows.length} abastecimento`
            + (
                payload.rows.length === 1
                    ? ''
                    : 's'
            );
    }

    confirmation.hidden = false;

    showFuelPhotoFeedback(
        'Leitura validada. '
        + 'Revise o resumo final antes de confirmar o lançamento.',
        'success'
    );

    confirmation.scrollIntoView({
        behavior: 'smooth',
        block: 'nearest'
    });
}


function cancelFuelPhotoLaunchConfirmation() {
    const confirmation =
        document.getElementById(
            'fuelPhotoAiLaunchConfirmation'
        );

    if (confirmation) {
        confirmation.hidden = true;
    }
}


async function storeFuelPhotoImport() {
    const confirmation =
        document.getElementById(
            'fuelPhotoAiLaunchConfirmation'
        );

    const button =
        document.getElementById(
            'fuelPhotoAiLaunchButton'
        );

    if (
        !confirmation
        || confirmation.hidden
    ) {
        return;
    }

    const payload =
        buildFuelPhotoLaunchPayload();

    if (!payload.rows.length) {
        return;
    }

    button.disabled = true;

    const originalHtml =
        button.innerHTML;

    button.innerHTML =
        '<span class="spinner-border spinner-border-sm"></span>'
        + '<span>Lançando...</span>';

    try {
        const csrf =
            document.querySelector(
                'meta[name="csrf-token"]'
            )?.content;

        const formData =
            new FormData();

        formData.append(
            'payload',
            JSON.stringify(
                payload
            )
        );

        const sourceInput =
            document.getElementById(
                'fuelPhotoAiFile'
            );

        if (
            sourceInput?.files?.length
        ) {
            formData.append(
                'source_file',
                sourceInput.files[0]
            );
        }

        const response =
            await fetch(
                fuelPhotoStoreUrl,
                {
                    method: 'POST',

                    headers: {
                        'Accept':
                            'application/json',

                        /*
                         * Não definir Content-Type aqui.
                         * O navegador adiciona o boundary
                         * correto do multipart/form-data.
                         */
                        'X-CSRF-TOKEN':
                            csrf || ''
                    },

                    body:
                        formData
                }
            );

        const data =
            await response.json();

        if (
            !response.ok
            || !data.ok
        ) {
            throw new Error(
                data.message
                || 'Não foi possível lançar a ficha.'
            );
        }

        confirmation.hidden = true;

        const successMessage =
            data.message
            + (
                data.arla_fillings > 0
                    ? ` ARLA: ${data.arla_fillings} lançamento(s).`
                    : ''
            )
            + (
                data.archive_file_id
                    ? ' Ficha arquivada automaticamente.'
                    : ''
            )
            + (
                data.archive_warning
                    ? ' ' + data.archive_warning
                    : ''
            );

        showFuelPhotoFeedback(
            successMessage,
            'success'
        );

        if (data.archive_warning) {
            setTimeout(
                () => {
                    alert(
                        data.archive_warning
                    );
                },
                250
            );
        }

        /*
         * Evita clique duplo depois do sucesso.
         */
        const validateButton =
            document.querySelector(
                '[onclick="validateFuelPhotoReading()"]'
            );

        if (validateButton) {
            validateButton.disabled = true;

            validateButton.innerHTML =
                '<i class="bi bi-check2-circle"></i>'
                + ' Lançado no CHM';
        }

        button.disabled = true;

        /*
         * Atualiza a página depois de um pequeno intervalo
         * para refletir estoque e últimos abastecimentos.
         */
        setTimeout(
            () => {
                window.location.reload();
            },
            1600
        );

    } catch (error) {
        showFuelPhotoFeedback(
            error.message
            || 'Não foi possível lançar a ficha.',
            'error'
        );

        button.disabled = false;
        button.innerHTML =
            originalHtml;
    }
}


function showFuelPhotoFeedback(
    message,
    type = 'success',
    allowHtml = false
) {
    const box =
        document.getElementById(
            'fuelPhotoAiFeedback'
        );

    box.hidden = false;

    box.className =
        'fuel-photo-ai-feedback is-'
        + type;

    if (allowHtml) {
        box.innerHTML = message;
    } else {
        box.textContent = message;
    }
}


function fuelPhotoLiters(
    value,
    suffix = true
) {
    if (
        value === null
        || value === undefined
        || value === ''
    ) {
        return '—';
    }

    const text =
        Number(value).toLocaleString(
            'pt-BR',
            {
                minimumFractionDigits: 2,
                maximumFractionDigits: 3
            }
        );

    return suffix
        ? text + ' L'
        : text + ' L';
}


function escapeFuelPhoto(value) {
    const div =
        document.createElement('div');

    div.textContent =
        String(value ?? '');

    return div.innerHTML;
}


document.addEventListener(
    'DOMContentLoaded',
    () => {

        const fuelPhotoRowConfirmationGuard =
            event => {
                const field =
                    event.target.closest(
                        '#fuelPhotoAiRows [data-field]'
                    );

                if (!field) {
                    return;
                }

                const tr =
                    field.closest('tr');

                if (
                    !tr
                    || tr.dataset.confirmingRow
                        === '1'
                ) {
                    return;
                }

                invalidateFuelPhotoRowConfirmation(
                    tr
                );
            };

        document.addEventListener(
            'input',
            fuelPhotoRowConfirmationGuard
        );

        document.addEventListener(
            'change',
            fuelPhotoRowConfirmationGuard
        );


        document.addEventListener(
            'input',
            event => {
                /*
                 * O aceite consciente de duplicidade faz parte
                 * da confirmação final e não é uma edição da ficha.
                 */
                if (
                    event.target.id
                    === 'fuelPhotoAiDuplicateConfirm'
                ) {
                    return;
                }

                if (
                    event.target.closest(
                        '#fuelPhotoAiResult'
                    )
                ) {
                    invalidateFuelPhotoLaunchConfirmation();
                }
            }
        );

        document.addEventListener(
            'change',
            event => {
                /*
                 * Marcar/desmarcar o aceite da duplicidade
                 * não deve devolver o operador à revisão.
                 */
                if (
                    event.target.id
                    === 'fuelPhotoAiDuplicateConfirm'
                ) {
                    return;
                }

                if (
                    event.target.closest(
                        '#fuelPhotoAiResult'
                    )
                ) {
                    invalidateFuelPhotoLaunchConfirmation();
                }
            }
        );

        const input =
            document.getElementById(
                'fuelPhotoAiFile'
            );

        const modal =
            document.getElementById(
                'fuelPhotoImportModal'
            );

        if (!input || !modal) {
            return;
        }

        input.addEventListener(
            'change',
            event => {
                const file =
                    event.target.files?.[0];

                if (!file) {
                    resetFuelPhotoImport();
                    return;
                }

                const preview =
                    document.getElementById(
                        'fuelPhotoAiPreview'
                    );

                const empty =
                    document.getElementById(
                        'fuelPhotoAiPreviewEmpty'
                    );

                const info =
                    document.getElementById(
                        'fuelPhotoAiFileInfo'
                    );

                const button =
                    document.getElementById(
                        'fuelPhotoAiAnalyze'
                    );

                const isPdf =
                    file.type === 'application/pdf'
                    || file.name
                        .toLowerCase()
                        .endsWith('.pdf');

                if (isPdf) {
                    preview.src = '';
                    preview.hidden = true;

                    empty.hidden = false;
                    empty.innerHTML =
                        '<i class="bi bi-file-earmark-pdf"></i>'
                        + '<span>PDF selecionado · a primeira página será analisada</span>';

                } else {
                    preview.src =
                        URL.createObjectURL(file);

                    preview.hidden = false;
                    empty.hidden = true;
                }

                info.hidden = false;

                info.innerHTML =
                    `<strong>${escapeFuelPhoto(
                        file.name
                    )}</strong>`
                    + `<span>${(
                        file.size / 1024 / 1024
                    ).toFixed(2)} MB</span>`;

                button.disabled = false;

                document.getElementById(
                    'fuelPhotoAiResult'
                ).hidden = true;

                document.getElementById(
                    'fuelPhotoAiFeedback'
                ).hidden = true;
            }
        );


        modal.addEventListener(
            'click',
            event => {
                if (event.target === modal) {
                    closeFuelPhotoImport();
                }
            }
        );


        document.addEventListener(
            'keydown',
            event => {
                if (
                    event.key === 'Escape'
                    && !modal.hidden
                ) {
                    closeFuelPhotoImport();
                }
            }
        );
    }
);

</script>

@endif


@push('scripts')
<script>
    window.fuelLastFillingByVehicle = @json($lastFillingByVehicle ?? []);
</script>

    <script>

function updateFuelLastFillingCard(form = null) {
    const fillingForm =
        form
        || document.querySelector(
            '#fuel-modal-filling .fuel-filling-form'
        );

    const vehicleSelect =
        fillingForm?.querySelector(
            'select[name="vehicle_id"]'
        );

    const empty =
        document.getElementById(
            'fuelLastFillingEmpty'
        );

    const content =
        document.getElementById(
            'fuelLastFillingContent'
        );

    const status =
        document.getElementById(
            'fuelLastFillingStatus'
        );

    if (!vehicleSelect || !empty || !content || !status) {
        return;
    }

    const vehicleId = vehicleSelect.value;
    const dataMap =
        window.fuelLastFillingByVehicle || {};

    const filling =
        vehicleId
            ? dataMap[String(vehicleId)] || null
            : null;

    if (!filling) {
        status.textContent = 'Sem histórico';
        empty.hidden = false;
        content.hidden = true;
        return;
    }

    status.textContent = 'Encontrado';
    empty.hidden = true;
    content.hidden = false;

    const setText = (id, value) => {
        const el = document.getElementById(id);

        if (el) {
            el.textContent =
                value && String(value).trim() !== ''
                    ? value
                    : '—';
        }
    };

    setText('fuelLastFillingDate', filling.filled_at);
    setText('fuelLastFillingLiters', filling.quantity_liters ? `${filling.quantity_liters} L` : '—');
    setText('fuelLastFillingProduct', filling.product);
    setText('fuelLastFillingSource', filling.source_label);
    setText('fuelLastFillingPreviousKm', filling.previous_km ? `${filling.previous_km} km` : '—');
    setText('fuelLastFillingVehicleKm', filling.vehicle_km ? `${filling.vehicle_km} km` : '—');
    setText('fuelLastFillingTank', filling.tank);
    setText('fuelLastFillingResponsible', filling.responsible);
}


function openFuelModal(id) {
            closeFuelModals();

            const modal = document.getElementById(`fuel-modal-${id}`);

            if (modal) {
                modal.classList.add('is-open');
                document.body.classList.add('fuel-modal-open');

                if (id === 'filling') {
                    setTimeout(
                        updateFuelLastFillingCard,
                        0
                    );
                }
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
            const unitDisplay = form.querySelector('[data-fuel-unit-cost-display]');

            if (!litersInput || !totalInput || !unitInput) {
                return;
            }

            const liters = Number(litersInput.value || 0);
            const total = Number(totalInput.value || 0);

            if (liters <= 0 || total <= 0) {
                unitInput.value = '';
                if (unitDisplay) unitDisplay.textContent = 'R$ 0,00';
                return;
            }

            const unit = total / liters;

            unitInput.value = unit.toFixed(2);
            if (unitDisplay) unitDisplay.textContent = unit.toLocaleString('pt-BR', {
                style: 'currency',
                currency: 'BRL',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
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

    function syncVehicleFuelCompatibility(form) {
        const vehicle = form.querySelector('select[name="vehicle_id"]');
        const compatibility = window.fuelVehicleCompatibility?.[vehicle?.value];
        const help = form.querySelector('[data-vehicle-fuel-help]');
        if (help) help.textContent = compatibility?.label || 'Selecione o veículo para consultar os combustíveis permitidos.';
        ['fuel_tank_id', 'fuel_product_id'].forEach(function (name) {
            const select = form.querySelector(`select[name="${name}"]`);
            if (!select) return;
            [...select.options].forEach(function (option) {
                if (!option.dataset.productId) return;
                const allowed = !compatibility?.configured || compatibility.allowed_ids.includes(Number(option.dataset.productId));
                option.disabled = !allowed;
                option.textContent = option.textContent.replace(' — Veículo não aceita', '') + (allowed ? '' : ' — Veículo não aceita');
            });
            if (select.selectedOptions[0]?.disabled) select.value = '';
            const compatible = [...select.options].filter(o => o.dataset.productId && !o.disabled);
            if (compatible.length === 1 && !select.value) select.value = compatible[0].value;
        });
        updateFillingCostPreview(form);
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
        syncVehicleFuelCompatibility(form);
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
        if (event.target.matches('select[name="fuel_tank_id"]') || event.target.matches('select[name="vehicle_id"]') || event.target.matches('input[name="source"]')) {
            const form = event.target.closest('form');

            if (form && form.classList.contains('fuel-filling-form')) {
                if (event.target.matches('select[name="vehicle_id"]')) {
                    syncVehicleCounters(form);
                    syncVehicleFuelCompatibility(form);
                } else if (event.target.matches('input[name="source"]')) {
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
            kmInput.min = 0;
            hoursInput.min = 0;
            return;
        }

        const currentKm = Number(selected.dataset.currentKm || 0);
        const currentHours = Number(selected.dataset.currentHours || 0);

        kmInput.value = currentKm;
        // The current counter is a convenience default only. A filling may be
        // backdated, so HTML must not reject a value before the service can
        // classify it against the chronological timeline.
        kmInput.min = 0;

        hoursInput.value = currentHours;
        hoursInput.min = 0;
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
                syncVehicleFuelCompatibility(form);
                updateFuelLastFillingCard(form);
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
                syncVehicleFuelCompatibility(form);
                updateFuelLastFillingCard(form);
            });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', hydrateFuelFillingForms);
    } else {
        hydrateFuelFillingForms();
    }
    </script>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    window.fuelReadingDeltaInit = true;

    const kmInput = document.querySelector('input[name="vehicle_km"]');
    const hoursInput = document.querySelector('input[name="vehicle_hours"]');
    const vehicleSelect = document.querySelector('select[name="vehicle_id"]');

    if (!kmInput && !hoursInput) {
        return;
    }


    function parseReading(value) {

        if (value === null || value === undefined || value === '') {
            return null;
        }

        const number = Number(
            String(value)
                .trim()
                .replace(',', '.')
        );

        return Number.isFinite(number)
            ? number
            : null;
    }


    function formatReading(value) {

        return Number(value).toLocaleString(
            'pt-BR',
            {
                minimumFractionDigits:
                    Number.isInteger(value) ? 0 : 1,

                maximumFractionDigits: 1
            }
        );
    }


    function deltaElement(input, type) {

        if (!input) {
            return null;
        }

        const wrapper =
            input.closest('.form-group')
            || input.closest('.field')
            || input.parentElement;

        if (!wrapper) {
            return null;
        }

        let element =
            wrapper.querySelector(
                '[data-fuel-reading-delta="' + type + '"]'
            );

        if (!element) {

            element = document.createElement('div');

            element.className = 'fuel-reading-delta';
            element.dataset.fuelReadingDelta = type;
            element.hidden = true;

            input.insertAdjacentElement(
                'afterend',
                element
            );

        }

        return element;
    }


    function render(input, type) {

        if (!input) return;

        const element = deltaElement(input, type);

        if (!element) return;

        const base =
            parseReading(input.dataset.fuelBaseReading);

        const current =
            parseReading(input.value);

        if (base === null || current === null) {

            element.hidden = true;

            return;
        }

        const difference = current - base;

        if (Math.abs(difference) < 0.0001) {

            element.hidden = true;

            element.classList.remove(
                'is-positive',
                'is-negative'
            );

            return;
        }


        const isHours = type === 'hours';


        if (difference > 0) {

            element.classList.remove('is-negative');
            element.classList.add('is-positive');

            element.innerHTML =
                '<i class="bi '
                + (isHours ? 'bi-clock-history' : 'bi-signpost-split')
                + '"></i>'
                + '<span>'
                + (isHours ? 'HR trabalhadas' : 'KM rodados')
                + ': <strong>'
                + formatReading(difference)
                + ' '
                + (isHours ? 'h' : 'km')
                + '</strong></span>';

        } else {

            element.classList.remove('is-positive');
            element.classList.add('is-negative');

            element.innerHTML =
                '<i class="bi bi-exclamation-triangle"></i>'
                + '<span>Leitura inferior em <strong>'
                + formatReading(Math.abs(difference))
                + ' '
                + (isHours ? 'h' : 'km')
                + '</strong></span>';

        }

        element.hidden = false;
    }


    function setBaseReadings() {

        if (kmInput) {

            kmInput.dataset.fuelBaseReading =
                kmInput.value ?? '';

            render(kmInput, 'km');

        }

        if (hoursInput) {

            hoursInput.dataset.fuelBaseReading =
                hoursInput.value ?? '';

            render(hoursInput, 'hours');

        }

    }


    if (kmInput) {

        kmInput.addEventListener(
            'input',
            () => render(kmInput, 'km')
        );

        kmInput.addEventListener(
            'change',
            () => render(kmInput, 'km')
        );

    }


    if (hoursInput) {

        hoursInput.addEventListener(
            'input',
            () => render(hoursInput, 'hours')
        );

        hoursInput.addEventListener(
            'change',
            () => render(hoursInput, 'hours')
        );

    }


    /*
     * Quando troca o veículo, o sistema atualiza KM/HR.
     * Depois dessa atualização os novos valores passam a ser
     * a referência para calcular quanto rodou/trabalhou.
     */
    if (vehicleSelect) {

        vehicleSelect.addEventListener(
            'change',
            function () {

                setTimeout(setBaseReadings, 100);
                setTimeout(setBaseReadings, 300);

            }
        );

    }


    /*
     * Referência inicial.
     */
    setTimeout(setBaseReadings, 100);

});
</script>
@endpush
