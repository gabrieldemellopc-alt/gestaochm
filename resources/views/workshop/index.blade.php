@extends('layouts.app')



@push('styles')

<link rel="stylesheet" href="{{ asset('css/pages/workshop.css') }}?v=5">
<style>
    .workshop-dashboard-modal { position:fixed; inset:0; z-index:9999; display:grid; place-items:center; padding:24px; background:rgba(15, 23, 42, .68); backdrop-filter:blur(6px); }
    .workshop-dashboard-modal[hidden] { display:none; }
    .workshop-dashboard-card { --workshop-dashboard-surface:var(--chm-theme-card, #111827); --workshop-dashboard-surface-soft:var(--chm-theme-card-elevated, #172235); --workshop-dashboard-border:var(--chm-theme-border, rgba(148, 163, 184, .18)); --workshop-dashboard-border-strong:var(--chm-theme-border-strong, rgba(148, 163, 184, .3)); --workshop-dashboard-text:var(--chm-theme-text, #f1f5f9); --workshop-dashboard-muted:var(--chm-theme-muted, #a8b1c1); position:relative; width:min(1180px, 100%); max-height:calc(100vh - 48px); overflow:auto; padding:24px; border:1px solid var(--workshop-dashboard-border-strong); border-radius:22px; background:var(--workshop-dashboard-surface); color:var(--workshop-dashboard-text); box-shadow:0 24px 64px rgba(15, 23, 42, .38); }
    .workshop-dashboard-close { position:static; width:38px; height:38px; display:inline-grid; place-items:center; flex:0 0 auto; border:1px solid var(--workshop-dashboard-border); border-radius:10px; background:var(--workshop-dashboard-surface-soft); color:var(--workshop-dashboard-text); font-size:25px; line-height:1; cursor:pointer; }
    .workshop-dashboard-close:hover { border-color:rgba(224, 82, 91, .55); background:#b72d36; color:#fff; }
    .workshop-dashboard-heading { display:flex; align-items:flex-start; justify-content:space-between; gap:18px; padding:0 0 18px; border-bottom:1px solid var(--workshop-dashboard-border); }
    .workshop-dashboard-heading > div { min-width:0; }
    .workshop-dashboard-heading span { color:var(--workshop-dashboard-muted); font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.08em; }
    .workshop-dashboard-heading h2 { margin:5px 0 4px; color:var(--workshop-dashboard-text); font-size:24px; line-height:1.15; }
    .workshop-dashboard-heading p { margin:0; color:var(--workshop-dashboard-muted); font-size:13px; }
    .workshop-dashboard-controls { display:flex; align-items:flex-start; gap:12px; }
    .workshop-dashboard-heading label { display:grid; gap:5px; color:var(--workshop-dashboard-muted); font-size:11px; font-weight:800; }
    .workshop-dashboard-heading select { min-width:180px; min-height:38px; padding:0 32px 0 11px; border:1px solid var(--workshop-dashboard-border-strong); border-radius:9px; outline:none; background:var(--chm-theme-input, #0f172a); color:var(--workshop-dashboard-text); font:inherit; }
    .workshop-dashboard-kpis { display:grid; grid-template-columns:repeat(5, minmax(0, 1fr)); gap:12px; margin:18px 0 12px; }
    .workshop-dashboard-kpi, .workshop-dashboard-section { border:1px solid var(--workshop-dashboard-border); border-radius:14px; background:var(--workshop-dashboard-surface-soft); }
    .workshop-dashboard-kpi { padding:14px; }
    .workshop-dashboard-kpi span, .workshop-dashboard-kpi small { color:var(--workshop-dashboard-muted); }
    .workshop-dashboard-kpi strong, .workshop-dashboard-bar strong { color:var(--workshop-dashboard-text); }
    .workshop-dashboard-grid { gap:12px; }
    .workshop-dashboard-section { padding:16px; }
    .workshop-dashboard-section h3 { color:var(--workshop-dashboard-text); }
    .workshop-dashboard-bar span, .workshop-dashboard-orders span, .workshop-dashboard-empty, .workshop-dashboard-loading, .workshop-dashboard-caption { color:var(--workshop-dashboard-muted); }
    .workshop-dashboard-bar i { background:var(--chm-theme-input, #0f172a); }
    .workshop-dashboard-bar i b { background:linear-gradient(90deg, #397fd1, #74c1ff); }
    .workshop-dashboard-orders a { background:var(--workshop-dashboard-surface); border:1px solid var(--workshop-dashboard-border); color:var(--workshop-dashboard-text); }
    .workshop-dashboard-orders a:hover { background:var(--workshop-dashboard-surface-soft); border-color:var(--workshop-dashboard-border-strong); }
    .workshop-dashboard-orders b { color:var(--workshop-dashboard-text); }
    .workshop-dashboard-loading { padding:42px 0; text-align:center; }
    @media (max-width:900px) { .workshop-dashboard-kpis { grid-template-columns:repeat(3, minmax(0, 1fr)); } }
    @media (max-width:640px) { .workshop-dashboard-modal { padding:12px; } .workshop-dashboard-card { max-height:calc(100vh - 24px); padding:18px; } .workshop-dashboard-heading { align-items:stretch; flex-direction:column; } .workshop-dashboard-controls, .workshop-dashboard-heading label, .workshop-dashboard-heading select { width:100%; } .workshop-dashboard-kpis, .workshop-dashboard-grid { grid-template-columns:1fr; } }
</style>
@endpush



@section('content')



<div class="workshop-home-page">



    <div class="workshop-command-hero">



        <div class="workshop-command-main">

            <span class="workshop-home-kicker">

                Oficina

            </span>



            <h1>

                Central da Oficina

            </h1>



            <p>

                Acompanhe veículos em manutenção, alertas técnicos, estoque crítico, pneus e procedimentos operacionais em um único painel.

            </p>

        </div>



        <div class="workshop-hero-actions" aria-label="Ações rápidas da oficina">
            <button type="button" class="workshop-hero-action workshop-hero-action-primary" onclick="openWorkshopMaintenanceDashboard()"><i class="bi bi-tools"></i><span><strong>Painel de manutenção</strong><small>Veículos, ordens e acompanhamento</small></span><i class="bi bi-arrow-right"></i></button>
            <button type="button" class="workshop-hero-action" onclick="openWorkshopExpenseModal()"><i class="bi bi-receipt"></i><span><strong>Registrar despesa</strong><small>Custos e gastos da oficina</small></span><i class="bi bi-arrow-right"></i></button>
            <button type="button" class="workshop-hero-action" onclick="openWorkshopConsumptionModal()"><i class="bi bi-box-seam"></i><span><strong>Registrar consumo</strong><small>Item de estoque</small></span><i class="bi bi-arrow-right"></i></button>
        </div>



    </div>



    <div class="workshop-summary-grid">



        <div class="workshop-summary-card">
            <div class="workshop-summary-icon">

                <i class="bi bi-truck"></i>

            </div>



            <div>

                <span>Veículos</span>

                <strong>{{ $maintenanceVehiclesCount }}</strong>

                <p>Em manutenção ou indisponíveis</p>

            </div>

        </div>



        <div class="workshop-summary-card">
            <div class="workshop-summary-icon danger">

                <i class="bi bi-exclamation-triangle"></i>

            </div>



            <div>

                <span>Estoque</span>

                <strong>{{ $lowStockCount }}</strong>

                <p>Itens abaixo do mínimo</p>

            </div>

        </div>



        <div class="workshop-summary-card">
            <div class="workshop-summary-icon warning">

                <i class="bi bi-circle"></i>

            </div>



            <div>

                <span>Pneus</span>

                <strong>{{ $tiresAttentionCount }}</strong>

                <p>Com alerta ou manutenção</p>

            </div>

        </div>



        <div class="workshop-summary-card">
            <div class="workshop-summary-icon">

                <i class="bi bi-wallet2"></i>

            </div>



            <div>

                <span>Custo do mês</span>

                <strong>R$ {{ number_format($workshopOperationalCostMonth, 2, ',', '.') }}</strong>

                <p>{{ now()->locale('pt_BR')->translatedFormat('F/Y') }}</p>

            </div>

        </div>



    </div>



    <div class="workshop-content-grid">



        <section class="workshop-panel workshop-panel-large">



            <div class="workshop-panel-header">

                <div>

                    <span>Manutenção</span>

                    <h2>Veículos parados</h2>

                </div>



                <a href="{{ route('vehicles.index') }}">

                    Ver frota

                    <i class="bi bi-arrow-right"></i>

                </a>

            </div>



        @if($vehiclesInMaintenance->count())
        
            <div class="workshop-maintenance-list">
        
                @foreach($vehiclesInMaintenance as $maintenance)
        
                    @php
                        $maintenanceStartedAt = \Illuminate\Support\Carbon::parse(
                            $maintenance->started_at
                                ?? $maintenance->maintenance_created_at
                        );
        
                        $stoppedDays = $maintenanceStartedAt
                            ->copy()
                            ->startOfDay()
                            ->diffInDays(now()->startOfDay());
        
                        $stoppedTimeLabel = match (true) {
                            $stoppedDays === 0 => 'Iniciada hoje',
                            $stoppedDays === 1 => '1 dia parado',
                            default => $stoppedDays . ' dias parado',
                        };
        
                        $serviceStatusLabel = match ($maintenance->service_status) {
                            'technical_analysis' => 'Análise técnica',
                            'waiting_parts'      => 'Aguardando peças',
                            'in_progress'        => 'Em execução',
                            'paused'             => 'Pausada',
                            'finished'           => 'Finalizada',
                            'cancelled'          => 'Cancelada',
                            default              => 'Em manutenção',
                        };
        
                        $maintenanceTypeLabel = match ($maintenance->maintenance_type) {
                            'preventive' => 'Preventiva',
                            'corrective' => 'Corretiva',
                            'internal'   => 'Interna',
                            'external'   => 'Externa',
                            default      => 'Manutenção',
                        };
                        
                        $maintenanceDescription =
                            $maintenance->procedure_name
                            ?? $maintenanceTypeLabel;
                    @endphp
        
                    <a
                        href="{{ route(
                            'vehicle.maintenance.index',
                            $maintenance->vehicle_id
                        ) }}"
                        class="workshop-maintenance-card"
                    >
        
                        <div class="workshop-maintenance-card-header">
        
                            <div class="workshop-maintenance-vehicle">
        
                                <div class="workshop-vehicle-avatar">
        
                                    <i class="bi bi-truck"></i>
        
                                </div>
        
                                <div>
        
                                    <strong>
                                        {{ $maintenance->vehicle_plate ?? 'Sem placa' }}
                                    </strong>
        
                                    <span>
                                        {{ $maintenance->vehicle_name ?? 'Veículo sem nome' }}
                                    </span>
        
                                </div>
        
                            </div>
        
                            <div class="workshop-maintenance-status">
        
                                {{ $serviceStatusLabel }}
        
                            </div>
        
                        </div>
        
                        <div class="workshop-maintenance-description">
        
                            <span>{{ $maintenanceTypeLabel }}</span>
        
                            <strong>{{ $maintenanceDescription }}</strong>
        
                        </div>
        
                        <div class="workshop-maintenance-metrics">
        
                            <div class="workshop-maintenance-metric">
        
                                <span>Custo atual</span>
        
                                <strong>
                                    R$ {{ number_format(
                                        $maintenance->total_cost ?? 0,
                                        2,
                                        ',',
                                        '.'
                                    ) }}
                                </strong>
        
                            </div>
        
                            <div class="workshop-maintenance-metric">
        
                                <span>Tempo parado</span>
        
                                <strong>{{ $stoppedTimeLabel }}</strong>
        
                            </div>
        
                            <div class="workshop-maintenance-metric">
        
                                <span>Início</span>
        
                                <strong>
                                    {{ $maintenanceStartedAt->format('d/m/Y H:i') }}
                                </strong>
        
                            </div>
        
                            <div class="workshop-maintenance-metric">
        
                                <span>Execução</span>
        
                                <strong>
                                    {{ $maintenance->provider_name
                                        ?: (
                                            $maintenance->maintenance_type === 'external'
                                                ? 'Prestador não informado'
                                                : 'Equipe interna'
                                        )
                                    }}
                                </strong>
        
                            </div>
        
                        </div>
        
                        <div class="workshop-maintenance-card-footer">
        
                            <span>
                                Ordem #{{ $maintenance->maintenance_id }}
                            </span>
        
                            <strong>
                                Ver manutenção
                                <i class="bi bi-arrow-right"></i>
                            </strong>
        
                        </div>
        
                    </a>
        
                @endforeach
        
            </div>
        
        @else
        
            <div class="workshop-empty-state">
        
                <div>
        
                    <i class="bi bi-check-circle"></i>
        
                </div>
        
                <strong>Nenhum veículo em manutenção agora</strong>
        
                <p>
                    Quando uma manutenção for aberta, ela aparecerá aqui.
                </p>
        
            </div>
        
        @endif



        </section>



    </div>



    @include('workshop.partials.financial')



    <div class="workshop-preview-grid">



        <section class="workshop-panel">



            <div class="workshop-panel-header">

                <div>

                    <span>Estoque</span>

                    <h2>Itens em atenção</h2>

                </div>



                <a href="{{ route('stock.index') }}">

                    Abrir

                    <i class="bi bi-arrow-right"></i>

                </a>

            </div>



            @if($lowStockItems->count())

                <div class="workshop-mini-list">

                    @foreach($lowStockItems as $item)

                        <div class="workshop-mini-row">

                            <div>

                                <strong>{{ $item->name }}</strong>

                                <span>

                                    Atual: {{ number_format($item->quantity ?? 0, 2, ',', '.') }}

                                    {{ $item->unit ?? '' }}

                                    · mínimo:

                                    {{ number_format($item->minimum_quantity ?? 0, 2, ',', '.') }}

                                    {{ $item->unit ?? '' }}

                                </span>

                            </div>

                    

                            <small>baixo</small>

                        </div>

                    @endforeach

                </div>

            @else

                <div class="workshop-mini-empty">

                    Nenhum item abaixo do mínimo.

                </div>

            @endif



        </section>



        <section class="workshop-panel">



            <div class="workshop-panel-header">

                <div>

                    <span>Pneus</span>

                    <h2>Alertas recentes</h2>

                </div>



                <a href="{{ route('workshop.tires.index') }}">

                    Abrir

                    <i class="bi bi-arrow-right"></i>

                </a>

            </div>



            @if($tiresAttention->count())

                <div class="workshop-mini-list">

                    @foreach($tiresAttention as $tire)

                        <div class="workshop-mini-row">

                            <div>

                                <strong>{{ $tire->code ?? 'Pneu' }}</strong>

                                <span>

                                    Sulco atual:

                                    {{ $tire->minimum_tread ?? $tire->initial_tread_depth ?? '-' }} mm

                                </span>

                            </div>



                            <small>alerta</small>

                        </div>

                    @endforeach

                </div>

            @else

                <div class="workshop-mini-empty">

                    Nenhum pneu em alerta.

                </div>

            @endif



        </section>



        <section class="workshop-panel">



            <div class="workshop-panel-header">

                <div>

                    <span>Procedimentos</span>

                    <h2>Últimas regras</h2>

                </div>



                <a href="{{ route('procedures.index') }}">

                    Abrir

                    <i class="bi bi-arrow-right"></i>

                </a>

            </div>



            @if($proceduresPreview->count())

                <div class="workshop-mini-list">

                    @foreach($proceduresPreview as $procedure)

                        <div class="workshop-mini-row">

                            <div>

                                <strong>{{ $procedure->name ?? $procedure->title ?? 'Procedimento' }}</strong>

                                <span>

                                    Regra operacional cadastrada

                                </span>

                            </div>



                            <small>ativo</small>

                        </div>

                    @endforeach

                </div>

            @else

                <div class="workshop-mini-empty">

                    Nenhum procedimento cadastrado.

                </div>

            @endif



        </section>



    </div>



</div>



<div id="workshopMaintenanceDashboard" class="workshop-dashboard-modal" hidden aria-labelledby="workshopDashboardTitle" role="dialog" aria-modal="true">
    <div class="workshop-dashboard-card">
        <div class="workshop-dashboard-heading">
            <div>
                <span>Painel operacional</span>
                <h2 id="workshopDashboardTitle">Painel de manutenção</h2>
                <p id="workshopDashboardSubtitle">Indicadores consolidados — Últimos 30 dias</p>
            </div>
            <div class="workshop-dashboard-controls">
                <label>Período<select id="workshopDashboardPeriod"><option value="last_30_days">Últimos 30 dias</option><option value="current_month">Mês atual</option><option value="last_90_days">Últimos 90 dias</option><option value="current_year">Ano atual</option></select></label>
                <button type="button" class="workshop-dashboard-close" onclick="closeWorkshopMaintenanceDashboard()" aria-label="Fechar painel">×</button>
            </div>
        </div>
        <div id="workshopDashboardContent" class="workshop-dashboard-loading">Carregando indicadores…</div>
    </div>
</div>

@endsection

@push('scripts')
<script>
(() => {
    const workshopMaintenanceDashboardUrl = @json(route('workshop.maintenance-dashboard'));
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
    const number = value => Number(value || 0);
    const money = value => 'R$ ' + number(value).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const categoryLabel = value => ({other:'Outros',external:'Terceirizada',hydraulic:'Hidráulica',internal:'Interna',air_conditioning:'Ar-condicionado',brakes:'Freios',welding_boilermaking_implement:'Solda / Implemento',electrical:'Elétrica',engine:'Motor',suspension:'Suspensão',transmission:'Transmissão',preventive:'Preventiva',corrective:'Corretiva'})[value] || String(value || 'Outros').replace(/[_-]+/g, ' ').replace(/\b\w/g, char => char.toUpperCase());
    const empty = title => `<section class="workshop-dashboard-section"><h3>${title}</h3><p class="workshop-dashboard-empty">Sem dados no período.</p></section>`;
    const bars = (title, rows, cost = false) => {
        if (!rows?.length) return empty(title);
        const max = Math.max(...rows.map(row => number(cost ? row.total_cost : row.count)), 1);
        return `<section class="workshop-dashboard-section"><h3>${title}</h3><div class="workshop-dashboard-bars">${rows.map((row, index) => { const label = row.name ? `${row.name} · ${row.plate || 'sem placa'}` : (row.label || categoryLabel(row.type)); const value = cost ? row.total_cost : row.count; return `<div class="workshop-dashboard-bar"><span>${index + 1}. ${escapeHtml(label)}</span><i><b style="width:${Math.max(4, number(value) / max * 100)}%"></b></i><strong>${cost ? money(value) : number(value)}</strong></div>`; }).join('')}</div></section>`;
    };
    const trendChart = rows => {
        if (!rows?.length) return empty('Evolução das manutenções');
        const width = Math.max(620, rows.length * 46), height = 220, pad = {left:42,right:18,top:18,bottom:42}, maximum = Math.max(...rows.map(row => number(row.value)), 1), x = index => pad.left + index * (width-pad.left-pad.right) / Math.max(rows.length-1, 1), y = value => height-pad.bottom-number(value)/maximum*(height-pad.top-pad.bottom), points = rows.map((row,index) => `${x(index)},${y(row.value)}`).join(' '), step = Math.max(1, Math.ceil(rows.length/8));
        return `<section class="workshop-dashboard-section workshop-dashboard-trend"><h3>Evolução das manutenções</h3><p class="workshop-dashboard-caption">Ordens abertas ao longo do período selecionado.</p><div class="workshop-dashboard-chart-scroll"><svg viewBox="0 0 ${width} ${height}" role="img" aria-label="Evolução das manutenções">${[.25,.5,.75,1].map(level => `<line class="workshop-trend-grid" x1="${pad.left}" x2="${width-pad.right}" y1="${height-pad.bottom-(height-pad.top-pad.bottom)*level}" y2="${height-pad.bottom-(height-pad.top-pad.bottom)*level}"/>`).join('')}<polyline class="workshop-trend-line" points="${points}"/>${rows.map((row,index) => `<g><title>${escapeHtml(row.label)}: ${number(row.value)} ordem(ns)</title><circle class="workshop-trend-point" cx="${x(index)}" cy="${y(row.value)}" r="3.5"/>${index % step === 0 || index === rows.length-1 ? `<text class="workshop-trend-label" x="${x(index)}" y="${height-14}">${escapeHtml(row.label)}</text>` : ''}</g>`).join('')}</svg></div></section>`;
    };

    window.openWorkshopMaintenanceDashboard = async function () {
        const modal = document.getElementById('workshopMaintenanceDashboard');
        const content = document.getElementById('workshopDashboardContent');
        const period = document.getElementById('workshopDashboardPeriod').value;
        modal.hidden = false; content.className = 'workshop-dashboard-loading'; content.textContent = 'Carregando indicadores…';
        try {
            const response = await fetch(workshopMaintenanceDashboardUrl + '?period=' + encodeURIComponent(period), {headers: {Accept: 'application/json'}});
            if (!response.ok) throw new Error('dashboard_request_failed');
            const data = await response.json(), summary = data.summary;
            document.getElementById('workshopDashboardSubtitle').textContent = 'Indicadores consolidados — ' + data.period_label;
            const kpi = (label, value, note) => `<article class="workshop-dashboard-kpi"><span>${label}</span><strong>${value}</strong><small>${note}</small></article>`;
            content.className = '';
            const financial = summary.financial;
            const financialKpis = data.permissions.view_costs && financial ? [
                kpi('Custo das manutenções', money(financial.maintenance_total), 'Materiais, serviços e custos avulsos'),
                kpi('Materiais', money(financial.materials), 'Consumo no período'),
                kpi('Serviços / outros custos', money(financial.services_other), 'Serviços executados'),
                kpi('Despesas da oficina', money(financial.workshop_expenses), 'Despesas no período'),
                kpi('Custo total da oficina', money(financial.workshop_total), 'Manutenções + despesas'),
                kpi('Custo médio / OM', money(financial.average_per_maintenance), financial.maintenance_ids?.length ? `Média entre OMs` : 'Nenhuma OM participante'),
            ].join('') : '';
            content.innerHTML = `<div class="workshop-dashboard-kpis">${kpi('OMs abertas', number(summary.open_orders), 'Em andamento agora')}${kpi('Veículos em manutenção', number(summary.vehicles_in_maintenance), 'Com ordem aberta')}${kpi('OMs concluídas', number(summary.completed_orders), 'Encerradas no período')}${kpi('Tempo médio parado', summary.average_downtime_days === null ? 'N/D' : String(summary.average_downtime_days).replace('.', ',') + ' dias', 'Início até encerramento')}${financialKpis}</div><div class="workshop-dashboard-grid">${bars('Status das ordens', data.status)}${bars('Manutenções por categoria', data.types)}${bars('Veículos com mais ordens', data.top_vehicles)}${bars('Procedimentos recorrentes', data.procedures)}${bars('Ordens abertas há mais tempo', data.old_open_orders)}</div>${trendChart(data.trend)}`;
        } catch (error) { content.className = 'workshop-dashboard-loading'; content.textContent = 'Não foi possível carregar o painel de manutenção.'; }
    };
    window.closeWorkshopMaintenanceDashboard = function () { document.getElementById('workshopMaintenanceDashboard').hidden = true; };
    document.getElementById('workshopDashboardPeriod').addEventListener('change', window.openWorkshopMaintenanceDashboard);
    document.addEventListener('keydown', event => { if (event.key === 'Escape') window.closeWorkshopMaintenanceDashboard(); });
    document.getElementById('workshopMaintenanceDashboard').addEventListener('click', event => { if (event.target === event.currentTarget) window.closeWorkshopMaintenanceDashboard(); });
})();
</script>
@endpush
