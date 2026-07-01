@extends('layouts.app')

@php
    $pageTitle = 'Auditoria';
    $pageSubtitle = 'Rastros do sistema';

    $formatJson = function ($value) {
        if (blank($value)) {
            return null;
        }

        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    };
@endphp

@push('styles')
<link rel="stylesheet" href="{{ asset('css/pages/audit.css') }}?v=1">
@endpush

@section('content')
<div class="audit-page">
    <header class="audit-header">
        <div>
            <span>Rastros do sistema</span>
            <h1>Auditoria</h1>
            <p>
                Consulta somente leitura dos eventos registrados para
                {{ $auditScopeLabel }}.
            </p>
        </div>

        <div class="audit-context">
            <span>{{ $activeDivision?->name ?? 'Divisao nao definida' }}</span>
            <strong>{{ $auditScopeLabel }}</strong>
        </div>
    </header>

    <form method="GET" action="{{ route('audit.index') }}" class="audit-filters">
        <div>
            <label for="auditModule">Modulo</label>
            <select id="auditModule" name="module">
                <option value="">Todos</option>
                @foreach($modules as $module)
                    <option value="{{ $module }}" @selected(($filters['module'] ?? '') === $module)>
                        {{ ucfirst($module) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="auditAction">Acao</label>
            <select id="auditAction" name="action">
                <option value="">Todas</option>
                @foreach($actions as $action)
                    <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>
                        {{ ucfirst($action) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="auditUser">Usuario</label>
            <select id="auditUser" name="user_id">
                <option value="">Todos</option>
                @foreach($auditUsers as $auditUser)
                    <option value="{{ $auditUser->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $auditUser->id)>
                        {{ $auditUser->name }}{{ $auditUser->email ? ' - ' . $auditUser->email : '' }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="auditLocation">Unidade</label>
            <select id="auditLocation" name="location_id">
                <option value="">Todas permitidas</option>
                @foreach($auditLocations as $location)
                    <option value="{{ $location->id }}" @selected((string) ($filters['location_id'] ?? '') === (string) $location->id)>
                        {{ $location->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="auditDateFrom">De</label>
            <input id="auditDateFrom" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
        </div>

        <div>
            <label for="auditDateTo">Ate</label>
            <input id="auditDateTo" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
        </div>

        <div class="audit-filter-search">
            <label for="auditSearch">Busca</label>
            <input
                id="auditSearch"
                type="search"
                name="search"
                value="{{ $filters['search'] ?? '' }}"
                placeholder="Resumo, motivo, entidade ou ID"
            >
        </div>

        <div class="audit-filter-actions">
            <button type="submit">Filtrar</button>
        </div>
        <div class="audit-filter-actions">
            <a href="{{ route('audit.index') }}">Limpar</a>
        </div>
    </form>

    <section class="audit-card">
        <div class="audit-card-header">
            <div>
                <span>Eventos</span>
                <h2>{{ $logs->total() }} registro(s)</h2>
            </div>
        </div>

        @forelse($logs as $log)
            @php
                $entityLabel = $log->auditable_type
                    ? class_basename($log->auditable_type) . ($log->auditable_id ? ' #' . $log->auditable_id : '')
                    : 'Entidade nao informada';

                $beforeJson = $formatJson($log->before_data);
                $afterJson = $formatJson($log->after_data);
                $metadataJson = $formatJson($log->metadata);
            @endphp

            <article class="audit-event">
                <div class="audit-event-main">
                    <div class="audit-event-icon">
                        <i data-lucide="fingerprint"></i>
                    </div>

                    <div>
                        <div class="audit-event-top">
                            <span class="audit-badge module">{{ $log->module ?? 'sistema' }}</span>
                            <span class="audit-badge action">{{ $log->action }}</span>
                            @if($log->user_profile)
                                <span class="audit-badge profile">{{ $log->user_profile }}</span>
                            @endif
                        </div>

                        <h3>{{ $log->summary ?? $entityLabel }}</h3>

                        <p>
                            {{ $entityLabel }}
                            @if($log->reason)
                                <span>Motivo: {{ $log->reason }}</span>
                            @endif
                        </p>
                    </div>
                </div>

                <div class="audit-event-meta">
                    <strong>{{ optional($log->created_at)->format('d/m/Y H:i') }}</strong>
                    <span>{{ $log->user?->name ?? 'Usuario nao informado' }}</span>
                    <small>
                        {{ $log->tenant?->name ?? 'Tenant --' }}
                        · {{ $log->division?->name ?? 'Divisao --' }}
                        · {{ $log->location?->name ?? 'Unidade --' }}
                    </small>
                </div>

                <div class="audit-event-details">
                    @if($beforeJson)
                        <details>
                            <summary>Antes</summary>
                            <pre>{{ $beforeJson }}</pre>
                        </details>
                    @endif

                    @if($afterJson)
                        <details>
                            <summary>Depois</summary>
                            <pre>{{ $afterJson }}</pre>
                        </details>
                    @endif

                    @if($metadataJson)
                        <details>
                            <summary>Metadados</summary>
                            <pre>{{ $metadataJson }}</pre>
                        </details>
                    @endif

                    <details>
                        <summary>Origem</summary>
                        <div class="audit-origin">
                            <span>IP: {{ $log->ip_address ?? '--' }}</span>
                            <span>User agent: {{ $log->user_agent ?? '--' }}</span>
                        </div>
                    </details>
                </div>
            </article>
        @empty
            <div class="audit-empty">
                <i data-lucide="search-x"></i>
                <strong>Nenhum registro encontrado</strong>
                <p>Ajuste os filtros para consultar outros rastros do sistema.</p>
            </div>
        @endforelse
    </section>

    @if($logs->hasPages())
        <div class="audit-pagination">
            {{ $logs->links() }}
        </div>
    @endif
</div>
@endsection
