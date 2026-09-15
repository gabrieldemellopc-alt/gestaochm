@extends('layouts.app')

@section('title', 'Central de Consistência')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pages/consistency.css') }}?v=5">
@endpush

@section('content')
<div class="consistency-page">

    <header class="consistency-header">
        <div>
            <span>Administração</span>
            <h1>Central de Consistência</h1>
            <p>Identifique, revise e trate possíveis inconsistências nos dados operacionais.</p>
        </div>
    </header>

    <section class="consistency-kpis">
        @foreach([
            'new' => 'Ativos',
            'critical' => 'Críticos atuais',
            'reviewing' => 'Em análise',
            'historical' => 'Históricos ativos',
            'resolved' => 'Resolvidos',
            'ignored' => 'Não são problema'
        ] as $key => $label)
            <div class="consistency-kpi {{ $key === 'critical' ? 'is-critical' : '' }}">
                <strong>{{ $kpis[$key] }}</strong>
                <span>{{ $label }}</span>
            </div>
        @endforeach
    </section>

    <nav class="consistency-tabs" aria-label="Situação dos alertas">
        <a
            href="{{ route('consistency.index', request()->except('status', 'page')) }}"
            class="{{ !request('status') ? 'active' : '' }}"
        >
            Ativos
        </a>

        <a
            href="{{ route('consistency.index', array_merge(request()->except('page'), ['status' => 'reviewing'])) }}"
            class="{{ request('status') === 'reviewing' ? 'active' : '' }}"
        >
            Em análise
        </a>

        <a
            href="{{ route('consistency.index', array_merge(request()->except('page'), ['status' => 'resolved'])) }}"
            class="{{ request('status') === 'resolved' ? 'active' : '' }}"
        >
            Resolvidos
        </a>

        <a
            href="{{ route('consistency.index', array_merge(request()->except('page'), ['status' => 'ignored'])) }}"
            class="{{ request('status') === 'ignored' ? 'active' : '' }}"
        >
            Não são problema
        </a>

        <a
            href="{{ route('consistency.index', array_merge(request()->except('page'), ['status' => 'archived'])) }}"
            class="{{ request('status') === 'archived' ? 'active' : '' }}"
        >
            Arquivados
        </a>
    </nav>

    <form method="GET" class="consistency-filters">

        <div>
            <label for="consistency-status">Status</label>
            <select id="consistency-status" name="status">
                <option value="">Ativos</option>
                <option value="new" @selected(request('status') === 'new')>Novos</option>
                <option value="reviewing" @selected(request('status') === 'reviewing')>Em análise</option>
                <option value="resolved" @selected(request('status') === 'resolved')>Resolvidos</option>
                <option value="ignored" @selected(request('status') === 'ignored')>Não são problema</option>
                <option value="archived" @selected(request('status') === 'archived')>Arquivados</option>
            </select>
        </div>

        <div>
            <label for="consistency-context">Contexto</label>
            <select id="consistency-context" name="context_type">
                <option value="">Todos os contextos</option>
                <option value="current" @selected(request('context_type') === 'current')>Atual</option>
                <option value="historical" @selected(request('context_type') === 'historical')>Histórico</option>
            </select>
        </div>

        <div class="consistency-search">
            <label for="consistency-search">Buscar</label>
            <input
                id="consistency-search"
                name="search"
                value="{{ request('search') }}"
                placeholder="Veículo, placa, código ou ocorrência..."
            >
        </div>

        <div class="consistency-filter-actions">
            <button type="submit">
                <i class="bi bi-funnel"></i>
                Filtrar
            </button>

            @if(request()->hasAny(['status', 'context_type', 'search']))
                <a href="{{ route('consistency.index') }}">
                    Limpar
                </a>
            @endif
        </div>
    </form>

    <section class="consistency-list">
        @forelse($alerts as $a)
            @php
                $d = $a->details ?? [];
                $v = $a->consistencyVehicle;
            @endphp

            <article class="consistency-alert">

                <div class="consistency-alert-main">

                    <div class="consistency-alert-top">
                        <span class="consistency-severity severity-{{ $a->severity }}">
                            {{ $a->severity }}
                        </span>

                        <span class="consistency-context-badge">
                            {{ $a->context_type === 'historical' ? 'Histórico' : 'Atual' }}
                        </span>

                        @if($a->last_scanned_at && $a->last_detected_at && $a->last_detected_at->equalTo($a->last_scanned_at))
                            <span class="consistency-detection">
                                Ainda detectado
                            </span>
                        @elseif($a->last_scanned_at && $a->last_detected_at && $a->last_detected_at->lt($a->last_scanned_at))
                            <span class="consistency-detection consistency-detection-cleared">
                                Não reencontrado
                            </span>
                        @endif
                    </div>

                    @if($v)
                        <strong class="consistency-vehicle">
                            {{ $v->name }}
                            ·
                            {{
                                $v->plate
                                ?: ($v->renavam
                                    ? 'RENAVAM: '.$v->renavam
                                    : ($v->serial_number
                                        ? 'Série: '.$v->serial_number
                                        : ($v->asset_code
                                            ? 'Código: '.$v->asset_code
                                            : 'Sem identificador')))
                            }}
                        </strong>
                    @endif

                    <h2>{{ $a->title }}</h2>
                    <p>{{ $a->summary }}</p>

                    @if($a->rule_key === 'vehicle_reading_regression')
                        <p class="consistency-alert-detail">
                            {{ $presenter->reading($d['previous']['value'] ?? 0, $d['type'] ?? 'km') }}
                            →
                            {{ $presenter->reading($d['current']['value'] ?? 0, $d['type'] ?? 'km') }}
                            · Queda: {{ $presenter->distance($d['magnitude'] ?? 0) }}
                        </p>

                    @elseif($a->rule_key === 'vehicle_current_counter_divergence')
                        <p class="consistency-alert-detail">
                            Atual: {{ $presenter->reading($d['current'] ?? 0, $d['type'] ?? 'km') }}
                            · Última válida: {{ $presenter->reading($d['last_valid'] ?? 0, $d['type'] ?? 'km') }}
                            · Diferença: {{ $presenter->distance($d['difference'] ?? 0) }}
                        </p>

                    @elseif($a->rule_key === 'vehicle_repeated_reading')
                        <p class="consistency-alert-detail">
                            Leitura: {{ $presenter->reading($d['value'] ?? 0) }}
                            · Ocorrências: {{ $d['events'] ?? 0 }}
                            · Período: {{ $presenter->date($d['from'] ?? null) }}
                            a {{ $presenter->date($d['to'] ?? null) }}
                        </p>

                    @elseif($a->rule_key === 'fuel_impossible_consumption')
                        <p class="consistency-alert-detail">
                            Distância: {{ $presenter->distance($d['distance'] ?? 0) }}
                            · Consumo: {{ $presenter->consumption($d['km_per_liter'] ?? 0) }}
                        </p>
                    @endif

                    @if($a->resolution_note)
                        <div class="consistency-resolution">
                            <strong>Tratamento registrado</strong>
                            <span>{{ $a->resolution_note }}</span>
                        </div>
                    @endif

                </div>

                <div class="consistency-alert-actions">

                    @if(in_array($a->status, ['new', 'reviewing'], true))

                        @if($a->status !== 'reviewing')
                            <form method="POST" action="{{ route('consistency.update', $a) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="reviewing">

                                <button type="submit">
                                    <i class="bi bi-eye"></i>
                                    Em análise
                                </button>
                            </form>
                        @endif

                        <details class="consistency-resolve">
                            <summary class="primary">
                                <i class="bi bi-check-lg"></i>
                                Resolver
                            </summary>

                            <form method="POST" action="{{ route('consistency.update', $a) }}">
                                @csrf
                                @method('PATCH')

                                <input type="hidden" name="status" value="resolved">

                                <textarea
                                    name="resolution_note"
                                    rows="3"
                                    required
                                    maxlength="3000"
                                    placeholder="Descreva brevemente o que foi corrigido ou tratado."
                                ></textarea>

                                <button type="submit" class="primary">
                                    <i class="bi bi-check-lg"></i>
                                    Confirmar resolução
                                </button>
                            </form>
                        </details>

                        <details class="consistency-ignore">
                            <summary>
                                <i class="bi bi-check-circle"></i>
                                Não é um problema
                            </summary>

                            <form method="POST" action="{{ route('consistency.update', $a) }}">
                                @csrf
                                @method('PATCH')

                                <input type="hidden" name="status" value="ignored">

                                <textarea
                                    name="resolution_note"
                                    rows="3"
                                    required
                                    maxlength="3000"
                                    placeholder="Explique brevemente por que este alerta não representa um problema."
                                ></textarea>

                                <button type="submit" class="ignore-action">
                                    Confirmar
                                </button>
                            </form>
                        </details>

                        <form method="POST" action="{{ route('consistency.update', $a) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="archived">

                            <button type="submit" class="quiet-action">
                                <i class="bi bi-archive"></i>
                                Arquivar
                            </button>
                        </form>

                    @else
                        <span class="consistency-treated-status">
                            @switch($a->status)
                                @case('resolved')
                                    Resolvido
                                    @break
                                @case('ignored')
                                    Não é um problema
                                    @break
                                @case('archived')
                                    Arquivado
                                    @break
                                @default
                                    {{ $a->status }}
                            @endswitch
                        </span>
                    @endif

                </div>

            </article>

        @empty
            <div class="consistency-empty">
                Nenhuma inconsistência encontrada para os filtros selecionados.
            </div>
        @endforelse
    </section>

    {{ $alerts->links() }}

</div>
@endsection
