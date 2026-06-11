@extends('layouts.app')

@php

    $pageTitle = 'Dashboard Operacional';

    $pageSubtitle = 'Controle de Frota';

@endphp

@push('styles')

<link
    rel="stylesheet"
    href="{{ asset('css/pages/dashboard.css') }}?v=3"
>

@endpush
@section('content')

<div
    class="dashboard-page"

    x-data="dashboardFleet()"
>

    {{-- KPIs --}}
    <div class="kpi-grid">

        <div
            class="kpi-card compact clickable"
            :class="{ active: activeFilter == 'all' }"
            @click="setFilter('all')"
        >
        
            <div class="kpi-icon">
                <i data-lucide="truck"></i>
            </div>
        
            <div class="kpi-content">
                <small>
                    Frota ativa
                </small>
        
                <strong>
                    {{ $vehicles->count() }}
                </strong>
            </div>
        
        </div>
        
        <div
            class="kpi-card compact warning clickable"
            :class="{ active: activeFilter == 'warning' }"
            @click="setFilter('warning')"
        >
        
            <div class="kpi-icon">
                <i data-lucide="triangle-alert"></i>
            </div>
        
            <div class="kpi-content">
                <small>
                    Atenção
                </small>
        
                <strong>
                    {{ $warningVehicles }}
                </strong>
            </div>
        
        </div>
        
        <div
            class="kpi-card compact danger clickable"
            :class="{ active: activeFilter == 'danger' }"
            @click="setFilter('danger')"
        >
        
            <div class="kpi-icon">
                <i data-lucide="circle-alert"></i>
            </div>
        
            <div class="kpi-content">
                <small>
                    Críticos
                </small>
        
                <strong>
                    {{ $criticalVehicles }}
                </strong>
            </div>
        
        </div>
        
        <div
            class="kpi-card compact maintenance clickable"
            :class="{ active: activeFilter == 'maintenance' }"
            @click="setFilter('maintenance')"
        >
        
            <div class="kpi-icon">
                <i data-lucide="wrench"></i>
            </div>
        
            <div class="kpi-content">
                <small>
                    Em manutenção
                </small>
        
                <strong>
                    {{ $maintenanceVehicles }}
                </strong>
            </div>
        
        </div>
        
        <div class="kpi-actions-card">
        
            <a
                href="{{ route('vehicle.quick-update') }}"
                class="kpi-action-btn primary"
            >
                <div>
                    <small>
                        Atualizar rápido
                    </small>
        
                    <strong>
                        KM/HR
                    </strong>
                </div>
        
                <i data-lucide="gauge"></i>
            </a>
        
            <button
                type="button"
                class="kpi-action-btn primary"
                @click="openChecklistFlow()"
            >
                <div>
                    <small>
                        Rotina diária
                    </small>
            
                    <strong>
                        Checklist diário
                    </strong>
                </div>
            
                <i data-lucide="clipboard-check"></i>
            </button>
        
        </div>

    </div>
    
    <div class="dashboard-filter-bar">
    
        <div class="dashboard-search-box">
    
            <label>
                Buscar
            </label>
    
            <div class="dashboard-search-input">
    
                <i data-lucide="search"></i>
    
                <input
                    type="text"
                    x-model="search"
                    placeholder="Nome, placa, marca, modelo, ano..."
                >
    
            </div>
    
        </div>
    
    </div>
    
    {{-- GRID --}}
    <div class="dashboard-grid">

        {{-- VEÍCULOS --}}
        <section class="vehicles-grid">

            @foreach($vehicles as $vehicle)
                @php
                
                    $searchText = strtolower(
                        trim(
                            ($vehicle->name ?? '') . ' ' .
                            ($vehicle->plate ?? '') . ' ' .
                            ($vehicle->brand ?? '') . ' ' .
                            ($vehicle->model ?? '') . ' ' .
                            ($vehicle->year ?? '')
                        )
                    );
                
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
                    
                                <span class="vehicle-plate">
                                    {{ $vehicle->plate }}
                                </span>
                    
                                <h3>
                                    {{ $vehicle->name }}
                                </h3>
                    
                            </div>
                    
                        </div>
                    
                        @if($vehicle->is_in_operation)

                            <span
                                class="vehicle-operation-pill in-operation"
                                title="Motorista: {{ $vehicle->operation_driver_name ?? 'Não informado' }} | Início: {{ $vehicle->operation_started_at_formatted ?? '-' }}"
                            >
                                <i data-lucide="radio-tower"></i>
                                Em operação
                            </span>
                        
                        @elseif($vehicle->operational_status == 'maintenance')
                        
                            <span class="vehicle-operation-pill maintenance">
                                <i data-lucide="wrench"></i>
                                Manutenção
                            </span>
                        
                        @elseif($vehicle->status == 'inactive')
                        
                            <span class="vehicle-operation-pill inactive">
                                <i data-lucide="circle-off"></i>
                                Inativo
                            </span>
                        
                        @endif
                    
                    </div>
                    {{-- INFO --}}
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

                    </div>

                
                    <div
                        class="vehicle-card-bottom-action {{ $vehicle->main_alert ? 'has-alert ' . $vehicle->main_alert['status'] : 'is-clean' }}"
                    >
                        @if($vehicle->main_alert)
                    
                            <div class="vehicle-card-alert-inline">
                    
                                <i data-lucide="triangle-alert"></i>
                    
                                <span>
                                    {{ $shortMainAlert ?? $vehicle->main_alert['message'] }}
                                </span>
                    
                            </div>
                    
                        @else
                    
                            <div class="vehicle-card-details-inline">
                    
                                <span>
                                    Ver detalhes
                                </span>
                    
                                <i data-lucide="chevron-right"></i>
                    
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
                                data-start-datetime="{{ $vehicle->operation_started_at_formatted }}"
                                onclick="event.stopPropagation(); openCloseOperationModalFromButton(this)"
                            >
                                <i data-lucide="square"></i>
                                Fechar operação
                            </button>
                        @endif
                
                    @else
                
                        @if(!$cannotStartOperation)
                
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
                                <i data-lucide="play"></i>
                                Iniciar operação
                            </button>
                
                        @endif
                
                    @endif
                
                </div>
                </div>

            @endforeach

        </section>

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
                        <i data-lucide="activity"></i>
                    </div>
        
                </div>
        
                <div class="operation-task-list">
        
                    @if($criticalVehicles > 0)
        
                        <button
                            type="button"
                            class="operation-task danger"
                            @click="setFilter('danger')"
                        >
        
                            <div class="task-icon">
                                <i data-lucide="triangle-alert"></i>
                            </div>
        
                            <div class="task-content">
                                <strong>
                                    Revisar críticos
                                </strong>
        
                                <p>
                                    {{ $criticalVehicles }} veículo(s) exigem atenção.
                                </p>
                            </div>
        
                            <i
                                class="task-arrow"
                                data-lucide="chevron-right"
                            ></i>
        
                        </button>
        
                    @endif
        
                    @if($operationalUpdatePendingCount > 0)
        
                        <a
                            href="{{ route('vehicle.quick-update') }}"
                            class="operation-task warning"
                        >
        
                            <div class="task-icon">
                                <i data-lucide="gauge"></i>
                            </div>
        
                            <div class="task-content">
                                <strong>
                                    Atualizar KM/HR
                                </strong>
        
                                <p>
                                    {{ $operationalUpdatePendingCount }} pendência(s) operacionais.
                                </p>
                            </div>
        
                            <i
                                class="task-arrow"
                                data-lucide="chevron-right"
                            ></i>
        
                        </a>
        
                    @endif
        
                    <a
                        href="{{ route('stock.index') }}"
                        class="operation-task {{ $lowStockCount > 0 ? 'stock-warning' : 'success' }}"
                    >
        
                        <div class="task-icon">
                            <i data-lucide="boxes"></i>
                        </div>
        
                        <div class="task-content">
                            <strong>
                                Estoque operacional
                            </strong>
        
                            <p>
                                @if($lowStockCount > 0)
                                    {{ $lowStockCount }} item(ns) em atenção.
                                @else
                                    Estoque em nível adequado.
                                @endif
                            </p>
                        </div>
        
                        <i
                            class="task-arrow"
                            data-lucide="chevron-right"
                        ></i>
        
                    </a>
        
                    @if(
                        $criticalVehicles == 0
                        &&
                        $warningVehicles == 0
                        &&
                        $operationalUpdatePendingCount == 0
                        &&
                        $lowStockCount == 0
                    )
        
                        <div class="operation-task success static">
        
                            <div class="task-icon">
                                <i data-lucide="check-circle"></i>
                            </div>
        
                            <div class="task-content">
                                <strong>
                                    Operação em dia
                                </strong>
        
                                <p>
                                    Nenhuma prioridade pendente.
                                </p>
                            </div>
        
                        </div>
        
                    @endif
        
                </div>
        
            </section>
        
            {{-- AÇÕES RÁPIDAS --}}
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
        
                    <i data-lucide="zap"></i>
        
                </div>
        
                <div class="quick-actions-grid">
        
                    <a
                        href="{{ route('vehicle.quick-update') }}"
                        class="quick-action-card"
                    >
                        <i data-lucide="gauge"></i>
        
                        <span>
                            Atualizar KM/HR
                        </span>
                    </a>
        
                    <a
                        href="{{ route('stock.index') }}"
                        class="quick-action-card"
                    >
                        <i data-lucide="boxes"></i>
        
                        <span>
                            Estoque
                        </span>
                    </a>
        
                    <a
                        href="{{ route('vehicles.index') }}"
                        class="quick-action-card"
                    >
                        <i data-lucide="truck"></i>
        
                        <span>
                            Veículos
                        </span>
                    </a>
        
                    <button
                        type="button"
                        class="quick-action-card"
                        @click="openChecklistFlow()"
                    >
                        <i data-lucide="clipboard-check"></i>
                    
                        <span>
                            Checklist
                        </span>
                    </button>
        
                </div>
        
            </section>
        
            {{-- RESUMO DA FROTA --}}
            <section class="side-widget">
        
                <div class="side-widget-header">
        
                    <div>
                        <small>
                            Situação
                        </small>
        
                        <h3>
                            Resumo da frota
                        </h3>
                    </div>
        
                    <i data-lucide="bar-chart-3"></i>
        
                </div>
        
                <div class="fleet-summary-list">
        
                    <div class="fleet-summary-item success">
                        <span>
                            Operacionais
                        </span>
        
                        <strong>
                            {{ $operationalVehicles }}
                        </strong>
                    </div>
        
                    <div class="fleet-summary-item warning">
                        <span>
                            Em manutenção
                        </span>
        
                        <strong>
                            {{ $maintenanceVehicles }}
                        </strong>
                    </div>
        
                    <div class="fleet-summary-item muted">
                        <span>
                            Inativos
                        </span>
        
                        <strong>
                            {{ $inactiveVehicles }}
                        </strong>
                    </div>
        
                </div>
        
            </section>
        
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
                        :src="`/images/${vehicle.type_icon ?? 'lixo.png'}`"
                        alt="Veículo"
                    >

                </div>

                <div>

                    <div class="vehicle-center-title-row">

                        <h2
                            x-text="vehicle.name"
                        ></h2>

                        <span
                            class="vehicle-center-status"
                            :class="
                                vehicle.operational_status == 'maintenance'
                                    ? 'maintenance'
                                    : (
                                        vehicle.status == 'inactive'
                                            ? 'inactive'
                                            : 'operational'
                                    )
                            "
                        >
                            <template x-if="vehicle.operational_status == 'maintenance'">
                                <span>
                                    <i data-lucide="wrench"></i>
                                    Em manutenção
                                </span>
                            </template>

                            <template x-if="vehicle.status == 'inactive'">
                                <span>
                                    <i data-lucide="circle-off"></i>
                                    Inativo
                                </span>
                            </template>

                            <template x-if="vehicle.operational_status != 'maintenance' && vehicle.status != 'inactive'">
                                <span>
                                    <i data-lucide="check-circle"></i>
                                    Operacional
                                </span>
                            </template>
                        </span>

                    </div>

                    <div class="vehicle-center-meta">

                        <span x-text="vehicle.plate"></span>

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

            <button
                type="button"
                class="vehicle-center-close"
                @click="close()"
            >
                <i data-lucide="x"></i>
            </button>

        </div>

        {{-- KPIS --}}
        {{-- RESUMO COMPACTO --}}
        <div class="vehicle-center-summary-line">
        
            <div class="vehicle-center-summary-item">
        
                <span>
                    Hodômetro:
                </span>
        
                <strong
                    x-text="
                        Number(vehicle.current_km ?? 0)
                        .toLocaleString('pt-BR') + ' KM'
                    "
                ></strong>
        
            </div>
        
            <div class="vehicle-center-summary-divider"></div>
        
            <div class="vehicle-center-summary-item">
        
                <span>
                    Horímetro:
                </span>
        
                <strong
                    x-text="(vehicle.current_hours ?? 0) + ' Horas'"
                ></strong>
        
            </div>
        
            <div class="vehicle-center-summary-divider"></div>
        
            <div class="vehicle-center-summary-item">
        
                <span>
                    Alertas:
                </span>
        
                <strong
                    x-text="
                        (vehicle.alerts ? vehicle.alerts.length : 0) + ' ativo(s)'
                    "
                ></strong>
        
            </div>
        
            <div class="vehicle-center-summary-divider"></div>
        
            <div
                class="vehicle-center-summary-item status"
                :class="
                    vehicle.alert_status == 'danger'
                        ? 'danger'
                        : vehicle.alert_status == 'warning'
                            ? 'warning'
                            : 'success'
                "
            >
        
                <span>
                    Status:
                </span>
        
                <strong
                    x-text="
                        vehicle.alert_status == 'danger'
                            ? 'Crítico'
                            : vehicle.alert_status == 'warning'
                                ? 'Atenção'
                                : 'OK'
                    "
                ></strong>
        
            </div>
        
        </div>

        {{-- BODY --}}
        <div class="vehicle-center-body">

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

                        <i data-lucide="gauge"></i>

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
                                    step="0.1"
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
                <section class="vehicle-center-card">

                    <div class="vehicle-center-card-header">

                        <div>
                            <small>
                                Situação do veículo
                            </small>

                            <h3>
                                Status operacional
                            </h3>
                        </div>

                        <i data-lucide="activity"></i>

                    </div>

                    <div class="vehicle-center-status-form">

                        <select
                            x-model="vehicle.operational_status"
                        >

                            <option value="operational">
                                Operacional
                            </option>

                            <option value="maintenance">
                                Em manutenção
                            </option>

                        </select>

                        <button
                            type="button"
                            @click.prevent.stop="updateOperationalStatus"
                        >
                            Salvar status
                        </button>

                    </div>

                </section>

                {{-- AÇÕES --}}
                <section class="vehicle-center-card">

                    <div class="vehicle-center-card-header">

                        <div>
                            <small>
                                Ações
                            </small>

                            <h3>
                                Central do veículo
                            </h3>
                        </div>

                        <i data-lucide="zap"></i>

                    </div>

                    <div class="vehicle-center-actions">

                        <a
                            href="#"
                            id="vehicleMaintenanceLink"
                            class="vehicle-center-action primary"
                        >
                            <i data-lucide="wrench"></i>
                        
                            <span>
                                Lançar manutenção
                            </span>
                        </a>

                        <a
                            :href="`/vehicle/${vehicle.id}/history`"
                            class="vehicle-center-action"
                        >
                            <i data-lucide="history"></i>

                            <span>
                                Histórico completo
                            </span>
                        </a>

                        <a
                            :href="`/vehicles/${vehicle.id}/edit`"
                            class="vehicle-center-action"
                        >
                            <i data-lucide="pencil"></i>

                            <span>
                                Editar veículo
                            </span>
                        </a>

                        <a
                            href="{{ route('vehicle.quick-update') }}"
                            class="vehicle-center-action" style="display:none"
                        >
                            <i data-lucide="list-checks"></i>

                            <span>
                                Atualização em massa
                            </span>
                        </a>
                        <a
                            :href="`/vehicles/${vehicle.id}/tires`"
                            class="vehicle-center-action"
                        >
                            <i data-lucide="circle-dot"></i>
                        
                            <span>
                                Controle de pneus
                            </span>
                        </a>

                    </div>

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
            
                        <i data-lucide="triangle-alert"></i>
            
                    </div>
            
                    <template
                        x-if="
                            !vehicle.alerts
                            ||
                            vehicle.alerts.length == 0
                        "
                    >
            
                        <div class="vehicle-center-empty">
            
                            <i data-lucide="check-circle"></i>
            
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
            
                                <i data-lucide="triangle-alert"></i>
            
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
            
                            <i data-lucide="clock-3"></i>
            
                        </div>
            
                        <template
                            x-if="
                                !vehicle.update_logs
                                ||
                                vehicle.update_logs.length == 0
                            "
                        >
            
                            <div class="vehicle-center-empty">
            
                                <i data-lucide="inbox"></i>
            
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
            
                            <i data-lucide="wrench"></i>
            
                        </div>
            
                        <template
                            x-if="
                                !vehicle.maintenances
                                ||
                                vehicle.maintenances.length == 0
                            "
                        >
            
                            <div class="vehicle-center-empty">
            
                                <i data-lucide="clipboard-x"></i>
            
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
                <i data-lucide="x"></i>
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
                        <i data-lucide="clipboard-check"></i>
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
                <i data-lucide="x"></i>
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

                    <i data-lucide="list-checks"></i>

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
                                <i data-lucide="check"></i>
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

                    <i data-lucide="message-square-text"></i>

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
                <i data-lucide="check-circle"></i>
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
                <i data-lucide="x"></i>
            </button>
        </div>

        @if($myOpenOperation && !$canManageOperationDrivers)
            <div class="operation-modal-alert">
                Você já possui uma operação aberta no veículo
                <strong>{{ $myOpenOperation->vehicle->plate ?? 'sem placa' }}</strong>.
                Encerre a operação atual antes de iniciar outra.
            </div>
        @endif

        <form method="POST" id="startOperationForm">
            @csrf

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
                    <i data-lucide="play"></i>
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
                <i data-lucide="x"></i>
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

        <form method="POST" id="closeOperationForm">
            @csrf
            @method('PUT')

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
                    <i data-lucide="check-circle-2"></i>
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
        document.getElementById('startOperationHours').value = vehicle.current_hours ?? '';
        filterOperationDriversByLocation(vehicle.location_id, vehicle.location_name);
        modal.style.display = 'flex';
        bindStartOperationDelayWatcher();
        if (window.lucide) {
            lucide.createIcons();
        }
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

        modal.style.display = 'flex';

        if (window.lucide) {
            lucide.createIcons();
        }
    }

    function closeOperationModals() {
        const startModal = document.getElementById('startOperationModal');
        const closeModal = document.getElementById('closeOperationModal');

        if (startModal) startModal.style.display = 'none';
        if (closeModal) closeModal.style.display = 'none';
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeOperationModals();
        }
    });
</script>

<script>


function dashboardFleet() {
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
        search: '',
        
        activeFilter: 'all',
        
        setFilter(filter) {
        
            this.activeFilter = filter;
        },
        
        vehicleMatches(alertStatus, operationalStatus, searchText) {
        
            alertStatus =
                String(alertStatus || '');
        
            operationalStatus =
                String(operationalStatus || '');
        
            searchText =
                String(searchText || '').toLowerCase();
        
            const term =
                String(this.search || '')
                    .toLowerCase()
                    .trim();
        
            const matchesSearch =
                !term ||
                searchText.includes(term);
        
            let matchesFilter = true;
        
            if (this.activeFilter === 'danger') {
                matchesFilter =
                    alertStatus === 'danger';
            }
        
            if (this.activeFilter === 'warning') {
                matchesFilter =
                    alertStatus === 'warning';
            }
        
            if (this.activeFilter === 'operational') {
                matchesFilter =
                    operationalStatus === 'operational';
            }
        
            if (this.activeFilter === 'maintenance') {
                matchesFilter =
                    operationalStatus === 'maintenance';
            }
        
            return matchesSearch && matchesFilter;
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
                if (window.lucide) {
                    lucide.createIcons();
                }
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
                if (window.lucide) {
                    lucide.createIcons();
                }
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
                if (window.lucide) {
                    lucide.createIcons();
                }
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
        
        async updateKm(event) {
            
                if (event) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                if (Number(this.vehicle.current_km) < Number(this.originalKm)) {
                
                    alert(
                        `O novo KM não pode ser menor que o KM atual (${this.originalKm}).`
                    );
                
                    this.vehicle.current_km =
                        this.originalKm;
                
                    return;
                }
                await fetch(
            
                    `/vehicles/${this.vehicle.id}/update-km`,
            
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
            
                        body: JSON.stringify({
            
                            km:
                                this.vehicle.current_km
            
                        })
                    }
            
                );
                alert('Novo hodômetro salvo!');
                location.reload();
            },
        
        async updateHours(event) {
        
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            if (Number(this.vehicle.current_hours) < Number(this.originalHours)) {
            
                alert(
                    `O novo horímetro não pode ser menor que o horímetro atual (${this.originalHours}).`
                );
            
                this.vehicle.current_hours =
                    this.originalHours;
            
                return;
            }
            await fetch(
        
                `/vehicles/${this.vehicle.id}/update-hours`,
        
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
        
                    body: JSON.stringify({
        
                        hours:
                            this.vehicle.current_hours
        
                    })
                }
        
            );
            alert('Novo horímetro salvo!');
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

            await fetch(
                `/vehicles/${this.vehicle.id}/operational-status`,
                {

                    method: 'POST',

                    headers: {

                        'Content-Type': 'application/json',

                        'X-CSRF-TOKEN':
                            document
                            .querySelector(
                                'meta[name="csrf-token"]'
                            )
                            .content
                    },

                    body: JSON.stringify({

                        operational_status:
                            this.vehicle.operational_status
                    })
                }
            );
            alert('Status atualizado!');
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

    '/maintenances',

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
