@extends('layouts.app')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/pages/fuel.css') }}?v=3">
@endpush
@section('content')
<main class="fuel-page fuel-history-page" x-data="{ cancelId: null }">
@php($hasFilters = request()->filled(['start_date', 'end_date', 'vehicle_id', 'fuel_product_id', 'fuel_tank_id', 'source', 'status']))
<header class="fuel-header"><div><span class="fuel-kicker">Abastecimentos</span><h1>Histórico completo de saídas</h1><p>Consulte lançamentos e cancelamentos da unidade ativa.</p></div><a href="{{ route('fuel.tanks.index') }}" class="fuel-secondary-action">Voltar</a></header>
<form class="fuel-history-filters" method="GET">
<input type="date" name="start_date" value="{{ request('start_date') }}"><input type="date" name="end_date" value="{{ request('end_date') }}">
<select name="vehicle_id"><option value="">Todos os veículos</option>@foreach ($vehicles as $vehicle)<option value="{{ $vehicle->id }}" @selected(request('vehicle_id') == $vehicle->id)>{{ $vehicle->name }} · {{ $vehicle->plate }}</option>@endforeach</select>
<select name="fuel_product_id"><option value="">Todos os produtos</option>@foreach ($products as $product)<option value="{{ $product->id }}" @selected(request('fuel_product_id') == $product->id)>{{ $product->name }}</option>@endforeach</select>
<select name="fuel_tank_id"><option value="">Todos os tanques</option>@foreach ($tanks as $tank)<option value="{{ $tank->id }}" @selected(request('fuel_tank_id') == $tank->id)>{{ $tank->name }}</option>@endforeach</select>
<select name="source"><option value="">Todas as origens</option><option value="internal_tank" @selected(request('source') === 'internal_tank')>Tanque da unidade</option><option value="external_station" @selected(request('source') === 'external_station')>Posto externo</option></select><select name="status"><option value="">Todos os status</option><option value="active" @selected(request('status') === 'active')>Realizado</option><option value="cancelled" @selected(request('status') === 'cancelled')>Cancelado</option></select><button class="fuel-primary-action">Filtrar</button>@if ($hasFilters)<a href="{{ route('fuel.fillings.history') }}" class="fuel-secondary-action">Limpar filtros</a>@endif
</form>
<section class="fuel-panel"><div class="fuel-table-wrap"><table class="fuel-table"><thead><tr><th>Data / veículo</th><th>Origem / produto</th><th>Quantidade</th><th>KM / condutor</th><th>Responsável</th><th>Status</th><th></th></tr></thead><tbody>
@forelse ($fillings as $filling)
<tr class="{{ $filling->cancelled_at ? 'is-cancelled' : '' }}"><td>{{ $filling->filled_at?->format('d/m/Y H:i') }}<br>{{ $filling->vehicle?->name }} · {{ $filling->vehicle?->plate }}</td><td>{{ $filling->source_label }}<br><small>{{ $filling->location_label }} · {{ $filling->product?->name }}</small></td><td>{{ number_format((float) $filling->quantity_liters, 3, ',', '.') }} L @if ($fuelPermissions['view_costs'])<small>R$ {{ number_format((float) $filling->total_cost, 2, ',', '.') }}</small>@endif</td><td class="fuel-history-reading"><strong>{{ $filling->vehicle_km !== null ? rtrim(rtrim(number_format((float) $filling->vehicle_km, 2, ',', '.'), '0'), ',') : '—' }}@if ($filling->vehicle_km !== null) km @endif</strong><small>{{ $filling->driver?->name ?: 'Sem condutor' }}</small></td><td>{{ $filling->responsible?->name ?: '—' }}</td><td><span class="fuel-history-status {{ $filling->cancelled_at ? 'is-cancelled' : 'is-complete' }}">{{ $filling->cancelled_at ? 'Cancelado' : 'Realizado' }}</span>@if ($filling->cancelled_at)<small>{{ $filling->cancel_reason }} · {{ $filling->canceller?->name }}</small>@endif</td><td>@if (! $filling->cancelled_at && $fuelPermissions['cancel'])<button type="button" class="fuel-secondary-action fuel-cancel-action" x-on:click="cancelId = {{ $filling->id }}">Cancelar</button>@endif</td></tr>
@empty
<tr><td colspan="7" class="fuel-table-empty">Nenhum abastecimento encontrado.</td></tr>
@endforelse
</tbody></table></div>{{ $fillings->links() }}</section>
@foreach ($fillings as $filling)
@if (! $filling->cancelled_at && $fuelPermissions['cancel'])
<div class="fuel-modal-overlay" :class="{ 'is-open': cancelId === {{ $filling->id }} }"><div class="fuel-modal-card"><h2>Cancelar abastecimento #{{ $filling->id }}</h2><p>Informe o motivo. O lançamento será mantido no histórico para auditoria.</p><form method="POST" action="{{ route('fuel.fillings.cancel', $filling) }}" class="fuel-form">@csrf<textarea name="reason" required minlength="5" placeholder="Motivo do cancelamento">{{ old('reason') }}</textarea><div class="fuel-form-actions"><button type="button" class="fuel-secondary-action" x-on:click="cancelId = null">Voltar</button><button class="fuel-primary-action">Cancelar lançamento</button></div></form></div></div>
@endif
@endforeach
</main>
@endsection
