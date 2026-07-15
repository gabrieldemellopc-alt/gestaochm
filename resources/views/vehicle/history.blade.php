@extends('layouts.app')



@section('content')

@push('styles')



<link

    rel="stylesheet"

    href="{{ asset('css/pages/history.css') }}?v=2"
>



@endpush

<div class="vehicle-history-wrapper">



    {{-- HEADER --}}

    <div class="page-header">



        <div>



            <h1>

                Histórico do Veículo

            </h1>



            <p>

                Gestão operacional e técnica da frota

            </p>



        </div>



        <a

            href="{{ route('dashboard') }}"

            class="back-button"

        >



            <i data-lucide="arrow-left"></i>



            Voltar



        </a>



    </div>



    {{-- HERO --}}

    <div class="vehicle-history-hero">



        <div class="hero-left">



            <div class="vehicle-avatar">



                <img

                    src="{{ asset('images/' . ($vehicle->type ?? 'default') . '.png') }}"

                    alt=""

                >

            </div>



            <div>



                <div class="vehicle-title-row">



                    <h2>

                        {{ $vehicle->name }}

                    </h2>



                    @php
                        $vehicleStatusLabel = match ($vehicle->operational_status) {
                            'operational' => 'Operacional',
                            'maintenance' => 'Em manutenção',
                            'inactive' => 'Inativo',
                            'inoperant' => 'Inoperante',
                            'accident' => 'Sinistro',
                            'support' => 'Socorro',
                            'testing' => 'Em testes',
                            'transfer' => 'Em transferência',
                            'transferred' => 'Transferido',
                            default => 'Não informado',
                        };
                    @endphp
                    
                    <span class="vehicle-status-badge">
                        {{ $vehicleStatusLabel }}
                    </span>



                </div>



                <div class="vehicle-subtitle">



                    {{ $vehicle->plate }}

                    •



                    {{ $vehicle->division->name ?? 'Divisão' }}

                    •



                    {{ $vehicle->location->name ?? 'Operação urbana' }}



                </div>



            </div>



        </div>



        <div class="hero-kpis">



            <div class="hero-kpi-card">



                <small>

                    Hodômetro

                </small>



                <strong>



                    {{ number_format($vehicle->current_km ?? 0, 0, ',', '.') }}



                </strong>



                <span>

                    KM

                </span>



            </div>



            <div class="hero-kpi-card">



                <small>

                    Horímetro

                </small>



                <strong>



                    {{ number_format($vehicle->current_hours ?? 0, 0, ',', '.') }}



                </strong>



                <span>

                    Horas

                </span>



            </div>



            <div class="hero-kpi-card">



                <small>

                    Manutenções

                </small>



                <strong>



                    {{ $vehicle->maintenances->count() }}



                </strong>



                <span>

                    Registros

                </span>



            </div>



        </div>



    </div>



    {{-- TABS --}}

    <div

        x-data="{



            tab: 'operational',



            maintenanceFilterProcedure: '',



            maintenanceFilterStartDate: '',



            maintenanceFilterEndDate: '',



            maintenanceSearch: '',



            maintenanceOrder: 'desc',

            cancelMaintenanceId: null


        }"

        class="history-tabs-wrapper"

    >



        <div class="history-tabs">



            <button

                @click="tab = 'operational'"

                :class="{ 'active': tab === 'operational' }"

                class="history-tab-btn"

            >



                <i data-lucide="activity"></i>



                Operacional



            </button>



            <button

                @click="tab = 'maintenances'"

                :class="{ 'active': tab === 'maintenances' }"

                class="history-tab-btn"

            >



                <i data-lucide="wrench"></i>



                Manutenções



            </button>



            <button

                @click="tab = 'locations'"

                :class="{ 'active': tab === 'locations' }"

                class="history-tab-btn"

            >



                <i data-lucide="map-pinned"></i>



                Localizações



            </button>



        </div>



        {{-- OPERACIONAL --}}

        <div

            x-show="tab === 'operational'"

            x-transition

            class="history-section"

        >



            <div class="history-section-header">



                <h3>

                    Histórico operacional

                </h3>



                <p>

                    Atualizações de KM, horímetro e movimentações

                </p>



            </div>



            <div class="timeline-stack">



                @forelse($vehicle->updateLogs as $log)



                    <div class="timeline-card">



                        <div class="timeline-dot"></div>



                        <div class="timeline-body">



                            <div class="timeline-top">



                                <strong>



                                    @switch($log->type)



                                        @case('km')

                                            Hodômetro

                                            @break



                                        @case('hours')

                                            Horímetro

                                            @break

                                        @case('location')

                                            Local

                                            @break



                                        @default

                                            {{ ucfirst($log->type) }}



                                    @endswitch



                                </strong>



                                <span>



                                    {{ $log->created_at->format('d/m/Y H:i') }}



                                </span>



                            </div>



                            <div class="timeline-values">



                            @if($log->type === 'location')



                                <span>



                                    {{ $log->old_location_name ?? '--' }}



                                </span>



                                <i data-lucide="arrow-right"></i>



                                <strong>



                                    {{ $log->new_location_name ?? '--' }}



                                </strong>



                            @elseif($log->type === 'km')



                                <span>



                                    {{ number_format($log->old_value ?? 0, 0, ',', '.') }} KM



                                </span>



                                <i data-lucide="arrow-right"></i>



                                <strong>



                                    {{ number_format($log->new_value ?? 0, 0, ',', '.') }} KM



                                </strong>



                            @elseif($log->type === 'hours')



                                <span>



                                    {{ number_format($log->old_value ?? 0, 0, ',', '.') }} H



                                </span>



                                <i data-lucide="arrow-right"></i>



                                <strong>



                                    {{ number_format($log->new_value ?? 0, 0, ',', '.') }} H



                                </strong>



                            @else



                                <span>



                                    {{ $log->old_value ?? '--' }}



                                </span>



                                <i data-lucide="arrow-right"></i>



                                <strong>



                                    {{ $log->new_value ?? '--' }}



                                </strong>



                            @endif



                        </div>



                            @if($log->user)



                                <small>



                                    por {{ $log->user->name }}



                                </small>



                            @endif



                        </div>



                    </div>



                @empty



                    <div class="history-empty">



                        Nenhum histórico operacional encontrado.



                    </div>



                @endforelse



            </div>



        </div>



        {{-- MANUTENÇÕES --}}

        <div

            x-show="tab === 'maintenances'"

            x-transition

            class="history-section"

        >



            <div class="history-section-header">



                <div>



                    <h3>

                        Histórico de manutenções

                    </h3>



                    <p>

                        Serviços realizados neste veículo

                    </p>



                </div>



            </div>



            {{-- FILTROS --}}

            <div class="maintenance-filters-wrapper">



                {{-- PROCEDIMENTO --}}

                <div class="maintenance-filter-card">



                    <div class="maintenance-filter-title">



                        <i data-lucide="wrench"></i>



                        Procedimento



                    </div>



                    <select

                        x-model="maintenanceFilterProcedure"

                        class="nf-input"

                    >



                        <option value="">

                            Todos os procedimentos

                        </option>



                        @foreach(

                            $vehicle->procedures

                                ->unique('id')

                                ->sortBy('name')

                            as $procedure

                        )



                            <option value="{{ $procedure->id }}">



                                {{ $procedure->name }}



                            </option>



                        @endforeach



                    </select>



                </div>



                {{-- DATA --}}

                <div class="maintenance-filter-card">



                    <div class="maintenance-filter-title">



                        <i data-lucide="calendar-days"></i>



                        Período



                    </div>



                    <div class="date-range-group">



                        <input

                            type="date"

                            x-model="maintenanceFilterStartDate"

                            class="nf-input"

                        >



                        <span class="date-separator">

                            até

                        </span>



                        <input

                            type="date"

                            x-model="maintenanceFilterEndDate"

                            class="nf-input"

                        >



                    </div>



                </div>



                {{-- OBSERVAÇÃO --}}

                <div class="maintenance-filter-card flex-grow-filter">



                    <div class="maintenance-filter-title">



                        <i data-lucide="search"></i>



                        Observações



                    </div>



                    <input

                        type="text"

                        x-model="maintenanceSearch"

                        placeholder="Buscar observação, oficina, detalhe..."

                        class="nf-input"

                    >



                </div>



                {{-- ORDENAÇÃO --}}

                <div class="maintenance-order-box">



                    <div class="maintenance-filter-title">



                        <i data-lucide="arrow-down-up"></i>



                        Ordenação



                    </div>



                    <select

                        x-model="maintenanceOrder"

                        class="nf-input compact-input"

                    >



                        <option value="desc">

                            Mais recentes

                        </option>



                        <option value="asc">

                            Mais antigas

                        </option>



                    </select>



                </div>



            </div>

            {{-- LISTA --}}

            <div class="maintenance-stack">



                @forelse($vehicle->maintenances as $maintenance)
                    @php
                        $maintenanceItems = $maintenance->items ?? collect();
                        $hasNewItems = $maintenanceItems->isNotEmpty();
                        $legacyValues = $maintenance->values ?? collect();

                        $procedureIds = $hasNewItems
                            ? $maintenanceItems->pluck('procedure_id')->filter()->map(fn ($id) => (string) $id)->unique()->values()
                            : collect([$maintenance->procedure_id])->filter()->map(fn ($id) => (string) $id)->values();

                        $procedureNames = $hasNewItems
                            ? $maintenanceItems
                                ->map(fn ($item) => $item->procedure->name ?? null)
                                ->filter()
                                ->unique()
                                ->values()
                            : collect([$maintenance->procedure->name ?? null])->filter()->values();

                        $procedureSummary = $procedureNames->isNotEmpty()
                            ? $procedureNames->take(2)->implode(' + ')
                            : 'Manutenção rápida';

                        $extraProcedureCount = max($procedureNames->count() - 2, 0);

                        $maintenanceTypes = $hasNewItems
                            ? $maintenanceItems->pluck('maintenance_type')->filter()->unique()->values()
                            : collect([$maintenance->maintenance_type])->filter()->values();

                        $typeLabel = $maintenanceTypes->count() > 1
                            ? 'Mista'
                            : match($maintenanceTypes->first()) {
                                'internal' => 'Oficina interna',
                                'external' => 'Serviçoo terceirizado',
                                default => 'Tipo não informado',
                            };

                        $typeClass = $maintenanceTypes->count() > 1
                            ? 'mixed'
                            : ($maintenanceTypes->first() ?: 'external');

                        $maintenanceDate = $maintenance->performed_at
                            ?? $maintenance->started_at
                            ?? $maintenance->created_at;

                        $searchText = strtolower(collect([
                            $maintenance->notes,
                            $maintenance->reason,
                            $maintenance->provider_name,
                            $procedureNames->implode(' '),
                            $maintenanceItems->pluck('notes')->filter()->implode(' '),
                        ])->filter()->implode(' '));

                        $isOpenMaintenance = $maintenance->workflow_status === 'open' && ! $maintenance->cancelled_at;
                        $isClosedMaintenance = $maintenance->workflow_status === 'closed' && ! $maintenance->cancelled_at;
                    @endphp



                    <div

                        class="maintenance-card"



                        x-show="



                            (

                                !maintenanceFilterProcedure

                                ||

                                @js($procedureIds->all()).includes(maintenanceFilterProcedure)

                            )



                            &&



                            (

                                !maintenanceSearch

                                ||

                                @js($searchText)

                                    .includes(

                                        maintenanceSearch.toLowerCase()

                                    )

                            )



                            &&



                            (

                                !maintenanceFilterStartDate

                                ||

                                '{{ optional($maintenanceDate)->format('Y-m-d') }}'

                                    >=

                                maintenanceFilterStartDate

                            )



                            &&



                            (

                                !maintenanceFilterEndDate

                                ||

                                '{{ optional($maintenanceDate)->format('Y-m-d') }}'

                                    <=

                                maintenanceFilterEndDate

                            )



                        "

                    >



                    <div class="maintenance-top">
                        <div>
                            <h4>
                                {{ $procedureSummary }}@if($extraProcedureCount > 0) + {{ $extraProcedureCount }} item(ns) @endif
                            </h4>

                            <small>
                                {{ match($maintenance->reason) {
                                    'preventive' => 'Preventiva',
                                    'corrective' => 'Corretiva',
                                    'inspection' => 'Inspeção',
                                    'other' => 'Outros',
                                    default => 'Não informado',
                                } }}
                            
                                @if($maintenance->maintenance_category)
                                    ·
                            
                                    {{ \App\Services\MaintenanceService::maintenanceCategories()[
                                        $maintenance->maintenance_category
                                    ] ?? 'Outros' }}
                                @endif
                            
                                ·
                            
                                {{ $hasNewItems
                                    ? $maintenanceItems->count() . ' item(ns)'
                                    : 'Registro legado'
                                }}
                            
                                ·
                            
                                {{ $typeLabel }}
                            </small>
                        </div>

                        <div class="maintenance-card-actions">
                            <a
                                href="{{ route(
                                    'vehicles.maintenance.show',
                                    [$vehicle->id, $maintenance->id]
                                ) }}"
                                class="maintenance-pdf-mini"
                            >
                                <i data-lucide="eye"></i>
                                Detalhes
                            </a>
                            <a
                                href="{{ route('vehicles.maintenance.order.pdf', [$vehicle->id, $maintenance->id]) }}"
                                class="maintenance-pdf-mini"
                                target="_blank"
                            >
                                <i data-lucide="file-text"></i>
                                PDF
                            </a>

                            @if($maintenance->cancelled_at)
                                <span class="maintenance-badge cancelled">
                                    Cancelada
                                </span>
                            @else
                                <span class="maintenance-badge {{ $typeClass }}">
                                    {{ $isClosedMaintenance ? 'Fechada' : ($isOpenMaintenance ? 'Aberta' : $typeLabel) }}
                                </span>

                                @if($isOpenMaintenance)
                                @can('cancelMaintenanceRecords')
                                    <button
                                        type="button"
                                        class="maintenance-cancel-action"
                                        @click="cancelMaintenanceId = {{ $maintenance->id }}"
                                    >
                                        <i data-lucide="ban"></i>
                                        Cancelar
                                    </button>
                                @endcan
                                @endif
                            @endif
                        </div>
                    </div>


                        @if($maintenance->cancelled_at)

                            <div class="maintenance-cancelled-note">
                                <i data-lucide="ban"></i>
                                <span>
                                    Cancelada em
                                    {{ $maintenance->cancelled_at->format('d/m/Y H:i') }}
                                    @can('viewAuditLogs')
                                    @if($maintenance->cancel_reason)
                                        - {{ $maintenance->cancel_reason }}
                                    @endif
                                    @endcan
                                </span>
                            </div>

                        @elseif($isOpenMaintenance)

                            @can('cancelMaintenanceRecords')

                                <div
                                    x-show="cancelMaintenanceId === {{ $maintenance->id }}"
                                    x-cloak
                                    class="maintenance-cancel-panel"
                                >
                                    <form
                                        method="POST"
                                        action="{{ route('vehicles.maintenance.cancel', [$vehicle->id, $maintenance->id]) }}"
                                    >
                                        @csrf
                                        <label>Motivo do cancelamento</label>
                                        <textarea
                                            name="reason"
                                            required
                                            minlength="5"
                                            rows="3"
                                            placeholder="Informe o motivo do cancelamento"
                                        ></textarea>

                                        <div class="maintenance-cancel-panel-actions">
                                            <button
                                                type="button"
                                                class="maintenance-cancel-secondary"
                                                @click="cancelMaintenanceId = null"
                                            >
                                                Voltar
                                            </button>

                                            <button
                                                type="submit"
                                                class="maintenance-cancel-submit"
                                            >
                                                Confirmar cancelamento
                                            </button>
                                        </div>
                                    </form>
                                </div>

                            @endcan

                        @endif

                        <div class="maintenance-meta">


                            <div>



                                <small>

                                    KM

                                </small>



                                <strong>



                                    {{ number_format($maintenance->performed_km ?? 0, 0, ',', '.') }}



                                </strong>



                            </div>



                            <div>



                                <small>

                                    Valor

                                </small>



                                <strong>



                                    R$

                                    {{ number_format($maintenance->total_cost ?? 0, 2, ',', '.') }}



                                </strong>



                            </div>



                            <div>



                                <small>

                                    Data

                                </small>



                                <strong>



                                    {{ optional($maintenanceDate)->format('d/m/Y') ?? '--' }}



                                </strong>



                            </div>



                        </div>



                        @if($hasNewItems)

                            <div class="maintenance-notes">
                                <strong>Serviços da ordem:</strong>

                                @foreach($maintenanceItems->take(3) as $item)
                                    <div>
                                        {{ $item->procedure->name ?? 'Procedimento nao informado' }}
                                        -
                                        {{ $item->maintenance_type === 'internal' ? 'Interna' : 'Externa' }}
                                        @if($item->values->count())
                                            - {{ $item->values->count() }} campo(s)
                                        @endif
                                    </div>
                                @endforeach

                                @if($maintenanceItems->count() > 3)
                                    <small>+ {{ $maintenanceItems->count() - 3 }} item(ns) nesta ordem.</small>
                                @endif
                            </div>

                        @elseif($legacyValues->count())

                            <div class="maintenance-notes">
                                <strong>Campos do registro legado:</strong>

                                @foreach($legacyValues->take(3) as $value)
                                    @if($value->value)
                                        <div>
                                            {{ $value->field->label ?? 'Campo' }}: {{ $value->value }}
                                        </div>
                                    @endif
                                @endforeach
                            </div>

                        @endif

                        @if($maintenance->provider_name)



                            <div class="maintenance-provider">



                                <i data-lucide="building-2"></i>



                                {{ $maintenance->provider_name }}



                            </div>



                        @endif



                        @if($maintenance->notes)



                            <div class="maintenance-notes">



                                {{ $maintenance->notes }}



                            </div>



                        @endif



                    </div>



                @empty



                    <div class="history-empty">



                        Nenhuma manutenção registrada.



                    </div>



                @endforelse



            </div>



        </div>



        {{-- LOCALIZAÇÕES --}}

        <div

            x-show="tab === 'locations'"

            x-transition

            class="history-section"

        >



            <div class="history-section-header">



                <h3>

                    Histórico de localizações

                </h3>



                <p>

                    Movimentações operacionais do veículo

                </p>



            </div>



            <div class="timeline-stack">



                @if($locationLogs->count())



                    {{-- PRIMEIRO REGISTRO --}}

                    <div class="timeline-card">



                        <div class="timeline-dot success"></div>



                        <div class="timeline-body">



                            <div class="timeline-top">



                                <strong>

                                    Início

                                </strong>



                                <span>



                                    {{ optional(

                                        $locationLogs->first()->created_at

                                    )->format('d/m/Y H:i') }}



                                </span>



                            </div>



                            <div class="timeline-start">

                            <span>{{ $locationLogs->first()->new_location_name ?? 'Sem localização' }}</span>



                            </div>



                        </div>



                    </div>



                    {{-- MOVIMENTAÇÕES --}}

                    @foreach($locationLogs as $log)



                        <div class="timeline-card">



                            <div class="timeline-dot"></div>



                            <div class="timeline-body">



                                <div class="timeline-top">



                                    <strong>

                                        Alteração de localização

                                    </strong>



                                    <span>



                                        {{ $log->created_at->format('d/m/Y H:i') }}



                                    </span>



                                </div>



                                <div class="timeline-values">



                                    <span>



                                        {{ $log->old_location_name ?? '--' }}

                                    </span>



                                    <i data-lucide="arrow-right"></i>



                                    <strong>



                                        {{ $log->new_location_name ?? '--' }}

                                    </strong>



                                </div>



                                @if($log->user)



                                    <small>



                                        por {{ $log->user->name }}



                                    </small>



                                @endif



                                @if($log->observation)



                                    <div class="timeline-observation">



                                        {{ $log->observation }}



                                    </div>



                                @endif



                            </div>



                        </div>



                    @endforeach



                @else



                    <div class="timeline-card">



                        <div class="timeline-dot success"></div>



                        <div class="timeline-body">



                            <div class="timeline-top">



                                <strong>

                                    Início

                                </strong>



                            </div>



                            <div class="timeline-values">



                                <span>

                                    --

                                </span>



                                <i data-lucide="arrow-right"></i>



                                <strong>



                                    {{ $vehicle->currentAllocation->location->name ?? 'Sem localização' }}



                                </strong>



                            </div>



                        </div>



                    </div>



                @endif



            </div>



        </div>



    </div>



</div>



@endsection
