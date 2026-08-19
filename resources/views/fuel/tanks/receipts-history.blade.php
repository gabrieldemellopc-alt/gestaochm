@extends('layouts.app')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/pages/fuel.css') }}?v=3">
@endpush
@section('content')
<main class="fuel-page fuel-history-page" x-data="{ cancelId: null }">
@php($hasFilters = request()->filled(['start_date', 'end_date', 'fuel_product_id', 'fuel_tank_id', 'supplier_name', 'status']))
<header class="fuel-header"><div><span class="fuel-kicker">Recebimentos</span><h1>Histórico completo de entradas</h1></div><a href="{{ route('fuel.tanks.index') }}" class="fuel-secondary-action">Voltar</a></header>
<form class="fuel-history-filters" method="GET"><input type="date" name="start_date" value="{{ request('start_date') }}"><input type="date" name="end_date" value="{{ request('end_date') }}"><select name="fuel_product_id"><option value="">Todos os produtos</option>@foreach ($products as $product)<option value="{{ $product->id }}" @selected(request('fuel_product_id') == $product->id)>{{ $product->name }}</option>@endforeach</select><select name="fuel_tank_id"><option value="">Todos os tanques</option>@foreach ($tanks as $tank)<option value="{{ $tank->id }}" @selected(request('fuel_tank_id') == $tank->id)>{{ $tank->name }}</option>@endforeach</select><input name="supplier_name" value="{{ request('supplier_name') }}" placeholder="Fornecedor"><select name="status"><option value="">Todos os status</option><option value="active" @selected(request('status') === 'active')>Realizado</option><option value="cancelled" @selected(request('status') === 'cancelled')>Cancelado</option></select><button class="fuel-primary-action">Filtrar</button>@if ($hasFilters)<a href="{{ route('fuel.receipts.history') }}" class="fuel-secondary-action">Limpar filtros</a>@endif</form>
<section class="fuel-panel"><div class="fuel-table-wrap"><table class="fuel-table"><thead><tr><th>Data / tanque</th><th>Produto</th><th>Quantidade / valor</th><th>Fornecedor / documento</th><th>Responsável</th><th>Status</th><th></th></tr></thead><tbody>
@forelse ($receipts as $receipt)
<tr class="{{ $receipt->cancelled_at ? 'is-cancelled' : '' }}"><td>{{ $receipt->received_at?->format('d/m/Y H:i') }}<br>{{ $receipt->tank?->name }}</td><td>{{ $receipt->product?->name }}</td><td>{{ number_format((float) $receipt->quantity_liters, 3, ',', '.') }} L @if ($fuelPermissions['view_costs'])<small>R$ {{ number_format((float) $receipt->total_cost, 2, ',', '.') }}</small>@endif</td><td>{{ $receipt->supplier_name ?: '—' }}<br>{{ $receipt->invoice_number ?: 'Sem documento' }}</td><td>{{ $receipt->responsible?->name ?: '—' }}</td><td><span class="fuel-history-status {{ $receipt->cancelled_at ? 'is-cancelled' : 'is-complete' }}">{{ $receipt->cancelled_at ? 'Cancelado' : 'Realizado' }}</span>@if ($receipt->cancelled_at)<small>{{ $receipt->cancel_reason }} · {{ $receipt->canceller?->name }}</small>@endif</td><td>@if (! $receipt->cancelled_at && $fuelPermissions['cancel'])<button type="button" class="fuel-secondary-action fuel-cancel-action" x-on:click="cancelId = {{ $receipt->id }}">Cancelar</button>@endif</td></tr>
@empty
<tr><td colspan="7" class="fuel-table-empty">Nenhum recebimento encontrado.</td></tr>
@endforelse
</tbody></table></div>{{ $receipts->links() }}</section>
@foreach ($receipts as $receipt)
@if (! $receipt->cancelled_at && $fuelPermissions['cancel'])
<div class="fuel-modal-overlay" :class="{ 'is-open': cancelId === {{ $receipt->id }} }"><div class="fuel-modal-card"><h2>Cancelar recebimento #{{ $receipt->id }}</h2><p>Informe o motivo. O lançamento será mantido no histórico para auditoria.</p><form method="POST" action="{{ route('fuel.receipts.cancel', $receipt) }}" class="fuel-form">@csrf<textarea name="reason" required minlength="5" placeholder="Motivo do cancelamento">{{ old('reason') }}</textarea><div class="fuel-form-actions"><button type="button" class="fuel-secondary-action" x-on:click="cancelId = null">Voltar</button><button class="fuel-primary-action">Cancelar lançamento</button></div></form></div></div>
@endif
@endforeach
</main>
@endsection
