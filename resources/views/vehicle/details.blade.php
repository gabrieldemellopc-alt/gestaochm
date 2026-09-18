@extends('layouts.app')



@php

    $pageTitle = 'Veículo';

    $pageSubtitle = $vehicle->plate . ' · ' . $vehicle->name;

    $operationsEnabled = (bool) config('chm.features.operations_enabled', false);

    $fuelEnabled = (bool) config('chm.features.fuel_enabled', true);
    $maintenanceRestrictionReason = app(\App\Services\AggregatedVehiclePolicy::class)
        ->maintenanceRestrictionReason($vehicle, $vehicle->location);

@endphp


@push('styles')

<link

    rel="stylesheet"

    href="{{ asset('css/pages/vehicle-center.css') }}?v=4"
>
<link rel="stylesheet" href="{{ asset('css/pages/vehicle-reading-correction.css') }}?v=1">

@endpush



@section('content')



<div
    class="vehicle-details-page"
    x-data="{
        readingQuickOpen: false,
        readingTab: 'update',
        statusQuickOpen: false,
        kmHistoryOpen: false,
        currentStatus: @js($vehicle->operational_status),
        selectedStatus: @js($vehicle->operational_status)
    }"
>


    {{-- HERO --}}

    <div class="vehicle-details-hero">



        <div class="vehicle-center-identity">



            <div class="vehicle-center-icon">



                <img

                    src="{{ asset('images/' . ($vehicle->type_icon ?? 'lixo.png')) }}"

                    alt="Veículo"

                >



            </div>



            <div>



                <div class="vehicle-center-title-row">



                    <h2>

                        {{ $vehicle->name }}

                    </h2>



                    @php
                        $statusConfig = match($vehicle->operational_status) {
                            'maintenance' => [
                                'class' => 'maintenance',
                                'icon' => 'wrench',
                                'label' => 'Em manutenção',
                            ],

                            'inactive' => [
                                'class' => 'inactive',
                                'icon' => 'circle-off',
                                'label' => 'Inativo',
                            ],

                            'inoperant' => [
                                'class' => 'danger',
                                'icon' => 'x-circle',
                                'label' => 'Inoperante',
                            ],

                            'accident' => [
                                'class' => 'danger',
                                'icon' => 'triangle-alert',
                                'label' => 'Sinistro',
                            ],

                            'support' => [
                                'class' => 'warning',
                                'icon' => 'truck',
                                'label' => 'Socorro',
                            ],

                            'testing' => [
                                'class' => 'info',
                                'icon' => 'flask-conical',
                                'label' => 'Em testes',
                            ],

                            'transfer' => [
                                'class' => 'warning',
                                'icon' => 'arrow-right-left',
                                'label' => 'Transferência',
                            ],

                            'transferred' => [
                                'class' => 'inactive',
                                'icon' => 'route',
                                'label' => 'Transferido',
                            ],

                            default => [
                                'class' => 'operational',
                                'icon' => 'check-circle',
                                'label' => 'Operacional',
                            ],
                        };
                    @endphp

                    <span class="vehicle-center-status {{ $statusConfig['class'] }}">
                        <i class="{{ chm_icon($statusConfig['icon']) }}"></i>
                        {{ $statusConfig['label'] }}
                    </span>



                </div>



                <div class="vehicle-center-meta">



                    <span>{{ $vehicle->plate }}</span>



                    <span>•</span>



                    <span>{{ $vehicle->brand ?? 'Sem marca' }}</span>



                    @if($vehicle->year)

                        <span>• {{ $vehicle->year }}</span>

                    @endif

                    @if($vehicle->renavam)
                        <span>• RENAVAM: {{ $vehicle->renavam }}</span>
                    @endif

                    @if($vehicle->serial_number)
                        <span>• Nº de série: {{ $vehicle->serial_number }}</span>
                    @endif

                    @if($vehicle->chassis)
                        <span>• CHASSI: {{ $vehicle->chassis }}</span>
                    @endif



                    @if($vehicle->currentAllocation?->location)

                        <span>• {{ $vehicle->currentAllocation->location->name }}</span>

                    @endif



                </div>



            </div>



        </div>



        <div class="vehicle-details-hero-actions">



            <a

                href="{{ route('vehicles.index') }}"

                class="vehicle-center-action"

            >

                <i class="bi bi-arrow-left"></i>

                <span>Veículos</span>

            </a>



            <a

                href="{{ route('vehicles.edit', $vehicle) }}"

                class="vehicle-center-action"

            >

                <i class="bi bi-pencil"></i>

                <span>Editar</span>

            </a>

            <a
                href="{{ route('vehicles.history', $vehicle) }}"
                class="vehicle-center-action"
            >
                <i class="bi bi-clock-history"></i>
                <span>Histórico</span>
            </a>



        </div>



    </div>

    {{-- KPI BAR --}}

{{-- PAINEL OPERACIONAL COMPACTO --}}

@php
    $alertsCollection = collect($vehicle->alerts ?? []);
    $activeAlertsCount = $alertsCollection->count();

    $fuelValues = collect($fuelTrend ?? [])->pluck('liters')->map(fn ($v) => (float) $v);
    $fuelMax = max((float) ($fuelValues->max() ?? 0), 1);

    $kmValues = collect($kmDeltaTrend ?? [])->pluck('delta')->map(fn ($v) => (float) $v);
    $kmMax = max((float) ($kmValues->max() ?? 0), 1);

    $makeSvgPoints = function ($items, $field, $maxValue) {
        $count = count($items);

        if ($count < 2) {
            return '';
        }

        return collect($items)->values()->map(function ($item, $index) use ($field, $maxValue, $count) {
            $x = 18 + (($index / max($count - 1, 1)) * 664);
            $value = (float) data_get($item, $field, 0);
            $y = 164 - (($value / max($maxValue, 1)) * 126);

            return round($x, 1).','.round($y, 1);
        })->implode(' ');
    };

    $fuelPoints = $makeSvgPoints($fuelTrend ?? collect(), 'liters', $fuelMax);
    $kmPoints = $makeSvgPoints($kmDeltaTrend ?? collect(), 'delta', $kmMax);
@endphp

<section class="vehicle-panel-kpi-strip">

    <div class="vehicle-panel-kpi">
        <span class="vehicle-panel-kpi-icon"><i class="bi bi-speedometer2"></i></span>
        <div>
            <small>Hodômetro</small>
            <strong>{{ number_format((float) ($vehicle->current_km ?? 0), 0, ',', '.') }} km</strong>
        </div>
    </div>

    <div class="vehicle-panel-kpi">
        <span class="vehicle-panel-kpi-icon"><i class="bi bi-clock"></i></span>
        <div>
            <small>Horímetro</small>
            <strong>{{ number_format((float) ($vehicle->current_hours ?? 0), 0, ',', '.') }} h</strong>
        </div>
    </div>

    <div class="vehicle-panel-kpi vehicle-panel-kpi--status">
        <span class="vehicle-panel-kpi-icon">
            <i class="{{ chm_icon($statusConfig['icon']) }}"></i>
        </span>

        <div class="vehicle-panel-kpi-content">
            <small>Status operacional</small>
            <strong>{{ $statusConfig['label'] }}</strong>
        </div>

        @if($vehicle->operational_status === 'maintenance')
            <span
                class="vehicle-panel-kpi-action is-disabled"
                title="Status controlado pela manutenção aberta."
            >
                <i class="bi bi-lock"></i>
            </span>
        @else
            <button
                type="button"
                class="vehicle-panel-kpi-action"
                @click="
                    selectedStatus = currentStatus;
                    statusQuickOpen = true;
                "
            >
                Alterar
            </button>
        @endif
    </div>

    <div class="vehicle-panel-kpi">
        <span class="vehicle-panel-kpi-icon"><i class="bi bi-exclamation-triangle"></i></span>
        <div>
            <small>Alertas ativos</small>
            <strong>{{ $activeAlertsCount }}</strong>
        </div>
    </div>

    <div class="vehicle-panel-kpi vehicle-panel-kpi--split">
        <div>
            <small>Disponibilidade</small>
            <strong>
                {{ $availabilityRate !== null
                    ? number_format((float) $availabilityRate, 1, ',', '.').' %'
                    : '--' }}
            </strong>
        </div>

        <div>
            <small>Tempo parado</small>
            <strong>{{ $totalDowntimeText ?? '--' }}</strong>
        </div>
    </div>

</section>

{{-- AÇÕES RÁPIDAS --}}
<nav class="vehicle-panel-actions">

    <button type="button" @click="readingTab = 'update'; readingQuickOpen = true" >
        <i class="bi bi-speedometer2"></i>
        <span>Atualizar KM/HR</span>
    </button>

    @if($fuelEnabled)
        <a href="{{ route('fuel.tanks.index', ['fuel_modal' => 'filling', 'fuel_vehicle_id' => $vehicle->id]) }}">
            <i class="bi bi-fuel-pump"></i>
            <span>Lançar abastecimento</span>
        </a>
    @endif

    <a href="{{ route('vehicles.tires.index', $vehicle) }}">
        <i class="bi bi-record-circle"></i>
        <span>Pneus</span>
    </a>

    @if($maintenanceRestrictionReason)
        <span
            class="is-disabled"
            title="{{ $maintenanceRestrictionReason }}"
        >
            <i class="bi bi-lock"></i>
            <span>Manutenções</span>
        </span>
    @else
        <a href="{{ route('vehicle.maintenance.index', $vehicle) }}">
            <i class="bi bi-wrench-adjustable"></i>
            <span>Manutenções</span>
        </a>

    <button
        id="vehicleReportModalButton"
        type="button"
        aria-haspopup="dialog"
        aria-expanded="false"
        onclick="openVehicleReportModal()"
    >
        <i class="bi bi-file-earmark-text"></i>
        <span>Relatório</span>
    </button>

    @endif

</nav>

{{-- GRÁFICOS --}}
<div class="vehicle-panel-charts">

    <section class="vehicle-center-card vehicle-panel-chart-card">
        <header class="vehicle-panel-section-head">
            <div>
                <small>Combustível</small>
                <h3>Últimos abastecimentos</h3>
                <span>Volume em litros</span>
            </div>

            <a href="{{ route('fuel.fillings.history', ['vehicle_id' => $vehicle->id]) }}">
                Ver todos <i class="bi bi-arrow-right"></i>
            </a>
        </header>

        @if(($fuelTrend ?? collect())->count() >= 2)
            <div class="vehicle-native-chart">
                <svg viewBox="0 0 700 190" preserveAspectRatio="none" aria-label="Volume dos últimos abastecimentos">
                    <line x1="18" y1="164" x2="682" y2="164" class="vehicle-chart-axis"/>
                    <line x1="18" y1="122" x2="682" y2="122" class="vehicle-chart-grid"/>
                    <line x1="18" y1="80" x2="682" y2="80" class="vehicle-chart-grid"/>
                    <line x1="18" y1="38" x2="682" y2="38" class="vehicle-chart-grid"/>

                    <polyline
                        points="{{ $fuelPoints }}"
                        class="vehicle-chart-line vehicle-chart-line--fuel"
                    />

                    @foreach($fuelTrend as $index => $point)
                        @php
                            $x = 18 + (($index / max($fuelTrend->count() - 1, 1)) * 664);
                            $y = 164 - (((float) $point['liters'] / $fuelMax) * 126);
                        @endphp

                        <circle
                            cx="{{ round($x, 1) }}"
                            cy="{{ round($y, 1) }}"
                            r="4"
                            class="vehicle-chart-dot vehicle-chart-dot--fuel"
                            data-chart-point
                            data-chart-kind="fuel"
                            data-chart-date="{{ $point['datetime'] }}"
                            data-chart-value="{{ number_format($point['liters'], 3, ',', '.') }} L"
                            tabindex="0"
                        ></circle>
                    @endforeach
                </svg>

                <div class="vehicle-chart-tooltip" data-chart-tooltip>
                    <small data-tooltip-date></small>
                    <strong data-tooltip-value></strong>
                </div>

                <div class="vehicle-chart-labels">
                    @foreach($fuelTrend as $point)
                        <span>{{ $point['date'] }}</span>
                    @endforeach
                </div>
            </div>
        @else
            <div class="vehicle-panel-chart-empty">
                <i class="bi bi-graph-up"></i>
                <span>Dados insuficientes para gerar a tendência de abastecimentos.</span>
            </div>
        @endif
    </section>

    <section class="vehicle-center-card vehicle-panel-chart-card">
        <header class="vehicle-panel-section-head">
            <div>
                <small>Rodagem</small>
                <h3>Rodagem entre leituras</h3>
                <span>Diferença de KM entre hodômetros válidos</span>
            </div>

            <button type="button" @click="kmHistoryOpen = true">
                Ver todos <i class="bi bi-arrow-right"></i>
            </button>
        </header>

        @if(($kmDeltaTrend ?? collect())->count() >= 2)
            <div class="vehicle-native-chart">
                <svg viewBox="0 0 700 190" preserveAspectRatio="none" aria-label="Rodagem entre leituras válidas">
                    <line x1="18" y1="164" x2="682" y2="164" class="vehicle-chart-axis"/>
                    <line x1="18" y1="122" x2="682" y2="122" class="vehicle-chart-grid"/>
                    <line x1="18" y1="80" x2="682" y2="80" class="vehicle-chart-grid"/>
                    <line x1="18" y1="38" x2="682" y2="38" class="vehicle-chart-grid"/>

                    <polyline
                        points="{{ $kmPoints }}"
                        class="vehicle-chart-line vehicle-chart-line--km"
                    />

                    @foreach($kmDeltaTrend as $index => $point)
                        @php
                            $x = 18 + (($index / max($kmDeltaTrend->count() - 1, 1)) * 664);
                            $y = 164 - (((float) $point['delta'] / $kmMax) * 126);
                        @endphp

                        <circle
                            cx="{{ round($x, 1) }}"
                            cy="{{ round($y, 1) }}"
                            r="4"
                            class="vehicle-chart-dot vehicle-chart-dot--km"
                            data-chart-point
                            data-chart-kind="km"
                            data-chart-date="{{ $point['datetime'] }}"
                            data-chart-value="{{ number_format($point['delta'], 1, ',', '.') }} km"
                            data-chart-reading="{{ number_format($point['reading'], 0, ',', '.') }} km"
                            tabindex="0"
                        ></circle>
                    @endforeach
                </svg>

                <div class="vehicle-chart-tooltip" data-chart-tooltip>
                    <small data-tooltip-date></small>
                    <strong data-tooltip-value></strong>
                    <span data-tooltip-extra></span>
                </div>

                <div class="vehicle-chart-labels">
                    @foreach($kmDeltaTrend as $point)
                        <span>{{ $point['date'] }}</span>
                    @endforeach
                </div>
            </div>
        @else
            <div class="vehicle-panel-chart-empty">
                <i class="bi bi-graph-up"></i>
                <span>Dados insuficientes para calcular a rodagem entre leituras.</span>
            </div>
        @endif
    </section>

</div>

{{-- MONITORAMENTO + MANUTENÇÕES --}}
<div class="vehicle-panel-secondary-grid">

    <section class="vehicle-center-card vehicle-panel-compact-card">
        <header class="vehicle-panel-section-head">
            <div>
                <small>Monitoramento</small>
                <h3>Alertas do veículo</h3>
            </div>

            @if($activeAlertsCount > 3)
                <span class="vehicle-panel-more-count">
                    +{{ $activeAlertsCount - 3 }}
                </span>
            @endif
        </header>

        @if($activeAlertsCount === 0)
            <div class="vehicle-panel-empty-compact">
                <i class="bi bi-check-circle"></i>
                <span>Nenhum alerta ativo.</span>
            </div>
        @else
            <div class="vehicle-panel-alert-list">
                @foreach($alertsCollection->take(3) as $alert)
                    <div class="vehicle-panel-alert-row {{ $alert['status'] ?? 'warning' }}">
                        <span class="vehicle-panel-alert-dot"></span>

                        <div>
                            <strong>{{ $alert['message'] ?? 'Alerta operacional' }}</strong>

                            @if(!empty($alert['procedure']))
                                <small>{{ $alert['procedure'] }}</small>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <section class="vehicle-center-card vehicle-panel-compact-card">
        <header class="vehicle-panel-section-head">
            <div>
                <small>Manutenção</small>
                <h3>Manutenções recentes</h3>
            </div>

            @unless($maintenanceRestrictionReason)
                <a href="{{ route('vehicle.maintenance.index', $vehicle) }}">
                    Ver todas <i class="bi bi-arrow-right"></i>
                </a>
            @endunless
        </header>

        @if($maintenanceRestrictionReason)
            <div class="vehicle-panel-restriction">
                <i class="bi bi-lock"></i>
                <span>{{ $maintenanceRestrictionReason }}</span>
            </div>
        @elseif(($recentMaintenances ?? collect())->isEmpty())
            <div class="vehicle-panel-empty-compact">
                <i class="bi bi-clipboard"></i>
                <span>Nenhuma manutenção registrada.</span>
            </div>
        @else
            <div class="vehicle-panel-maintenance-list">
                @foreach($recentMaintenances as $maintenance)
                    <div class="vehicle-panel-maintenance-row">
                        <div>
                            <strong>{{ $maintenance->procedure?->name ?? 'Procedimento' }}</strong>
                            <small>{{ $maintenance->reason ?? 'Manutenção' }}</small>
                        </div>

                        <span>
                            {{
                                optional(
                                    $maintenance->performed_at
                                    ?? $maintenance->started_at
                                    ?? $maintenance->created_at
                                )->format('d/m/Y') ?? '--'
                            }}
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

</div>

{{-- MODAL HISTÓRICO DE RODAGEM --}}
<div
    class="vehicle-panel-modal-overlay"
    x-show="kmHistoryOpen"
    x-cloak
    @click.self="kmHistoryOpen = false"
>
    <div class="vehicle-panel-modal vehicle-panel-modal--km-history">
        <header>
            <div>
                <small>Histórico operacional</small>
                <h3>Rodagem entre leituras</h3>
            </div>

            <button type="button" @click="kmHistoryOpen = false">
                <i class="bi bi-x-lg"></i>
            </button>
        </header>

        <div class="vehicle-km-history-filters">
            <label>
                Data inicial
                <input
                    type="date"
                    id="vehicleKmHistoryStart"
                    onchange="filterVehicleKmHistory()"
                >
            </label>

            <label>
                Data final
                <input
                    type="date"
                    id="vehicleKmHistoryEnd"
                    onchange="filterVehicleKmHistory()"
                >
            </label>

            <button type="button" onclick="clearVehicleKmHistoryFilters()">
                <i class="bi bi-x-circle"></i>
                Limpar
            </button>

            <span id="vehicleKmHistoryCount">
                {{ $kmReadingHistory->count() }}
                {{ $kmReadingHistory->count() === 1 ? 'leitura' : 'leituras' }}
            </span>
        </div>

        <div class="vehicle-km-history-table-wrap">
            <table class="vehicle-km-history-table">
                <thead>
                    <tr>
                        <th>Data / hora</th>
                        <th>Leitura</th>
                        <th>Rodagem</th>
                        <th>Origem</th>
                        <th>Responsável</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($kmReadingHistory as $reading)
                        <tr
                            data-km-reading-row
                            data-date="{{ $reading['date'] }}"
                        >
                            <td>{{ $reading['datetime'] }}</td>

                            <td>
                                <strong>
                                    {{ number_format($reading['reading'], 0, ',', '.') }} km
                                </strong>
                            </td>

                            <td>
                                @if($reading['delta'] !== null)
                                    +{{ number_format($reading['delta'], 0, ',', '.') }} km
                                @else
                                    —
                                @endif
                            </td>

                            <td>
                                <strong>{{ $reading['source'] }}</strong>

                                @if($reading['observation'])
                                    <small>{{ $reading['observation'] }}</small>
                                @endif
                            </td>

                            <td>{{ $reading['user'] ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="vehicle-km-history-empty">
                                Nenhuma leitura válida registrada.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div
                id="vehicleKmHistoryEmptyFilter"
                class="vehicle-km-history-empty"
                hidden
            >
                Nenhuma leitura encontrada no período selecionado.
            </div>
        </div>
    </div>
</div>

{{-- MODAL KM / HR --}}
<div
    class="vehicle-panel-modal-overlay"
    x-show="readingQuickOpen"
    x-cloak
    @click.self="readingQuickOpen = false; readingTab = 'update'"
>
    <div class="vehicle-panel-modal vehicle-panel-modal--reading">
        <header>
            <div>
                <small>Leituras do veículo</small>
                <h3>KM e Horímetro</h3>
            </div>

            <button
                type="button"
                @click="readingQuickOpen = false; readingTab = 'update'"
            >
                <i class="bi bi-x-lg"></i>
            </button>
        </header>

        <div class="vehicle-reading-tabs">
            <button
                type="button"
                :class="{ 'is-active': readingTab === 'update' }"
                @click="readingTab = 'update'"
            >
                <i class="bi bi-speedometer2"></i>
                Atualizar
            </button>

            @if($canCorrectReadings)
                <button
                    type="button"
                    :class="{ 'is-active': readingTab === 'correct' }"
                    @click="readingTab = 'correct'"
                >
                    <i class="bi bi-arrow-counterclockwise"></i>
                    Corrigir
                </button>
            @endif
        </div>

        <div
            x-show="readingTab === 'update'"
            class="vehicle-reading-update-tab"
        >
            <div class="vehicle-reading-current-summary">
                <div>
                    <small>Hodômetro atual</small>
                    <strong>{{ number_format((float) ($vehicle->current_km ?? 0), 0, ',', '.') }} km</strong>
                </div>

                <div>
                    <small>Horímetro atual</small>
                    <strong>{{ number_format((float) ($vehicle->current_hours ?? 0), 1, ',', '.') }} h</strong>
                </div>
            </div>

            <div class="vehicle-panel-reading-grid">
                <form
                    method="POST"
                    action="{{ route('vehicles.update-km', $vehicle) }}"
                    onsubmit="return confirmLargeKmUpdate(this, {{ (float) ($vehicle->current_km ?? 0) }});"
                >
                    @csrf
                    <input type="hidden" name="km_reading_confirmed" value="0">

                    <label>Hodômetro atual</label>

                    <div class="vehicle-panel-input-unit">
                        <input
                            type="number"
                            name="km"
                            value="{{ $vehicle->current_km ?? 0 }}"
                            min="0"
                            step="1"
                            required
                        >
                        <span>KM</span>
                    </div>

                    <button type="submit">
                        Atualizar hodômetro
                    </button>
                </form>

                <form
                    method="POST"
                    action="{{ route('vehicles.update-hours', $vehicle) }}"
                    onsubmit="return confirmLargeHoursUpdate(this, {{ (float) ($vehicle->current_hours ?? 0) }});"
                >
                    @csrf
                    <input type="hidden" name="hours_reading_confirmed" value="0">

                    <label>Horímetro atual</label>

                    <div class="vehicle-panel-input-unit">
                        <input
                            type="number"
                            name="hours"
                            value="{{ $vehicle->current_hours ?? 0 }}"
                            min="0"
                            step="1"
                            required
                        >
                        <span>H</span>
                    </div>

                    <button type="submit">
                        Atualizar horímetro
                    </button>
                </form>
            </div>
        </div>

        @if($canCorrectReadings)
            <div
                x-show="readingTab === 'correct'"
                x-cloak
                class="vehicle-reading-correction-tab"
            >
                <div class="vehicle-reading-correction-intro">
                    <i class="bi bi-shield-exclamation"></i>
                    <div>
                        <strong>Correção administrativa</strong>
                        <span>
                            Utilize somente para substituir uma leitura lançada incorretamente.
                            A operação permanece registrada para auditoria.
                        </span>
                    </div>
                </div>

                <form method="POST" enctype="multipart/form-data" action="{{ route('vehicles.reading-correction.store', $vehicle) }}" class="reading-correction-form" id="readingCorrectionForm">
            @csrf
            <div class="reading-correction-body">
            <div class="reading-correction-current">
                <span>Leitura atual</span>
                <strong>{{ number_format((float) $vehicle->current_km, 0, ',', '.') }} KM</strong>
                <strong>{{ number_format((float) $vehicle->current_hours, 1, ',', '.') }} h</strong>
            </div>

            <div class="reading-correction-grid">
                <div class="reading-correction-field">
                    <label>Novo KM <span>opcional</span></label>
                    <input type="number" min="0" step="1" name="new_km" value="{{ old('new_km') }}">
                </div>
                <div class="reading-correction-field">
                    <label>Novo horímetro <span>opcional</span></label>
                    <input type="number" min="0" step="0.1" name="new_hours" value="{{ old('new_hours') }}">
                </div>
            </div>

            <div class="reading-correction-field">
                <label>Leitura incorreta a substituir</label>
                <select name="target_log_id" required>
                    <option value="">Selecione o evento original</option>
                    @foreach($vehicle->updateLogs->whereIn('type', ['km', 'hours'])->filter->is_reading_usable as $log)
                        <option value="{{ $log->id }}">{{ $log->type === 'hours' ? 'HORÍMETRO' : 'KM' }}: {{ $log->new_value }}{{ $log->type === 'hours' ? ' h' : '' }} — {{ optional($log->read_at ?? $log->created_at)->format('d/m/Y H:i') }} — {{ $log->source_label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="reading-correction-field">
                <label>Motivo da correção <span id="readingCorrectionWordCount">0 / 8 palavras</span></label>
                <textarea name="reason" required rows="3" placeholder="Descreva o motivo da correção com pelo menos 8 palavras.">{{ old('reason') }}</textarea>
            </div>

            <section class="reading-correction-evidence reading-correction-photo-evidence">
                <strong>Comprovação fotográfica</strong>
                <p>Envie duas fotos para comprovar a leitura informada.</p>
                <p class="reading-correction-photo-note"><i class="bi bi-info-circle"></i> As imagens devem apresentar data/hora e coordenadas de localização (latitude/longitude).</p>
                <input type="hidden" name="mobile_evidence_id" id="readingCorrectionMobileEvidenceId">
                <button type="button" class="reading-correction-mobile-button" onclick="createReadingPhotoQr()"><i class="bi bi-qr-code"></i> Enviar fotos pelo celular</button>
                <div id="readingCorrectionPhotoQr" class="reading-correction-photo-qr" hidden></div>
                <div id="readingCorrectionPhotoStatus" class="reading-correction-photo-status" aria-live="polite"></div>
                <div class="reading-correction-photo-grid">
                    <div class="reading-correction-upload">
                        <label for="readingCorrectionPlatePhoto" class="reading-correction-upload-title">{{ filled($vehicle->plate) ? 'Foto da placa' : 'Foto de identificação do veículo/equipamento' }}</label>
                        <small>{{ filled($vehicle->plate) ? 'Fotografe a placa do veículo de forma legível.' : 'Fotografe o código, identificação ou característica que permita identificar o equipamento.' }}</small>
                        <label for="readingCorrectionPlatePhoto" class="reading-correction-select-photo"><i class="bi bi-image"></i> Selecionar foto</label><input id="readingCorrectionPlatePhoto" type="file" name="plate_photo" accept="image/*" capture="environment">
                        <div class="reading-correction-photo-preview" data-preview="readingCorrectionPlatePhoto"></div>
                    </div>
                    <div class="reading-correction-upload">
                        <label for="readingCorrectionPhoto" id="readingCorrectionPhotoLabel" class="reading-correction-upload-title">Foto do hodômetro/horímetro</label>
                        <small>Fotografe o painel mostrando claramente a leitura informada.</small>
                        <label for="readingCorrectionPhoto" class="reading-correction-select-photo"><i class="bi bi-image"></i> Selecionar foto</label><input id="readingCorrectionPhoto" type="file" name="reading_photo" accept="image/*" capture="environment">
                        <div class="reading-correction-photo-preview" data-preview="readingCorrectionPhoto"></div>
                    </div>
                </div>
            </section>
            <section class="reading-correction-impact-area" aria-live="polite">
                <div id="readingCorrectionImpacts" class="reading-correction-impact">
                    <div class="reading-correction-impact-state"><i class="bi bi-info-circle"></i><div><strong>Impactos ainda não analisados</strong><p>Revise os impactos da correção antes de confirmar.</p></div></div>
                </div>
            </section>
            </div>

            <div class="reading-correction-actions">
                <button
                    type="button"
                    @click="readingQuickOpen = false; readingTab = 'update'"
                >
                    Cancelar
                </button>
                <button type="button" id="readingCorrectionSubmit" disabled onclick="previewReadingCorrection()">Revisar impactos</button>
            </div>
        </form>
            </div>
        @endif
    </div>
</div>

{{-- MODAL STATUS --}}
@if($vehicle->operational_status !== 'maintenance')
<div
    class="vehicle-panel-modal-overlay"
    x-show="statusQuickOpen"
    x-cloak
    @click.self="statusQuickOpen = false"
>
    <div class="vehicle-panel-modal vehicle-panel-modal--small">
        <header>
            <div>
                <small>Situação do veículo</small>
                <h3>Alterar status operacional</h3>
            </div>

            <button type="button" @click="statusQuickOpen = false">
                <i class="bi bi-x-lg"></i>
            </button>
        </header>

        <form
            method="POST"
            action="{{ route('vehicles.operational-status.update', $vehicle) }}"
            class="vehicle-panel-status-form"
        >
            @csrf

            <label>Status</label>

            <select
                name="operational_status"
                x-model="selectedStatus"
                required
            >
                <option value="operational">Operacional</option>
                <option value="inactive">Inativo</option>
                <option value="inoperant">Inoperante</option>
                <option value="accident">Sinistro</option>
                <option value="support">Socorro</option>
                <option value="testing">Testes</option>
                <option value="transfer">Transferência</option>
                <option value="transferred">Transferido</option>
            </select>

            <label x-show="selectedStatus !== currentStatus">
                Motivo / observação
            </label>

            <textarea
                x-show="selectedStatus !== currentStatus"
                x-cloak
                name="status_reason"
                rows="3"
                :required="selectedStatus !== currentStatus"
                placeholder="Informe o motivo da alteração..."
            ></textarea>

            <footer>
                <button type="button" @click="statusQuickOpen = false">
                    Cancelar
                </button>

                <button
                    type="submit"
                    class="primary"
                    :disabled="selectedStatus === currentStatus"
                >
                    Salvar status
                </button>
            </footer>
        </form>
    </div>
</div>
@endif



<div
    id="vehicleReportModal"
    class="vehicle-report-modal-backdrop"
    aria-hidden="true"
>
    <div class="vehicle-report-modal" role="dialog" aria-modal="true" aria-labelledby="vehicleReportModalTitle">
        <div class="vehicle-report-modal-header">
            <div>
                <small>Prontu&aacute;rio operacional</small>
                <h3 id="vehicleReportModalTitle">Relat&oacute;rio do Ve&iacute;culo</h3>
                <p>Configure o per&iacute;odo e os blocos que ser&atilde;o exibidos no Dossi&ecirc; Individual.</p>
            </div>

            <button type="button" onclick="closeVehicleReportModal()" aria-label="Fechar relat&oacute;rio do ve&iacute;culo">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <form
            method="GET"
            action="{{ route('reports.vehicle-dossier.index') }}"
            class="vehicle-report-form"
            onsubmit="return validateVehicleReportForm(this)"
        >
            <input type="hidden" name="vehicle_id" value="{{ $vehicle->id }}">
            <input type="hidden" name="section_config" value="1">

            <section class="vehicle-report-modal-section">
                <div class="vehicle-report-section-title">
                    <span>1</span>
                    <strong>Per&iacute;odo</strong>
                </div>

                <div class="vehicle-report-shortcuts">
                    <button type="button" onclick="setVehicleReportPeriod('last_30')">&Uacute;ltimos 30 dias</button>
                    <button type="button" onclick="setVehicleReportPeriod('current_month')">M&ecirc;s atual</button>
                    <button type="button" onclick="setVehicleReportPeriod('last_90')">&Uacute;ltimos 90 dias</button>
                    <button type="button" onclick="setVehicleReportPeriod('custom')">Personalizado</button>
                </div>

                <div class="vehicle-report-date-grid">
                    <label>
                        Data inicial
                        <input type="date" name="start_date" id="vehicleReportStartDate" required>
                    </label>

                    <label>
                        Data final
                        <input type="date" name="end_date" id="vehicleReportEndDate" required>
                    </label>
                </div>
            </section>

            <section class="vehicle-report-modal-section">
                <div class="vehicle-report-section-title">
                    <span>2</span>
                    <strong>Conte&uacute;do do relat&oacute;rio</strong>
                </div>

                <div class="vehicle-report-check-grid">
                    <label><input type="checkbox" name="sections[]" value="summary" checked> Resumo executivo</label>
                    <label><input type="checkbox" name="sections[]" value="maintenances" checked> Manuten&ccedil;&otilde;es</label>
                    <label><input type="checkbox" name="sections[]" value="maintenance_costs" checked> Custos registrados da ordem</label>
                    <label><input type="checkbox" name="sections[]" value="stock" checked> Pe&ccedil;as e consumo de estoque</label>
                    <label><input type="checkbox" name="sections[]" value="tires" checked> Pneus</label>
                    <label><input type="checkbox" name="sections[]" value="fuel" checked> Abastecimentos</label>
                    <label><input type="checkbox" name="sections[]" value="fuel_consumption" checked> Consumo de combust&iacute;vel</label>
                    <label><input type="checkbox" name="sections[]" value="km_hr"> Atualiza&ccedil;&otilde;es de KM e hor&iacute;metro</label>
                    <label><input type="checkbox" name="sections[]" value="downtime"> Status operacional e indisponibilidade</label>
                    <label><input type="checkbox" name="sections[]" value="alerts"> Alertas e preventivas</label>
                </div>
            </section>

            @can('viewAuditLogs')
                <section class="vehicle-report-modal-section">
                    <div class="vehicle-report-section-title">
                        <span>3</span>
                        <strong>Op&ccedil;&otilde;es avan&ccedil;adas</strong>
                    </div>

                    <div class="vehicle-report-check-grid is-advanced">
                        <label><input type="checkbox" name="include_cancelled" value="1"> Incluir registros cancelados</label>
                        <label><input type="checkbox" name="include_audit" value="1"> Incluir detalhes de auditoria</label>
                    </div>
                </section>
            @endcan

            <p id="vehicleReportValidation" class="vehicle-report-validation" hidden>Selecione pelo menos um conte&uacute;do para visualizar no relat&oacute;rio.</p>

            <div class="vehicle-report-modal-actions">
                <button type="button" class="vehicle-report-secondary" onclick="closeVehicleReportModal()">
                    Cancelar
                </button>

                <button
                    type="submit"
                    class="vehicle-report-secondary"
                    formaction="{{ route('reports.vehicle-dossier.pdf') }}"
                    formtarget="_blank"
                >
                    Gerar PDF
                </button>

                <button type="submit" class="vehicle-report-primary">
                    Visualizar relat&oacute;rio
                </button>
            </div>
        </form>
    </div>
</div>
<script>
@if($canCorrectReadings)
    let readingImpactState = 'not_reviewed';
    let readingPhotoEvidenceTimer;
    let readingImpactHighlightTimer;
    function openReadingCorrectionModal() {
        document.getElementById('readingCorrectionModal').style.display = 'flex';
        document.body.classList.add('reading-correction-open');
        readingImpactState = 'not_reviewed';
        renderReadingImpactNotice();
        renderReadingPhotoState({});
        updateReadingSubmit();
    }

    function closeReadingCorrectionModal() {
        document.getElementById('readingCorrectionModal').style.display = 'none';
        document.body.classList.remove('reading-correction-open');
        clearInterval(readingPhotoEvidenceTimer); const id=document.getElementById('readingCorrectionMobileEvidenceId').value;
        if(id) fetch(`/vehicles/{{ $vehicle->id }}/reading-correction/evidence/${id}/cancel`,{method:'POST',headers:{'X-CSRF-TOKEN':document.querySelector('#readingCorrectionForm [name=_token]').value}});
    }

    async function createReadingPhotoQr() { const form=document.getElementById('readingCorrectionForm'), box=document.getElementById('readingCorrectionPhotoQr'); const response=await fetch(@json(route('vehicles.reading-correction.evidence.create',$vehicle)),{method:'POST',headers:{Accept:'application/json','X-CSRF-TOKEN':form.querySelector('[name=_token]').value}}); const data=await response.json(); if(!response.ok) return; form.mobile_evidence_id.value=data.id; box.hidden=false; box.innerHTML=`<img src="${data.qr}" alt="QR Code para envio de fotos"><span>Aguardando fotos enviadas pelo celular...</span>`; clearInterval(readingPhotoEvidenceTimer); readingPhotoEvidenceTimer=setInterval(()=>pollReadingPhotoEvidence(data.id),3500); }
    async function pollReadingPhotoEvidence(id) { const response=await fetch(`/vehicles/{{ $vehicle->id }}/reading-correction/evidence/${id}/status`,{headers:{Accept:'application/json'}}); if(!response.ok)return; const data=await response.json(); renderReadingPhotoState(data); if(data.plate&&data.reading){clearInterval(readingPhotoEvidenceTimer);document.getElementById('readingCorrectionPhotoQr').hidden=true;} updateReadingSubmit(); }
    function renderReadingPhotoState(data) { const plate=data.photos?.identification, reading=data.photos?.reading, status=document.getElementById('readingCorrectionPhotoStatus'), count=(plate?1:0)+(reading?1:0); status.className=`reading-correction-photo-status ${count===2?'is-complete':count?'is-partial':''}`; status.innerHTML=`<strong><i class="bi ${count===2?'bi-check-circle-fill':'bi-hourglass-split'}"></i> ${count===2?'Comprovação fotográfica completa':count?'Fotos parcialmente recebidas':'Aguardando fotos'}</strong><span>${count}/2 fotos recebidas</span>`; renderReceivedPhoto('readingCorrectionPlatePhoto',plate,'Foto da placa/identificação'); renderReceivedPhoto('readingCorrectionPhoto',reading,'Foto do hodômetro/horímetro'); }
    function renderReceivedPhoto(inputId,photo,label) { const input=document.getElementById(inputId), card=input.closest('.reading-correction-upload'), preview=card.querySelector('.reading-correction-photo-preview'), selector=card.querySelector('.reading-correction-select-photo'); if(!photo)return; input.value=''; input.dataset.received='1'; selector.hidden=true; preview.innerHTML=`<a href="${photo.url}" target="_blank" rel="noopener"><img src="${photo.url}" alt="${label}"></a><span><i class="bi bi-check-circle-fill"></i> Foto recebida</span><button type="button" aria-label="Substituir ${label}">×</button>`; preview.querySelector('button').onclick=()=>{delete input.dataset.received;selector.hidden=false;preview.innerHTML='';card.classList.add('is-replacing');readingImpactState='stale';renderReadingImpactNotice();updateReadingSubmit();}; }

    async function previewReadingCorrection() {
        const form = document.getElementById('readingCorrectionForm');
        const target = document.getElementById('readingCorrectionImpacts');
        if (!form.reportValidity()) return;
        const response = await fetch(@json(route('vehicles.reading-correction.preview', $vehicle)), {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value },
            body: new FormData(form),
        });
        const data = await response.json();

        if (! response.ok) {
            const errors = data.errors ? Object.values(data.errors).flat() : [data.message || 'Não foi possível analisar os impactos.'];
            target.innerHTML = `<div class="reading-correction-impact-state is-error">${errors.map(message => `<p>${escapeReadingCorrectionHtml(message)}</p>`).join('')}</div>`;
            return;
        }

        const impacts = data.impacts || [];
        const impactItems = impacts.length
            ? impacts.map(item => `<article class="reading-correction-impact-item"><div><strong>${escapeReadingCorrectionHtml(item.type)}</strong><small>${escapeReadingCorrectionHtml(item.date)}</small></div><b>${escapeReadingCorrectionHtml(item.value)}</b><span>${escapeReadingCorrectionHtml(item.reference)}</span></article>`).join('')
            : '<div class="reading-correction-impact-state is-success"><i class="bi bi-check-circle"></i><div><strong>Nenhum lançamento potencialmente afetado</strong><p>Nenhum lançamento acima da nova leitura foi encontrado.</p></div></div>';
        target.innerHTML = `<label id="readingCorrectionConfirmation" class="reading-correction-impact-header"><input type="checkbox" name="impact_confirmed" value="1" required><span class="reading-correction-impact-copy"><strong>Lançamentos potencialmente afetados</strong><p>Esses registros não serão alterados automaticamente, mas podem precisar de conferência.</p><span class="reading-correction-warning-copy"><strong>Estou ciente dos impactos desta correção.</strong><small>Registros relacionados poderão ter seus indicadores recalculados, sem exclusão do histórico original.</small></span></span><span class="reading-correction-impact-badge">${impacts.length} registro${impacts.length === 1 ? '' : 's'}</span></label><div class="reading-correction-impact-list">${impactItems}</div>`;

        const confirmation = document.querySelector('#readingCorrectionConfirmation input');
        if (confirmation) confirmation.addEventListener('change', updateReadingSubmit);
        readingImpactState = 'reviewed';
        updateReadingSubmit();
        scrollToReadingImpacts();
    }

    document.querySelectorAll('#readingCorrectionForm [name="new_km"], #readingCorrectionForm [name="new_hours"], #readingCorrectionForm [name="reason"], #readingCorrectionForm [name="target_log_id"], #readingCorrectionForm [name="plate_photo"], #readingCorrectionForm [name="reading_photo"]')
        .forEach(input => input.addEventListener(input.tagName === 'SELECT' ? 'change' : 'input', () => {
            readingImpactState = readingImpactState === 'reviewed' ? 'stale' : 'not_reviewed';
            updateReadingSubmit();
            renderReadingImpactNotice();
        }));

    function reasonWordCount(){return document.querySelector('#readingCorrectionForm [name="reason"]').value.trim().split(/\s+/).filter(Boolean).length;}
    function scrollToReadingImpacts() { requestAnimationFrame(() => { const container=document.querySelector('.reading-correction-body'); const impacts=document.getElementById('readingCorrectionImpacts'); if (!container || !impacts) return; const containerRect=container.getBoundingClientRect(); const impactsRect=impacts.getBoundingClientRect(); container.scrollTo({top:Math.max(0,container.scrollTop+impactsRect.top-containerRect.top-12),behavior:'smooth'}); clearTimeout(readingImpactHighlightTimer); impacts.classList.add('is-highlighted'); readingImpactHighlightTimer=setTimeout(()=>impacts.classList.remove('is-highlighted'),1800); }); }
    function renderReadingImpactNotice() { const target=document.getElementById('readingCorrectionImpacts'); const stale=readingImpactState==='stale'; target.innerHTML=`<div class="reading-correction-impact-state"><i class="bi bi-info-circle"></i><div><strong>${stale?'Dados alterados após a análise':'Impactos ainda não analisados'}</strong><p>${stale?'Os dados foram alterados. Revise os impactos antes de confirmar.':'Revise os impactos da correção antes de confirmar.'}</p></div></div>`; }
    function updateReadingSubmit() { const f=document.getElementById('readingCorrectionForm'); const hasKm=!!f.new_km.value, hasHours=!!f.new_hours.value, change=hasKm||hasHours; const words=reasonWordCount(); const confirmation=f.querySelector('[name="impact_confirmed"]'); const button=document.getElementById('readingCorrectionSubmit'); const readyForReview=(f.plate_photo.files.length||f.plate_photo.dataset.received)&&(f.reading_photo.files.length||f.reading_photo.dataset.received)&&change&&words>=8&&f.target_log_id.value; const reviewed=readingImpactState==='reviewed'; document.getElementById('readingCorrectionPhotoLabel').textContent=hasKm&&hasHours?'Foto do hodômetro/horímetro':hasKm?'Foto do hodômetro':hasHours?'Foto do horímetro':'Foto do hodômetro/horímetro'; document.getElementById('readingCorrectionWordCount').textContent=`${words} / 8 palavras`; document.getElementById('readingCorrectionWordCount').classList.toggle('is-invalid',words<8); button.textContent=reviewed?'Confirmar correção':'Revisar impactos'; button.type=reviewed?'submit':'button'; if(reviewed) button.removeAttribute('onclick'); else button.setAttribute('onclick','previewReadingCorrection()'); button.disabled=!(readyForReview&&(!reviewed||confirmation?.checked)); }

    document.querySelectorAll('#readingCorrectionForm input[type="file"]').forEach(input => input.addEventListener('change', () => {
        const preview=document.querySelector(`[data-preview="${input.id}"]`); const file=input.files[0];
        if (!file) { preview.innerHTML=''; updateReadingSubmit(); return; }
        const url=URL.createObjectURL(file); preview.innerHTML=`<img src="${url}" alt="Prévia da imagem selecionada"><span>${escapeReadingCorrectionHtml(file.name)}</span><button type="button">Remover</button>`;
        preview.querySelector('button').addEventListener('click', () => { input.value=''; preview.innerHTML=''; updateReadingSubmit(); }); updateReadingSubmit();
    }));

    function escapeReadingCorrectionHtml(value) {
        const element = document.createElement('div');
        element.textContent = String(value ?? '');
        return element.innerHTML;
    }
@endif
    const vehicleReportModal = document.getElementById('vehicleReportModal');
    const vehicleReportModalButton = document.getElementById('vehicleReportModalButton');
    const vehicleReportStartDate = document.getElementById('vehicleReportStartDate');
    const vehicleReportEndDate = document.getElementById('vehicleReportEndDate');
    const vehicleReportValidation = document.getElementById('vehicleReportValidation');

    function formatVehicleReportDate(date) {
        return date.toISOString().split('T')[0];
    }

    function openVehicleReportModal() {
        if (!vehicleReportStartDate.value || !vehicleReportEndDate.value) {
            setVehicleReportPeriod('last_30');
        }

        vehicleReportModal.classList.add('active');
        vehicleReportModal.setAttribute('aria-hidden', 'false');
        vehicleReportModalButton.setAttribute('aria-expanded', 'true');
    }

    function closeVehicleReportModal() {
        vehicleReportModal.classList.remove('active');
        vehicleReportModal.setAttribute('aria-hidden', 'true');
        vehicleReportModalButton.setAttribute('aria-expanded', 'false');
        vehicleReportValidation.hidden = true;
    }

    function setVehicleReportPeriod(period) {
        const today = new Date();
        let start = new Date(today);
        const end = new Date(today);

        if (period === 'current_month') {
            start = new Date(today.getFullYear(), today.getMonth(), 1);
        } else if (period === 'last_90') {
            start.setDate(today.getDate() - 90);
        } else if (period === 'custom') {
            vehicleReportStartDate.focus();
            return;
        } else {
            start.setDate(today.getDate() - 30);
        }

        vehicleReportStartDate.value = formatVehicleReportDate(start);
        vehicleReportEndDate.value = formatVehicleReportDate(end);
    }

    function validateVehicleReportForm(form) {
        const selectedSections = form.querySelectorAll('input[name="sections[]"]:checked');

        if (selectedSections.length === 0) {
            vehicleReportValidation.hidden = false;
            return false;
        }

        vehicleReportValidation.hidden = true;
        return true;
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && vehicleReportModal.classList.contains('active')) {
            closeVehicleReportModal();
        }
    });

    vehicleReportModal.addEventListener('click', function (event) {
        if (event.target === vehicleReportModal) {
            closeVehicleReportModal();
        }
    });
    function confirmLargeKmUpdate(form, originalKm) {
        const input = form.querySelector('input[name="km"]');
        const confirmation = form.querySelector('input[name="km_reading_confirmed"]');
        confirmation.value = '0';
        const currentKm = Number(input.value);
        const diffKm = currentKm - Number(originalKm);

        if (currentKm < Number(originalKm)) {
            alert(`O novo KM não pode ser menor que o KM atual (${Number(originalKm).toLocaleString('pt-BR')}).`);
            input.value = originalKm;
            return false;
        }

        if (diffKm > 500) {
            const confirmed = confirm(
                `Atenção: você está aumentando o hodômetro em ${diffKm.toLocaleString('pt-BR')} km.\n\n` +
                `KM atual: ${Number(originalKm).toLocaleString('pt-BR')}\n` +
                `Novo KM: ${currentKm.toLocaleString('pt-BR')}\n\n` +
                `Deseja confirmar esta atualização?`
            );
            confirmation.value = confirmed ? '1' : '0';
            return confirmed;
        }

        return true;
    }

    function confirmLargeHoursUpdate(form, originalHours) {
        const input = form.querySelector('input[name="hours"]');
        const confirmation = form.querySelector('input[name="hours_reading_confirmed"]');
        confirmation.value = '0';
        const currentHours = Number(input.value);
        const diffHours = currentHours - Number(originalHours);

        if (currentHours < Number(originalHours)) {
            alert(`O novo horímetro não pode ser menor que o horímetro atual (${Number(originalHours).toLocaleString('pt-BR')}).`);
            input.value = originalHours;
            return false;
        }

        if (diffHours > 24) {
            const confirmed = confirm(
                `Atenção: você está aumentando o horímetro em ${diffHours.toLocaleString('pt-BR')} hora(s).\n\n` +
                `Horímetro atual: ${Number(originalHours).toLocaleString('pt-BR')}\n` +
                `Novo horímetro: ${currentHours.toLocaleString('pt-BR')}\n\n` +
                `Deseja confirmar esta atualização?`
            );
            confirmation.value = confirmed ? '1' : '0';
            return confirmed;
        }

        return true;
    }
</script>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.vehicle-native-chart').forEach(function (chart) {
        const tooltip = chart.querySelector('[data-chart-tooltip]');
        const points = chart.querySelectorAll('[data-chart-point]');

        if (!tooltip || !points.length) {
            return;
        }

        const tooltipDate = tooltip.querySelector('[data-tooltip-date]');
        const tooltipValue = tooltip.querySelector('[data-tooltip-value]');
        const tooltipExtra = tooltip.querySelector('[data-tooltip-extra]');

        function showTooltip(point) {
            points.forEach(function (item) {
                item.classList.remove('is-active');
            });

            point.classList.add('is-active');

            const chartRect = chart.getBoundingClientRect();
            const pointRect = point.getBoundingClientRect();

            const centerX =
                pointRect.left - chartRect.left + (pointRect.width / 2);

            const centerY =
                pointRect.top - chartRect.top + (pointRect.height / 2);

            if (tooltipDate) {
                tooltipDate.textContent = point.dataset.chartDate || '';
            }

            if (tooltipValue) {
                tooltipValue.textContent = point.dataset.chartValue || '';
            }

            if (tooltipExtra) {
                const reading = point.dataset.chartReading || '';

                tooltipExtra.textContent = reading
                    ? 'Hodômetro: ' + reading
                    : '';

                tooltipExtra.style.display = reading ? 'block' : 'none';
            }

            tooltip.style.left = centerX + 'px';
            tooltip.style.top = centerY + 'px';

            tooltip.classList.add('is-visible');
        }

        function hideTooltip(point) {
            point.classList.remove('is-active');
            tooltip.classList.remove('is-visible');
        }

        points.forEach(function (point) {
            point.addEventListener('mouseenter', function () {
                showTooltip(point);
            });

            point.addEventListener('mouseleave', function () {
                hideTooltip(point);
            });

            point.addEventListener('focus', function () {
                showTooltip(point);
            });

            point.addEventListener('blur', function () {
                hideTooltip(point);
            });
        });
    });
});
</script>
@endpush

@push('scripts')
<script>
function filterVehicleKmHistory() {
    const start = document.getElementById('vehicleKmHistoryStart')?.value || '';
    const end = document.getElementById('vehicleKmHistoryEnd')?.value || '';
    const rows = Array.from(document.querySelectorAll('[data-km-reading-row]'));
    const empty = document.getElementById('vehicleKmHistoryEmptyFilter');
    const count = document.getElementById('vehicleKmHistoryCount');

    let visible = 0;

    rows.forEach(function (row) {
        const date = row.dataset.date || '';

        const matchesStart = !start || date >= start;
        const matchesEnd = !end || date <= end;
        const show = matchesStart && matchesEnd;

        row.style.display = show ? '' : 'none';

        if (show) {
            visible++;
        }
    });

    if (empty) {
        empty.hidden = visible !== 0 || rows.length === 0;
    }

    if (count) {
        count.textContent =
            visible + (visible === 1 ? ' leitura' : ' leituras');
    }
}

function clearVehicleKmHistoryFilters() {
    const start = document.getElementById('vehicleKmHistoryStart');
    const end = document.getElementById('vehicleKmHistoryEnd');

    if (start) start.value = '';
    if (end) end.value = '';

    filterVehicleKmHistory();
}
</script>
@endpush
