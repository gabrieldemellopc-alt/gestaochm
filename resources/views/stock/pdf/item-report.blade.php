<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 24px 28px; }
        body { font-family: DejaVu Sans, sans-serif; color:#172033; font-size:8px; line-height:1.32; }
        h1,h2,p { margin:0; }
        .header { padding-bottom:9px; border-bottom:2px solid #315f98; }
        .eyebrow { color:#64748b; font-size:7px; font-weight:bold; text-transform:uppercase; letter-spacing:.5px; }
        h1 { margin-top:3px; font-size:20px; }
        .subtitle { margin-top:4px; color:#475569; }
        .meta { margin-top:7px; color:#475569; font-size:7.4px; }
        .section { margin-top:12px; }
        .section h2 { margin-bottom:5px; color:#0f172a; font-size:11px; }
        table { width:100%; border-collapse:collapse; }
        thead { display:table-header-group; }
        tr { page-break-inside:avoid; }
        th,td { padding:4px; border:1px solid #cbd5e1; text-align:left; vertical-align:top; }
        th { color:#0f172a; background:#e2e8f0; font-size:6.7px; text-transform:uppercase; }
        .kpis td { width:14.28%; padding:6px; background:#f1f5f9; }
        .label { display:block; color:#64748b; font-size:6.5px; text-transform:uppercase; }
        .value { display:block; margin-top:3px; color:#0f172a; font-size:9px; font-weight:bold; }
        .muted { color:#64748b; }
        .empty { padding:14px; color:#64748b; text-align:center; }
        .footer { margin-top:12px; padding-top:6px; border-top:1px solid #cbd5e1; color:#64748b; font-size:6.8px; }
    </style>
</head>
<body>
@php
    $qty = fn ($value) => number_format((float) $value, 2, ',', '.');
    $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
    $dateTime = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d/m/Y H:i') : '-';
    $origin = function ($movement) {
        if ($movement->reversed_from_movement_id) return 'Reversão';
        if ($movement->maintenance_record_item_id) return 'Procedimento';
        if ($movement->maintenance_record_id) return 'Material direto';
        return $movement->movement_type === 'in' ? 'Compra / entrada' : 'Saída manual';
    };
@endphp

<div class="header">
    <span class="eyebrow">CHM - Relatório do item de estoque</span>
    <h1>{{ $item->name }}</h1>
    <p class="subtitle">{{ $item->category?->name ?? 'Sem categoria' }} | {{ $item->brand ?: 'Sem marca' }} | Unidade: {{ $item->unit }}</p>
    <p class="meta">Período: {{ $start->format('d/m/Y') }} a {{ $end->format('d/m/Y') }} | Emitido em: {{ now()->format('d/m/Y H:i') }}</p>
</div>

<div class="section">
    <h2>Resumo</h2>
    <table class="kpis"><tr>
        <td><span class="label">Estoque atual</span><span class="value">{{ $qty($item->quantity) }} {{ $item->unit }}</span></td>
        <td><span class="label">Estoque mínimo</span><span class="value">{{ $qty($item->minimum_quantity) }} {{ $item->unit }}</span></td>
        @if($canViewCosts)<td><span class="label">Custo médio</span><span class="value">{{ $money($item->unit_cost) }}</span></td><td><span class="label">Valor estimado</span><span class="value">{{ $money($item->quantity * $item->unit_cost) }}</span></td>@endif
        <td><span class="label">Entradas no período</span><span class="value">{{ $qty($summary['entries']) }}</span></td>
        <td><span class="label">Saídas no período</span><span class="value">{{ $qty($summary['outputs']) }}</span></td>
        <td><span class="label">Saldo movimentado</span><span class="value">{{ $qty($summary['balance']) }}</span></td>
        <td><span class="label">Última movimentação</span><span class="value">{{ $dateTime($summary['last_movement_at']) }}</span></td>
    </tr></table>
</div>

<div class="section">
    <h2>Movimentações no período</h2>
    <table><thead><tr><th>Data</th><th>Tipo</th><th>Quantidade</th>@if($canViewCosts)<th>Custo unit.</th><th>Custo total</th>@endif<th>Origem</th><th>Fornecedor / NF</th><th>Veículo / OM</th><th>Observação</th></tr></thead><tbody>
    @forelse($movements as $movement)
        <tr><td>{{ $dateTime($movement->moved_at ?? $movement->created_at) }}</td><td>{{ $movement->reversed_from_movement_id ? 'Reversão' : ($movement->cancelled_at ? 'Cancelado' : ($movement->movement_type === 'in' ? 'Entrada' : 'Saída')) }}</td><td>{{ $qty($movement->quantity) }} {{ $item->unit }}</td>@if($canViewCosts)<td>{{ $money($movement->unit_cost) }}</td><td>{{ $money($movement->total_cost ?? ($movement->quantity * $movement->unit_cost)) }}</td>@endif<td>{{ $origin($movement) }}</td><td>{{ $movement->supplier_name ?: '-' }}{{ $movement->invoice_number ? ' | NF '.$movement->invoice_number : '' }}</td><td>@if($movement->maintenanceRecord){{ $movement->maintenanceRecord->vehicle?->plate ?: ($movement->maintenanceRecord->vehicle?->name ?: 'Veículo não informado') }} | OM #{{ $movement->maintenance_record_id }}@else - @endif</td><td>{{ $movement->description ?: '-' }}@if($canViewAudit && $movement->cancel_reason) | Cancelamento: {{ $movement->cancel_reason }}@endif</td></tr>
    @empty<tr><td colspan="{{ $canViewCosts ? 9 : 7 }}" class="empty">Nenhuma movimentação no período.</td></tr>@endforelse
    </tbody></table>
</div>

@if($canViewCosts)
<div class="section"><h2>Evolução do custo unitário</h2><table><thead><tr><th>Data</th><th>Custo unitário</th><th>Custo total</th><th>Fornecedor</th><th>Nota fiscal</th></tr></thead><tbody>
@forelse($priceHistory as $movement)<tr><td>{{ $dateTime($movement->moved_at) }}</td><td>{{ $money($movement->unit_cost) }}</td><td>{{ $money($movement->total_cost) }}</td><td>{{ $movement->supplier_name ?: '-' }}</td><td>{{ $movement->invoice_number ?: '-' }}</td></tr>@empty<tr><td colspan="5" class="empty">Sem entradas com custo no período.</td></tr>@endforelse
</tbody></table></div>
@endif

<div class="section"><h2>Consumo em manutenções</h2><table><thead><tr><th>OM</th><th>Veículo</th><th>Data</th><th>Quantidade</th><th>Origem</th></tr></thead><tbody>
@forelse($maintenanceOutputs as $movement)<tr><td>#{{ $movement->maintenance_record_id }}</td><td>{{ $movement->maintenanceRecord?->vehicle?->plate ?: ($movement->maintenanceRecord?->vehicle?->name ?: 'Não informado') }}</td><td>{{ $dateTime($movement->moved_at) }}</td><td>{{ $qty($movement->quantity) }} {{ $item->unit }}</td><td>{{ $movement->maintenance_record_item_id ? 'Procedimento'.($movement->maintenanceRecordItem?->procedure?->name ? ': '.$movement->maintenanceRecordItem->procedure->name : '') : 'Material direto' }}</td></tr>@empty<tr><td colspan="5" class="empty">Sem consumo em manutenção no período.</td></tr>@endforelse
</tbody></table></div>

<div class="footer">Relatório emitido pelo sistema CHM. Os valores financeiros são exibidos conforme as permissões do usuário emissor.</div>
</body>
</html>
