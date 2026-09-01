@extends('layouts.app')
@push('styles')<link rel="stylesheet" href="{{ asset('css/pages/reports-financial.css') }}?v=1">@endpush
@section('content')
<main class="chm-financial-report">
<header class="chm-financial-report__header"><div><span>RELATÓRIOS</span><h1>Financeiro</h1><p>Custos operacionais consolidados por unidade.</p></div><a href="{{ route('reports.index') }}"><i class="bi bi-arrow-left"></i> Voltar para relatórios</a></header>
@if($error ?? null)<div class="chm-financial-report__notice">{{ $error }}</div>@else
<form method="GET" class="chm-financial-report__filters"><div><strong>Período do relatório</strong><small>Unidade: {{ $context['location']->name ?? 'Não informada' }}</small></div><label>Data inicial<input type="date" name="start_date" value="{{ $filters['start_date']->format('Y-m-d') }}"></label><label>Data final<input type="date" name="end_date" value="{{ $filters['end_date']->format('Y-m-d') }}"></label><button><i class="bi bi-funnel"></i> Aplicar filtros</button></form>
@if(! $filters['period_is_valid'])<div class="chm-financial-report__notice">A data inicial não pode ser maior que a data final.</div>@endif
<section class="chm-financial-report__kpis">
@foreach([['Manutenções','wrench',$maintenance_total],['Abastecimentos','fuel',$fuel_total],['Despesas da oficina','receipt',$workshop_expenses_total],['Consumíveis da oficina','package-minus',$workshop_consumption_total]] as [$label,$icon,$amount])<article><i class="{{ chm_icon($icon) }}"></i><span>{{ $label }}</span><strong>R$ {{ number_format($amount,2,',','.') }}</strong></article>@endforeach
<article class="is-total"><i class="bi bi-wallet2"></i><span>Custo operacional total</span><strong>R$ {{ number_format($total,2,',','.') }}</strong></article></section>
<section class="chm-financial-report__notice"><i class="bi bi-info-circle"></i><p>O total considera manutenções, abastecimentos, despesas da oficina e consumíveis internos. Entradas de estoque não são contabilizadas novamente como custo operacional.</p></section>
<section class="chm-financial-report__stock-purchases"><div><span>MOVIMENTAÇÃO FINANCEIRA / ESTOQUE</span><h2>Aquisições de estoque</h2><p>Este valor representa compras e entradas de materiais no período e não é somado ao custo operacional, pois os materiais são apropriados como custo quando efetivamente utilizados.</p></div><div class="chm-financial-report__stock-purchases-value"><strong>R$ {{ number_format($stock_purchases_total,2,',','.') }}</strong><small>{{ $stock_purchase_entries_count }} entrada(s) de compra no período</small></div></section>
@if($total <= 0)<section class="chm-financial-report__empty"><i class="bi bi-wallet2"></i> Nenhum custo operacional registrado no período selecionado.</section>@endif
@endif</main>
@endsection
