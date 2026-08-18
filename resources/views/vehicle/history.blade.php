@extends('layouts.app')

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pages/vehicle-history.css') }}?v=2">
@endpush

@php
    $status = match($vehicle->operational_status) {
        'operational' => 'Operacional', 'maintenance' => 'Em manutenção', 'inactive' => 'Inativo',
        'inoperant' => 'Inoperante', 'accident' => 'Sinistro', 'support' => 'Socorro', default => 'Não informado',
    };
    $summary = $history['summary'];
@endphp

<main class="vehicle-history-page" x-data="vehicleHistory()">
    <div class="vehicle-history-container">
        <section class="vehicle-history-header">
            <div>
                <span>Frota · visão consolidada</span>
                <h1>Histórico do Veículo</h1>
                <p>Gestão operacional e técnica da frota</p>
            </div>
            <a href="{{ route('dashboard') }}" class="vehicle-history-back"><i data-lucide="arrow-left"></i> Voltar</a>
        </section>

        <section class="vehicle-history-vehicle-card">
            <div class="vehicle-history-identity">
                <div class="vehicle-history-avatar"><img src="{{ asset('images/' . ($vehicle->type ?? 'default') . '.png') }}" alt=""></div>
                <div class="vehicle-history-identity-copy">
                    <div><h2>{{ $vehicle->name }}</h2><span class="vehicle-history-status">{{ $status }}</span></div>
                    <p>{{ $vehicle->plate ?: 'Placa não informada' }} · {{ $vehicle->division?->name ?? 'Divisão não informada' }} · {{ $vehicle->currentAllocation?->location?->name ?? $vehicle->location?->name ?? 'Unidade não informada' }}</p>
                </div>
            </div>
            <div class="vehicle-history-readings">
                <div><span>Hodômetro atual</span><strong>{{ $vehicle->current_km !== null ? number_format($vehicle->current_km, 0, ',', '.') . ' km' : 'N/D' }}</strong></div>
                <div><span>Horímetro atual</span><strong>{{ $vehicle->current_hours !== null ? number_format($vehicle->current_hours, 0, ',', '.') . ' h' : 'N/D' }}</strong></div>
            </div>
        </section>

        <section class="vehicle-history-kpi-grid">
            @foreach([
                ['gauge', 'Hodômetro atual', $vehicle->current_km !== null ? number_format($vehicle->current_km, 0, ',', '.') . ' km' : 'N/D'],
                ['timer', 'Horímetro atual', $vehicle->current_hours !== null ? number_format($vehicle->current_hours, 0, ',', '.') . ' h' : 'N/D'],
                ['fuel', 'Abastecimentos', $summary['fillings']], ['wrench', 'Manutenções', $summary['maintenances']],
                ['circle-dot', 'Pneus em uso', $summary['tires'] ?: 'N/D'], ['clock-3', 'Última atualização', $summary['last_update']?->format('d/m/Y H:i') ?? 'N/D'],
                ['chart-no-axes-column-increasing', 'Média Km/L', 'N/D'],
            ] as [$icon, $label, $value])
                <article class="vehicle-history-kpi-card"><i data-lucide="{{ $icon }}"></i><span>{{ $label }}</span><strong>{{ $value }}</strong></article>
            @endforeach
            @if($summary['total_cost'] !== null)<article class="vehicle-history-kpi-card is-cost"><i data-lucide="badge-dollar-sign"></i><span>Custo operacional</span><strong>R$ {{ number_format($summary['total_cost'], 2, ',', '.') }}</strong></article>@endif
        </section>

        <section class="vehicle-history-filters">
            <div class="vehicle-history-period"><span>Período</span><div><button @click="period='30'" :class="{active: period === '30'}">30 dias</button><button @click="period='90'" :class="{active: period === '90'}">90 dias</button><button @click="period='year'" :class="{active: period === 'year'}">Ano atual</button><button @click="period='all'" :class="{active: period === 'all'}">Todo histórico</button></div></div>
            <label><span>Tipo de evento</span><select x-model="type"><option value="all">Todos</option><option value="operational">Operacional</option><option value="fuel">Abastecimentos</option><option value="maintenance">Manutenções</option><option value="tire">Pneus</option><option value="reading">Leituras</option><option value="location">Localizações</option></select></label>
            <label class="vehicle-history-search"><span>Busca</span><input x-model.debounce.150ms="search" placeholder="Descrição, OM ou documento"></label>
        </section>

        <section class="vehicle-history-events">
            <header><div><span>Linha do tempo</span><h2>Eventos operacionais e técnicos</h2></div><strong x-text="visibleCount + ' eventos'"></strong></header>
            @forelse($history['events'] as $event)
                @php $searchText = strtolower(implode(' ', [$event['label'], $event['title'], $event['description'], ...array_values($event['details'])])); @endphp
                <article class="vehicle-history-event-card type-{{ $event['type'] }}" x-show="matches('{{ $event['type'] }}', '{{ $event['occurred_at']->format('Y-m-d') }}', @js($searchText))">
                    <div class="vehicle-history-event-top"><div class="vehicle-history-event-title"><i data-lucide="{{ match($event['type']) { 'fuel' => 'fuel', 'maintenance' => 'wrench', 'tire' => 'circle-dot', 'location' => 'map-pin', 'reading' => 'gauge', default => 'activity' } }}"></i><div><span class="vehicle-history-badge">{{ $event['label'] }}</span><h3>{{ $event['title'] }}</h3></div></div><time>{{ $event['occurred_at']->format('d/m/Y H:i') }}</time></div>
                    @if($event['cancelled'])<span class="vehicle-history-cancelled">Cancelado</span>@endif
                    @if($event['description'])<p>{{ $event['description'] }}</p>@endif
                    @if(!empty($event['details']))
                        <dl class="vehicle-history-event-fields">
                            @foreach($event['details'] as $label => $value)
                                @php
                                    $displayValue = is_array($value)
                                        ? implode(', ', array_map(
                                            fn ($item) => is_scalar($item) || $item instanceof \Stringable ? (string) $item : null,
                                            array_filter($value, fn ($item) => is_scalar($item) || $item instanceof \Stringable)
                                        ))
                                        : $value;
                                @endphp

                                @if($displayValue !== null && $displayValue !== '')
                                    <div class="vehicle-history-field-chip">
                                        <dt>{{ $label }}</dt>
                                        <dd>{{ $displayValue }}</dd>
                                    </div>
                                @endif
                            @endforeach
                        </dl>
                    @endif
                    @if($event['image'])<img class="vehicle-history-photo" src="{{ $event['image'] }}" alt="Foto da manutenção">@endif
                    @if($event['url'])<a class="vehicle-history-detail" href="{{ $event['url'] }}">Abrir detalhe <i data-lucide="arrow-up-right"></i></a>@endif
                </article>
            @empty
                <div class="vehicle-history-empty"><i data-lucide="history"></i><strong>Nenhum evento registrado</strong><p>Este veículo ainda não possui registros operacionais ou técnicos disponíveis.</p></div>
            @endforelse
            <div class="vehicle-history-empty" x-show="visibleCount === 0"><i data-lucide="search-x"></i><strong>Nenhum registro encontrado para os filtros selecionados.</strong></div>
        </section>
    </div>
</main>

<script>
function vehicleHistory() { return { period: 'all', type: 'all', search: '', visibleCount: {{ count($history['events']) }}, matches(type, date, text) { const today = new Date(), eventDate = new Date(date + 'T00:00:00'); let match = this.type === 'all' || this.type === type; if (this.period === '30') match = match && ((today - eventDate) / 86400000 <= 30); if (this.period === '90') match = match && ((today - eventDate) / 86400000 <= 90); if (this.period === 'year') match = match && eventDate.getFullYear() === today.getFullYear(); match = match && (!this.search || text.includes(this.search.toLowerCase())); this.$nextTick(() => { this.visibleCount = [...this.$el.querySelectorAll('.vehicle-history-event-card')].filter((item) => item.style.display !== 'none').length; }); return match; } } }
</script>
@endsection
