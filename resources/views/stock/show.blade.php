@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/pages/stock.css') }}?v=4">
@endpush

@section('content')
@php
    $canCreateEntry = (bool) ($stockPermissions['create_entry'] ?? false);
    $statusLabels = ['danger' => 'Crítico', 'warning' => 'Atenção', 'ok' => 'Adequado'];
    $movementLabel = function ($movement) {
        if ($movement->reversed_from_movement_id) return 'Reversão';
        if ($movement->cancelled_at) return 'Cancelado';
        return $movement->movement_type === 'in' ? 'Entrada' : 'Saída';
    };
    $movementOrigin = function ($movement) {
        if ($movement->reversed_from_movement_id) return 'Reversão';
        if ($movement->maintenance_record_item_id) return 'Procedimento';
        if ($movement->maintenance_record_id) return 'Material utilizado';
        return $movement->movement_type === 'in' ? 'Compra / entrada manual' : 'Ajuste manual';
    };
@endphp

<div class="stock-page stock-detail-page">
    <header class="stock-detail-hero">
        <div>
            <div class="stock-detail-navigation">
                <a class="stock-detail-back" href="{{ route('stock.index') }}"><i class="bi bi-arrow-left"></i> Voltar para estoque</a>
                <span class="stock-detail-category">Categoria: {{ $item->category?->name ?? 'Sem categoria' }}</span>
            </div>
            <h1>{{ $item->name }}</h1>
            <p>{{ $item->brand ?: 'Sem marca' }} · Unidade: {{ $item->unit }}</p>
        </div>
        <div class="stock-detail-hero-actions">
            <span class="stock-status-badge {{ $item->stock_status }}">{{ $statusLabels[$item->stock_status] ?? 'Adequado' }}</span>
            <button type="button" class="stock-detail-report" onclick="openStockReportModal()"><i class="bi bi-file-earmark-text"></i> Gerar relatório</button>
            @if($canCreateEntry)
                <a class="stock-detail-entry" href="{{ route('stock.index', ['entry' => $item->id]) }}"><i class="bi bi-plus-lg"></i> Nova entrada</a>
            @endif
        </div>
    </header>

    <section class="stock-detail-summary" aria-label="Resumo do item">
        <article><span>Estoque atual</span><strong>{{ number_format($item->quantity, 2, ',', '.') }} {{ $item->unit }}</strong></article>
        <article><span>Estoque mínimo</span><strong>{{ number_format($item->minimum_quantity, 2, ',', '.') }} {{ $item->unit }}</strong></article>
        @if($canViewCosts)
            <article><span>Custo médio atual</span><strong>R$ {{ number_format($item->unit_cost, 2, ',', '.') }}</strong></article>
            <article><span>Valor estimado</span><strong>R$ {{ number_format($item->quantity * $item->unit_cost, 2, ',', '.') }}</strong></article>
        @endif
        <article><span>Total de entradas</span><strong>{{ number_format($summary['entries'], 2, ',', '.') }} {{ $item->unit }}</strong></article>
        <article><span>Total de saídas</span><strong>{{ number_format($summary['outputs'], 2, ',', '.') }} {{ $item->unit }}</strong></article>
        <article><span>Última movimentação</span><strong>{{ $summary['last_movement_at'] ? \Illuminate\Support\Carbon::parse($summary['last_movement_at'])->format('d/m/Y H:i') : 'Sem movimentações' }}</strong></article>
    </section>

    @if($canViewCosts)
        <section class="stock-detail-panel">
            <div class="stock-detail-section-heading"><div><span>Custos</span><h2>Evolução do custo unitário</h2></div><small>{{ $priceHistory->count() }} entrada(s)</small></div>
            @if($priceHistory->isEmpty())
                <p class="stock-detail-empty">Ainda não há entradas com custo registrado.</p>
            @else
                @php $maxCost = max(0.01, (float) $priceHistory->max('unit_cost')); @endphp
                <div class="stock-price-timeline">
                    @foreach($priceHistory as $price)
                        <article>
                            <time>{{ ($price->moved_at ?? $price->created_at)?->format('d/m/Y') }}</time>
                            <div class="stock-price-bar"><span style="width: {{ max(3, ((float) $price->unit_cost / $maxCost) * 100) }}%"></span></div>
                            <strong>R$ {{ number_format($price->unit_cost, 2, ',', '.') }}</strong>
                            <small>{{ $price->supplier_name ?: 'Fornecedor não informado' }}{{ $price->invoice_number ? ' · NF '.$price->invoice_number : '' }}</small>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    <section class="stock-detail-panel">
        <div class="stock-detail-section-heading"><div><span>Auditoria operacional</span><h2>Histórico de movimentações</h2></div><small>Até 50 por página</small></div>
        <div class="stock-detail-table-wrap">
            <table class="stock-detail-table">
                <thead><tr><th>Data</th><th>Tipo</th><th>Quantidade</th>@if($canViewCosts)<th>Custo unit.</th><th>Custo total</th>@endif<th>Origem</th><th>Documento / fornecedor</th><th>Usuário</th><th>Observação</th></tr></thead>
                <tbody>
                @forelse($movements as $movement)
                    <tr class="{{ $movement->cancelled_at ? 'is-cancelled' : '' }}">
                        <td>{{ ($movement->moved_at ?? $movement->created_at)?->format('d/m/Y H:i') }}</td>
                        <td><span class="stock-movement-kind {{ $movement->movement_type }}">{{ $movementLabel($movement) }}</span></td>
                        <td>{{ number_format($movement->quantity, 2, ',', '.') }} {{ $item->unit }}</td>
                        @if($canViewCosts)<td>R$ {{ number_format($movement->unit_cost, 2, ',', '.') }}</td><td>R$ {{ number_format($movement->total_cost ?? ($movement->quantity * $movement->unit_cost), 2, ',', '.') }}</td>@endif
                        <td>{{ $movementOrigin($movement) }}</td>
                        <td>{{ $movement->invoice_number ?: '—' }}<small>{{ $movement->supplier_name ?: '—' }}</small></td>
                        <td>—</td>
                        <td>{{ $movement->description ?: '—' }}@if($canViewAudit && $movement->cancel_reason)<small>Cancelamento: {{ $movement->cancel_reason }}</small>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $canViewCosts ? 9 : 7 }}" class="stock-detail-empty">Nenhuma movimentação registrada.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($movements->hasPages())<div class="stock-detail-pagination">{{ $movements->links() }}</div>@endif
    </section>

    <section class="stock-detail-panel">
        <div class="stock-detail-section-heading"><div><span>Oficina</span><h2>Consumo em manutenções</h2></div><small>Últimas 50 saídas</small></div>
        <div class="stock-maintenance-list">
            @forelse($maintenanceOutputs as $movement)
                @php $maintenance = $movement->maintenanceRecord; $vehicle = $maintenance?->vehicle; @endphp
                <article>
                    <div><span>OM #{{ $maintenance?->id }}</span><strong>{{ $vehicle?->plate ?: ($vehicle?->name ?: 'Veículo não informado') }}</strong></div>
                    <div><span>Data</span><strong>{{ ($movement->moved_at ?? $movement->created_at)?->format('d/m/Y') }}</strong></div>
                    <div><span>Quantidade</span><strong>{{ number_format($movement->quantity, 2, ',', '.') }} {{ $item->unit }}</strong></div>
                    <div><span>Origem</span><strong>{{ $movement->maintenance_record_item_id ? 'Procedimento'.($movement->maintenanceRecordItem?->procedure?->name ? ': '.$movement->maintenanceRecordItem->procedure->name : '') : 'Material direto' }}</strong></div>
                    @if($maintenance && $vehicle)<a href="{{ route('vehicles.maintenance.show', [$vehicle, $maintenance]) }}">Abrir OM <i class="bi bi-arrow-up-right"></i></a>@endif
                </article>
            @empty
                <p class="stock-detail-empty">Este item ainda não possui consumo vinculado a manutenções.</p>
            @endforelse
        </div>
    </section>
</div>

<div class="stock-modal-overlay stock-report-overlay" id="stockReportModal" style="display:none;" onclick="if(event.target === this) closeStockReportModal()">
    <div class="stock-report-modal" role="dialog" aria-modal="true" aria-labelledby="stockReportTitle">
        <div class="stock-report-modal-header">
            <div><span>Relatório PDF</span><h2 id="stockReportTitle">Período do relatório</h2></div>
            <button type="button" onclick="closeStockReportModal()" aria-label="Fechar"><i class="bi bi-x-lg"></i></button>
        </div>
        <form action="{{ route('stock.items.report.pdf', $item) }}" method="GET" target="_blank" onsubmit="closeStockReportModal()">
            <div class="stock-report-fields">
                <label>Data inicial<input type="date" name="start_date" value="{{ now()->startOfMonth()->format('Y-m-d') }}" required></label>
                <label>Data final<input type="date" name="end_date" value="{{ now()->format('Y-m-d') }}" required></label>
            </div>
            <div class="stock-report-actions">
                <button type="button" class="stock-report-cancel" onclick="closeStockReportModal()">Cancelar</button>
                <button type="submit" class="stock-detail-entry"><i class="bi bi-file-earmark-arrow-down"></i> Gerar PDF</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function openStockReportModal() {
    document.getElementById('stockReportModal').style.display = 'flex';
}
function closeStockReportModal() {
    document.getElementById('stockReportModal').style.display = 'none';
}
</script>
@endpush
