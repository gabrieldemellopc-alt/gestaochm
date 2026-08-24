@extends('layouts.app')

@section('content')
<div class="reports-page">
    <div class="reports-header"><div><span>RELATÓRIOS</span><h1>Financeiro</h1><p>Custos operacionais consolidados por unidade.</p></div></div>
    @if(($error ?? null))<div class="alert alert-warning">{{ $error }}</div>@else
    <form method="GET" class="report-filters"><label>Início<input type="date" name="start_date" value="{{ $filters['start_date']->format('Y-m-d') }}"></label><label>Fim<input type="date" name="end_date" value="{{ $filters['end_date']->format('Y-m-d') }}"></label><button type="submit">Aplicar filtros</button></form>
    @if(! $filters['period_is_valid'])<div class="alert alert-warning">A data inicial não pode ser maior que a data final.</div>@endif
    <div class="report-summary-grid"><article><span>Manutenções</span><strong>R$ {{ number_format($maintenance_total,2,',','.') }}</strong></article><article><span>Abastecimentos</span><strong>R$ {{ number_format($fuel_total,2,',','.') }}</strong></article><article><span>Despesas da oficina</span><strong>R$ {{ number_format($workshop_expenses_total,2,',','.') }}</strong></article><article><span>Consumíveis da oficina</span><strong>R$ {{ number_format($workshop_consumption_total,2,',','.') }}</strong></article><article><span>Total</span><strong>R$ {{ number_format($total,2,',','.') }}</strong></article></div>
    @endif
</div>
@endsection
