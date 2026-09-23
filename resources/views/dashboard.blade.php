@extends('layouts.app')







@php







    $pageTitle = 'Dashboard Operacional';







    $pageSubtitle = 'Controle de Frota';







    $operationsEnabled = (bool) config('chm.features.operations_enabled', false);



    $fuelEnabled = (bool) config('chm.features.fuel_enabled', true);
    $aggregatedVehiclePolicy = app(\App\Services\AggregatedVehiclePolicy::class);



    $dashboardPermissionService = app(\App\Services\Permissions\ProfilePermissionService::class);

    $dashboardCanPermission = function (string $permissionKey) use ($dashboardPermissionService) {

        $dashboardCurrentUser = auth()->user();



        if (! $dashboardCurrentUser) { return false; }

        return $dashboardPermissionService->allows($dashboardCurrentUser, $permissionKey);

    };



    $canAccessVehicleMaintenance = $dashboardCanPermission('navigation.workshop') && $dashboardCanPermission('maintenance.view');

    $canFillVehicle = $fuelEnabled && $dashboardCanPermission('navigation.fuel') && ($dashboardCanPermission('fuel.fill_internal') || $dashboardCanPermission('fuel.fill_external'));

    $canViewVehicleFuelHistory = $fuelEnabled
        && $dashboardCanPermission('navigation.fuel')
        && $dashboardCanPermission('fuel.view');

    $canAccessVehicleTires = $dashboardCanPermission('navigation.tires') && $dashboardCanPermission('tires.view');

    $dashboardVehicleActions = [
        'maintenance' => $canAccessVehicleMaintenance,
        'history' => $dashboardCanPermission('vehicles.view'),
        'panel' => $dashboardCanPermission('navigation.vehicles') && $dashboardCanPermission('vehicles.view'),
        'tires' => $canAccessVehicleTires,
        'fuel' => $canFillVehicle,
        'fuel_history' => $canViewVehicleFuelHistory,
        'edit' => $dashboardCanPermission('vehicles.update'),
    ];

    $dashboardFuelFillingUrlTemplate = route('fuel.tanks.index', [
        'fuel_modal' => 'filling',
        'fuel_vehicle_id' => '__vehicle_id__',
        'return_to' => 'fleet_dashboard',
    ]);



@endphp

@push('styles')







<link



    rel="stylesheet"



    href="{{ asset('css/pages/dashboard.css') }}?v=4"
>







@endpush



@section('content')







<div



    class="dashboard-page"



    x-data='dashboardFleet(@json($dashboardVehicleActions))'

>







    {{-- KPIs --}}





{{-- VISÃO GERAL DO TOPO --}}

<div class="dashboard-workspace-grid">



    {{-- COLUNA PRINCIPAL --}}

    <main class="dashboard-main-column">

        {{-- KPIs --}}

        <div class="kpi-grid">
{{-- Grupo composto por Frota Ativa + Em manutenção + Filtros abaixo --}}
    <div class="kpi-double-group">

        {{-- Linha superior com os 2 cards --}}
        <div class="kpi-cards-row">
            {{-- Frota ativa --}}
            <button
                type="button"
                class="kpi-card compact clickable"
                :class="{ active: activeFilter == 'all' }"
                @click="setFilter('all')"
            >
                <div class="kpi-icon">
                    <i class="bi bi-truck"></i>
                </div>
                <div class="kpi-content">
                    <small>Frota ativa</small>
                    <strong>{{ $vehicles->count() }}</strong>
                </div>
            </button>

            {{-- Em manutenção --}}
            <button
                type="button"
                class="kpi-card compact maintenance clickable"
                :class="{ active: activeFilter == 'maintenance' }"
                @click="setFilter('maintenance')"
            >
                <div class="kpi-icon">
                    <i class="bi bi-wrench-adjustable"></i>
                </div>
                <div class="kpi-content">
                    <small>Em manutenção</small>
                    <strong>{{ $maintenanceVehicles }}</strong>
                </div>
            </button>
        </div>

        {{-- Linha inferior com os 3 botões dividindo a largura total dos 2 cards --}}
        <nav
            class="dashboard-fleet-filter"
            aria-label="Filtro de frota exibida"
        >
            @foreach(['internal' => 'Internos', 'aggregated' => 'Agregados', 'rented' => 'Alugados', 'all' => 'Todos veículos'] as $value => $label)
                <a
                    href="{{ route('dashboard', ['fleet_relation' => $value]) }}"
                    class="{{ $fleetRelation === $value ? 'is-active' : '' }}"
                    @if($fleetRelation === $value) aria-current="page" @endif
                >
                    {{ $label }}
                </a>
            @endforeach
        </nav>

    </div>



            {{-- Veículos com alerta --}}

            <div class="kpi-alert-card">



                <div class="kpi-alert-header">

                    <div>

                        <small>Monitoramento</small>

                        <strong>Veículos com alerta</strong>

                    </div>



                    <i class="bi bi-bell"></i>

                </div>



                <div class="kpi-alert-levels">



                    <button

                        type="button"

                        class="kpi-alert-level warning clickable"

                        :class="{ active: activeFilter == 'warning' }"

                        @click="setFilter('warning')"

                    >

                        <span class="kpi-alert-icon">

                            <i class="bi bi-exclamation-triangle"></i>

                        </span>



                        <span class="kpi-alert-content">

                            <small>Em atenção</small>



                            <strong>

                                {{ $warningVehicles }}

                            </strong>

                        </span>

                    </button>



                    <button

                        type="button"

                        class="kpi-alert-level danger clickable"

                        :class="{ active: activeFilter == 'danger' }"

                        @click="setFilter('danger')"

                    >

                        <span class="kpi-alert-icon">

                            <i class="bi bi-exclamation-triangle"></i>

                        </span>



                        <span class="kpi-alert-content">

                            <small>Críticos</small>



                            <strong>

                                {{ $criticalVehicles }}

                            </strong>

                        </span>

                    </button>



                </div>



            </div>





            {{-- Acesso rápido --}}
            <div class="kpi-quick-actions-stack">

                <a
                    href="{{ route('vehicle.quick-update') }}"
                    class="kpi-quick-action kpi-quick-action--split"
                >
                    <div class="kpi-quick-action-content">
                        <small>Atualizar rápido</small>
                        <strong>KM/HR</strong>
                    </div>

                    <span class="kpi-quick-action-icon">
                        <i class="bi bi-speedometer2"></i>
                    </span>
                </a>

                <a
                    href="{{ route('fuel.daily-check.index') }}"
                    class="kpi-quick-action kpi-quick-action--split kpi-quick-action--archive {{ $pendingFuelReceiptInvoiceCount > 0 ? 'has-alert' : '' }}"
                >
                    <div class="kpi-quick-action-content">
                        <small>Documentos</small>

                        <div class="kpi-quick-action-title-row">
                            <strong>Arquivo Diário</strong>

                            @if($pendingFuelReceiptInvoiceCount > 0)
                                <span
                                    class="kpi-archive-alert-badge"
                                    title="{{ $pendingFuelReceiptInvoiceCount }} {{ $pendingFuelReceiptInvoiceCount === 1 ? 'NF pendente' : 'NFs pendentes' }}"
                                >
                                    {{ $pendingFuelReceiptInvoiceCount }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <span class="kpi-quick-action-icon">
                        <i class="bi bi-folder2-open"></i>
                    </span>
                </a>

            </div>

        </div>


        {{-- Busca --}}

        <div class="dashboard-filter-bar">



            <div class="dashboard-search-box">



                <label>

                    Buscar

                </label>



                <div class="dashboard-search-input">



                    <i class="bi bi-search"></i>



                    <input

                        type="text"

                        x-model="search"

                        placeholder="Nome, placa, código, RENAVAM, série..."

                    >



                </div>



            </div>



        </div>



        {{-- VEÍCULOS --}}



        <section class="vehicles-grid">







            @foreach($vehicles as $vehicle)



                @php







                    $searchText = strtolower(



                        trim(



                            ($vehicle->name ?? '') . ' ' .



                            ($vehicle->plate ?? '') . ' ' .

                            ($vehicle->asset_code ?? '') . ' ' .

                            ($vehicle->renavam ?? '') . ' ' .

                            ($vehicle->serial_number ?? '') . ' ' .



                            ($vehicle->brand ?? '') . ' ' .



                            ($vehicle->model ?? '') . ' ' .



                            ($vehicle->year ?? '')



                        )



                    );

                    $secondaryIdentifier = match (true) {
                        filled($vehicle->plate) => $vehicle->plate,
                        filled($vehicle->asset_code) => 'Código: '.$vehicle->asset_code,
                        filled($vehicle->renavam) => 'RENAVAM: '.$vehicle->renavam,
                        filled($vehicle->serial_number) => 'Série: '.$vehicle->serial_number,
                        default => null,
                    };







                    $shortMainAlert = null;







                    if ($vehicle->main_alert) {







                        $shortMainAlert = str_replace(



                            [



                                'KM sem atualização há mais de ',



                                'Horímetro sem atualização há mais de ',



                                'HR sem atualização há mais de ',



                            ],



                            [



                                'KM desatualizado há +',



                                'Horímetro desatualizado há +',



                                'Horímetro desatualizado há +',



                            ],



                            $vehicle->main_alert['message']



                        );



                    }







                @endphp


                <div



                    class="vehicle-card {{ $vehicle->alert_status }} {{ $vehicle->is_in_operation ? 'is-in-operation' : '' }}"



                    data-alert-status="{{ $vehicle->alert_status }}"



                    data-operational-status="{{ $vehicle->operational_status }}"



                    title="{{ $vehicle->is_in_operation ? 'Em operação com ' . ($vehicle->operation_driver_name ?? 'motorista não informado') . ' desde ' . ($vehicle->operation_started_at_formatted ?? '-') : '' }}"



                    data-search="{{ $searchText }}"



                    x-show="vehicleMatches(



                        $el.dataset.alertStatus,



                        $el.dataset.operationalStatus,



                        $el.dataset.search



                    )"



                    x-transition.opacity



                    @click.stop='openModal(@json($vehicle))'



                >















                    {{-- HEADER --}}











                    <div class="vehicle-header">







                        <div class="vehicle-main">







                            <div class="vehicle-type-icon">







                                <img



                                    src="{{ asset('images/' . $vehicle->type_icon) }}"



                                    alt="Tipo veículo"



                                >







                            </div>







                            <div class="vehicle-main-info">







                                <h3>



                                    {{ $vehicle->name }}



                                </h3>

                                @if($secondaryIdentifier)
                                    <span class="vehicle-plate">{{ $secondaryIdentifier }}</span>
                                @endif







                            </div>







                        </div>







                        @if($vehicle->operational_status !== 'operational')



                            @php

                                $statusElapsedDays =

                                    $vehicle->current_status_days !== null

                                        ? (int) $vehicle->current_status_days

                                        : null;

                            @endphp



                            <div class="vehicle-maintenance-status-group">



                                <span

                                    class="

                                        vehicle-operation-pill

                                        vehicle-status-pill

                                        status-{{ $vehicle->operational_status_tone }}

                                    "

                                >

                                    <i class="{{ chm_icon($vehicle->operational_status_icon) }}"></i>



                                    {{ $vehicle->operational_status_label }}

                                </span>



                                @if($statusElapsedDays !== null)



                                    <span class="vehicle-maintenance-stopped-time">



                                        <i class="bi bi-pause-circle"></i>



                                        @if($vehicle->operational_status === 'maintenance')



                                            @if($statusElapsedDays <= 0)



                                                Parado hoje



                                            @elseif($statusElapsedDays === 1)



                                                Parado há 1 dia



                                            @else



                                                Parado há {{ $statusElapsedDays }} dias



                                            @endif



                                        @else



                                            @if($statusElapsedDays <= 0)



                                                Desde hoje



                                            @elseif($statusElapsedDays === 1)



                                                Há 1 dia



                                            @else



                                                Há {{ $statusElapsedDays }} dias



                                            @endif



                                        @endif



                                    </span>



                                @endif



                            </div>



                        @elseif($vehicle->is_in_operation)



                            <span

                                class="vehicle-operation-pill in-operation"

                                title="Motorista: {{ $vehicle->operation_driver_name ?? 'Não informado' }} | Início: {{ $vehicle->operation_started_at_formatted ?? '-' }}"

                            >

                                <i class="bi bi-broadcast"></i>



                                Em operação

                            </span>



                        @endif







                    </div>



                    {{-- INFO --}}

                    @php
                        $fuelAverage = $vehicleFuelAverages[$vehicle->id] ?? [
                            'formatted' => 'N/D',
                            'status' => 'unavailable',
                            'title' => 'Dados insuficientes para calcular a média',
                        ];
                    @endphp

                    <div class="vehicle-info">






                        <div>






                            KM







                            <strong>







                                {{ number_format(



                                    $vehicle->current_km,



                                    0,



                                    ',',



                                    '.'



                                ) }}







                            </strong>







                        </div>







                        <div>







                            HORAS







                            <strong>







                                {{ $vehicle->current_hours }}h







                            </strong>







                        </div>

                        <div
                            class="vehicle-info-average {{ $fuelAverage['status'] }}"
                            title="{{ $fuelAverage['title'] }}"
                        >
                            MÉDIA

                            <strong>{{ $fuelAverage['formatted'] }}</strong>
                        </div>

                    </div>










                    <div



                        class="vehicle-card-bottom-action {{ $vehicle->main_alert ? 'has-alert ' . $vehicle->main_alert['status'] : 'is-clean' }}"



                    >



                        @if($vehicle->main_alert)







                            <div class="vehicle-card-alert-inline">







                                <i class="bi bi-exclamation-triangle"></i>







                                <span>



                                    {{ $shortMainAlert ?? $vehicle->main_alert['message'] }}



                                </span>







                            </div>







                        @else







                            <div class="vehicle-card-details-inline">







                                <span>



                                    Ver detalhes



                                </span>







                                <i class="bi bi-chevron-right"></i>







                            </div>







                        @endif



                    </div>







                    <div class="vehicle-operation-actions">







                    @if($vehicle->is_in_operation)







                        @php



                            $canCloseThisOperation =



                                $canManageOperationDrivers



                                ||



                                optional($vehicle->open_operation)->driver_id === auth()->id();



                        @endphp







                        @if($canCloseThisOperation)



                            <button



                                type="button"



                                class="vehicle-operation-action-btn close"



                                data-operation-id="{{ $vehicle->open_operation->id }}"



                                data-vehicle-name="{{ $vehicle->name }}"



                                data-vehicle-plate="{{ $vehicle->plate }}"



                                data-driver-name="{{ $vehicle->operation_driver_name }}"



                                data-start-km="{{ $vehicle->open_operation->start_vehicle_km }}"



                                data-start-hours="{{ $vehicle->open_operation->start_vehicle_hours }}"
                                data-current-km="{{ $vehicle->current_km }}"
                                data-current-hours="{{ $vehicle->current_hours }}"


                                data-start-datetime="{{ $vehicle->operation_started_at_formatted }}"



                                onclick="event.stopPropagation(); openCloseOperationModalFromButton(this)"



                            >



                                <i class="bi bi-square"></i>



                                Fechar operação



                            </button>



                        @endif







                    @else







                        @if($operationsEnabled && !$cannotStartOperation)





                            <button



                                type="button"



                                class="vehicle-operation-action-btn start"



                                data-vehicle-id="{{ $vehicle->id }}"



                                data-vehicle-name="{{ $vehicle->name }}"



                                data-vehicle-plate="{{ $vehicle->plate }}"



                                data-current-km="{{ $vehicle->current_km }}"



                                data-current-hours="{{ $vehicle->current_hours }}"



                                data-location-id="{{ $vehicle->operation_location_id }}"



                                data-location-name="{{ $vehicle->operation_location_name }}"



                                onclick="event.stopPropagation(); openStartOperationModalFromButton(this)"



                            >



                                <i class="bi bi-play-fill"></i>



                                Iniciar operação



                            </button>







                        @endif
                        @if($canAccessVehicleMaintenance || $canFillVehicle || $canAccessVehicleTires)
                            <div class="vehicle-card-actions">
                                @if($canAccessVehicleMaintenance)
                                    @php
                                        $maintenanceRestrictionReason = $aggregatedVehiclePolicy
                                            ->maintenanceRestrictionReason($vehicle, $vehicle->location);
                                    @endphp
                                    @if($maintenanceRestrictionReason)
                                    <button type="button" class="vehicle-card-action vehicle-card-action--maintenance is-disabled" disabled aria-disabled="true" title="Manutenção não permitida para veículos agregados nesta unidade." onclick="event.stopPropagation();">
                                        <span class="vehicle-action-icon"><i class="bi bi-lock"></i></span>
                                        <span class="sr-only">{{ $maintenanceRestrictionReason }}</span>
                                    </button>
                                    @else
                                    <a href="{{ route('vehicle.maintenance.index', $vehicle) }}" class="vehicle-card-action vehicle-card-action--maintenance" title="Abrir manutenção do veículo" aria-label="Abrir manutenção do veículo" onclick="event.stopPropagation();">

                                        <span class="vehicle-action-hover-arrow" aria-hidden="true">&uarr;</span>

                                        <span class="vehicle-action-icon"><i class="bi bi-wrench-adjustable"></i></span>

                                    </a>
                                    @endif
                                @endif

                                @if($canFillVehicle)
                                    <a href="{{ str_replace('__vehicle_id__', $vehicle->id, $dashboardFuelFillingUrlTemplate) }}" class="vehicle-card-action vehicle-card-action--fuel" title="Lançar abastecimento do veículo" aria-label="Lançar abastecimento do veículo" onclick="event.stopPropagation();">

                                        <span class="vehicle-action-hover-arrow" aria-hidden="true">&uarr;</span>

                                        <span class="vehicle-action-icon"><i class="bi bi-fuel-pump"></i></span>

                                    </a>
                                @endif

                                @if($canAccessVehicleTires)
                                    <a href="{{ route('vehicles.tires.index', $vehicle) }}" class="vehicle-card-action vehicle-card-action--tires" title="Abrir pneus do veículo" aria-label="Abrir pneus do veículo" onclick="event.stopPropagation();">

                                        <span class="vehicle-action-hover-arrow" aria-hidden="true">&uarr;</span>

                                        <span class="vehicle-action-icon"><i class="bi bi-circle"></i></span>

                                    </a>
                                @endif
                            </div>
                        @endif





                    @endif







                </div>



                </div>







            @endforeach







        </section>



    </main>







    {{-- COLUNA LATERAL --}}
    <aside class="dashboard-side-column">

            {{-- PRIORIDADES OPERACIONAIS --}}



            <section class="operation-panel compact">







                <div class="operation-panel-header">







                    <div>



                        <small>



                            Hoje



                        </small>







                        <h2>



                            Prioridades operacionais



                        </h2>



                    </div>







                    <div class="operation-panel-icon">



                        <i class="bi bi-activity"></i>



                    </div>







                </div>







                <div class="operation-task-list">



                {{-- VEÍCULOS CRÍTICOS --}}

                @if($criticalVehicles > 0)



                    <button

                        type="button"

                        class="operation-task danger"

                        @click="setFilter('danger')"

                    >



                        <div class="task-icon">

                            <i class="bi bi-exclamation-triangle"></i>

                        </div>



                        <div class="task-content">



                            <div class="task-title-row">

                                <strong>Revisar veículos críticos</strong>



                                <span class="task-priority-badge danger">

                                    Crítico

                                </span>

                            </div>



                            <p>

                                {{ $criticalVehicles }}

                                {{ $criticalVehicles === 1 ? 'veículo exige' : 'veículos exigem' }}

                                atenção imediata.

                            </p>



                        </div>



                        <i

                            class="task-arrow"

                            class="bi bi-chevron-right"

                        ></i>



                    </button>



                @endif





                {{-- TANQUES CRÍTICOS --}}

                @if($criticalFuelTankCount > 0)



                    <a

                        href="{{ route('fuel.tanks.index') }}"

                        class="operation-task danger"

                    >



                        <div class="task-icon">

                            <i class="bi bi-fuel-pump"></i>

                        </div>



                        <div class="task-content">



                            <div class="task-title-row">

                                <strong>Combustível em nível crítico</strong>



                                <span class="task-priority-badge danger">

                                    Urgente

                                </span>

                            </div>



                            <p>

                                {{ $criticalFuelTankCount }}

                                {{ $criticalFuelTankCount === 1 ? 'tanque está' : 'tanques estão' }}

                                abaixo do nível mínimo.

                            </p>



                        </div>



                        <i

                            class="task-arrow"

                            class="bi bi-chevron-right"

                        ></i>



                    </a>



                @endif





                {{-- ESTOQUE CRÍTICO --}}

                @if($criticalStockCount > 0)



                    <a

                        href="{{ route('stock.index') }}"

                        class="operation-task danger"

                    >



                        <div class="task-icon">

                            <i class="bi bi-box-seam"></i>

                        </div>



                        <div class="task-content">



                            <div class="task-title-row">

                                <strong>Reposição de estoque</strong>



                                <span class="task-priority-badge danger">

                                    Crítico

                                </span>

                            </div>



                            <p>

                                {{ $criticalStockCount }}

                                {{ $criticalStockCount === 1 ? 'item está' : 'itens estão' }}

                                em nível crítico.

                            </p>



                        </div>



                        <i

                            class="task-arrow"

                            class="bi bi-chevron-right"

                        ></i>



                    </a>



                @endif





                {{-- ATUALIZAÇÃO KM/HR --}}

                @if($operationalUpdatePendingCount > 0)



                    <a

                        href="{{ route('vehicle.quick-update') }}"

                        class="operation-task warning"

                    >



                        <div class="task-icon">

                            <i class="bi bi-speedometer2"></i>

                        </div>



                        <div class="task-content">



                            <div class="task-title-row">

                                <strong>Atualizar KM/HR</strong>



                                <span class="task-priority-badge warning">

                                    Atenção

                                </span>

                            </div>



                            <p>

                                {{ $operationalUpdatePendingCount }}

                                {{ $operationalUpdatePendingCount === 1 ? 'veículo aguarda' : 'veículos aguardam' }}

                                atualização operacional.

                            </p>



                        </div>



                        <i

                            class="task-arrow"

                            class="bi bi-chevron-right"

                        ></i>



                    </a>



                @endif





                {{-- NFS DE COMBUSTÍVEL PENDENTES --}}
                @if($pendingFuelReceiptInvoiceCount > 0)

                    <a
                        href="{{ route('fuel.receipts.history') }}"
                        class="operation-task warning"
                    >
                        <div class="task-icon">
                            <i class="bi bi-receipt"></i>
                        </div>

                        <div class="task-content">

                            <div class="task-title-row">

                                <strong>
                                    Notas fiscais pendentes
                                </strong>

                                <span class="task-priority-badge warning">
                                    Atenção
                                </span>

                            </div>

                            <p>
                                {{ $pendingFuelReceiptInvoiceCount }}

                                {{
                                    $pendingFuelReceiptInvoiceCount === 1
                                        ? 'recebimento aguarda'
                                        : 'recebimentos aguardam'
                                }}

                                anexo da nota fiscal.
                            </p>

                        </div>

                        <i
                            class="task-arrow bi bi-chevron-right"
                        ></i>

                    </a>

                @endif


                {{-- TANQUES EM ATENÇÃO --}}

                @if($warningFuelTankCount > 0)



                    <a

                        href="{{ route('fuel.tanks.index') }}"

                        class="operation-task warning"

                    >



                        <div class="task-icon">

                            <i class="bi bi-fuel-pump"></i>

                        </div>



                        <div class="task-content">



                            <div class="task-title-row">

                                <strong>Monitorar tanques</strong>



                                <span class="task-priority-badge warning">

                                    Atenção

                                </span>

                            </div>



                            <p>

                                {{ $warningFuelTankCount }}

                                {{ $warningFuelTankCount === 1 ? 'tanque está' : 'tanques estão' }}

                                com menos de 30% da capacidade.

                            </p>



                        </div>



                        <i

                            class="task-arrow"

                            class="bi bi-chevron-right"

                        ></i>



                    </a>



                @endif





                {{-- ESTOQUE EM ATENÇÃO --}}

                @if($warningStockCount > 0)



                    <a

                        href="{{ route('stock.index') }}"

                        class="operation-task stock-warning"

                    >



                        <div class="task-icon">

                            <i class="bi bi-boxes"></i>

                        </div>



                        <div class="task-content">



                            <div class="task-title-row">

                                <strong>Estoque próximo do mínimo</strong>



                                <span class="task-priority-badge warning">

                                    Atenção

                                </span>

                            </div>



                            <p>

                                {{ $warningStockCount }}

                                {{ $warningStockCount === 1 ? 'item precisa' : 'itens precisam' }}

                                de acompanhamento.

                            </p>



                        </div>



                        <i

                            class="task-arrow"

                            class="bi bi-chevron-right"

                        ></i>



                    </a>



                @endif





                {{-- OPERAÇÃO EM DIA --}}

                @if(

                    $criticalVehicles == 0

                    &&

                    $warningVehicles == 0

                    &&

                    $operationalUpdatePendingCount == 0

                    &&

                    $criticalStockCount == 0

                    &&

                    $warningStockCount == 0

                    &&

                    $criticalFuelTankCount == 0

                    &&

                    $warningFuelTankCount == 0
                    && $pendingFuelReceiptInvoiceCount == 0

                )



                    <div class="operation-task success static">



                        <div class="task-icon">

                            <i class="bi bi-check-circle"></i>

                        </div>



                        <div class="task-content">



                            <strong>

                                Operação em dia

                            </strong>



                            <p>

                                Nenhuma prioridade operacional pendente.

                            </p>



                        </div>



                    </div>



                @endif



            </div>







            </section>






        <section class="side-widget operational-indicators-widget">

            <div class="side-widget-header">
                <div>
                    <small>Últimos 30 dias</small>
                    <h3>Indicadores críticos</h3>
                </div>

                <i class="bi bi-bar-chart-line"></i>
            </div>

            <div class="operational-indicator-section">
                <div class="operational-indicator-title">
                    <span>Maiores consumos</span>
                    <small>Litros abastecidos</small>
                </div>

                <div class="operational-ranking-list">
                    @forelse($fuelConsumptionRanking as $index => $item)
                        <div class="operational-ranking-item">

                            <div class="operational-ranking-main">
                                <strong>{{ $item['vehicle_label'] }}</strong>
                                <small>
                                    {{ $item['product'] }}{{ $item['has_multiple_products'] ? ' + outros' : '' }}
                                </small>
                            </div>

                            <div class="operational-ranking-value">
                                <strong>{{ number_format($item['liters'], 1, ',', '.') }} L</strong>
                                @if($canViewDashboardCosts)
                                    <small>R$ {{ number_format($item['total_cost'], 2, ',', '.') }}</small>
                                @endif
                            </div>
                        </div>

                    @empty
                        <p class="operational-indicator-empty">Sem abastecimentos no período.</p>
                    @endforelse
                </div>
            </div>

            <div class="operational-indicator-section">
                <div class="operational-indicator-title">
                    <span>Parados há mais tempo</span>
                    <small>Situação atual</small>
                </div>

                <div class="operational-ranking-list">
                    @forelse($longestStoppedVehicles as $index => $item)
                        <div class="operational-ranking-item stopped">

                            <div class="operational-ranking-main">
                                <strong>{{ $item['vehicle_label'] }}</strong>
                                <small title="{{ $item['reason'] }}">
                                    {{ $item['status'] }}
                                    @if($item['started_at'])
                                        · desde {{ $item['started_at'] }}
                                    @endif
                                </small>
                            </div>

                            <div class="operational-ranking-value danger">
                                <strong>{{ $item['days'] !== null ? $item['days'] : '—' }}</strong>
                                <small>{{ $item['days'] === 1 ? 'dia' : 'dias' }}</small>
                            </div>
                        </div>
                    @empty
                        <p class="operational-indicator-empty">Nenhum veículo parado no momento.</p>
                    @endforelse
                </div>
            </div>

            <div class="operational-indicator-section cost-comparison-section">
                <div class="operational-indicator-title">
                    <span>Custos nos últimos 6 meses</span>
                    <small>Combustível × manutenção</small>
                </div>

                @if($canViewDashboardCosts)
                    <div class="operational-cost-legend">
                        <span><i class="fuel"></i>Combustível</span>
                        <span><i class="maintenance"></i>Manutenção</span>
                    </div>

                    <div class="operational-cost-chart">
                        @foreach($sixMonthCostSeries as $month)
                            <div class="operational-cost-month">
                                <div class="operational-cost-bars">
                                    <span
                                        class="operational-cost-bar fuel"
                                        style="height: {{ max(2, $month['fuel_percent']) }}%"
                                        title="Combustível: R$ {{ number_format($month['fuel'], 2, ',', '.') }}"
                                    ></span>
                                    <span
                                        class="operational-cost-bar maintenance"
                                        style="height: {{ max(2, $month['maintenance_percent']) }}%"
                                        title="Manutenção: R$ {{ number_format($month['maintenance'], 2, ',', '.') }}"
                                    ></span>
                                </div>
                                <small>{{ $month['label'] }}</small>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="operational-indicator-empty restricted">
                        <i class="bi bi-lock"></i>
                        Custos restritos para seu perfil.
                    </p>
                @endif
            </div>

        </section>

        {{-- RESUMO DA FROTA --}}


        <section class="side-widget fleet-summary-widget">



            <div class="side-widget-header">



                <div>

                    <small>Situação</small>



                    <h3>

                        Resumo da frota

                    </h3>

                </div>



                <i class="bi bi-bar-chart"></i>



            </div>



            <div class="fleet-summary-list">



                @foreach($statusSummary as $statusItem)



                    @php

                        $filterName = match ($statusItem['status']) {

                            'operational' => 'operational',

                            'maintenance' => 'maintenance',

                            default => 'status:' . $statusItem['status'],

                        };

                    @endphp



                    <button

                        type="button"

                        class="

                            fleet-summary-item

                            {{ $statusItem['tone'] }}

                        "

                        @click="setFilter('{{ $filterName }}')"

                    >

                        <span class="fleet-summary-label">



                            <i class="{{ chm_icon($statusItem['icon']) }}"></i>



                            <span>

                                {{ $statusItem['label'] }}

                            </span>



                        </span>



                        <strong>

                            {{ $statusItem['count'] }}

                        </strong>



                    </button>



                @endforeach



            </div>



        </section>



{{-- AÇÕES RÁPIDAS --}}

            @if(false)



            <section class="side-widget">







                <div class="side-widget-header">







                    <div>



                        <small>



                            Atalhos



                        </small>







                        <h3>



                            Ações rápidas



                        </h3>



                    </div>







                    <i class="bi bi-lightning-charge"></i>







                </div>







                <div class="quick-actions-grid">







                    <a



                        href="{{ route('vehicle.quick-update') }}"



                        class="quick-action-card"



                    >



                        <i class="bi bi-speedometer2"></i>







                        <span>



                            Atualizar KM/HR



                        </span>



                    </a>







                    <a



                        href="{{ route('stock.index') }}"



                        class="quick-action-card"



                    >



                        <i class="bi bi-boxes"></i>







                        <span>



                            Estoque



                        </span>



                    </a>







                    <a



                        href="{{ route('vehicles.index') }}"



                        class="quick-action-card"



                    >



                        <i class="bi bi-truck"></i>







                        <span>



                            Veículos



                        </span>



                    </a>







                    <button



                        type="button"



                        class="quick-action-card"



                        @click="openChecklistFlow()"

                        style="display:none"

                    >



                        <i class="bi bi-clipboard-check"></i>







                        <span>



                            Checklist



                        </span>



                    </button>







                </div>







            </section>



            @endif

    </aside>



</div>













    {{-- MODAL --}}



{{-- MODAL ENXUTO / CENTRAL DO VEÍCULO --}}



<div



    class="modal-overlay"



    x-show="open"



    x-transition.opacity



    style="display:none;"



    @click.self="close()"



>



    <div



        class="vehicle-center-modal"



        x-transition.scale.origin.center



    >







        {{-- HEADER --}}



        <div class="vehicle-center-header">







            <div class="vehicle-center-identity">







                <div class="vehicle-center-icon">







                    <img



                        :src="vehicle.icon_url"



                        alt="Veículo"



                    >







                </div>







                <div>







                    <div class="vehicle-center-title-row">







                        <h2



                            x-text="vehicle.name"



                        ></h2>















                    </div>







                    <div class="vehicle-center-meta">







                        <span x-text="vehicleSecondaryIdentifier(vehicle)"></span>







                        <span>



                            •



                        </span>







                        <span



                            x-text="vehicle.brand ?? 'Sem marca'"



                        ></span>







                        <template x-if="vehicle.year">



                            <span>



                                • <span x-text="vehicle.year"></span>



                            </span>



                        </template>







                    </div>







                </div>







            </div>







            <div class="vehicle-center-header-actions vehicle-center-header-actions--expanded">

                <a
                    class="vehicle-center-open-page"
                    :href="`/vehicles/${vehicle.id}/details`"
                    x-show="vehicleActions.panel"
                    title="Abrir painel completo do veículo"
                >
                    <i class="bi bi-layout-text-window-reverse"></i>
                    <span>Painel completo</span>
                </a>

                <a
                    class="vehicle-center-open-page"
                    :href="`/vehicle/${vehicle.id}/history`"
                    x-show="vehicleActions.history"
                    title="Consultar histórico veicular"
                >
                    <i class="bi bi-clock-history"></i>
                    <span>Histórico veicular</span>
                </a>

                <a
                    class="vehicle-center-open-page"
                    :href="`/vehicles/${vehicle.id}/edit`"
                    x-show="vehicleActions.edit"
                    title="Editar cadastro do veículo"
                >
                    <i class="bi bi-pencil-square"></i>
                    <span>Editar veículo</span>
                </a>

                <button
                    type="button"
                    class="vehicle-center-close"
                    @click="close()"
                    title="Fechar"
                >
                    <i class="bi bi-x-lg"></i>
                </button>

            </div>







        </div>









        {{-- BODY V2 --}}
        <div class="vehicle-modal-v3">

            {{-- ATALHOS + GRÁFICO --}}

<div class="vehicle-modal-v3-main">

    <div class="vehicle-modal-v3-summary-row">

        <div class="vehicle-modal-v3-summary-status">
            <section class="vehicle-modal-v3-status-compact">

                                <div class="vehicle-modal-v3-status-copy">

                                    <small>Situação operacional</small>

                                    <div class="vehicle-modal-v3-status-line">

                                        <span
                                            class="vehicle-modal-v3-status-badge"
                                            :class="'is-' + originalOperationalStatus"
                                        >
                                            <i
                                                class="bi"
                                                :class="
                                                    originalOperationalStatus === 'operational'
                                                        ? 'bi-check-circle'
                                                        : originalOperationalStatus === 'maintenance'
                                                            ? 'bi-wrench-adjustable'
                                                            : 'bi-exclamation-circle'
                                                "
                                            ></i>

                                            <span
                                                x-text="
    originalOperationalStatus === 'operational'
        ? 'Operacional'
        : originalOperationalStatus === 'maintenance'
            ? 'Em manutenção'
            : originalOperationalStatus === 'inactive'
                ? 'Inativo'
                : originalOperationalStatus === 'inoperant'
                    ? 'Inoperante'
                    : originalOperationalStatus === 'accident'
                        ? 'Sinistro'
                        : originalOperationalStatus === 'support'
                            ? 'Socorro'
                            : originalOperationalStatus === 'testing'
                                ? 'Em testes'
                                : originalOperationalStatus === 'transfer'
                                    ? 'Em transferência'
                                    : originalOperationalStatus === 'transferred'
                                        ? 'Transferido'
                                        : originalOperationalStatus
"
                                            ></span>
                                        </span>

                                    </div>

                                </div>

                                <button
                                    type="button"
                                    class="vehicle-modal-v3-status-compact-action"
                                    @click="
                                        selectedOperationalStatus = originalOperationalStatus;
                                        statusReason = '';
                                        window.dispatchEvent(
                                            new CustomEvent('open-vehicle-status-modal')
                                        );
                                    "
                                >
                                    <i class="bi bi-pencil"></i>
                                    Alterar
                                </button>

                            </section>
        </div>

        <div class="vehicle-modal-v3-summary-kpis">
            <div class="vehicle-modal-kpi-strip">



                        <div class="vehicle-modal-kpi vehicle-modal-kpi--editable">

    <small>Hodômetro</small>

    <div class="vehicle-modal-inline-reading">

        <input
            type="number"
            min="0"
            step="1"
            inputmode="numeric"
            x-model="inlineKm"
            :min="originalKm"
            :readonly="!vehicleActions.edit"
            aria-label="Hodômetro atual"
        >

        <span>km</span>

    </div>

    <button
        type="button"
        class="vehicle-modal-inline-save"
        x-show="vehicleActions.edit"
        :disabled="
            savingInlineKm
            ||
            Number(inlineKm) === Number(vehicle.current_km ?? 0)
        "
        @click="saveInlineVehicleReading('km')"
    >
        <i class="bi bi-arrow-repeat"></i>
        <span x-text="savingInlineKm ? 'Atualizando...' : 'Atualizar'"></span>
    </button>

</div>



                        <div class="vehicle-modal-kpi vehicle-modal-kpi--editable">

    <small>Horímetro</small>

    <div class="vehicle-modal-inline-reading">

        <input
            type="number"
            min="0"
            step="1"
            inputmode="numeric"
            x-model="inlineHours"
            :min="originalHours"
            :readonly="!vehicleActions.edit"
            aria-label="Horímetro atual"
        >

        <span>h</span>

    </div>

    <button
        type="button"
        class="vehicle-modal-inline-save"
        x-show="vehicleActions.edit"
        :disabled="
            savingInlineHours
            ||
            Number(inlineHours) === Number(vehicle.current_hours ?? 0)
        "
        @click="saveInlineVehicleReading('hours')"
    >
        <i class="bi bi-arrow-repeat"></i>
        <span x-text="savingInlineHours ? 'Atualizando...' : 'Atualizar'"></span>
    </button>

</div>



                        <div class="vehicle-modal-kpi">

                            <small>Tempo parado</small>

                            <strong x-text="vehicle.total_downtime_text ?? '--'"></strong>

                            <span x-text="vehicle.total_downtime_subtext ?? ''"></span>

                        </div>



                        <div class="vehicle-modal-kpi">

                            <small>Disponível</small>

                            <strong x-text="vehicle.availability_text ?? '--'"></strong>

                            <span x-text="vehicle.availability_subtext ?? ''"></span>

                        </div>



                        <div

                            class="vehicle-modal-kpi"

                            :class="vehicle.alert_status == 'danger' ? 'danger' : vehicle.alert_status == 'warning' ? 'warning' : 'success'"

                role="button"
                tabindex="0"
                title="Ver todos os alertas"
                @click="
                    if (vehicle.alerts && vehicle.alerts.length) {
                        window.dispatchEvent(
                            new CustomEvent('open-vehicle-alerts-modal')
                        );
                    }
                "
                @keydown.enter.prevent="
                    if (vehicle.alerts && vehicle.alerts.length) {
                        window.dispatchEvent(
                            new CustomEvent('open-vehicle-alerts-modal')
                        );
                    }
                "

                        >

                            <small>Alertas</small>

                            <strong

                                x-text="vehicle.alert_status == 'danger' ? 'Crítico' : vehicle.alert_status == 'warning' ? 'Atenção' : 'OK'"

                            ></strong>

                            <span

                                x-text="(vehicle.alerts ? vehicle.alerts.length : 0) + ((vehicle.alerts && vehicle.alerts.length === 1) ? ' alerta ativo' : ' alertas ativos')"

                            ></span>

                        </div>



                    </div>
        </div>

    </div>

    <div class="vehicle-modal-v3-work-row">

        <aside class="vehicle-modal-v3-actions-column">
            <nav class="vehicle-modal-v3-nav">
            <a
                                    :href="`/vehicle/${vehicle.id}/maintenance`"
                                    x-show="vehicleActions.maintenance"
                                    class="vehicle-modal-v3-nav-item"
                                >
                                    <i class="bi bi-wrench-adjustable"></i>

                                    <span>
                                        <strong>Setor de Manutenções</strong>
                                        <small>Ordens e serviços</small>
                                    </span>

                                    <i class="bi bi-chevron-right"></i>
                                </a>
            <a
                                    :href="`/vehicles/${vehicle.id}/tires`"
                                    x-show="vehicleActions.tires"
                                    class="vehicle-modal-v3-nav-item"
                                >
                                    <i class="bi bi-record-circle"></i>

                                    <span>
                                        <strong>Setor de Pneus</strong>
                                        <small>Controle de pneus</small>
                                    </span>

                                    <i class="bi bi-chevron-right"></i>
                                </a>
            <a
                                        :href="fuelFillingUrl(vehicle.id)"
                                        x-show="vehicleActions.fuel"
                                        class="vehicle-modal-v3-nav-item is-accent"
                                    >
                                        <i class="bi bi-fuel-pump"></i>

                                        <span>
                                            <strong>Novo abastecimento</strong>
                                            <small>Registrar consumo</small>
                                        </span>

                                        <i class="bi bi-chevron-right"></i>
                                    </a>
            <a
                                        :href="`/fuel/fillings/history?vehicle_id=${vehicle.id}`"
                                        x-show="vehicleActions.fuel_history"
                                        class="vehicle-modal-v3-nav-item is-accent-soft"
                                    >
                                        <i class="bi bi-list-ul"></i>

                                        <span>
                                            <strong>Últimos abastecimentos</strong>
                                            <small>Ver histórico</small>
                                        </span>

                                        <i class="bi bi-chevron-right"></i>
                                    </a>
            </nav>
        </aside>

        <div class="vehicle-modal-v3-chart-column">
            <section class="vehicle-modal-v3-card vehicle-modal-v3-chart-card">

    <header class="vehicle-modal-v3-card-head vehicle-modal-v3-chart-head">

        <div class="vehicle-modal-v3-chart-title">

            <small
                x-text="
                    activeVehicleTrend === 'km'
                        ? 'Rodagem'
                        : activeVehicleTrend === 'efficiency'
                            ? 'Consumo'
                            : 'Combustível'
                "
            ></small>

            <h3
                x-text="
                    activeVehicleTrend === 'km'
                        ? 'Rodagem diária entre leituras'
                        : activeVehicleTrend === 'efficiency'
                            ? 'Evolução de eficiência'
                            : 'Abastecimentos recentes'
                "
            ></h3>

            <p
                x-text="
                    activeVehicleTrend === 'km'
                        ? 'KM por dia entre cada leitura'
                        : activeVehicleTrend === 'efficiency'
                            ? 'Rendimento entre abastecimentos em km/L'
                            : 'Volume abastecido em litros'
                "
            ></p>

        </div>

        <div class="vehicle-modal-chart-head-actions">

            <div class="vehicle-modal-chart-toggle">

                <button
                    type="button"
                    class="vehicle-modal-chart-toggle-btn"
                    :class="{ 'is-active': activeVehicleTrend === 'km' }"
                    @click="activeVehicleTrend = 'km'"
                >
                    <i class="bi bi-graph-up"></i>
                    Evolução KM
                </button>

                <button
                    type="button"
                    class="vehicle-modal-chart-toggle-btn"
                    :class="{ 'is-active': activeVehicleTrend === 'fuel' }"
                    @click="activeVehicleTrend = 'fuel'"
                >
                    <i class="bi bi-fuel-pump"></i>
                    Abastecimentos
                </button>

                <button
                    type="button"
                    class="vehicle-modal-chart-toggle-btn"
                    :class="{ 'is-active': activeVehicleTrend === 'efficiency' }"
                    @click="activeVehicleTrend = 'efficiency'"
                >
                    <i class="bi bi-speedometer"></i>
                    Evolução KM/L
                </button>

            </div>

            <a
                :href="`/fuel/fillings/history?vehicle_id=${vehicle.id}`"
                x-show="
                    activeVehicleTrend === 'fuel'
                    && vehicleActions.fuel_history
                "
            >
                Ver todos
                <i class="bi bi-arrow-right"></i>
            </a>

        </div>

    </header>

    <div
        class="vehicle-modal-chart vehicle-modal-chart--large"
        x-data
        x-effect="
            vehicle.id;
            vehicle.fuel_trend;
            vehicle.km_trend;
            vehicle.efficiency_trend;
            activeVehicleTrend;

            $nextTick(() => {
                if (window.renderDashboardVehicleTrendChart) {
                    window.renderDashboardVehicleTrendChart(
                        $el,
                        activeVehicleTrend === 'km'
                            ? (
                                Array.isArray(vehicle.km_trend)
                                    ? vehicle.km_trend
                                    : []
                            )
                            : activeVehicleTrend === 'efficiency'
                                ? (
                                    Array.isArray(vehicle.efficiency_trend)
                                        ? vehicle.efficiency_trend
                                        : []
                                )
                                : (
                                    Array.isArray(vehicle.fuel_trend)
                                        ? vehicle.fuel_trend
                                        : []
                                ),
                        activeVehicleTrend
                    );
                }
            })
        "
    >
        <div class="vehicle-modal-chart-empty">
            <i class="bi bi-graph-up"></i>
            <span>Carregando gráfico...</span>
        </div>
    </div>

</section>
        </div>

    </div>

</div>



            {{-- ALERTAS + MANUTENÇÕES --}}
            <div class="vehicle-modal-v3-secondary">

                <section class="vehicle-modal-v3-card">

                    <header class="vehicle-modal-v3-card-head">
                        <div>
                            <small>Monitoramento</small>
                            <h3>Alertas e monitoramento</h3>
                        </div>

                        <button
                            type="button"
                            class="vehicle-modal-v3-count vehicle-modal-v3-count--button"
                            x-show="vehicle.alerts && vehicle.alerts.length > 3"
                            x-text="'+' + ((vehicle.alerts?.length || 0) - 3)"
                            title="Ver todos os alertas"
                            @click="
                                window.dispatchEvent(
                                    new CustomEvent('open-vehicle-alerts-modal')
                                )
                            "
                        ></button>
                    </header>

                    <template x-if="!vehicle.alerts || vehicle.alerts.length === 0">
                        <div class="vehicle-modal-v3-empty">
                            <i class="bi bi-check-circle"></i>
                            Nenhum alerta ativo.
                        </div>
                    </template>

                    <div class="vehicle-modal-v3-list">

                        <template
                            x-for="(alert, index) in (vehicle.alerts || []).slice(0, 3)"
                            :key="'alert-v3-' + index"
                        >
                            <article class="vehicle-modal-v3-alert-row">

                                <span
                                    class="vehicle-modal-v3-alert-marker"
                                    :class="alert.status || 'warning'"
                                ></span>

                                <div>
                                    <strong
                                        x-text="alert.message || 'Alerta operacional'"
                                    ></strong>

                                    <small
                                        x-text="alert.procedure || 'Monitoramento do veículo'"
                                    ></small>
                                </div>

                            </article>
                        </template>

                    </div>

                </section>


                <section class="vehicle-modal-v3-card">

                    <header class="vehicle-modal-v3-card-head">
                        <div>
                            <small>Manutenção</small>
                            <h3>Últimas manutenções</h3>
                        </div>

                        <a
                            :href="`/vehicle/${vehicle.id}/maintenance`"
                            x-show="vehicleActions.maintenance"
                        >
                            Ver histórico
                            <i class="bi bi-arrow-right"></i>
                        </a>
                    </header>

                    <template
                        x-if="
                            !vehicle.recent_maintenances_modal
                            ||
                            vehicle.recent_maintenances_modal.length === 0
                        "
                    >
                        <div class="vehicle-modal-v3-empty">
                            <i class="bi bi-clipboard"></i>
                            Nenhuma manutenção registrada.
                        </div>
                    </template>

                    <div class="vehicle-modal-v3-list">

                        <template
                            x-for="maintenance in (vehicle.recent_maintenances_modal || [])"
                            :key="'maintenance-v3-' + maintenance.id"
                        >
                            <a
                                class="vehicle-modal-v3-maintenance-row"
                                :href="maintenance.url"
                            >

                                <span class="vehicle-modal-v3-maintenance-icon">
                                    <i class="bi bi-wrench-adjustable"></i>
                                </span>

                                <div class="vehicle-modal-v3-maintenance-main">

                                    <div class="vehicle-modal-v3-maintenance-title">
                                        <strong
                                            x-text="'#' + maintenance.id + ' · ' + maintenance.name"
                                        ></strong>

                                        <span
                                            class="vehicle-modal-v3-maintenance-status"
                                            :class="'is-' + (maintenance.workflow_status || 'open')"
                                            x-text="maintenance.workflow_label"
                                        ></span>
                                    </div>

                                    <div class="vehicle-modal-v3-maintenance-meta">
                                        <span x-text="maintenance.reason"></span>

                                        <template x-if="maintenance.service_status_label">
                                            <span
                                                x-text="maintenance.service_status_label"
                                            ></span>
                                        </template>
                                    </div>

                                    <div class="vehicle-modal-v3-maintenance-dates">

                                        <span>
                                            <i class="bi bi-box-arrow-in-right"></i>
                                            <b>Entrada:</b>
                                            <span x-text="maintenance.started_at || '—'"></span>
                                        </span>

                                        <template x-if="maintenance.finished_at">
                                            <span>
                                                <i class="bi bi-check2-circle"></i>
                                                <b>Saída:</b>
                                                <span x-text="maintenance.finished_at"></span>
                                            </span>
                                        </template>

                                    </div>

                                </div>

                                <div class="vehicle-modal-v3-maintenance-side">

                                    <template x-if="maintenance.can_view_cost">
                                        <strong
                                            class="vehicle-modal-v3-maintenance-cost"
                                            x-text="
                                                Number(maintenance.total_cost || 0)
                                                    .toLocaleString(
                                                        'pt-BR',
                                                        {
                                                            style: 'currency',
                                                            currency: 'BRL'
                                                        }
                                                    )
                                            "
                                        ></strong>
                                    </template>

                                    <i class="bi bi-chevron-right"></i>

                                </div>

                            </a>
                        </template>

                    </div>

                </section>

            </div>

        </div>



        {{-- MODAL DE ALERTAS DO VEÍCULO --}}
        <div
            class="vehicle-alerts-modal-overlay"
            x-data="{ open: false }"
            x-show="open"
            x-cloak
            style="display:none;"
            @open-vehicle-alerts-modal.window="open = true"
            @keydown.escape.window="open = false"
            @click.self="open = false"
        >

            <section
                class="vehicle-alerts-modal"
                x-show="open"
                x-transition.opacity.scale.95
            >

                <header class="vehicle-alerts-modal-head">

                    <div>

                        <small>Monitoramento</small>

                        <h3>Alertas do veículo</h3>

                        <p>
                            <strong x-text="vehicle.name || vehicle.plate || 'Veículo'"></strong>

                            <span
                                x-text="
                                    ' · '
                                    + ((vehicle.alerts?.length || 0))
                                    + (
                                        (vehicle.alerts?.length || 0) === 1
                                            ? ' alerta ativo'
                                            : ' alertas ativos'
                                    )
                                "
                            ></span>
                        </p>

                    </div>

                    <button
                        type="button"
                        class="vehicle-alerts-modal-close"
                        @click="open = false"
                        aria-label="Fechar"
                    >
                        <i class="bi bi-x-lg"></i>
                    </button>

                </header>


                <div class="vehicle-alerts-modal-body">

                    <template
                        x-if="!vehicle.alerts || vehicle.alerts.length === 0"
                    >
                        <div class="vehicle-alerts-modal-empty">

                            <i class="bi bi-check-circle"></i>

                            <strong>Nenhum alerta ativo</strong>

                            <span>
                                Não há alertas de monitoramento para este veículo.
                            </span>

                        </div>
                    </template>


                    <div
                        class="vehicle-alerts-modal-list"
                        x-show="vehicle.alerts && vehicle.alerts.length"
                    >

                        <template
                            x-for="(alert, index) in (vehicle.alerts || [])"
                            :key="'alert-modal-' + index"
                        >

                            <article
                                class="vehicle-alerts-modal-row"
                                :class="'is-' + (alert.status || 'warning')"
                            >

                                <span
                                    class="vehicle-alerts-modal-marker"
                                    :class="alert.status || 'warning'"
                                >
                                    <i
                                        class="bi"
                                        :class="
                                            alert.status === 'danger'
                                                ? 'bi-exclamation-octagon'
                                                : alert.status === 'success'
                                                    ? 'bi-check-circle'
                                                    : 'bi-exclamation-triangle'
                                        "
                                    ></i>
                                </span>


                                <div class="vehicle-alerts-modal-copy">

                                    <strong
                                        x-text="
                                            alert.message
                                            || 'Alerta operacional'
                                        "
                                    ></strong>

                                    <small
                                        x-text="
                                            alert.procedure
                                            || 'Monitoramento do veículo'
                                        "
                                    ></small>

                                </div>


                                <span
                                    class="vehicle-alerts-modal-level"
                                    :class="alert.status || 'warning'"
                                    x-text="
                                        alert.status === 'danger'
                                            ? 'Crítico'
                                            : alert.status === 'success'
                                                ? 'Informativo'
                                                : 'Atenção'
                                    "
                                ></span>

                            </article>

                        </template>

                    </div>

                </div>

            </section>

        </div>


        {{-- MODAL STATUS OPERACIONAL --}}
        <div
            class="vehicle-status-modal-overlay"
            x-data="{ open: false }"
            x-show="open"
            x-cloak
            @open-vehicle-status-modal.window="open = true"
            @keydown.escape.window="open = false"
            @click.self="open = false"
        >

            <section
                class="vehicle-status-modal"
                role="dialog"
                aria-modal="true"
            >

                <header class="vehicle-status-modal-head">

                    <div>
                        <small>Situação do veículo</small>
                        <h3>Status operacional</h3>

                        <p>
                            Consulte ou altere a situação operacional do veículo.
                        </p>
                    </div>

                    <button
                        type="button"
                        @click="open = false"
                    >
                        <i class="bi bi-x-lg"></i>
                    </button>

                </header>


                <div class="vehicle-status-modal-current">

                    <span>Status atual</span>

                    <strong
                        x-text="operationalStatusLabel(originalOperationalStatus)"
                    ></strong>

                </div>


                <template x-if="originalOperationalStatus !== 'maintenance'">

                    <div class="vehicle-status-modal-form">

                        <label>
                            Novo status

                            <select x-model="selectedOperationalStatus">
                                <option value="operational">Operacional</option>
                                <option value="inactive">Inativo</option>
                                <option value="inoperant">Inoperante</option>
                                <option value="accident">Sinistro</option>
                                <option value="support">Socorro</option>
                                <option value="testing">Testes</option>
                                <option value="transfer">Transferência</option>
                                <option value="transferred">Transferido</option>
                            </select>
                        </label>


                        <label
                            x-show="
                                selectedOperationalStatus
                                !==
                                originalOperationalStatus
                            "
                            x-cloak
                        >
                            Motivo / observação

                            <textarea
                                x-model="statusReason"
                                rows="4"
                                maxlength="300"
                                placeholder="Informe o motivo da alteração..."
                            ></textarea>

                            <small
                                x-text="`${statusReason.length}/300 caracteres`"
                            ></small>
                        </label>


                        <footer>

                            <button
                                type="button"
                                class="secondary"
                                @click="open = false"
                            >
                                Cancelar
                            </button>

                            <button
                                type="button"
                                class="primary"
                                @click="saveOperationalStatus()"
                                :disabled="
                                    selectedOperationalStatus
                                    ===
                                    originalOperationalStatus
                                    ||
                                    !statusReason.trim()
                                "
                            >
                                <i class="bi bi-check-lg"></i>
                                Salvar alteração
                            </button>

                        </footer>

                    </div>

                </template>


                <template x-if="originalOperationalStatus === 'maintenance'">

                    <div class="vehicle-status-modal-lock">

                        <i class="bi bi-lock"></i>

                        <div>
                            <strong>
                                Status controlado pela manutenção
                            </strong>

                            <p>
                                Para alterar a situação deste veículo,
                                encerre a ordem de manutenção atualmente aberta.
                            </p>
                        </div>

                    </div>

                </template>

            </section>

        </div>


        {{-- BODY LEGADO - mantido temporariamente para rollback --}}
        <div class="vehicle-center-body vehicle-center-body--legacy">







            {{-- COLUNA ESQUERDA --}}



            <div class="vehicle-center-left">







                {{-- ATUALIZAÇÃO OPERACIONAL --}}



                <section class="vehicle-center-card">







                    <div class="vehicle-center-card-header">







                        <div>



                            <small>



                                Atualização rápida



                            </small>







                            <h3>



                                KM e Horímetro



                            </h3>



                        </div>







                        <i class="bi bi-speedometer2"></i>







                    </div>







                    <div class="vehicle-center-fields">







                        <div class="vehicle-center-field">







                            <label>



                                Hodômetro atual



                            </label>







                            <div class="vehicle-center-input-row">







                                <input



                                    type="number"



                                    x-model="vehicle.current_km"



                                    :min="originalKm"



                                    step="1"



                                >







                                <span>



                                    KM



                                </span>







                                <button



                                    type="button"



                                    @click.prevent.stop="updateKm"



                                >



                                    Atualizar



                                </button>







                            </div>







                        </div>







                        <div class="vehicle-center-field">







                            <label>



                                Horímetro atual



                            </label>







                            <div class="vehicle-center-input-row">







                                <input



                                    type="number"



                                    x-model="vehicle.current_hours"



                                    :min="originalHours"



                                    step="1"



                                >







                                <span>



                                    H



                                </span>







                                <button



                                    type="button"



                                    @click.prevent.stop="updateHours"



                                >



                                    Atualizar



                                </button>







                            </div>







                        </div>







                    </div>







                </section>







                {{-- STATUS OPERACIONAL --}}



                <section class="vehicle-center-card vehicle-modal-status-card">



                    <div class="vehicle-center-card-header">

                        <div>

                            <small>Situação do veículo</small>

                            <h3>Status operacional</h3>

                        </div>



                        <div

                            class="vehicle-modal-status-badge"

                            :class="'status-' + vehicle.operational_status"

                        >

                            <i :class="statusConfig(vehicle.operational_status).icon"></i>

                            <span x-text="statusConfig(vehicle.operational_status).label"></span>

                        </div>

                    </div>



                    <div class="vehicle-modal-status-metrics">



                        <div>

                            <small>Desde</small>

                            <strong x-text="vehicle.status_changed_date ?? '--'"></strong>

                            <span x-text="vehicle.status_changed_time ?? ''"></span>

                        </div>



                        <div>

                            <small>Tempo neste status</small>

                            <strong x-text="vehicle.down_time_text ?? '--'"></strong>

                            <span x-text="vehicle.down_time_subtext ?? ''"></span>

                        </div>



                    </div>



                    <template x-if="vehicle.open_downtime_reason">

                        <div

                            class="vehicle-modal-status-reason"

                            x-text="vehicle.open_downtime_reason"

                        ></div>

                    </template>





                    <template x-if="originalOperationalStatus !== 'maintenance'">



                    <div class="vehicle-center-status-form">



                        <div class="vehicle-center-field">



                            <select x-model="selectedOperationalStatus">



                                <option value="operational">

                                    Operacional

                                </option>



                                <option value="inactive">

                                    Inativo

                                </option>



                                <option value="inoperant">

                                    Inoperante

                                </option>



                                <option value="accident">

                                    Sinistro

                                </option>



                                <option value="support">

                                    Socorro

                                </option>



                                <option value="testing">

                                    Em testes

                                </option>



                                <option value="transfer">

                                    Transferência

                                </option>



                                <option value="transferred">

                                    Transferido

                                </option>



                            </select>



                        </div>



                        <div

                            class="vehicle-center-field"

                            x-show="

                                selectedOperationalStatus

                                !==

                                originalOperationalStatus

                            "

                            x-cloak

                        >



                            <label>

                                Observação da alteração

                            </label>



                            <textarea

                                x-model="statusReason"

                                rows="3"

                                placeholder="Informe o motivo da alteração de status..."

                            ></textarea>



                        </div>



                        <button

                            type="button"

                            @click.prevent.stop="updateOperationalStatus"

                            :disabled="

                                selectedOperationalStatus

                                    === originalOperationalStatus

                                ||

                                (

                                    selectedOperationalStatus

                                        !== originalOperationalStatus

                                    &&

                                    !statusReason.trim()

                                )

                            "

                        >

                            Salvar status

                        </button>



                    </div>



                </template>

                <template x-if="originalOperationalStatus === 'maintenance'">



                    <div class="vehicle-maintenance-status-lock">



                        <div class="vehicle-maintenance-status-lock-text">



                            <i class="bi bi-lock"></i>



                            <div>



                                <strong>

                                    Status controlado pela manutenção

                                </strong>



                                <p>

                                    Para liberar ou alterar o status deste veículo,

                                    encerre a ordem de manutenção aberta.

                                </p>



                            </div>



                        </div>



                        <a

                            :href="`/vehicle/${vehicle.id}/maintenance`"

                            class="vehicle-maintenance-status-link"

                        >

                            <i class="bi bi-wrench-adjustable"></i>



                            Ir para manutenção

                        </a>



                    </div>



                </template>

                </section>







            </div>







            {{-- COLUNA DIREITA --}}



            <div class="vehicle-center-right">







                {{-- ALERTAS --}}



                <section class="vehicle-center-card">







                    <div class="vehicle-center-card-header">







                        <div>



                            <small>



                                Monitoramento



                            </small>







                            <h3>



                                Alertas do veículo



                            </h3>



                        </div>







                        <i class="bi bi-exclamation-triangle"></i>







                    </div>







                    <template



                        x-if="



                            !vehicle.alerts



                            ||



                            vehicle.alerts.length == 0



                        "



                    >







                        <div class="vehicle-center-empty">







                            <i class="bi bi-check-circle"></i>







                            <strong>



                                Nenhum alerta ativo



                            </strong>







                            <p>



                                Este veículo não possui pendências no momento.



                            </p>







                        </div>







                    </template>







                    <div class="vehicle-center-alert-list">







                        <template



                            x-for="alert in (vehicle.alerts ?? []).slice(0, 4)"



                            :key="alert.message"



                        >







                            <div



                                class="vehicle-center-alert"



                                :class="alert.status"



                            >







                                <i class="bi bi-exclamation-triangle"></i>







                                <div>



                                    <strong



                                        x-text="shortAlert(alert)"



                                    ></strong>







                                    <small



                                        x-text="alert.procedure ?? 'Alerta operacional'"



                                    ></small>



                                </div>







                            </div>







                        </template>







                    </div>







                </section>







                {{-- GRID INTERNA DA COLUNA DIREITA --}}



                <div class="vehicle-center-right-split">







                    {{-- ÚLTIMAS ATUALIZAÇÕES --}}



                    <section class="vehicle-center-card">







                        <div class="vehicle-center-card-header">







                            <div>



                                <small>



                                    Histórico recente



                                </small>







                                <h3>



                                    Atualizações operacionais



                                </h3>



                            </div>







                            <i class="bi bi-clock"></i>







                        </div>







                        <template



                            x-if="



                                !vehicle.update_logs



                                ||



                                vehicle.update_logs.length == 0



                            "



                        >







                            <div class="vehicle-center-empty">







                                <i class="bi bi-inbox"></i>







                                <strong>



                                    Nenhuma atualização registrada



                                </strong>







                                <p>



                                    Alterações de KM, HR e status aparecerão aqui.



                                </p>







                            </div>







                        </template>







                        <div class="vehicle-center-timeline">







                            <template



                                x-for="log in (vehicle.update_logs ?? []).slice(0, 4)"



                                :key="log.id"



                            >







                                <div class="vehicle-center-log">







                                    <div class="log-dot"></div>







                                    <div>







                                        <strong



                                            x-text="logTitle(log.type)"



                                        ></strong>







                                        <p>



                                            <span



                                                x-text="log.old_value ?? '--'"



                                            ></span>







                                            ➔



                                            <span



                                                x-text="log.new_value"



                                            ></span>



                                        </p>







                                        <small



                                            x-text="



                                                new Date(log.created_at)



                                                .toLocaleDateString('pt-BR')



                                            "



                                        ></small>







                                    </div>







                                </div>







                            </template>







                        </div>







                    </section>







                    {{-- ÚLTIMAS MANUTENÇÕES --}}



                    <section class="vehicle-center-card">







                        <div class="vehicle-center-card-header">







                            <div>



                                <small>



                                    Manutenção



                                </small>







                                <h3>



                                    Últimos registros



                                </h3>



                            </div>







                            <i class="bi bi-wrench-adjustable"></i>







                        </div>







                        <template



                            x-if="



                                !vehicle.maintenances



                                ||



                                vehicle.maintenances.length == 0



                            "



                        >







                            <div class="vehicle-center-empty">







                                <i class="bi bi-clipboard-x"></i>







                                <strong>



                                    Nenhuma manutenção registrada



                                </strong>







                                <p>



                                    Os lançamentos de manutenção aparecerão aqui.



                                </p>







                            </div>







                        </template>







                        <div class="vehicle-center-maintenance-list">







                            <template



                                x-for="maintenance in (vehicle.maintenances ?? []).slice(0, 4)"



                                :key="maintenance.id"



                            >







                                <div class="vehicle-center-maintenance">







                                    <div>







                                        <strong



                                            x-text="



                                                maintenance.procedure?.name



                                                ?? 'Procedimento'



                                            "



                                        ></strong>







                                        <small



                                            x-text="



                                                maintenance.reason



                                                ?? 'Preventiva'



                                            "



                                        ></small>







                                    </div>







                                    <span



                                        x-text="



                                            maintenance.performed_at



                                                ? new Date(maintenance.performed_at)



                                                    .toLocaleDateString('pt-BR')



                                                : '--'



                                        "



                                    ></span>







                                </div>







                            </template>







                        </div>







                    </section>







                </div>







            </div>



        </div>







    </div>



</div>







{{-- MODAL: SELETOR DE CHECKLIST --}}



<div



    class="modal-overlay"



    x-show="checklistSelectorOpen"



    x-transition.opacity



    style="display:none;"



    @click.self="closeChecklistSelector()"



>



    <div



        class="checklist-selector-modal"



        x-transition.scale.origin.center



    >







        <div class="checklist-modal-header">







            <div>



                <small>



                    Checklist diário



                </small>







                <h2>



                    Selecione o contexto operacional



                </h2>







                <p>



                    Você possui mais de uma permissão disponível. Escolha onde deseja preencher o checklist de hoje.



                </p>



            </div>







            <button



                type="button"



                class="checklist-modal-close"



                @click="closeChecklistSelector()"



            >



                <i class="bi bi-x-lg"></i>



            </button>







        </div>







        <div class="checklist-option-list">







            <template



                x-for="option in checklistOptions"



            :key="`${option.division_id}-${option.location_id}-${option.profile}-${option.template_id}`"



            >



                <button



                    type="button"



                    class="checklist-option-card"



                    @click="openChecklist(option)"



                >







                    <div class="checklist-option-icon">



                        <i class="bi bi-clipboard-check"></i>



                    </div>







                    <div>



                        <strong



                            x-text="option.label"



                        ></strong>







                        <span>



                            <span x-text="option.profile_label"></span>



                            ·



                            <span x-text="option.location_name ?? 'Todas unidades'"></span>



                        </span>



                    </div>







                    <em



                        :class="option.status"



                        x-text="option.status_label"



                    ></em>







                </button>



            </template>







        </div>







    </div>



</div>











{{-- MODAL: PREENCHIMENTO DO CHECKLIST --}}



<div



    class="modal-overlay"



    x-show="checklistModalOpen"



    x-transition.opacity



    style="display:none;"



    @click.self="closeChecklistModal()"



>



    <div



        class="daily-checklist-modal"



        x-transition.scale.origin.center



    >







        <div class="checklist-modal-header">







            <div>



                <small>



                    Checklist diário



                </small>







                <h2 x-text="currentChecklist.template_name ?? 'Preenchimento operacional'">



                    Preenchimento operacional



                </h2>







                <p>



                    <span x-text="currentChecklist.division_name"></span>



                    ·



                    <span x-text="currentChecklist.location_name ?? 'Todas unidades'"></span>



                    ·



                    <span x-text="currentChecklist.profile_label"></span>



                </p>



            </div>







            <button



                type="button"



                class="checklist-modal-close"



                @click="closeChecklistModal()"



            >



                <i class="bi bi-x-lg"></i>



            </button>







        </div>







        <div class="daily-checklist-body">







            <section class="daily-checklist-card">







                <div class="daily-checklist-card-header">







                    <div>



                        <small>



                            Identificação



                        </small>







                        <h3>



                            Veículo e situação



                        </h3>



                    </div>







                    <span



                        class="daily-checklist-status"



                        :class="currentChecklist.status"



                        x-text="currentChecklist.status_label"



                    ></span>







                </div>







                <div class="daily-checklist-field">







                    <label>



                        Veículo



                    </label>







                    <select



                        x-model="currentChecklist.vehicle_id"



                    >



                        <option value="">



                            Selecione o veículo



                        </option>







                        <template



                            x-for="vehicle in checklistVehicles"



                            :key="vehicle.id"



                        >



                            <option



                                :value="String(vehicle.id)"



                                x-text="vehicle.label"



                            ></option>



                        </template>



                    </select>







                </div>







            </section>







            <section class="daily-checklist-card">







                <div class="daily-checklist-card-header">







                    <div>



                        <small>



                            Verificação



                        </small>







                        <h3>



                            Itens do checklist



                        </h3>



                    </div>







                    <i class="bi bi-list-check"></i>







                </div>







                <div class="daily-checklist-items">







                    <template



                        x-for="item in currentChecklist.items"



                        :key="item.id"



                    >



                        <label class="daily-checklist-item">







                            <input



                                type="checkbox"



                                x-model="item.checked"



                            >







                            <span>



                                <i class="bi bi-check-lg"></i>



                            </span>







                            <strong



                                x-text="item.label"



                            ></strong>







                        </label>



                    </template>







                </div>







            </section>







            <section class="daily-checklist-card">







                <div class="daily-checklist-card-header">







                    <div>



                        <small>



                            Observações



                        </small>







                        <h3>



                            Notas da operação



                        </h3>



                    </div>







                    <i class="bi bi-chat-text"></i>







                </div>







                <div class="daily-checklist-field">







                    <label>



                        Observações gerais



                    </label>







                    <textarea



                        x-model="currentChecklist.notes"



                        placeholder="Descreva avarias, pendências, observações ou informe que não houve anormalidades..."



                    ></textarea>







                </div>







            </section>







        </div>







        <div class="daily-checklist-actions">







            <button



                type="button"



                class="daily-checklist-btn muted"



                @click="closeChecklistModal()"



            >



                Cancelar



            </button>







            <button



                type="button"



                class="daily-checklist-btn secondary"



                @click="saveChecklistDraft()"



            >



                Salvar rascunho



            </button>







            <button



                type="button"



                class="daily-checklist-btn primary"



                @click="completeChecklist()"



            >



                <i class="bi bi-check-circle"></i>



                Concluir checklist



            </button>







        </div>







    </div>



</div>



</div>







<div id="startOperationModal" class="operation-modal-backdrop" style="display:none;">



    <div class="operation-modal-card">







        <div class="operation-modal-header">



            <div>



                <span>Iniciar Operação</span>



                <h2 id="startOperationVehicleTitle">Veículo</h2>



                <p>Confirme os dados iniciais antes de colocar o veículo em operação.</p>



            </div>







            <button type="button" onclick="closeOperationModals()" class="operation-modal-close">



                <i class="bi bi-x-lg"></i>



            </button>



        </div>







        @if($myOpenOperation && !$canManageOperationDrivers)



            <div class="operation-modal-alert">



                Você já possui uma operação aberta no veículo



                <strong>{{ $myOpenOperation->vehicle->plate ?? 'sem placa' }}</strong>.



                Encerre a operação atual antes de iniciar outra.



            </div>



        @endif







        <form method="POST" id="startOperationForm" onsubmit="return confirmDashboardOperationReadings(this, 'start');">


            @csrf
            <input type="hidden" name="km_reading_confirmed" value="0">
            <input type="hidden" name="hours_reading_confirmed" value="0">






            @if($canManageOperationDrivers)



                <div class="form-group">



                    <label>Motorista responsável</label>







                    <select name="driver_id" id="operationDriverSelect" required>



                        <option value="">Selecione o motorista</option>







                        @foreach($operationDrivers as $driver)



                            <option



                                value="{{ $driver->id }}"



                                data-location-ids="{{ $driver->operation_location_ids }}"



                                data-has-open-operation="{{ $driver->has_open_operation ? '1' : '0' }}"



                                @disabled($driver->has_open_operation)



                            >



                                {{ $driver->name }}



                                @if($driver->has_open_operation)



                                    [Em operação]



                                @endif



                            </option>



                        @endforeach



                    </select>







                    <small id="operationDriverHelp" class="operation-driver-help">



                        Serão exibidos apenas motoristas da mesma cidade/localidade do veículo.



                    </small>



                </div>



            @endif







            <div class="operation-form-grid">



                <div class="form-group">



                    <label>KM inicial</label>



                    <input type="number" step="0.01" name="start_vehicle_km" id="startOperationKm" required>



                </div>







                <div class="form-group">



                    <label>Horímetro inicial</label>



                    <input type="number" step="0.01" name="start_vehicle_hours" id="startOperationHours">



                </div>







                <div class="form-group">



                    <label>Data/hora de início</label>



                    <input



                        type="datetime-local"



                        name="start_datetime_reported"



                        id="startOperationDatetime"



                        value="{{ now()->format('Y-m-d\TH:i') }}"



                        max="{{ now()->format('Y-m-d\TH:i') }}"



                        required



                    >



                </div>



            </div>







            <div class="form-group">



                <label>Observações</label>



                <textarea



                    name="start_observation"



                    rows="3"



                    placeholder="Ex: saída para rota, coleta, atendimento emergencial..."



                ></textarea>



            </div>







            <div class="operation-delay-box" id="startDelayBox" style="display:none;">



                <strong>Lançamento retroativo</strong>



                <p>



                    A justificativa será exigida quando o início informado for mais de 15 minutos antes do horário atual do sistema.



                </p>







                <div class="operation-form-grid">



                    <div class="form-group">



                        <label>Motivo</label>



                        <select name="start_delay_reason">



                            <option value="">Selecione se necessário</option>



                            <option value="system_unavailable">Sistema indisponível no momento</option>



                            <option value="supervisor_authorized">Autorizado pelo supervisor</option>



                            <option value="no_internet">Sem conexão de internet no local</option>



                            <option value="emergency_service">Atendimento emergencial iniciado antes do registro</option>



                            <option value="operational_adjustment">Ajuste operacional posterior</option>



                            <option value="shift_change">Troca de turno ou repasse operacional</option>



                            <option value="other">Outro motivo</option>



                        </select>



                    </div>







                    <div class="form-group">



                        <label>Justificativa</label>



                        <input



                            type="text"



                            name="start_delay_justification"



                            placeholder="Descreva o motivo operacional"



                        >



                    </div>



                </div>



            </div>







            <div class="operation-modal-footer">



                <button type="button" class="chm-page-button secondary" onclick="closeOperationModals()">



                    Cancelar



                </button>







                <button



                    type="submit"



                    class="chm-page-button primary"



                    @if($myOpenOperation && !$canManageOperationDrivers) disabled @endif



                >



                    <i class="bi bi-play-fill"></i>



                    Iniciar operação



                </button>



            </div>







        </form>







    </div>



</div>



<div id="closeOperationModal" class="operation-modal-backdrop" style="display:none;">



    <div class="operation-modal-card">







        <div class="operation-modal-header">



            <div>



                <span>Fechar Operação</span>



                <h2 id="closeOperationVehicleTitle">Veículo</h2>



                <p id="closeOperationDescription">Informe os dados finais da operação.</p>



            </div>







            <button type="button" onclick="closeOperationModals()" class="operation-modal-close">



                <i class="bi bi-x-lg"></i>



            </button>



        </div>







        <div class="operation-current-box">



            <div>



                <span>Início</span>



                <strong id="closeOperationStartDatetime">-</strong>



            </div>







            <div>



                <span>KM inicial</span>



                <strong id="closeOperationStartKm">-</strong>



            </div>







            <div>



                <span>HR inicial</span>



                <strong id="closeOperationStartHours">-</strong>



            </div>



        </div>







        <form method="POST" id="closeOperationForm" onsubmit="return confirmDashboardOperationReadings(this, 'end');">


            @csrf



            @method('PUT')
            <input type="hidden" name="km_reading_confirmed" value="0">
            <input type="hidden" name="hours_reading_confirmed" value="0">






            <div class="operation-form-grid">



                <div class="form-group">



                    <label>KM final</label>



                    <input type="number" step="0.01" name="end_vehicle_km" required>



                </div>







                <div class="form-group">



                    <label>Horímetro final</label>



                    <input type="number" step="0.01" name="end_vehicle_hours">



                </div>







                <div class="form-group">



                    <label>Data/hora de fim</label>



                    <input



                        type="datetime-local"



                        name="end_datetime_reported"



                        value="{{ now()->format('Y-m-d\TH:i') }}"



                        max="{{ now()->format('Y-m-d\TH:i') }}"



                        required



                    >



                </div>



            </div>







            <div class="form-group">



                <label>Observações de encerramento</label>



                <textarea



                    name="end_observation"



                    rows="3"



                    placeholder="Ex: operação finalizada sem ocorrência, abastecimento pendente, avaria identificada..."



                ></textarea>



            </div>











            <div class="operation-modal-footer">



                <button type="button" class="chm-page-button secondary" onclick="closeOperationModals()">



                    Cancelar



                </button>







                <button type="submit" class="chm-page-button primary">



                    <i class="bi bi-check-circle"></i>



                    Fechar operação



                </button>



            </div>







        </form>







    </div>



</div>



<script>



    function updateStartDelayBoxVisibility() {



        const datetimeInput = document.getElementById('startOperationDatetime');



        const delayBox = document.getElementById('startDelayBox');







        if (!datetimeInput || !delayBox || !datetimeInput.value) return;







        const reportedDate = new Date(datetimeInput.value);



        const nowDate = new Date();







        const diffMinutes = Math.floor((nowDate - reportedDate) / 60000);







        const shouldShow = diffMinutes > 15;







        delayBox.style.display = shouldShow ? 'block' : 'none';







        const reason = delayBox.querySelector('[name="start_delay_reason"]');



        const justification = delayBox.querySelector('[name="start_delay_justification"]');







        if (reason) {



            reason.required = shouldShow;







            if (!shouldShow) {



                reason.value = '';



            }



        }







        if (justification) {



            justification.required = shouldShow;







            if (!shouldShow) {



                justification.value = '';



            }



        }



    }







    function bindStartOperationDelayWatcher() {



        const datetimeInput = document.getElementById('startOperationDatetime');







        if (!datetimeInput) return;







        datetimeInput.removeEventListener('change', updateStartDelayBoxVisibility);



        datetimeInput.removeEventListener('input', updateStartDelayBoxVisibility);







        datetimeInput.addEventListener('change', updateStartDelayBoxVisibility);



        datetimeInput.addEventListener('input', updateStartDelayBoxVisibility);







        updateStartDelayBoxVisibility();



    }







    const startOperationRouteTemplate =



        "{{ route('vehicles.operations.store', ['vehicle' => '__VEHICLE_ID__']) }}";







    const closeOperationRouteTemplate =



        "{{ route('operations.finish', ['operation' => '__OPERATION_ID__']) }}";







    function openStartOperationModalFromButton(button) {



        openStartOperationModal({



            vehicle_id: button.dataset.vehicleId,



            vehicle_name: button.dataset.vehicleName,



            vehicle_plate: button.dataset.vehiclePlate,



            current_km: button.dataset.currentKm,



            current_hours: button.dataset.currentHours,



            location_id: button.dataset.locationId,



            location_name: button.dataset.locationName,



        });



    }







    function openCloseOperationModalFromButton(button) {



        openCloseOperationModal({



            operation_id: button.dataset.operationId,



            vehicle_name: button.dataset.vehicleName,



            vehicle_plate: button.dataset.vehiclePlate,



            driver_name: button.dataset.driverName,



            start_km: button.dataset.startKm,



            start_hours: button.dataset.startHours,



            start_datetime: button.dataset.startDatetime,
            current_km: button.dataset.currentKm,
            current_hours: button.dataset.currentHours,


        });



    }







    function filterOperationDriversByLocation(locationId, locationName) {



        const select = document.getElementById('operationDriverSelect');



        const help = document.getElementById('operationDriverHelp');







        if (!select) return;







        select.value = '';







        let visibleCount = 0;







        Array.from(select.options).forEach(function (option) {



            if (!option.value) {



                option.hidden = false;



                option.disabled = false;



                return;



            }







            const locationIds = (option.dataset.locationIds || '')



                .split(',')



                .map(id => id.trim())



                .filter(Boolean);







            const hasOpenOperation = option.dataset.hasOpenOperation === '1';







            const sameLocation =



                !locationId ||



                locationIds.length === 0 ||



                locationIds.includes(String(locationId));







            option.hidden = !sameLocation;







            if (sameLocation) {



                visibleCount++;



            }







            option.disabled = hasOpenOperation;



        });







        if (help) {



            if (locationName) {



                help.innerText = visibleCount > 0



                    ? `Mostrando motoristas vinculados à localidade: ${locationName}.`



                    : `Nenhum motorista disponível para a localidade: ${locationName}.`;



            } else {



                help.innerText = 'Veículo sem localidade definida. Mostrando motoristas disponíveis da divisão.';



            }



        }



    }



    function openStartOperationModal(vehicle) {



        const modal = document.getElementById('startOperationModal');



        const form = document.getElementById('startOperationForm');







        if (!modal || !form) return;







        form.action = startOperationRouteTemplate.replace('__VEHICLE_ID__', vehicle.vehicle_id);







        document.getElementById('startOperationVehicleTitle').innerText =



            `${vehicle.vehicle_plate ?? ''} · ${vehicle.vehicle_name ?? 'Veículo'}`;







        document.getElementById('startOperationKm').value = vehicle.current_km ?? '';
        document.getElementById('startOperationKm').dataset.currentReading = vehicle.current_km ?? 0;


        document.getElementById('startOperationHours').value = vehicle.current_hours ?? '';
        document.getElementById('startOperationHours').dataset.currentReading = vehicle.current_hours ?? 0;


        filterOperationDriversByLocation(vehicle.location_id, vehicle.location_name);



        modal.style.display = 'flex';



        bindStartOperationDelayWatcher();



    }







    function openCloseOperationModal(operation) {



        const modal = document.getElementById('closeOperationModal');



        const form = document.getElementById('closeOperationForm');







        if (!modal || !form) return;







        form.action = closeOperationRouteTemplate.replace('__OPERATION_ID__', operation.operation_id);







        document.getElementById('closeOperationVehicleTitle').innerText =



            `${operation.vehicle_plate ?? ''} · ${operation.vehicle_name ?? 'Veículo'}`;







        document.getElementById('closeOperationDescription').innerText =



            `Motorista: ${operation.driver_name ?? 'Não informado'}`;







        document.getElementById('closeOperationStartDatetime').innerText =



            operation.start_datetime ?? '-';







        document.getElementById('closeOperationStartKm').innerText =



            operation.start_km ?? '-';







        document.getElementById('closeOperationStartHours').innerText =


            operation.start_hours ?? '-';

        form.querySelector('[name="end_vehicle_km"]').dataset.currentReading = operation.current_km ?? 0;
        form.querySelector('[name="end_vehicle_hours"]').dataset.currentReading = operation.current_hours ?? 0;






        modal.style.display = 'flex';



    }







    function closeOperationModals() {


        const startModal = document.getElementById('startOperationModal');



        const closeModal = document.getElementById('closeOperationModal');







        if (startModal) startModal.style.display = 'none';



        if (closeModal) closeModal.style.display = 'none';



    }

    function confirmDashboardOperationReadings(form, prefix) {
        const checks = [
            [`${prefix}_vehicle_km`, 'km_reading_confirmed', 500, 'KM'],
            [`${prefix}_vehicle_hours`, 'hours_reading_confirmed', 24, 'horímetro'],
        ];

        for (const [field, flag, threshold, label] of checks) {
            const input = form.querySelector(`[name="${field}"]`);
            const confirmation = form.querySelector(`[name="${flag}"]`);
            confirmation.value = '0';

            if (! input || input.value === '') continue;

            if (Number(input.value) - Number(input.dataset.currentReading || 0) > threshold) {
                if (! confirm(`O ${label} informado está muito acima da leitura atual. Confirma que a leitura está correta?`)) {
                    input.focus();
                    return false;
                }
                confirmation.value = '1';
            }
        }

        return true;
    }






    document.addEventListener('keydown', function (event) {



        if (event.key === 'Escape') {



            closeOperationModals();



        }



    });




function renderDashboardVehicleTrendChart(container, fuelTrend = [], kmTrend = [], mode = 'fuel') {
    const points = mode === 'km' ? kmTrend : fuelTrend;
    const stroke = mode === 'km' ? '#8ab4ff' : '#ef5a62';
    const suffix = mode === 'km' ? ' km' : ' L';
    const decimals = mode === 'km' ? 0 : 1;
    const emptyText = mode === 'km'
        ? 'Sem leituras suficientes para gerar a evolução de KM.'
        : 'Sem abastecimentos suficientes para gerar o gráfico.';

    container.innerHTML = '';

    const wrapper = document.createElement('div');
    wrapper.className = 'vehicle-modal-chart-inner';

    if (!Array.isArray(points) || points.length < 2) {
        const empty = document.createElement('div');
        empty.className = 'vehicle-modal-chart-empty';
        empty.textContent = emptyText;
        wrapper.appendChild(empty);
        container.appendChild(wrapper);
        return;
    }

    const width = container.clientWidth || 760;
    const height = 260;
    const padding = { top: 16, right: 18, bottom: 34, left: 18 };

    const values = points.map(p => Number(p.value || 0));
    const min = Math.min(...values);
    const max = Math.max(...values);
    const range = Math.max(max - min, 1);

    const svgNS = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(svgNS, 'svg');
    svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
    svg.setAttribute('class', 'vehicle-modal-chart-svg');

    for (let i = 0; i < 4; i++) {
        const y = padding.top + ((height - padding.top - padding.bottom) / 3) * i;
        const line = document.createElementNS(svgNS, 'line');
        line.setAttribute('x1', padding.left);
        line.setAttribute('x2', width - padding.right);
        line.setAttribute('y1', y);
        line.setAttribute('y2', y);
        line.setAttribute('class', 'vehicle-modal-chart-grid-line');
        svg.appendChild(line);
    }

    const stepX = (width - padding.left - padding.right) / Math.max(points.length - 1, 1);

    const coords = points.map((point, index) => {
        const x = padding.left + (stepX * index);
        const normalized = (Number(point.value || 0) - min) / range;
        const y = (height - padding.bottom) - normalized * (height - padding.top - padding.bottom);
        return { ...point, x, y };
    });

    const polyline = document.createElementNS(svgNS, 'polyline');
    polyline.setAttribute(
        'points',
        coords.map(point => `${point.x},${point.y}`).join(' ')
    );
    polyline.setAttribute('fill', 'none');
    polyline.setAttribute('stroke', stroke);
    polyline.setAttribute('stroke-width', '3');
    polyline.setAttribute('stroke-linecap', 'round');
    polyline.setAttribute('stroke-linejoin', 'round');
    svg.appendChild(polyline);

    coords.forEach((point, index) => {
        const circle = document.createElementNS(svgNS, 'circle');
        circle.setAttribute('cx', point.x);
        circle.setAttribute('cy', point.y);
        circle.setAttribute('r', '4');
        circle.setAttribute('fill', stroke);
        svg.appendChild(circle);

        const label = document.createElementNS(svgNS, 'text');
        label.setAttribute('x', point.x);
        label.setAttribute('y', height - 10);
        label.setAttribute('text-anchor', index === 0 ? 'start' : index === coords.length - 1 ? 'end' : 'middle');
        label.setAttribute('class', 'vehicle-modal-chart-label');
        label.textContent = point.label || '';
        svg.appendChild(label);
    });

    const tooltip = document.createElement('div');
    tooltip.className = 'vehicle-modal-chart-tooltip';
    wrapper.appendChild(tooltip);

    coords.forEach(point => {
        const hover = document.createElementNS(svgNS, 'circle');
        hover.setAttribute('cx', point.x);
        hover.setAttribute('cy', point.y);
        hover.setAttribute('r', '12');
        hover.setAttribute('fill', 'transparent');
        hover.style.cursor = 'pointer';

        hover.addEventListener('mouseenter', () => {
            tooltip.innerHTML = `
                <strong>${point.formatted_value ?? Number(point.value).toLocaleString('pt-BR', {
                    minimumFractionDigits: decimals,
                    maximumFractionDigits: decimals
                }) + suffix}</strong>
                <span>${point.date ?? point.label ?? ''}</span>
            `;
            tooltip.classList.add('is-visible');
        });

        hover.addEventListener('mousemove', (event) => {
            const rect = container.getBoundingClientRect();
            tooltip.style.left = `${event.clientX - rect.left + 12}px`;
            tooltip.style.top = `${event.clientY - rect.top - 10}px`;
        });

        hover.addEventListener('mouseleave', () => {
            tooltip.classList.remove('is-visible');
        });

        svg.appendChild(hover);
    });

    wrapper.appendChild(svg);
    container.appendChild(wrapper);
}

</script>







<script>











const dashboardFuelFillingUrlTemplate = @json($dashboardFuelFillingUrlTemplate);

function dashboardFleet(vehicleActions = {}) {



    return {







        open: false,



        originalKm: 0,



        checklistSelectorOpen: false,



        checklistModalOpen: false,







        checklistOptions: [],



        checklistVehicles: [],



        currentChecklist: {



            items: [],



        },



        originalHours: 0,



        vehicle: {},

        vehicleActions,

        fuelFillingUrl(vehicleId) {

            return dashboardFuelFillingUrlTemplate.replace('__vehicle_id__', encodeURIComponent(vehicleId));

        },

        originalOperationalStatus: '',

        inlineKm: '',
        inlineHours: '',
        savingInlineKm: false,
        savingInlineHours: false,

        activeVehicleTrend: 'fuel',

        selectedOperationalStatus: '',

        statusReason: '',

        search: '',







        activeFilter: 'all',







        setFilter(filter) {







            this.activeFilter = filter;



        },







        vehicleMatches(alertStatus, operationalStatus, searchText) {

            const normalizedSearch = String(searchText ?? '').toLowerCase();

            const normalizedQuery = this.normalizeVehicleSearch(this.search);

            const normalizedStatus = String(operationalStatus ?? 'operational');

            const normalizedAlert = String(alertStatus ?? 'clean');



            const matchesSearch =

                !this.search

                ||

                normalizedSearch.includes(

                    String(this.search).toLowerCase().trim()

                ) || this.normalizeVehicleSearch(normalizedSearch).includes(normalizedQuery);



            if (!matchesSearch) {

                return false;

            }



            if (this.activeFilter === 'all') {

                return true;

            }



            if (this.activeFilter === 'operational') {

                return normalizedStatus === 'operational';

            }



            if (this.activeFilter === 'maintenance') {

                return normalizedStatus === 'maintenance';

            }



            if (this.activeFilter === 'warning') {

                return normalizedAlert === 'warning';

            }



            if (this.activeFilter === 'danger') {

                return normalizedAlert === 'danger';

            }



            /*

            * Filtros dinâmicos do resumo lateral:

            * status:inactive

            * status:testing

            * status:accident

            * status:support

            */

            if (this.activeFilter.startsWith('status:')) {



                const requestedStatus =

                    this.activeFilter.replace('status:', '');



                return normalizedStatus === requestedStatus;

            }



            return true;

        },

        normalizeVehicleSearch(value) {

            return String(value ?? '').toLowerCase().replace(/[\s-]+/g, '');

        },

        vehicleSecondaryIdentifier(vehicle) {

            if (vehicle?.plate) return vehicle.plate;
            if (vehicle?.asset_code) return `Código: ${vehicle.asset_code}`;
            if (vehicle?.renavam) return `RENAVAM: ${vehicle.renavam}`;
            if (vehicle?.serial_number) return `Série: ${vehicle.serial_number}`;

            return '';

        },



        shortAlert(alert) {







            if (!alert || !alert.message) {



                return 'Alerta operacional';



            }







            return String(alert.message)



                .replace(



                    'KM sem atualização há mais de ',



                    'KM desatualizado há +'



                )



                .replace(



                    'Horímetro sem atualização há mais de ',



                    'Horímetro desatualizado há +'



                )



                .replace(



                    'HR sem atualização há mais de ',



                    'Horímetro desatualizado há +'



                );



        },







        logTitle(type) {







            if (type === 'km') {



                return 'Hodômetro';



            }







            if (type === 'hours') {



                return 'Horímetro';



            }







            if (type === 'division') {



                return 'Divisão';



            }







            if (type === 'location') {



                return 'Localização';



            }







            if (type === 'operational_status') {



                return 'Status operacional';



            }







            return type ?? 'Atualização';



        },



        procedures: @json($procedures),







        stockItems: @json($stockItems),







        selectedProcedureId: '',







        fields: {},



        canInternal: true,



        activeTab: 'maintenance',



        historyTab: 'updates',



        init() {







            this.$watch(







                'selectedProcedureId',







                () => {







                    this.syncExecutionType();







                }



            );



        },



        filteredStockItems(categoryId) {







            return this.stockItems.filter(







                item =>







                    Number(item.stock_category_id)



                    ===



                    Number(categoryId)







            );



        },







        async openChecklistFlow() {



        try {



            const response = await fetch(



                '/daily-checklists/options',



                {



                    headers: {



                        'Accept': 'application/json',



                    },



                }



            );







            const data = await response.json();







            this.checklistOptions = data.options || [];







            if (this.checklistOptions.length === 0) {



                alert('Nenhum checklist disponível para seu usuário.');



                return;



            }







            if (this.checklistOptions.length === 1) {



                await this.openChecklist(this.checklistOptions[0]);



                return;



            }







            this.checklistSelectorOpen = true;







            this.$nextTick(() => {



            });







        } catch (error) {



            console.error(error);







            alert('Erro ao carregar opções de checklist.');



        }



    },







    closeChecklistSelector() {



        this.checklistSelectorOpen = false;



    },







    async openChecklist(option) {



        try {



            const response = await fetch(



                '/daily-checklists/show-or-create',



                {



                    method: 'POST',







                    headers: {



                        'Content-Type': 'application/json',



                        'Accept': 'application/json',



                        'X-CSRF-TOKEN':



                            document



                                .querySelector('meta[name="csrf-token"]')



                                .content,



                    },







                    body: JSON.stringify({



                        division_id: option.division_id,



                        location_id: option.location_id,



                        module: option.module,



                        profile: option.profile,



                        template_id: option.template_id,



                    }),



                }



            );







            const data = await response.json();







            this.checklistVehicles = (data.vehicles || []).map(function (vehicle) {



                return {



                    ...vehicle,



                    id: String(vehicle.id),



                };



            });







            this.currentChecklist = data.checklist;







            this.currentChecklist.vehicle_id =



                this.currentChecklist.vehicle_id



                    ? String(this.currentChecklist.vehicle_id)



                    : '';



            if (!response.ok) {



                console.error(data);







                alert(



                    data.message



                        ? data.message



                        : 'Erro ao abrir checklist.'



                );







                return;



            }



            this.checklistSelectorOpen = false;



            this.checklistModalOpen = true;







            this.$nextTick(() => {



            });







        } catch (error) {



            console.error(error);







            alert('Erro ao abrir checklist.');



        }



    },







    closeChecklistModal() {



        this.checklistModalOpen = false;



    },







    checklistPayload() {



        return {



            vehicle_id:



                this.currentChecklist.vehicle_id



                    ? Number(this.currentChecklist.vehicle_id)



                    : null,







            notes:



                this.currentChecklist.notes || '',







            items:



                (this.currentChecklist.items || []).map(function (item) {



                    return {



                        id: item.id,



                        checked: !!item.checked,



                        notes: item.notes || '',



                    };



                }),



        };



    },



    statusConfig(status) {

        const statuses = {

            operational: { class: 'operational', icon: 'bi bi-check-circle', label: 'Operacional' },

            maintenance: { class: 'maintenance', icon: 'bi bi-wrench-adjustable', label: 'Em manutenção' },

            inactive: { class: 'inactive', icon: 'bi bi-slash-circle', label: 'Inativo' },

            inoperant: { class: 'danger', icon: 'x-circle', label: 'Inoperante' },

            accident: { class: 'danger', icon: 'triangle-alert', label: 'Sinistro' },

            support: { class: 'warning', icon: 'truck', label: 'Socorro' },

            testing: { class: 'info', icon: 'flask-conical', label: 'Em testes' },

            transfer: { class: 'warning', icon: 'arrow-right-left', label: 'Transferência' },

            transferred: { class: 'inactive', icon: 'route', label: 'Transferido' },

        };



        return statuses[status] ?? statuses.operational;

    },



    async saveChecklistDraft() {



        if (!this.currentChecklist || !this.currentChecklist.id) {



            return;



        }







        try {



            const response = await fetch(



                `/daily-checklists/${this.currentChecklist.id}/save`,



                {



                    method: 'POST',







                    headers: {



                        'Content-Type': 'application/json',



                        'Accept': 'application/json',



                        'X-CSRF-TOKEN':



                            document



                                .querySelector('meta[name="csrf-token"]')



                                .content,



                    },







                    body: JSON.stringify(



                        this.checklistPayload()



                    ),



                }



            );







            const data = await response.json();







            this.currentChecklist = data.checklist;







            alert(data.message || 'Checklist salvo.');







        } catch (error) {



            console.error(error);







            alert('Erro ao salvar checklist.');



        }



    },







    async completeChecklist() {



        if (!this.currentChecklist || !this.currentChecklist.id) {



            return;



        }







        try {



            const response = await fetch(



                `/daily-checklists/${this.currentChecklist.id}/complete`,



                {



                    method: 'POST',







                    headers: {



                        'Content-Type': 'application/json',



                        'Accept': 'application/json',



                        'X-CSRF-TOKEN':



                            document



                                .querySelector('meta[name="csrf-token"]')



                                .content,



                    },







                    body: JSON.stringify(



                        this.checklistPayload()



                    ),



                }



            );







            const data = await response.json();







            this.currentChecklist = data.checklist;







            alert(data.message || 'Checklist concluído.');







            this.checklistModalOpen = false;







            location.reload();







        } catch (error) {



            console.error(error);







            alert('Erro ao concluir checklist.');



        }



    },







        async refreshVehicle() {







            const response = await fetch(







                `/api/vehicles/${this.vehicle.id}`







            );







            const data = await response.json();







            this.vehicle = data.vehicle;







        },







        openModal(vehicle) {







            this.vehicle =



                JSON.parse(



                    JSON.stringify(vehicle)



                );

            this.originalOperationalStatus = this.vehicle.operational_status;
            this.activeVehicleTrend = 'fuel';

            this.inlineKm = this.vehicle.current_km ?? '';
            this.inlineHours = this.vehicle.current_hours ?? '';



            this.selectedOperationalStatus = this.vehicle.operational_status;



            this.statusReason = '';





            this.originalKm =



                Number(this.vehicle.current_km ?? 0);







            this.originalHours =



                Number(this.vehicle.current_hours ?? 0);







            /*



            |--------------------------------------------------------------------------



            | LINK PARA MANUTENÇÃO DO VEÍCULO



            |--------------------------------------------------------------------------



            */







            const maintenanceLink =



                document.getElementById('vehicleMaintenanceLink');







            if (maintenanceLink) {



                maintenanceLink.href =



                    `/vehicle/${this.vehicle.id}/maintenance`;



            }







            /*



            |--------------------------------------------------------------------------



            | DEFAULTS



            |--------------------------------------------------------------------------



            */







            this.selectedProcedureId = '';







            this.fields = {







                execution_type: 'internal',







                reason: 'preventiva',







                extra_cost: 0,







                notes: ''







            };







            this.activeTab = 'maintenance';







            this.historyTab = 'updates';







            this.open = true;



            this.$nextTick(() => {



            });



        },



        close() {







            this.open = false;



        },







        get selectedFields() {







            const procedure =



                this.procedures.find(



                    p => Number(p.id) === Number(this.selectedProcedureId)



                );







            return procedure



                ? procedure.fields



                : [];



        },







        syncExecutionType() {







            const procedure =







                this.procedures.find(







                    p =>







                        Number(p.id)



                        ===



                        Number(this.selectedProcedureId)







                );







            /*



            |--------------------------------------------------------------------------



            | SEM PROCEDIMENTO



            |--------------------------------------------------------------------------



            */







            if (!procedure) {







                this.canInternal = true;







                this.fields.execution_type =



                    'internal';







                return;



            }







            /*



            |--------------------------------------------------------------------------



            | DEFINE PERMISSÃO



            |--------------------------------------------------------------------------



            */







            this.canInternal =



                !!procedure.can_be_internal;







            /*



            |--------------------------------------------------------------------------



            | NÃO PODE INTERNO



            |--------------------------------------------------------------------------



            */







            if (!this.canInternal) {







                this.fields.execution_type =



                    'external';



            }



        },







        async saveInlineVehicleReading(type) {

            if (type === 'km') {

                const value = Number(
                    String(this.inlineKm ?? '').replace(',', '.')
                );

                if (! Number.isFinite(value) || value < 0) {
                    alert('Informe um hodômetro válido.');
                    return;
                }

                this.savingInlineKm = true;

                try {

                    this.vehicle.current_km = value;

                    await this.updateKm();

                    /*
                     * Se updateKm abortar por validação,
                     * ele restaura vehicle.current_km.
                     */
                    this.inlineKm =
                        this.vehicle.current_km
                        ?? this.originalKm
                        ?? '';

                } finally {

                    this.savingInlineKm = false;

                }

                return;
            }


            if (type === 'hours') {

                const value = Number(
                    String(this.inlineHours ?? '').replace(',', '.')
                );

                if (! Number.isFinite(value) || value < 0) {
                    alert('Informe um horímetro válido.');
                    return;
                }

                this.savingInlineHours = true;

                try {

                    this.vehicle.current_hours = value;

                    await this.updateHours();

                    /*
                     * Se updateHours abortar por validação,
                     * ele restaura vehicle.current_hours.
                     */
                    this.inlineHours =
                        this.vehicle.current_hours
                        ?? this.originalHours
                        ?? '';

                } finally {

                    this.savingInlineHours = false;

                }

            }

        },


        async updateKm(event) {

            if (event) {

                event.preventDefault();

                event.stopPropagation();

            }



            const currentKm = Number(this.vehicle.current_km);

            const originalKm = Number(this.originalKm);

            const diffKm = currentKm - originalKm;



            if (currentKm < originalKm) {

                alert(`O novo KM não pode ser menor que o KM atual (${this.originalKm}).`);



                this.vehicle.current_km = this.originalKm;



                return;

            }



            let kmReadingConfirmed = false;

            if (diffKm > 500) {
                const confirmed = confirm(

                    `Atenção: você está aumentando o hodômetro em ${diffKm.toLocaleString('pt-BR')} km.\n\n` +

                    `KM atual: ${originalKm.toLocaleString('pt-BR')}\n` +

                    `Novo KM: ${currentKm.toLocaleString('pt-BR')}\n\n` +

                    `Deseja confirmar esta atualização?`

                );



                if (! confirmed) {
                    this.vehicle.current_km = this.originalKm;

                    return;

                }

                kmReadingConfirmed = true;
            }



            const response = await fetch(

                `/vehicles/${this.vehicle.id}/update-km`,

                {

                    method: 'POST',

                    headers: {

                        'Content-Type': 'application/json',

                        'Accept': 'application/json',

                        'X-CSRF-TOKEN':

                            document

                                .querySelector('meta[name="csrf-token"]')

                                .content

                    },

                    body: JSON.stringify({

                        km: currentKm,
                        km_reading_confirmed: kmReadingConfirmed
                    })

                }

            );



            const data = await response.json();



            if (! response.ok) {

                alert(data.message || 'Não foi possível atualizar o hodômetro.');

                return;

            }



            alert(data.message || 'Novo hodômetro salvo!');

            location.reload();

        },





        async updateHours(event) {

            if (event) {

                event.preventDefault();

                event.stopPropagation();

            }



            const currentHours = Number(this.vehicle.current_hours);

            const originalHours = Number(this.originalHours);

            const diffHours = currentHours - originalHours;



            if (currentHours < originalHours) {

                alert(`O novo horímetro não pode ser menor que o horímetro atual (${this.originalHours}).`);



                this.vehicle.current_hours = this.originalHours;



                return;

            }



            let hoursReadingConfirmed = false;

            if (diffHours > 24) {
                const confirmed = confirm(

                    `Atenção: você está aumentando o horímetro em ${diffHours.toLocaleString('pt-BR')} hora(s).\n\n` +

                    `Horímetro atual: ${originalHours.toLocaleString('pt-BR')}\n` +

                    `Novo horímetro: ${currentHours.toLocaleString('pt-BR')}\n\n` +

                    `Deseja confirmar esta atualização?`

                );



                if (! confirmed) {
                    this.vehicle.current_hours = this.originalHours;

                    return;

                }

                hoursReadingConfirmed = true;
            }



            const response = await fetch(

                `/vehicles/${this.vehicle.id}/update-hours`,

                {

                    method: 'POST',

                    headers: {

                        'Content-Type': 'application/json',

                        'Accept': 'application/json',

                        'X-CSRF-TOKEN':

                            document

                                .querySelector('meta[name="csrf-token"]')

                                .content

                    },

                    body: JSON.stringify({

                        hours: currentHours,
                        hours_reading_confirmed: hoursReadingConfirmed
                    })

                }

            );



            const data = await response.json();



            if (! response.ok) {

                alert(data.message || 'Não foi possível atualizar o horímetro.');

                return;

            }



            alert(data.message || 'Novo horímetro salvo!');

            location.reload();

        },

        get selectedProcedure() {







            return this.procedures.find(







                p => Number(p.id)



                ===



                Number(this.selectedProcedureId)







            );



        },



        async updateOperationalStatus() {



            if (

                this.selectedOperationalStatus !== this.originalOperationalStatus &&

                !this.statusReason.trim()

            ) {



                alert('Informe uma observação para alterar o status.');



                return;

            }



            const response = await fetch(



                `/vehicles/${this.vehicle.id}/operational-status`,



                {



                    method: 'POST',



                    headers: {



                        'Content-Type': 'application/json',



                        'Accept': 'application/json',



                        'X-CSRF-TOKEN':

                            document

                                .querySelector('meta[name="csrf-token"]')

                                .content



                    },



                    body: JSON.stringify({



                        operational_status: this.selectedOperationalStatus,



                        status_reason:

                            this.selectedOperationalStatus !== this.originalOperationalStatus

                                ? this.statusReason

                                : null



                    })



                }



            );



            const data = await response.json();



            if (!response.ok) {



                alert(data.message ?? 'Erro ao atualizar.');



                return;

            }



            alert(data.message);



            location.reload();



        },



        async saveMaintenance() {







            if (







                this.fields.execution_type



                !=



                'external'







            ) {







                this.fields.provider_name = null;



            }







            try {







                this.fields.reason ??=



                    'preventiva';







                this.fields.extra_cost ??=



                    0;







                this.fields.notes ??=



                    '';







                /*



                |--------------------------------------------------------------------------



                | DADOS OPERACIONAIS



                |--------------------------------------------------------------------------



                */







                this.fields.performed_km =



                    this.vehicle.current_km;







                this.fields.performed_hours =



                    this.vehicle.current_hours;







                this.fields.performed_at =



                    new Date()



                    .toISOString()



                    .slice(0, 10);







                /*



                |--------------------------------------------------------------------------



                | PAYLOAD



                |--------------------------------------------------------------------------



                */







                const payload = {







                    vehicle_id:



                        this.vehicle.id,







                    procedure_id:



                        this.selectedProcedureId,







                    maintenance_type:







                        this.fields.execution_type



                            ?? 'external',







                    performed_km:



                        this.vehicle.current_km,







                    performed_hours:



                        this.vehicle.current_hours,







                    performed_at:



                        new Date()



                        .toISOString()



                        .slice(0, 10),







                    reason:



                        this.fields.reason ?? 'preventiva',







                    extra_cost:



                        this.fields.extra_cost ?? 0,







                    notes:



                        this.fields.notes ?? '',



                    provider_name:



                        this.fields.provider_name ?? '',







                    fields: this.fields



                };







                const response = await fetch(







    `/vehicles/${this.vehicle.id}/maintenance`,





    {







        method: 'POST',







        headers: {







            'Content-Type': 'application/json',







            'Accept': 'application/json',







            'X-CSRF-TOKEN':







                document



                    .querySelector(



                        'meta[name="csrf-token"]'



                    )



                    .content



        },







        body: JSON.stringify(payload)







    }







);







                if (!response.ok) {







    const errorText =



        await response.text();







    console.error(errorText);







    throw new Error(errorText);



}







                alert('Manutenção salva!');







                location.reload();



            } catch (error) {







                console.error(error);







                alert(



                    'Erro ao salvar manutenção'



                );



            }



},



    }







}







</script>







@endsection

@push('scripts')
<script>
window.renderDashboardVehicleTrendChart = function(container, points, mode = 'fuel') {

    if (!container) return;

    const isKm = mode === 'km';
    const isEfficiency = mode === 'efficiency';

    const emptyMessage = isKm
        ? 'Sem leituras suficientes para gerar a evolução de KM.'
        : 'Sem abastecimentos suficientes para gerar o gráfico.';

    if (!Array.isArray(points) || points.length < 2) {

        container.innerHTML = `
            <div class="vehicle-modal-chart-empty">
                <i class="bi bi-graph-up"></i>
                <span>${emptyMessage}</span>
            </div>
        `;

        return;
    }


    const width = 700;
    const height = 210;

    const left = 22;
    const right = 18;
    const top = 18;
    const bottom = 36;

    const plotWidth = width - left - right;
    const plotHeight = height - top - bottom;


    /*
     * Normaliza os dois tipos de série.
     *
     * fuel:
     *   liters
     *
     * km:
     *   value
     */
    const normalized = points.map(point => {

        const distance = Number(
            point.value ?? point.vehicle_km ?? 0
        );

        const intervalDays = Math.max(
            Number(point.interval_days ?? 1),
            1 / 1440
        );

        const value = isEfficiency
            ? Number(point.value ?? 0)
            : isKm
                ? distance / intervalDays
                : Number(point.liters ?? 0);

        return {
            ...point,
            chartValue: value,
            axisLabel: point.label ?? point.date ?? '',
            tooltipDate:
                point.datetime
                ?? point.date
                ?? point.label
                ?? ''
        };

    });


    const values = normalized.map(point => point.chartValue);

    /*
     * Combustível parte do zero.
     *
     * KM usa o menor hodômetro da série como base,
     * para que uma evolução 158.000 -> 159.000 seja visualmente
     * perceptível, em vez de ficar achatada.
     */
    let minValue = (isKm || isEfficiency)
        ? Math.min(...values)
        : 0;

    let maxValue = Math.max(...values);

    if (maxValue === minValue) {
        maxValue = minValue + 1;
    }

    if (isKm || isEfficiency) {

        const range = maxValue - minValue;
        const padding = Math.max(range * .10, 1);

        minValue = Math.max(minValue - padding, 0);
        maxValue += padding;

    }

    const range = Math.max(maxValue - minValue, 1);

    let averageValue = 0;

    if (isEfficiency) {

        const totalDistance = normalized.reduce(
            (sum, point) =>
                sum + Number(point.distance ?? 0),
            0
        );

        const totalLiters = normalized.reduce(
            (sum, point) =>
                sum + Number(point.liters ?? 0),
            0
        );

        averageValue =
            totalDistance
            / Math.max(totalLiters, 0.001);

    } else if (isKm) {

        const totalDistance = normalized.reduce(
            (sum, point) =>
                sum + Number(point.value ?? 0),
            0
        );

        const totalDays = normalized.reduce(
            (sum, point) =>
                sum + Math.max(
                    Number(point.interval_days ?? 1),
                    1 / 1440
                ),
            0
        );

        averageValue =
            totalDistance
            / Math.max(totalDays, 1 / 1440);

    } else {

        const dailyTotals = {};

        normalized.forEach(point => {

            const rawDate =
                point.datetime
                ?? point.date
                ?? point.label
                ?? '';

            const dayKey =
                String(rawDate).split(' ')[0];

            dailyTotals[dayKey] =
                (dailyTotals[dayKey] ?? 0)
                + Number(point.chartValue || 0);

        });

        const dailyValues = Object.values(dailyTotals);

        averageValue =
            dailyValues.reduce((sum, value) => sum + value, 0)
            / Math.max(dailyValues.length, 1);

    }

    const averageNormalized =
        Math.max(
            0,
            Math.min(
                1,
                (averageValue - minValue) / range
            )
        );

    const averageY =
        top
        + plotHeight
        - (averageNormalized * plotHeight);

    const averageFormatted = isEfficiency
        ? averageValue.toLocaleString(
            'pt-BR',
            {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }
        ) + ' km/L'
        : isKm
            ? Math.round(averageValue).toLocaleString('pt-BR') + ' km/dia'
            : averageValue.toLocaleString(
                'pt-BR',
                {
                    minimumFractionDigits: 1,
                    maximumFractionDigits: 1
                }
            ) + ' L/dia';


    const coords = normalized.map((point, index) => {

        const x = left + (
            index / Math.max(normalized.length - 1, 1)
        ) * plotWidth;

        const normalizedValue =
            (point.chartValue - minValue) / range;

        const y =
            top
            + plotHeight
            - (normalizedValue * plotHeight);

        return {
            ...point,
            x,
            y
        };

    });


    const polyline = coords
        .map(point => `${point.x.toFixed(1)},${point.y.toFixed(1)}`)
        .join(' ');


    const grid = [0.25, 0.5, 0.75, 1].map(level => {

        const y =
            top
            + plotHeight
            - (plotHeight * level);

        return `
            <line
                x1="${left}"
                y1="${y}"
                x2="${width - right}"
                y2="${y}"
                class="vehicle-modal-chart-grid"
            />
        `;

    }).join('');


    const stroke =
        isEfficiency
            ? '#aeb9c8'
            : isKm
                ? '#7faaf2'
                : '#e0525b';


    const circles = coords.map((point, index) => `

        <circle
            cx="${point.x}"
            cy="${point.y}"
            r="4"
            class="vehicle-modal-chart-point"
            data-index="${index}"
            tabindex="0"
            style="
                fill:${stroke};
                stroke:${stroke};
            "
        ></circle>

    `).join('');


    const labels = coords.map(point => `

        <span>${point.axisLabel || ''}</span>

    `).join('');


    container.innerHTML = `

        <div class="vehicle-modal-chart-stage">

            <svg
                viewBox="0 0 ${width} ${height}"
                preserveAspectRatio="none"
            >

                ${grid}

                <line
                    x1="${left}"
                    y1="${averageY}"
                    x2="${width - right}"
                    y2="${averageY}"
                    class="vehicle-modal-chart-average-line"
                />

                <line
                    x1="${left}"
                    y1="${top + plotHeight}"
                    x2="${width - right}"
                    y2="${top + plotHeight}"
                    class="vehicle-modal-chart-axis"
                />

                <polyline
                    points="${polyline}"
                    class="vehicle-modal-chart-line"
                    style="stroke:${stroke};"
                />

                ${circles}

            </svg>


            <div
                class="vehicle-modal-chart-average-label"
                style="top:${(averageY / height) * 100}%"
            >
                <span>${
                    isEfficiency
                        ? 'Média ponderada'
                        : 'Média por dia'
                }</span>
                <strong>${averageFormatted}</strong>
            </div>

            <div class="vehicle-modal-chart-tooltip">
                <small></small>
                <strong></strong>
            </div>

        </div>


        <div class="vehicle-modal-chart-labels">

            ${labels}

        </div>

    `;


    const tooltip =
        container.querySelector(
            '.vehicle-modal-chart-tooltip'
        );

    const dateElement =
        tooltip.querySelector('small');

    const valueElement =
        tooltip.querySelector('strong');


    container
        .querySelectorAll('.vehicle-modal-chart-point')
        .forEach(pointEl => {

            const index =
                Number(pointEl.dataset.index);

            const point =
                coords[index];


            function show() {

                container
                    .querySelectorAll(
                        '.vehicle-modal-chart-point'
                    )
                    .forEach(el =>
                        el.classList.remove('is-active')
                    );

                pointEl.classList.add('is-active');


                dateElement.textContent =
                    point.tooltipDate;


                if (isEfficiency) {

                    valueElement.textContent =
                        Number(point.chartValue)
                            .toLocaleString(
                                'pt-BR',
                                {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                }
                            )
                        + ' km/L';

                    const distance =
                        Number(point.distance ?? 0);

                    const liters =
                        Number(point.liters ?? 0);

                    dateElement.textContent =
                        point.tooltipDate
                        + ' · '
                        + distance.toLocaleString(
                            'pt-BR',
                            {
                                maximumFractionDigits: 0
                            }
                        )
                        + ' km · '
                        + liters.toLocaleString(
                            'pt-BR',
                            {
                                minimumFractionDigits: 3,
                                maximumFractionDigits: 3
                            }
                        )
                        + ' L';

                } else if (isKm) {

                    const distanceText =
                        point.formatted_value
                        ?? (
                            Number(point.chartValue)
                                .toLocaleString(
                                    'pt-BR',
                                    {
                                        maximumFractionDigits: 0
                                    }
                                )
                            + ' km'
                        );

                    const previousKm =
                        Number(point.previous_km ?? 0);

                    const currentKm =
                        Number(point.current_km ?? 0);

                    dateElement.textContent =
                        point.tooltipDate
                        + (
                            previousKm > 0 && currentKm > 0
                                ? ' · '
                                  + previousKm.toLocaleString('pt-BR')
                                  + ' → '
                                  + currentKm.toLocaleString('pt-BR')
                                  + ' km'
                                : ''
                        );

                    valueElement.textContent =
                        Number(point.chartValue)
                            .toLocaleString(
                                'pt-BR',
                                {
                                    maximumFractionDigits: 1
                                }
                            )
                        + ' km/dia';

                } else {

                    valueElement.textContent =
                        Number(point.chartValue)
                            .toLocaleString(
                                'pt-BR',
                                {
                                    minimumFractionDigits: 3,
                                    maximumFractionDigits: 3
                                }
                            )
                        + ' L';

                }


                tooltip.style.left =
                    ((point.x / width) * 100)
                    + '%';

                tooltip.style.top =
                    ((point.y / height) * 100)
                    + '%';

                tooltip.classList.add(
                    'is-visible'
                );

            }


            function hide() {

                pointEl.classList.remove(
                    'is-active'
                );

                tooltip.classList.remove(
                    'is-visible'
                );

            }


            pointEl.addEventListener(
                'mouseenter',
                show
            );

            pointEl.addEventListener(
                'mouseleave',
                hide
            );

            pointEl.addEventListener(
                'focus',
                show
            );

            pointEl.addEventListener(
                'blur',
                hide
            );

        });

};
</script>
@endpush
