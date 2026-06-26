@extends('layouts.app')

@push('styles')

<link
    rel="stylesheet"
    href="{{ asset('css/pages/reports.css') }}?v=2"
>

@endpush

@section('content')

<div class="reports-page">

    {{-- HERO --}}
    <div class="reports-hero">

        <div class="reports-hero-badge">

            Central analítica da frota

        </div>

        <h1>
            Relatórios Operacionais
        </h1>

        <p>
            Indicadores, análises e inteligência operacional da frota
        </p>

    </div>

    {{-- KPI GRID --}}
    <div class="reports-kpi-grid">

        {{-- MANUTENÇÕES --}}
        <div class="reports-kpi-card info">

            <div class="reports-kpi-label">

                Manutenções • Últimos 30 dias

            </div>

            <div class="reports-kpi-value">

                {{ $maintenanceCount30 }}

            </div>

            <div class="reports-kpi-description">

                {{ $internalMaintenances30 }}
                internas •

                {{ $externalMaintenances30 }}
                externas

            </div>

            <div class="reports-kpi-trend">

                @if($maintenanceVariation >= 0)

                    <span class="trend-positive">

                        +{{ number_format($maintenanceVariation, 1, ',', '.') }}%

                    </span>

                @else

                    <span class="trend-negative">

                        {{ number_format($maintenanceVariation, 1, ',', '.') }}%

                    </span>

                @endif

                vs média últimos 6 meses

            </div>

        </div>

        {{-- CUSTOS --}}
        <div class="reports-kpi-card highlight">

            <div class="reports-kpi-label">

                Custos • Últimos 30 dias

            </div>

            <div class="reports-kpi-value">

                R$
                {{ number_format($maintenanceCost30, 2, ',', '.') }}

            </div>

            <div class="reports-kpi-description">

                custos operacionais acumulados

            </div>

            <div class="reports-kpi-trend">

                @if($costVariation >= 0)

                    <span class="trend-negative">

                        +{{ number_format($costVariation, 1, ',', '.') }}%

                    </span>

                @else

                    <span class="trend-positive">

                        {{ number_format($costVariation, 1, ',', '.') }}%

                    </span>

                @endif

                vs média últimos 6 meses

            </div>

        </div>

        {{-- CUSTO MÉDIO --}}
        <div class="reports-kpi-card success">

            <div class="reports-kpi-label">

                Custo médio por manutenção

            </div>

            <div class="reports-kpi-value">

                R$
                {{ number_format($averageMaintenanceCost, 2, ',', '.') }}

            </div>

            <div class="reports-kpi-description">

                média operacional do período

            </div>

        </div>

        {{-- VEÍCULO CRÍTICO --}}
        <div class="reports-kpi-card warning">

            <div class="reports-kpi-label">

                Veículo com maior custo

            </div>

            <div class="reports-kpi-value vehicle-kpi">

                {{ $criticalVehicle?->name ?? '—' }}

            </div>

            <div class="reports-kpi-description">

                R$

                {{ number_format(
                    $criticalVehicle?->maintenances_sum_total_cost ?? 0,
                    2,
                    ',',
                    '.'
                ) }}

                acumulados

            </div>

        </div>

    </div>

    {{-- RELATÓRIOS --}}
    <div class="reports-grid">

        {{-- MANUTENÇÕES --}}
        <div class="report-module-card">

            <div class="report-module-icon">

                <i data-lucide="wrench"></i>

            </div>

            <h3>
                Relatório de Manutenções
            </h3>

            <p>
                Gere relatórios completos de serviços executados,
                custos, procedimentos e oficinas.
            </p>

            <div class="reports-kpi-description">

                {{ $maintenancePreview['maintenanceCount'] ?? 0 }}
                registros no período padrão •
                R$
                {{ number_format($maintenancePreview['totalCost'] ?? 0, 2, ',', '.') }}
                operacionais

                @if(($maintenancePreview['cancelledCount'] ?? 0) > 0)
                    • {{ $maintenancePreview['cancelledCount'] }} cancelada(s) fora dos totais
                @endif

            </div>

            <div class="report-module-actions">

                <button
                    class="report-module-button"
                    onclick="
                        openMaintenanceReportModal()
                    "
                >

                    Abrir relatório

                </button>

            </div>

        </div>

        {{-- PNEUS --}}

        <div class="report-module-card">



            <div class="report-module-icon">



                <i data-lucide="circle-dot"></i>



            </div>



            <h3>

                Relatorio de Pneus

            </h3>



            <p>

                Acompanhe pneus por unidade, veiculo, recapagens,
                sulco atual e alertas criticos.

            </p>



            <div class="reports-kpi-description">

                Inventario atual, eventos por periodo e pontos de atencao

            </div>



            <div class="report-module-actions">



                <a

                    href="{{ route('reports.tires.index') }}"

                    class="report-module-button"

                >



                    Abrir relatorio



                </a>



            </div>



        </div>



        {{-- ABASTECIMENTOS --}}

        <div class="report-module-card">



            <div class="report-module-icon">



                <i data-lucide="fuel"></i>



            </div>



            <h3>

                Relatorio de Abastecimentos

            </h3>



            <p>

                Acompanhe saldos de tanques, recebimentos, abastecimentos
                e consumo por veiculo.

            </p>



            <div class="reports-kpi-description">

                Diesel, ARLA, custos, alertas e movimentacoes por periodo

            </div>



            <div class="report-module-actions">



                <a

                    href="{{ route('reports.fuel.index') }}"

                    class="report-module-button"

                >



                    Abrir painel



                </a>



            </div>



        </div>



        {{-- OPERACIONAL --}}
        <div class="report-module-card">

            <div class="report-module-icon">

                <i data-lucide="truck"></i>

            </div>

            <h3>
                Relatório Operacional
            </h3>

            <p>
                Histórico de KM, horímetro,
                localizações e movimentações da frota.
            </p>

            <div class="report-module-actions">

                <button
                    class="report-module-button"
                >

                    Em breve

                </button>

            </div>

        </div>

        {{-- ESTOQUE --}}
        <div class="report-module-card">

            <div class="report-module-icon">

                <i data-lucide="package-search"></i>

            </div>

            <h3>
                Relatório de Estoque
            </h3>

            <p>
                Entradas, saídas, movimentações
                e consumo de itens operacionais.
            </p>

            <div class="report-module-actions">

                <button
                    class="report-module-button"
                    onclick="
                        openStockReportModal()
                    "
                >

                    Abrir relatório

                </button>

            </div>

        </div>

        {{-- ALERTAS --}}
        <div class="report-module-card disabled">

            <div class="report-module-icon">

                <i data-lucide="triangle-alert"></i>

            </div>

            <h3>
                Alertas Preventivos
            </h3>

            <p>
                Controle preventivo,
                vencimentos e pendências operacionais.
            </p>

            <div class="report-module-actions">

                <button
                    class="report-module-button secondary"
                >

                    Em desenvolvimento

                </button>

            </div>

        </div>

    </div>

</div>

{{-- MODAL --}}
<div
    id="reportModal"
    class="report-modal-overlay"
>

    <div class="report-modal">

        <div class="report-modal-header">

            <div>

                <h3 id="reportModalTitle">
                    Relatório
                </h3>

                <p>
                    Configure os parâmetros da exportação
                </p>

            </div>

            <button
                class="report-modal-close"
                onclick="closeReportModal()"
            >

                <i data-lucide="x"></i>

            </button>

        </div>

        <div class="report-modal-body">

            {{-- PERÍODO --}}
            <div class="report-form-group">

                <label>
                    Período
                </label>
                
                <div class="report-period-shortcuts">
                
                    <button
                        type="button"
                        class="report-shortcut-button"
                        onclick="setReportPeriod(30)"
                    >
                
                        Últimos 30 dias
                
                    </button>
                
                    <button
                        type="button"
                        class="report-shortcut-button"
                        onclick="setReportPeriod(90)"
                    >
                
                        Últimos 90 dias
                
                    </button>
                
                    <button
                        type="button"
                        class="report-shortcut-button"
                        onclick="setReportPeriod(180)"
                    >
                
                        Últimos 180 dias
                
                    </button>
                
                </div>
                
                <div class="report-date-grid">

                    <input
                        type="date"
                        class="nf-input"
                        id="reportStartDate"
                    >

                    <input
                        type="date"
                        class="nf-input"
                        id="reportEndDate"
                    >

                </div>

            </div>

            {{-- FILTROS --}}

            <div class="report-form-group">

                <label>

                    Filtros

                </label>

                <div class="report-date-grid">

                    <select class="nf-input" id="reportVehicleId">
                        <option value="">Todos os veículos</option>
                        @foreach($reportVehicles as $vehicle)
                            <option value="{{ $vehicle->id }}">
                                {{ $vehicle->plate }} - {{ $vehicle->name }}
                            </option>
                        @endforeach
                    </select>

                    <select class="nf-input" id="reportMaintenanceType">
                        <option value="">Internas e externas</option>
                        <option value="internal">Somente internas</option>
                        <option value="external">Somente externas</option>
                    </select>

                    <select class="nf-input" id="reportProcedureId">
                        <option value="">Todos os procedimentos</option>
                        @foreach($procedures as $procedure)
                            <option value="{{ $procedure->id }}">
                                {{ $procedure->name }}
                            </option>
                        @endforeach
                    </select>

                    <input
                        type="text"
                        class="nf-input"
                        id="reportProviderName"
                        list="reportProviderOptions"
                        placeholder="Fornecedor/oficina"
                    >

                    <datalist id="reportProviderOptions">
                        @foreach($providers as $provider)
                            <option value="{{ $provider }}"></option>
                        @endforeach
                    </datalist>

                    <select class="nf-input" id="reportMaintenanceStatus">
                        <option value="active">Somente ativas</option>
                        @if($context['can_view_cancelled'])
                            <option value="all">Ativas e canceladas</option>
                            <option value="cancelled">Somente canceladas</option>
                        @endif
                    </select>

                </div>

            </div>

            {{-- EXPORTAÇÃO --}}
            <div class="report-form-group">

                <label>
                    Tipo de exportação
                </label>

                <div class="report-export-grid">
                
                    <button
                        class="report-export-card active"
                        type="button"
                        data-type="pdf"
                        onclick="selectExportType(this)"
                    >
                
                        <i data-lucide="file-text"></i>
                
                        PDF
                
                    </button>
                
                    <button
                        class="report-export-card"
                        type="button"
                        data-type="excel"
                        onclick="selectExportType(this)"
                    >
                
                        <i data-lucide="sheet"></i>
                
                        Excel
                
                    </button>
                
                </div>
            </div>

        </div>

        <div class="report-modal-footer">

            <button
                class="report-secondary-button"
                onclick="closeReportModal()"
            >

                Cancelar

            </button>

            <form
                method="POST"
                id="reportExportForm"
                action="{{ route('reports.maintenance.export') }}"
            >
            
                @csrf
            
                <input
                    type="hidden"
                    name="export_type"
                    id="reportExportType"
                    value="pdf"
                >
                <input
                    type="hidden"
                    name="start_date"
                    id="reportFormStartDate"
                >
            
                <input
                    type="hidden"
                    name="end_date"
                    id="reportFormEndDate"
                >

                <input type="hidden" name="vehicle_id" id="reportFormVehicleId">

                <input type="hidden" name="maintenance_type" id="reportFormMaintenanceType">

                <input type="hidden" name="procedure_id" id="reportFormProcedureId">

                <input type="hidden" name="provider_name" id="reportFormProviderName">

                <input type="hidden" name="status" id="reportFormMaintenanceStatus" value="active">

                <input type="hidden" name="include_cancelled" id="reportFormIncludeCancelled" value="0">
            
                <button
                    type="submit"
                    class="report-module-button"
                    onclick="syncReportDates()"
                >
            
                    Gerar relatório
            
                </button>
            
            </form>

        </div>

    </div>

</div>

<script>

    const reportModal =
        document.getElementById(
            'reportModal'
        );

    const reportModalTitle =
        document.getElementById(
            'reportModalTitle'
        );

    function openMaintenanceReportModal()
    {
        reportModal.classList.add(
            'active'
        );

        reportModalTitle.innerText =
            'Relatório de Manutenções';
    }

    function openStockReportModal()
    {
        reportModal.classList.add(
            'active'
        );

        reportModalTitle.innerText =
            'Relatório de Estoque';
    }

    function closeReportModal()
    {
        reportModal.classList.remove(
            'active'
        );
    }
    function setReportPeriod(days)
    {
        const startInput =
            document.getElementById(
                'reportStartDate'
            );
    
        const endInput =
            document.getElementById(
                'reportEndDate'
            );
    
        const today =
            new Date();
    
        const startDate =
            new Date();
    
        startDate.setDate(
            today.getDate() - days
        );
    
        endInput.value =
            today.toISOString().split('T')[0];
    
        startInput.value =
            startDate.toISOString().split('T')[0];
    }
    function syncReportDates()
    {
        document.getElementById(
            'reportFormStartDate'
        ).value =
            document.getElementById(
                'reportStartDate'
            ).value;
    
        document.getElementById(
            'reportFormEndDate'
        ).value =
            document.getElementById(
                'reportEndDate'
            ).value;
    
        document.getElementById('reportFormVehicleId').value =
            document.getElementById('reportVehicleId').value;

        document.getElementById('reportFormMaintenanceType').value =
            document.getElementById('reportMaintenanceType').value;

        document.getElementById('reportFormProcedureId').value =
            document.getElementById('reportProcedureId').value;

        document.getElementById('reportFormProviderName').value =
            document.getElementById('reportProviderName').value;

        const statusValue =
            document.getElementById('reportMaintenanceStatus').value;

        document.getElementById('reportFormMaintenanceStatus').value =
            statusValue;

        document.getElementById('reportFormIncludeCancelled').value =
            statusValue === 'all' || statusValue === 'cancelled' ? '1' : '0';

        const form =
            document.getElementById(
                'reportExportForm'
            );
    
        if(selectedExportType === 'excel')
        {
            form.action =
                "{{ route('reports.maintenance.export.excel') }}";
        }
        else
        {
            form.action =
                "{{ route('reports.maintenance.export') }}";
        }
    }
    
    let selectedExportType = 'pdf';
    
    function selectExportType(button)
    {
        document
            .querySelectorAll('.report-export-card')
            .forEach(card => {
    
                card.classList.remove('active');
    
            });
    
        button.classList.add('active');
    
        selectedExportType =
            button.dataset.type;
    
        document.getElementById(
            'reportExportType'
        ).value = selectedExportType;
    }
</script>


@endsection
