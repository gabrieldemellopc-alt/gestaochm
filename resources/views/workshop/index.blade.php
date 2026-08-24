@extends('layouts.app')



@push('styles')

<link rel="stylesheet" href="{{ asset('css/pages/workshop.css') }}?v=5">
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
            
            <button type="button" class="workshop-maintenance-dashboard-button" onclick="openWorkshopMaintenanceDashboard()"><i data-lucide="chart-column"></i><span>Painel de manutenção</span></button>
            <div class="workshop-shortcut-list workshop-shortcut-list-horizontal">
            
                <a href="{{ route('workshop.tires.index') }}" class="workshop-shortcut-card">
            
                    <div>
                        <i data-lucide="circle-dot"></i>
                    </div>
            
                    <section>
                        <strong>Pneus</strong>
                        <span>Estoque, instalações, sulco e alertas.</span>
                    </section>
            
                    <i data-lucide="arrow-up-right"></i>
            
                </a>
            
                <a href="{{ route('stock.index') }}" class="workshop-shortcut-card">
            
                    <div>
                        <i data-lucide="boxes"></i>
                    </div>
            
                    <section>
                        <strong>Estoque</strong>
                        <span>Itens, categorias e movimentações.</span>
                    </section>
            
                    <i data-lucide="arrow-up-right"></i>
            
                </a>
            
                <a href="{{ route('procedures.index') }}" class="workshop-shortcut-card">
            
                    <div>
                        <i data-lucide="clipboard-list"></i>
                    </div>
            
                    <section>
                        <strong>Procedimentos</strong>
                        <span>Regras de manutenção e execução.</span>
                    </section>
            
                    <i data-lucide="arrow-up-right"></i>
            
                </a>
            
            </div>

        </div>



        <div class="workshop-hero-panel">

            <div class="workshop-hero-panel-icon">

                <i data-lucide="wrench"></i>

            </div>



            <div>

                <span>Status operacional</span>

                <strong>

                    {{ $maintenanceVehiclesCount > 0 ? $maintenanceVehiclesCount . ' veículo(s) em atenção' : 'Operação estável' }}

                </strong>

                <p>

                    Dados consolidados da oficina, estoque, pneus e procedimentos.

                </p>

            </div>

        </div>



    </div>



    <div class="workshop-summary-grid">



        <div class="workshop-summary-card">
            <div class="workshop-summary-icon">

                <i data-lucide="truck"></i>

            </div>



            <div>

                <span>Veículos</span>

                <strong>{{ $maintenanceVehiclesCount }}</strong>

                <p>Em manutenção ou indisponíveis</p>

            </div>

        </div>



        <div class="workshop-summary-card">
            <div class="workshop-summary-icon danger">

                <i data-lucide="triangle-alert"></i>

            </div>



            <div>

                <span>Estoque</span>

                <strong>{{ $lowStockCount }}</strong>

                <p>Itens abaixo do mínimo</p>

            </div>

        </div>



        <div class="workshop-summary-card">
            <div class="workshop-summary-icon warning">

                <i data-lucide="circle-dot"></i>

            </div>



            <div>

                <span>Pneus</span>

                <strong>{{ $tiresAttentionCount }}</strong>

                <p>Com alerta ou manutenção</p>

            </div>

        </div>



        <div class="workshop-summary-card">
            <div class="workshop-summary-icon">

                <i data-lucide="clipboard-list"></i>

            </div>



            <div>

                <span>Procedimentos</span>

                <strong>{{ $proceduresCount }}</strong>

                <p>Regras operacionais cadastradas</p>

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

                    <i data-lucide="arrow-right"></i>

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
        
                                    <i data-lucide="truck"></i>
        
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
                                <i data-lucide="arrow-right"></i>
                            </strong>
        
                        </div>
        
                    </a>
        
                @endforeach
        
            </div>
        
        @else
        
            <div class="workshop-empty-state">
        
                <div>
        
                    <i data-lucide="check-circle-2"></i>
        
                </div>
        
                <strong>Nenhum veículo em manutenção agora</strong>
        
                <p>
                    Quando uma manutenção for aberta, ela aparecerá aqui.
                </p>
        
            </div>
        
        @endif



        </section>



    </div>



    <div class="workshop-preview-grid">



        <section class="workshop-panel">



            <div class="workshop-panel-header">

                <div>

                    <span>Estoque</span>

                    <h2>Itens em atenção</h2>

                </div>



                <a href="{{ route('stock.index') }}">

                    Abrir

                    <i data-lucide="arrow-right"></i>

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

                    <i data-lucide="arrow-right"></i>

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

                    <i data-lucide="arrow-right"></i>

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



@include('workshop.partials.financial')

<div id="workshopMaintenanceDashboard" class="workshop-dashboard-modal" hidden aria-labelledby="workshopDashboardTitle" role="dialog" aria-modal="true">
    <div class="workshop-dashboard-card">
        <button type="button" class="workshop-dashboard-close" onclick="closeWorkshopMaintenanceDashboard()" aria-label="Fechar painel">×</button>
        <div class="workshop-dashboard-heading"><div><span>Painel operacional</span><h2 id="workshopDashboardTitle">Painel de manutenção</h2><p id="workshopDashboardSubtitle">Indicadores consolidados — Últimos 30 dias</p></div><label>Período<select id="workshopDashboardPeriod"><option value="last_30_days">Últimos 30 dias</option><option value="current_month">Mês atual</option><option value="last_90_days">Últimos 90 dias</option><option value="current_year">Ano atual</option></select></label></div>
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
            content.innerHTML = `<div class="workshop-dashboard-kpis">${kpi('OMs abertas', number(summary.open_orders), 'Em andamento agora')}${kpi('Veículos em manutenção', number(summary.vehicles_in_maintenance), 'Com ordem aberta')}${kpi('OMs concluídas', number(summary.completed_orders), 'No período')}${kpi('Tempo médio parado', summary.average_downtime_days === null ? 'N/D' : String(summary.average_downtime_days).replace('.', ',') + ' dias', 'Abertura até encerramento')}${data.permissions.view_costs ? kpi('Custo total', money(summary.total_cost), 'No período') : ''}</div><div class="workshop-dashboard-grid">${bars('Status das ordens', data.status)}${bars('Manutenções por categoria', data.types)}${bars('Veículos com mais ordens', data.top_vehicles)}${data.permissions.view_costs ? bars('Veículos com maior custo', data.top_vehicles_by_cost, true) : ''}${bars('Procedimentos recorrentes', data.procedures)}${bars('Ordens abertas há mais tempo', data.old_open_orders)}</div>${trendChart(data.trend)}`;
        } catch (error) { content.className = 'workshop-dashboard-loading'; content.textContent = 'Não foi possível carregar o painel de manutenção.'; }
    };
    window.closeWorkshopMaintenanceDashboard = function () { document.getElementById('workshopMaintenanceDashboard').hidden = true; };
    document.getElementById('workshopDashboardPeriod').addEventListener('change', window.openWorkshopMaintenanceDashboard);
    document.addEventListener('keydown', event => { if (event.key === 'Escape') window.closeWorkshopMaintenanceDashboard(); });
    document.getElementById('workshopMaintenanceDashboard').addEventListener('click', event => { if (event.target === event.currentTarget) window.closeWorkshopMaintenanceDashboard(); });
})();
</script>
@endpush
