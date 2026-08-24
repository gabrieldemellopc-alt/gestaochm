@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/pages/reports.css') }}?v=3">
@endpush

@section('content')
@php
    $reportPermissions = $reportPermissions ?? [];
    $canReport = fn (string $key): bool => (bool) ($reportPermissions[$key] ?? true);
    $canExportReportPdf = $canReport('reports.export_pdf');
    $canExportReportExcel = $canReport('reports.export_excel');
    $canViewReportCosts = $canReport('reports.view_costs');
    $availableReportKeys = [
        'reports.maintenance',
        'reports.tires',
        'reports.fuel',
        'reports.vehicle_dossier',
        'reports.stock',
        'reports.financial',
    ];
    $hasAvailableReports = collect($availableReportKeys)->contains(fn (string $key) => $canReport($key));
    $canOpenMaintenanceExport = $canReport('reports.maintenance') && ($canExportReportPdf || $canExportReportExcel);
@endphp

<div class="reports-page reports-index-page">

    {{-- =====================================================
         HEADER
         ===================================================== --}}
    <header class="reports-index-header">
        <div class="reports-index-header-copy">
            <span class="reports-index-kicker">
                Central analítica
            </span>

            <h1>Relatórios Operacionais</h1>

            <p>
                Consulte indicadores, análises e informações consolidadas
                da frota de {{ $context['location']->name ?? 'sua unidade' }}.
            </p>
        </div>

        <div class="reports-index-header-meta">
            <div class="reports-index-header-chip">
                <i data-lucide="building-2"></i>

                <span>
                    <small>Unidade ativa</small>
                    <strong>
                        {{ $context['location']->name ?? 'Não informada' }}
                    </strong>
                </span>
            </div>

            <div class="reports-index-header-chip">
                <i data-lucide="calendar-range"></i>

                <span>
                    <small>Período padrão</small>
                    <strong>Últimos 30 dias</strong>
                </span>
            </div>

            <div class="reports-index-header-chip">
                <i data-lucide="layout-grid"></i>

                <span>
                    <small>Módulos disponíveis</small>
                    <strong>
                        {{
                            collect($availableReportKeys)
                                ->filter(fn (string $key) => $canReport($key))
                                ->count()
                        }}
                    </strong>
                </span>
            </div>
        </div>
    </header>

    {{-- =====================================================
         FAIXA ANALÍTICA
         ===================================================== --}}
    @if($canReport('reports.maintenance'))
        <section
            class="reports-index-overview"
            aria-label="Resumo operacional dos últimos 30 dias"
        >
            <div class="reports-index-overview-heading">
                <span class="reports-index-overview-icon">
                    <i data-lucide="activity"></i>
                </span>

                <span>
                    Visão dos últimos 30 dias
                </span>
            </div>

            <div class="reports-index-overview-items">

                <div class="reports-index-overview-item">
                    <span>Manutenções</span>

                    <strong>
                        {{ $maintenanceCount30 }}
                    </strong>

                    <small>
                        {{ $internalMaintenances30 }} internas ·
                        {{ $externalMaintenances30 }} externas
                    </small>
                </div>

                <div class="reports-index-overview-separator"></div>

                <div class="reports-index-overview-item">
                    <span>Custo acumulado</span>

                    <strong>
                        @if($canViewReportCosts)
                            R$ {{ number_format(
                                $maintenanceCost30,
                                2,
                                ',',
                                '.'
                            ) }}
                        @else
                            Restrito
                        @endif
                    </strong>

                    @if($canViewReportCosts)
                        <small
                            class="{{
                                $costVariation > 0
                                    ? 'is-negative'
                                    : 'is-positive'
                            }}"
                        >
                            {{
                                $costVariation > 0
                                    ? '+'
                                    : ''
                            }}{{ number_format(
                                $costVariation,
                                1,
                                ',',
                                '.'
                            ) }}%
                            versus média semestral
                        </small>
                    @else
                        <small>Visualização sem valores financeiros</small>
                    @endif
                </div>

                <div class="reports-index-overview-separator"></div>

                <div class="reports-index-overview-item">
                    <span>Custo médio</span>

                    <strong>
                        @if($canViewReportCosts)
                            R$ {{ number_format(
                                $averageMaintenanceCost,
                                2,
                                ',',
                                '.'
                            ) }}
                        @else
                            Restrito
                        @endif
                    </strong>

                    <small>por manutenção no período</small>
                </div>

                <div class="reports-index-overview-separator"></div>

                <div class="reports-index-overview-item vehicle">
                    <span>Maior custo acumulado</span>

                    <strong>
                        {{ $criticalVehicle?->name ?? 'Sem registros' }}
                    </strong>

                    <small>
                        @if($canViewReportCosts)
                            R$ {{ number_format(
                                $criticalVehicle?->maintenances_sum_total_cost
                                    ?? 0,
                                2,
                                ',',
                                '.'
                            ) }}
                        @else
                            Custos restritos
                        @endif
                    </small>
                </div>

            </div>
        </section>
    @endif

    {{-- =====================================================
         RELATÓRIOS PRINCIPAIS
         ===================================================== --}}
    @if(
        $canReport('reports.maintenance')
        || $canReport('reports.vehicle_dossier')
    )
        <section class="reports-index-section">
            <div class="reports-index-section-header">
                <div>
                    <span class="reports-index-section-kicker">
                        Principais
                    </span>

                    <h2>Análises operacionais</h2>

                    <p>
                        Relatórios consolidados para acompanhamento da frota,
                        custos e histórico dos veículos.
                    </p>
                </div>
            </div>

            <div class="reports-index-featured-grid">

                {{-- MANUTENÇÕES --}}
                @if($canReport('reports.maintenance'))
                    <article
                        class="reports-index-featured-card maintenance"
                    >
                        <div class="reports-index-card-top">
                            <div class="reports-index-card-icon">
                                <i data-lucide="wrench"></i>
                            </div>

                            <span class="reports-index-card-category">
                                Manutenção
                            </span>
                        </div>

                        <div class="reports-index-card-content">
                            <h3>Relatório de Manutenções</h3>

                            <p>
                                Analise serviços executados, procedimentos,
                                oficinas, custos e registros cancelados.
                            </p>

                            <div class="reports-index-featured-metrics">
                                <div>
                                    <span>Registros</span>

                                    <strong>
                                        {{
                                            $maintenancePreview[
                                                'maintenanceCount'
                                            ] ?? 0
                                        }}
                                    </strong>
                                </div>

                                <div>
                                    <span>Custo operacional</span>

                                    <strong>
                                        @if($canViewReportCosts)
                                            R$ {{ number_format(
                                                $maintenancePreview[
                                                    'totalCost'
                                                ] ?? 0,
                                                2,
                                                ',',
                                                '.'
                                            ) }}
                                        @else
                                            Restrito
                                        @endif
                                    </strong>
                                </div>

                                <div>
                                    <span>Canceladas</span>

                                    <strong>
                                        {{
                                            $maintenancePreview[
                                                'cancelledCount'
                                            ] ?? 0
                                        }}
                                    </strong>
                                </div>
                            </div>
                        </div>

                        @if($canOpenMaintenanceExport)
                            <button
                                type="button"
                                class="reports-index-card-action"
                                onclick="openMaintenanceReportModal()"
                            >
                                <span>Abrir relatório</span>
                                <i data-lucide="arrow-right"></i>
                            </button>
                        @endif
                    </article>
                @endif

                {{-- DOSSIÊ --}}
                @if($canReport('reports.vehicle_dossier') && $canReport('vehicles.view_dossier'))
                    <a
                        href="{{ route(
                            'reports.vehicle-dossier.index'
                        ) }}"
                        class="reports-index-featured-card dossier"
                    >
                        <div class="reports-index-card-top">
                            <div class="reports-index-card-icon">
                                <i data-lucide="clipboard-list"></i>
                            </div>

                            <span class="reports-index-card-category">
                                Veículo
                            </span>
                        </div>

                        <div class="reports-index-card-content">
                            <h3>Dossiê do Veículo</h3>

                            <p>
                                Consulte o prontuário operacional completo de
                                um veículo, incluindo manutenção, combustível,
                                pneus, utilização e alertas.
                            </p>

                            <div class="reports-index-featured-tags">
                                <span>Manutenções</span>
                                <span>Abastecimentos</span>
                                <span>Pneus</span>
                                <span>KM e horímetro</span>
                            </div>
                        </div>

                        <div class="reports-index-card-action">
                            <span>Abrir dossiê</span>
                            <i data-lucide="arrow-right"></i>
                        </div>
                    </a>
                @endif

            </div>
        </section>
    @endif

    {{-- =====================================================
         RELATÓRIOS COMPLEMENTARES
         ===================================================== --}}
    @if(
        $canReport('reports.tires')
        || $canReport('reports.fuel')
        || $canReport('reports.stock')
        || $canReport('reports.financial')
    )
        <section class="reports-index-section">
            <div class="reports-index-section-header">
                <div>
                    <span class="reports-index-section-kicker">
                        Complementares
                    </span>

                    <h2>Controles especializados</h2>

                    <p>
                        Consulte painéis específicos para pneus,
                        abastecimentos e movimentações de estoque.
                    </p>
                </div>
            </div>

            <div class="reports-index-compact-grid">

                @if($canReport('reports.financial'))
                    <a href="{{ route('reports.financial.index') }}" class="reports-index-compact-card fuel">
                        <div class="reports-index-compact-head"><div class="reports-index-card-icon"><i data-lucide="chart-no-axes-combined"></i></div><span class="reports-index-card-category">Financeiro</span></div>
                        <div class="reports-index-compact-content"><h3>Relatório Financeiro</h3><p>Manutenções, abastecimentos, despesas da oficina e consumíveis sem duplicar entradas de estoque.</p><div class="reports-index-compact-info"><span>Custos operacionais</span><span>Por período</span><span>Por unidade</span></div></div>
                        <div class="reports-index-card-action"><span>Abrir relatório</span><i data-lucide="arrow-right"></i></div>
                    </a>
                @endif

                {{-- PNEUS --}}
                @if($canReport('reports.tires'))
                    <a
                        href="{{ route('reports.tires.index') }}"
                        class="reports-index-compact-card tires"
                    >
                        <div class="reports-index-compact-head">
                            <div class="reports-index-card-icon">
                                <i data-lucide="circle-dot"></i>
                            </div>

                            <span class="reports-index-card-category">
                                Pneus
                            </span>
                        </div>

                        <div class="reports-index-compact-content">
                            <h3>Relatório de Pneus</h3>

                            <p>
                                Inventário, recapagens, sulcos, eventos e
                                pontos críticos por veículo.
                            </p>

                            <div class="reports-index-compact-info">
                                <span>Inventário atual</span>
                                <span>Alertas críticos</span>
                                <span>Eventos por período</span>
                            </div>
                        </div>

                        <div class="reports-index-card-action">
                            <span>Abrir relatório</span>
                            <i data-lucide="arrow-right"></i>
                        </div>
                    </a>
                @endif

                {{-- ABASTECIMENTOS --}}
                @if($canReport('reports.fuel'))
                    <a
                        href="{{ route('reports.fuel.index') }}"
                        class="reports-index-compact-card fuel"
                    >
                        <div class="reports-index-compact-head">
                            <div class="reports-index-card-icon">
                                <i data-lucide="fuel"></i>
                            </div>

                            <span class="reports-index-card-category">
                                Combustível
                            </span>
                        </div>

                        <div class="reports-index-compact-content">
                            <h3>Relatório de Abastecimentos</h3>

                            <p>
                                Saldos, recebimentos, consumo por veículo,
                                produtos e custos do período.
                            </p>

                            <div class="reports-index-compact-info">
                                <span>Diesel e ARLA</span>
                                <span>Consumo por veículo</span>
                                <span>Custos e movimentações</span>
                            </div>
                        </div>

                        <div class="reports-index-card-action">
                            <span>Abrir painel</span>
                            <i data-lucide="arrow-right"></i>
                        </div>
                    </a>
                @endif

                {{-- ESTOQUE --}}
                @if($canReport('reports.stock'))
                    <a
                        href="{{ route('reports.stock.index') }}"
                        class="reports-index-compact-card stock"
                    >
                        <div class="reports-index-compact-head">
                            <div class="reports-index-card-icon">
                                <i data-lucide="package-search"></i>
                            </div>

                            <span class="reports-index-card-category">
                                Estoque
                            </span>
                        </div>

                        <div class="reports-index-compact-content">
                            <h3>Relatório de Estoque</h3>

                            <p>
                                Analise entradas, saídas, consumo de materiais
                                e itens abaixo do nível mínimo.
                            </p>

                            <div class="reports-index-compact-info">
                                <span>
                                    {{ $stockItems ?? 0 }} itens cadastrados
                                </span>

                                <span>Movimentações</span>
                                <span>Consumo operacional</span>
                            </div>
                        </div>

                        <div class="reports-index-card-action">
                            <span>Abrir relatório</span>
                            <i data-lucide="arrow-right"></i>
                        </div>
                    </a>
                @endif

            </div>
        </section>
    @endif

    @unless($hasAvailableReports)
        <section class="reports-index-empty">
            <span class="reports-index-empty-icon">
                <i data-lucide="lock"></i>
            </span>

            <div>
                <h2>Nenhum relatório disponível</h2>

                <p>
                    Seu perfil não possui acesso a relatórios nesta unidade.
                </p>
            </div>
        </section>
    @endunless

</div>

@if($canOpenMaintenanceExport)
    <div id="reportModal" class="report-modal-overlay">
        <div class="report-modal">
            <div class="report-modal-header">
                <div>
                    <h3 id="reportModalTitle">Relatório</h3>
                    <p>Configure os parâmetros da exportação</p>
                </div>
                <button class="report-modal-close" onclick="closeReportModal()">
                    <i data-lucide="x"></i>
                </button>
            </div>

            <div class="report-modal-body">
                <div class="report-form-group">
                    <label>Período</label>
                    <div class="report-period-shortcuts">
                        <button type="button" class="report-shortcut-button" onclick="setReportPeriod(30)">Últimos 30 dias</button>
                        <button type="button" class="report-shortcut-button" onclick="setReportPeriod(90)">Últimos 90 dias</button>
                        <button type="button" class="report-shortcut-button" onclick="setReportPeriod(180)">Últimos 180 dias</button>
                    </div>
                    <div class="report-date-grid">
                        <input type="date" class="nf-input" id="reportStartDate">
                        <input type="date" class="nf-input" id="reportEndDate">
                    </div>
                </div>

                <div class="report-form-group">
                    <label>Filtros</label>
                    <div class="report-date-grid">
                        <select class="nf-input" id="reportVehicleId">
                            <option value="">Todos os veículos</option>
                            @foreach($reportVehicles as $vehicle)
                                <option value="{{ $vehicle->id }}">{{ $vehicle->plate }} - {{ $vehicle->name }}</option>
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
                                <option value="{{ $procedure->id }}">{{ $procedure->name }}</option>
                            @endforeach
                        </select>

                        <input type="text" class="nf-input" id="reportProviderName" list="reportProviderOptions" placeholder="Fornecedor/oficina">
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

                @if($canExportReportPdf || $canExportReportExcel)
                    <div class="report-form-group">
                        <label>Tipo de exportação</label>
                        <div class="report-export-grid">
                            @if($canExportReportPdf)
                                <button class="report-export-card active" type="button" data-type="pdf" onclick="selectExportType(this)">
                                    <i data-lucide="file-text"></i>
                                    PDF
                                </button>
                            @endif
                            @if($canExportReportExcel)
                                <button class="report-export-card {{ $canExportReportPdf ? '' : 'active' }}" type="button" data-type="excel" onclick="selectExportType(this)">
                                    <i data-lucide="sheet"></i>
                                    Excel
                                </button>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            <div class="report-modal-footer">
                <button class="report-secondary-button" onclick="closeReportModal()">Cancelar</button>
                <form method="POST" id="reportExportForm" action="{{ $canExportReportPdf ? route('reports.maintenance.export') : route('reports.maintenance.export.excel') }}">
                    @csrf
                    <input type="hidden" name="export_type" id="reportExportType" value="{{ $canExportReportPdf ? 'pdf' : 'excel' }}">
                    <input type="hidden" name="start_date" id="reportFormStartDate">
                    <input type="hidden" name="end_date" id="reportFormEndDate">
                    <input type="hidden" name="vehicle_id" id="reportFormVehicleId">
                    <input type="hidden" name="maintenance_type" id="reportFormMaintenanceType">
                    <input type="hidden" name="procedure_id" id="reportFormProcedureId">
                    <input type="hidden" name="provider_name" id="reportFormProviderName">
                    <input type="hidden" name="status" id="reportFormMaintenanceStatus" value="active">
                    <input type="hidden" name="include_cancelled" id="reportFormIncludeCancelled" value="0">
                    <button type="submit" class="report-module-button" onclick="syncReportDates()">Gerar relatório</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        const reportModal = document.getElementById('reportModal');
        const reportModalTitle = document.getElementById('reportModalTitle');
        let selectedExportType = '{{ $canExportReportPdf ? 'pdf' : 'excel' }}';

        function openMaintenanceReportModal() {
            reportModal.classList.add('active');
            reportModalTitle.innerText = 'Relatório de Manutenções';
        }

        function closeReportModal() {
            reportModal.classList.remove('active');
        }

        function setReportPeriod(days) {
            const startInput = document.getElementById('reportStartDate');
            const endInput = document.getElementById('reportEndDate');
            const today = new Date();
            const startDate = new Date();
            startDate.setDate(today.getDate() - days);
            endInput.value = today.toISOString().split('T')[0];
            startInput.value = startDate.toISOString().split('T')[0];
        }

        function syncReportDates() {
            document.getElementById('reportFormStartDate').value = document.getElementById('reportStartDate').value;
            document.getElementById('reportFormEndDate').value = document.getElementById('reportEndDate').value;
            document.getElementById('reportFormVehicleId').value = document.getElementById('reportVehicleId').value;
            document.getElementById('reportFormMaintenanceType').value = document.getElementById('reportMaintenanceType').value;
            document.getElementById('reportFormProcedureId').value = document.getElementById('reportProcedureId').value;
            document.getElementById('reportFormProviderName').value = document.getElementById('reportProviderName').value;

            const statusValue = document.getElementById('reportMaintenanceStatus').value;
            document.getElementById('reportFormMaintenanceStatus').value = statusValue;
            document.getElementById('reportFormIncludeCancelled').value = statusValue === 'all' || statusValue === 'cancelled' ? '1' : '0';

            const form = document.getElementById('reportExportForm');
            form.action = selectedExportType === 'excel'
                ? "{{ route('reports.maintenance.export.excel') }}"
                : "{{ route('reports.maintenance.export') }}";
        }

        function selectExportType(button) {
            document.querySelectorAll('.report-export-card').forEach(card => card.classList.remove('active'));
            button.classList.add('active');
            selectedExportType = button.dataset.type;
            document.getElementById('reportExportType').value = selectedExportType;
        }
    </script>
@endif
@endsection
