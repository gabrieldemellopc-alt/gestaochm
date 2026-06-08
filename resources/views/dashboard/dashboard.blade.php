@extends('layouts.app')

@php

    $pageTitle = 'Dashboard Operacional';

    $pageSubtitle = 'Controle de Frota';

@endphp

@push('styles')

<link
    rel="stylesheet"
    href="{{ asset('css/pages/dashboard.css') }}"
>

@endpush
@section('content')

<div
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
                class="kpi-action-btn disabled"
                disabled
            >
                <div>
                    <small>
                        Em breve
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
                    class="vehicle-card {{ $vehicle->alert_status }}"
                    data-alert-status="{{ $vehicle->alert_status }}"
                    data-operational-status="{{ $vehicle->operational_status }}"
                    data-search="{{ $searchText }}"
                    x-show="vehicleMatches(
                        $el.dataset.alertStatus,
                        $el.dataset.operationalStatus,
                        $el.dataset.search
                    )"
                    x-transition.opacity
                    @click.stop='openModal(@json($vehicle))'
                >

                    {{-- ALERTA --}}
                    @if($vehicle->main_alert)

                        <div
                            class="vehicle-alert {{ $vehicle->main_alert['status'] }}"
                        >

                            {{ $shortMainAlert ?? $vehicle->main_alert['message'] }}

                        </div>

                    @endif

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
                    
                        <span
                            class="vehicle-operation-pill {{ $vehicle->operational_status }}"
                        >
                            @if($vehicle->operational_status == 'maintenance')
                                <i data-lucide="wrench"></i>
                                Manutenção
                            @elseif($vehicle->status == 'inactive')
                                <i data-lucide="circle-off"></i>
                                Inativo
                            @else
                                <i data-lucide="check-circle"></i>
                                Operacional
                            @endif
                        </span>
                    
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

                
                    <div class="vehicle-card-hover-action">
                    
                        <span>
                            Ver detalhes
                        </span>
                    
                        <i data-lucide="chevron-right"></i>
                    
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
                        <i data-lucide="clipboard-list"></i>
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
        
                    <a
                        href="#"
                        class="quick-action-card muted"
                    >
                        <i data-lucide="clipboard-check"></i>
        
                        <span>
                            Checklist
                        </span>
                    </a>
        
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
    <div
        class="modal-overlay"
        x-show="open"
        x-transition
        style="display:none;"
    >
    @php
    
    $statusLabels = [
        'active' => 'Ativo',
        'inactive' => 'Inativo',
    ];
    
    $operationalLabels = [
        'operational' => 'Operacional',
        'maintenance' => 'Em manutenção',
    ];
    
    @endphp
        <div class="vehicle-modal operational">

            {{-- HEADER --}}
            <div class="modal-header operational-header">
            
                <div class="vehicle-header-left">
            
                    {{-- ÍCONE --}}
                    <div class="vehicle-hero-icon">
            
                        <img
                            :src="`/images/${vehicle.type_icon ?? 'lixo.png'}`"
                            alt="Veículo"
                        >
            
                    </div>
            
                    {{-- INFO --}}
                    <div class="vehicle-hero-info">
            
                        <div class="vehicle-title-row">
            
                            <h2
                                x-text="vehicle.name"
                            ></h2>
            
                            <span
                                class="status-badge"
                                :class="vehicle.alert_status"
                            >
            
                                <span
                                    x-text="
                                        vehicle.alert_status == 'danger'
                                            ? 'Crítico'
                                            : vehicle.alert_status == 'warning'
                                                ? 'Atenção'
                                                : 'Operacional'
                                    "
                                ></span>
            
                            </span>
            
                        </div>
            
                        <div class="vehicle-meta">
            
                            <span
                                class="vehicle-plate"
                                x-text="vehicle.plate"
                            ></span>
            
                            <span class="vehicle-divider">
                                •
                            </span>
            
                            <span
                                    x-text="
                                        vehicle.division?.name"
                            </span>
            
                            <span class="vehicle-divider">
                                •
                            </span>
            
                            <span>
                                Operação urbana
                            </span>
            
                        </div>
            
                        <template x-if="vehicle.main_alert">
            
                            <div class="vehicle-next-alert">
            
                                <i
                                    data-lucide="triangle-alert"
                                ></i>
            
                                <span
                                    x-text="
                                        vehicle.main_alert.procedure +
                                        ' — ' +
                                        vehicle.main_alert.message
                                    "
                                ></span>
            
                            </div>
            
                        </template>
            
                    </div>
            
                </div>
            
                {{-- KPIs --}}
                <div class="vehicle-kpis">
            
                    <div class="vehicle-kpi">
            
                        <small>
                            Hodômetro
                        </small>
            
                        <strong
                            x-text="
                                Number(vehicle.current_km)
                                .toLocaleString('pt-BR')
                            "
                        ></strong>
            
                        <span>
                            KM
                        </span>
            
                    </div>
            
                    <div class="vehicle-kpi">
            
                        <small>
                            Horímetro
                        </small>
            
                        <strong
                            x-text="vehicle.current_hours"
                        ></strong>
            
                        <span>
                            Horas
                        </span>
            
                    </div>
            
                    <div class="vehicle-kpi">
            
                        <small>
                            Preventivas
                        </small>
            
                        <strong
                            x-text="
                                vehicle.alerts
                                    ? vehicle.alerts.length
                                    : 0
                            "
                        ></strong>
            
                        <span>
                            Ativas
                        </span>
            
                    </div>
            
                </div>
            
                {{-- FECHAR --}}
                <button
                    class="modal-close"
                    @click="close()"
                >
                    ✕
                </button>
            
            </div>
            {{-- BODY --}}
            <div class="modal-body operational-body">

                {{-- SIDEBAR --}}
                <div class="vehicle-side-panel">
                
                    {{-- STATUS OPERACIONAL --}}
                    <div class="vehicle-side-card">
                    
                        <div class="side-card-header">
                    
                            <div>
                    
                                <small>
                                    Status do veículo
                                </small>
                    
                                <h4>
                                    Operação
                                </h4>
                    
                            </div>
                    
                            <div
                                class="mini-status-dot"
                                :class="
                                    vehicle.operational_status == 'maintenance'
                                        ? 'maintenance'
                                        : 'operational'
                                "
                            ></div>
                    
                        </div>
                    
                        <div class="vehicle-field-row">
                    
                            <select
                                class="nf-select"
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
                                class="nf-mini-button"
                                @click.prevent.stop="updateOperationalStatus"
                            >
                                Salvar
                            </button>
                    
                        </div>
                    
                    </div>                
                    {{-- HODÔMETRO --}}
                    <div class="vehicle-side-card">
                    
                        <div class="side-card-header">
                    
                            <div>
                    
                                <small>
                                    Atualização
                                </small>
                    
                                <h4>
                                    Hodômetro
                                </h4>
                    
                            </div>
                    
                            <div class="mini-kpi">
                                KM
                            </div>
                    
                        </div>
                    
                        <div class="vehicle-field-row">
                    
                            <input
                                type="number"
                                class="nf-input"
                                x-model="vehicle.current_km"
                                :min="originalKm"
                                step="1"
                                placeholder="0"
                            >
                    
                            <button
                                type="button"
                                class="nf-mini-button"
                                @click.prevent.stop="updateKm"
                            >
                                Atualizar
                            </button>
                    
                        </div>
                    
                    </div>                
                    {{-- HORÍMETRO --}}
                    <div class="vehicle-side-card">
                    
                        <div class="side-card-header">
                    
                            <div>
                    
                                <small>
                                    Atualização
                                </small>
                    
                                <h4>
                                    Horímetro
                                </h4>
                    
                            </div>
                    
                            <div class="mini-kpi">
                                H
                            </div>
                    
                        </div>
                    
                        <div class="vehicle-field-row">
                    
                            <input
                                type="number"
                                class="nf-input"
                                x-model="vehicle.current_hours"
                                :min="originalHours"
                                step="0.1"
                                placeholder="0"
                            >
                    
                            <button
                                type="button"
                                class="nf-mini-button"
                                @click.prevent.stop="updateHours"
                            >
                                Atualizar
                            </button>
                    
                        </div>
                    
                    </div>                
                    {{-- AÇÕES --}}
                    <div class="vehicle-side-actions">
                
                        <a
                            :href="`/vehicle/${vehicle.id}/history`"
                            class="nf-secondary-button"
                        >
                        
                            <i data-lucide="history"></i>
                        
                            Histórico completo
                        
                        </a>
                
                        <a
                            :href="`/vehicles/${vehicle.id}/edit`"                            class="nf-secondary-button"
                        >
                        
                            <i data-lucide="pencil"></i>
                        
                            Editar veículo
                        
                        </a>
                
                    </div>
                    <!--ALERTAS-->
                    <div class="vehicle-alert-stack">
                    
                        <template
                            x-for="alert in vehicle.alerts"
                            :key="alert.id"
                        >
                    
                            <div
                                class="vehicle-alert-item"
                                :class="alert.status"
                            >
                    
                                <i data-lucide="triangle-alert"></i>
                    
                                <span
                                    x-text="alert.message"
                                ></span>
                    
                            </div>
                    
                        </template>
                    
                    </div>
                </div>
                {{-- FORM --}}
                <div class="vehicle-main-panel">

                    <div class="vehicle-tabs">
                    
                        <button
                            :class="{ active: activeTab == 'maintenance' }"
                            @click="activeTab = 'maintenance'"
                        >
                            <i data-lucide="wrench"></i>
                            Manutenção
                        </button>
                    
                        <button
                            :class="{ active: activeTab == 'history' }"
                            @click="activeTab = 'history'"
                        >
                            <i data-lucide="history"></i>
                            Histórico
                        </button>
                    
                        <button
                            :class="{ active: activeTab == 'alerts' }"
                            @click="activeTab = 'alerts'"
                        >
                            <i data-lucide="triangle-alert"></i>
                            Alertas
                        </button>
                    
                    </div>
                    
<div
    class="maintenance-form compact-maintenance-form"
    :class="
        fields.execution_type == 'external'
            ? 'external-maintenance'
            : 'internal-maintenance'
    "
    x-show="activeTab == 'maintenance'"
>

    {{-- HEADER --}}
    <div class="maintenance-header">

        <div>

            <h3>
                Inclusão rápida de manutenção
            </h3>

            <p>
                Registro operacional do veículo
            </p>

        </div>

    </div>

    {{-- TOPO --}}
    <div class="maintenance-toolbar">

        {{-- PROCEDIMENTO --}}
        <div class="toolbar-procedure">

            <label>
                Procedimento
            </label>

            <select
                class="form-input"
                x-model="selectedProcedureId"
            >

                <option value="">
                    Selecione
                </option>

                <template
                    x-for="procedure in vehicle.procedures"
                    :key="procedure.id"
                >

                    <option
                        :value="procedure.id"
                        x-text="procedure.name"
                    ></option>

                </template>

            </select>

        </div>

        {{-- EXECUÇÃO --}}
        <div class="toolbar-execution">

            <div class="toolbar-execution">

                <label>
                    Execução
                </label>

                <div class="execution-toggle">

                    {{-- INTERNA --}}
                    <button
                        type="button"
                        class="execution-pill"
                        :class="{
                        
                            active:
                                fields.execution_type == 'internal',
                        
                            disabled:
                                !canInternal
                        }"
                        @click="
                        if (canInternal) 
                            {
                        
                                fields.execution_type =
                                    'internal'
                            }
                        "
                    >

                        <i data-lucide="warehouse"></i>

                        Oficina interna

                    </button>

                    {{-- EXTERNA --}}
                    <button
                        type="button"
                        class="execution-pill"
                        :class="{
                            active:
                                fields.execution_type == 'external'
                        }"
                        @click="
                            fields.execution_type = 'external'
                        "
                    >

                        <i data-lucide="building-2"></i>

                        Terceirizado

                    </button>

                </div>

            </div>

        
        </div>

    </div>

    {{-- FORNECEDOR --}}
    <template x-if="fields.execution_type == 'external'">

        <div class="maintenance-inline-grid">

            <div class="dynamic-field">

                <label>
                    Oficina / Fornecedor
                </label>

                <input
                    type="text"
                    class="form-input"
                    placeholder="Nome da oficina"
                    x-model="fields.provider_name"
                >

            </div>

        </div>

    </template>

    {{-- ITENS --}}
    <template
        x-if="
            fields.execution_type != 'external'
            &&
            selectedFields.length
        "
    >

        <div class="maintenance-block">

            <div class="maintenance-block-title">

                <i data-lucide="boxes"></i>

                <span>
                    Itens utilizados
                </span>

            </div>

            <div class="maintenance-items-list">

                <template
                    x-for="field in selectedFields"
                    :key="field.id"
                >

                    <div class="maintenance-inline-item">

                        <div class="inline-item-info">

                            <strong
                                x-text="field.label"
                            ></strong>

                            <small>
                                Item operacional
                            </small>

                        </div>

                        {{-- STOCK --}}
                        <template
                            x-if="field.field_type == 'stock_item'"
                        >

                            <div class="inline-item-controls">

                                <select
                                    class="form-input"
                                    x-model="fields[field.slug]"
                                >

                                    <option value="">
                                        Selecione
                                    </option>

                                    <template
                                        x-for="
                                            item in filteredStockItems(
                                                field.stock_category_id
                                            )
                                        "
                                        :key="item.id"
                                    >

                                        <option
                                            :value="item.id"
                                            x-text="
                                                item.name +
                                                ' (' +
                                                item.quantity +
                                                ')'
                                            "
                                        ></option>

                                    </template>

                                </select>

                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    placeholder="Qtd"
                                    class="form-input qty-mini-input"
                                    x-model="
                                        fields[
                                            field.slug + '_quantity'
                                        ]
                                    "
                                >

                            </div>

                        </template>

                    </div>

                </template>

            </div>

        </div>

    </template>

    {{-- REGISTRO --}}
    <div class="maintenance-block">

        <div class="maintenance-block-title">

            <i data-lucide="clipboard-pen"></i>

            <span>
                Registro operacional
            </span>

        </div>

        <div class="maintenance-inline-grid">

            {{-- MOTIVO --}}
            <div class="dynamic-field">

                <label>
                    Motivo
                </label>

                <select
                    class="form-input"
                    x-model="fields.reason"
                >

                    <option value="preventiva">
                        Preventiva
                    </option>

                    <option value="corretiva">
                        Corretiva
                    </option>

                    <option value="desgaste">
                        Desgaste
                    </option>

                    <option value="emergencial">
                        Emergencial
                    </option>

                    <option value="programada">
                        Programada
                    </option>

                </select>

            </div>

            {{-- CUSTO --}}
            <div class="dynamic-field">
            
                <label>
            
                    <span
                        x-text="
                            fields.execution_type == 'external'
                                ? 'Custo do serviço'
                                : 'Custo adicional'
                        "
                    ></span>
            
                </label>
            
                <input
                    type="number"
                    step="0.01"
                    class="form-input"
                    placeholder="0.00"
                    x-model="fields.extra_cost"
                >
            
            </div>

        </div>

        {{-- OBS --}}
        <div class="dynamic-field">

            <label>
                Observações
            </label>

            <textarea
                class="form-input"
                rows="4"
                placeholder="Detalhes da manutenção..."
                x-model="fields.notes"
            ></textarea>

        </div>

    </div>

    {{-- RESUMO --}}
    <div class="maintenance-summary-card">

        <div class="summary-row">

            <span>
                Execução
            </span>

            <strong
                x-text="
                    fields.execution_type == 'external'
                        ? 'Serviço terceirizado'
                        : 'Oficina interna'
                "
            ></strong>

        </div>

        <div class="summary-row">

            <span>
                Itens utilizados
            </span>

            <strong
                x-text="selectedFields.length"
            ></strong>

        </div>

        <template
            x-if="fields.execution_type == 'external'"
        >

            <div class="summary-row">

                <span>
                    Custo previsto
                </span>

                <strong
                    x-text="
                        Number(
                            fields.extra_cost || 0
                        ).toLocaleString(
                            'pt-BR',
                            {
                                style: 'currency',
                                currency: 'BRL'
                            }
                        )
                    "
                ></strong>

            </div>

        </template>

    </div>

    {{-- SAVE --}}
    <button
        type="button"
        class="save-maintenance"
        @click="saveMaintenance()"
    >

        Salvar manutenção

    </button>

</div>    
                    <!--Aba Histórico-->
                    <div
                        class="history-panel"
                        x-show="activeTab == 'history'"
                    >
                    
                        {{-- HEADER --}}
                        <div class="history-header">
                    
                            <h3>
                                Histórico operacional
                            </h3>
                    
                            <p>
                                Movimentações e manutenções do veículo
                            </p>
                            
                            <div class="history-mini-tabs">
                            
                                <button
                                    class="history-mini-tab"
                                    :class="{
                                        'active': historyTab == 'updates'
                                    }"
                                    @click="historyTab = 'updates'"
                                >
                            
                                    <i data-lucide="activity"></i>
                            
                                    Operacional
                            
                                </button>
                            
                                <button
                                    class="history-mini-tab"
                                    :class="{
                                        'active': historyTab == 'maintenances'
                                    }"
                                    @click="historyTab = 'maintenances'"
                                >
                            
                                    <i data-lucide="wrench"></i>
                            
                                    Manutenções
                            
                                </button>
                            
                            </div>
                            
                        </div>
                    
                        {{-- ATUALIZAÇÕES --}}
                        <div
                            class="history-block"
                            x-show="historyTab == 'updates'"
                        >                    
                            <div class="history-block-title">
                    
                                <i data-lucide="activity"></i>
                    
                                <span>
                                    Atualizações operacionais
                                </span>
                    
                            </div>
                    
                            <template
                                x-if="
                                    !vehicle.update_logs
                                    ||
                                    vehicle.update_logs.length == 0
                                "
                            >
                    
                                <div class="history-empty">
                    
                                    <i data-lucide="inbox"></i>
                    
                                    <strong>
                                        Nenhuma atualização registrada
                                    </strong>
                    
                                    <p>
                                        Atualizações de KM, HR e operação aparecerão aqui.
                                    </p>
                    
                                </div>
                    
                            </template>
                    
                            <div class="timeline-list">
                    
                                <template
                                    x-for="
                                        log in (
                                            vehicle.update_logs ?? []
                                        ).slice(0, 5)
                                    "
                                    :key="log.id"
                                >
                    
                                    <div class="timeline-item">
                    
                                        <div class="timeline-dot"></div>
                    
                                        <div class="timeline-content">
                    
                                            <div class="timeline-header-row">
                    
                                                <strong x-text="
                                                    log.type == 'km'
                                                        ? 'Hodômetro'
                                                        : (
                                                            log.type == 'hours'
                                                                ? 'Horímetro'
                                                                : (
                                                                    log.type == 'division'
                                                                        ? 'Divisão'
                                                                        : (
                                                                            log.type == 'location'
                                                                                ? 'Localização'
                                                                                : log.type
                                                                        )
                                                                )
                                                        )
                                                "></strong>
                    
                                                <span
                                                    class="timeline-date"
                                                    x-text="
                                                        new Date(log.created_at)
                                                        .toLocaleDateString('pt-BR')
                                                    "
                                                ></span>
                    
                                            </div>
                    
                                            <p class="timeline-values">
                    
                                                <span
                                                    class="timeline-old"
                                                    x-text="log.old_value ?? '--'"
                                                ></span>
                    
                                                <i data-lucide="arrow-right"></i>
                    
                                                <span
                                                    class="timeline-new"
                                                    x-text="log.new_value"
                                                ></span>
                    
                                            </p>
                    
                                            <template x-if="log.user">
                    
                                                <small
                                                    class="timeline-user"
                                                    x-text="'por ' + log.user.name"
                                                ></small>
                    
                                            </template>
                    
                                        </div>
                    
                                    </div>
                    
                                </template>
                    
                            </div>
                            <template
                                x-if="
                                    vehicle.update_logs
                                    &&
                                    vehicle.update_logs.length > 5
                                "
                            >
                            
                                <button class="history-more-btn">
                            
                                    Ver histórico completo
                            
                                </button>
                            
                            </template>
                        </div>
                    
                        {{-- MANUTENÇÕES --}}
                        <div
                            class="history-block"
                            x-show="historyTab == 'maintenances'"
                        >                    
                            <div class="history-block-title">
                    
                                <i data-lucide="wrench"></i>
                    
                                <span>
                                    Manutenções realizadas
                                </span>
                    
                            </div>
                    
                            <template
                                x-if="
                                    !vehicle.maintenances
                                    ||
                                    vehicle.maintenances.length == 0
                                "
                            >
                    
                                <div class="history-empty">
                    
                                    <i data-lucide="clipboard-x"></i>
                    
                                    <strong>
                                        Nenhuma manutenção registrada
                                    </strong>
                    
                                    <p>
                                        As manutenções executadas aparecerão aqui.
                                    </p>
                    
                                </div>
                    
                            </template>
                    
                            <div class="maintenance-history-grid">
                    
                                <template
                                    x-for="
                                        maintenance in (
                                            vehicle.maintenances ?? []
                                        ).slice(0, 5)
                                    "
                                    :key="maintenance.id"
                                >
                    
                                    <div class="maintenance-history-card">
                    
                                        <div class="maintenance-history-top">
                    
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
                                                class="maintenance-history-type"
                                                :class="maintenance.maintenance_type"
                                                x-text="
                                                    maintenance.maintenance_type
                                                        == 'internal'
                                                            ? 'Interna'
                                                            : 'Externa'
                                                "
                                            ></span>
                    
                                        </div>
                    
                                        <div class="maintenance-history-meta">
                    
                                            <span>
                    
                                                KM:
                    
                                                <strong
                                                    x-text="
                                                        maintenance.performed_km
                                                        ?? '--'
                                                    "
                                                ></strong>
                    
                                            </span>
                    
                                            <span>
                    
                                                R$
                    
                                                <strong
                                                    x-text="
                                                        Number(
                                                            maintenance.total_cost
                                                            ?? 0
                                                        ).toLocaleString(
                                                            'pt-BR',
                                                            {
                                                                minimumFractionDigits: 2
                                                            }
                                                        )
                                                    "
                                                ></strong>
                    
                                            </span>
                    
                                        </div>
                    
                                        <div class="maintenance-history-footer">
                    
                                            <span
                                                x-text="
                                                    new Date(
                                                        maintenance.performed_at
                                                    ).toLocaleDateString(
                                                        'pt-BR'
                                                    )
                                                "
                                            ></span>
                    
                                        </div>
                    
                                    </div>
                    
                                </template>
                    
                            </div>
                            <template
                                x-if="
                                    vehicle.maintenances
                                    &&
                                    vehicle.maintenances.length > 5
                                "
                            >
                            
                                <button class="history-more-btn">
                            
                                    Ver todas manutenções
                            
                                </button>
                            
                            </template>
                        </div>
                    
                    </div>                    <!--Aba Alertas-->
                    <div
                    class="alerts-tab-panel"
                    x-show="activeTab == 'alerts'"
                >
                
                    <h3>
                        Alertas do veículo
                    </h3>
                
                    <template
                        x-for="alert in vehicle.alerts"
                        :key="alert.message"
                    >
                
                        <div
                            class="vehicle-alert-item"
                            :class="alert.status"
                        >
                
                            <i data-lucide="triangle-alert"></i>
                
                            <span
                                x-text="alert.message"
                            ></span>
                
                        </div>
                
                    </template>
                
                </div>
                </div>

            </div>

        </div>

    </div>

</div>

<script>


function dashboardFleet() {
    return {

        open: false,
        originalKm: 0,
        
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