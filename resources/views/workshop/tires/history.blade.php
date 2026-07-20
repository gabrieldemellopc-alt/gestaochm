@extends('layouts.app')

@php
    $pageTitle = 'Oficina';
    $pageSubtitle = 'Histórico do pneu';

    $statusLabel = match($tire->status) {
        'available' => 'Disponível' . ($tire->retreads_count > 0 ? '-R' . $tire->retreads_count : ''),
        'installed' => 'Instalado' . ($tire->retreads_count > 0 ? '-R' . $tire->retreads_count : ''),
        'maintenance' => 'Manutenção' . ($tire->retreads_count > 0 ? '-R' . $tire->retreads_count : ''),
        'discarded' => 'Descartado' . ($tire->retreads_count > 0 ? '-R' . $tire->retreads_count : ''),
        default => $tire->status,
    };
@endphp

@push('styles')
<link
    rel="stylesheet"
    href="{{ asset('css/pages/workshop-tires.css') }}?v=4"
>
@endpush

@section('content')

@php
    $tirePermissions = array_merge([
        'view' => false,
        'create_entry' => false,
        'install' => false,
        'remove' => false,
        'measure' => false,
        'retread' => false,
        'cancel' => false,
        'view_costs' => false,
        'manage_inventory' => false,
    ], $tirePermissions ?? []);

    $canCancelTireRecords = (bool) $tirePermissions['cancel'];
@endphp
<div class="workshop-tire-history-page">
    <header class="tire-history-header">
        <div>
            <a
                href="{{ route('workshop.tires.index') }}"
                class="tire-history-back"
            >
                <i data-lucide="arrow-left"></i>
                Voltar para pneus
            </a>

            <span>Histórico individual do pneu</span>
            <h1>{{ $tire->code }}</h1>
            <p>
                {{ $tire->brand ?? 'Sem marca' }}
                {{ $tire->model ? ' · ' . $tire->model : '' }}
                {{ $tire->size ? ' · ' . $tire->size : '' }}
            </p>
        </div>

        <span class="tire-history-status {{ $tire->status }}">
            {{ $statusLabel }}
        </span>
    </header>

    <section class="tire-history-summary">
        <article>
            <span>Sulco inicial</span>
            <strong>
                {{ $tire->initial_tread_depth !== null ? number_format((float) $tire->initial_tread_depth, 2, ',', '.') . ' mm' : '--' }}
            </strong>
        </article>

        <article>
            <span>Sulco atual</span>
            <strong>
                {{ $tire->current_tread_depth !== null ? number_format((float) $tire->current_tread_depth, 2, ',', '.') . ' mm' : '--' }}
            </strong>
        </article>

        <article>
            <span>Referência atual</span>
            <strong>
                @switch($tire->current_tread_source)
                    @case('retread')
                        Recapagem R{{ $tire->retreads_count }}
                        @break
                    @case('measurement')
                        Última medição
                        @break
                    @default
                        Sulco inicial
                @endswitch
            </strong>
        </article>

        <article>
            <span>Recapagens</span>
            <strong>{{ $tire->retreads_count }}</strong>
        </article>
    </section>

    <aside class="tire-history-notice">
        <i data-lucide="info"></i>
        <p>Alterações manuais de status anteriores não possuem histórico registrado.</p>
    </aside>

    <section class="tire-history-section">
        <div class="tire-history-section-header">
            <div>
                <span>Linha do tempo</span>
                <h2>Movimentações registradas</h2>
            </div>

            <strong>{{ $timeline->count() }} eventos</strong>
        </div>

        @if($timeline->isEmpty())
            <div class="tire-history-empty">
                <i data-lucide="history"></i>
                <strong>Nenhum evento registrado</strong>
                <p>Este pneu ainda não possui movimentações disponíveis para exibição.</p>
            </div>
        @else
            <div class="tire-history-timeline">
                @foreach($timeline as $event)
                    @php
                        $eventTypeLabel = match($event['type']) {
                            'entry' => 'Entrada',
                            'installation' => 'Instalação',
                            'removal' => 'Retirada',
                            'measurement' => 'Medição',
                            'retread' => 'Recapagem',
                        };
                    @endphp

                    <article class="tire-history-event {{ $event['type'] }} {{ ($event['is_cancelled'] ?? false) ? 'cancelled' : '' }}">
                        <div class="tire-history-marker">
                            @switch($event['type'])
                                @case('entry')
                                    <i data-lucide="package-plus"></i>
                                    @break
                                @case('installation')
                                    <i data-lucide="truck"></i>
                                    @break
                                @case('removal')
                                    <i data-lucide="package-minus"></i>
                                    @break
                                @case('measurement')
                                    <i data-lucide="ruler"></i>
                                    @break
                                @case('retread')
                                    <i data-lucide="refresh-cw"></i>
                                    @break
                            @endswitch
                        </div>

                        <div class="tire-history-event-body">
                            <div class="tire-history-event-top">
                                <div>
                                    <span>{{ $eventTypeLabel }}</span>
                                    <h3>{{ $event['title'] }}</h3>
                                </div>

                                <time>
                                    {{ optional($event['date'])->format('d/m/Y') ?? 'Data não informada' }}
                                </time>
                            </div>
                            @if($event['is_cancelled'] ?? false)
                                <div class="tire-history-cancelled">
                                    <strong>Cancelado em {{ optional($event['cancelled_at'])->format('d/m/Y H:i') ?? '--' }}</strong>
                                    @can('viewAuditLogs')
                                        @if($event['cancel_reason'] ?? null)
                                            <span>Motivo: {{ $event['cancel_reason'] }}</span>
                                        @endif
                                    @endcan
                                </div>
                            @endif


                            <div class="tire-history-event-details">
                                @foreach($event['details'] as $label => $value)
                                    @if($value !== null && $value !== '')
                                        <div>
                                            <span>{{ $label }}</span>
                                            <strong>{{ $value }}</strong>
                                        </div>
                                    @endif
                                @endforeach
                            </div>

                            @if($event['notes'])
                                <p class="tire-history-event-notes">
                                    {{ $event['notes'] }}
                                </p>
                            @endif

                            @if($canCancelTireRecords)
                                @if(! ($event['is_cancelled'] ?? false) && in_array($event['type'], ['measurement', 'retread'], true))
                                    <form
                                        method="POST"
                                        action="{{ $event['type'] === 'measurement'
                                            ? route('workshop.tires.measurements.cancel', [$tire, $event['record']])
                                            : route('workshop.tires.retreads.cancel', [$tire, $event['record']]) }}"
                                        class="tire-history-cancel-form"
                                    >
                                        @csrf
                                        <label>
                                            Motivo do cancelamento
                                            <textarea
                                                name="reason"
                                                rows="2"
                                                minlength="5"
                                                maxlength="2000"
                                                required
                                                placeholder="Informe o motivo para manter a trilha de auditoria"
                                            ></textarea>
                                        </label>

                                        <button type="submit">
                                            Cancelar lancamento
                                        </button>
                                    </form>
                                @endif
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</div>
@endsection
