@extends('layouts.app')

@php
    $pageTitle = 'Auditoria';
    $pageSubtitle = 'Rastros do sistema';
@endphp

@push('styles')
<link rel="stylesheet" href="{{ asset('css/pages/audit.css') }}?v=2">
@endpush

@section('content')
@php
    $auditPermissions = $auditPermissions ?? [];
    $canViewAuditDetails = $auditPermissions['audit.view_details'] ?? true;
    $canViewAuditTechnicalDetails = $auditPermissions['audit.view_technical_details'] ?? true;
@endphp
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
            <span>{{ $activeDivision?->name ?? 'Divisão não definida' }}</span>
            <strong>{{ $auditScopeLabel }}</strong>
        </div>
    </header>

    <form method="GET" action="{{ route('audit.index') }}" class="audit-filters">
        <div>
            <label for="auditModule">Módulo</label>
            <select id="auditModule" name="module">
                <option value="">Todos</option>
                @foreach($modules as $module)
                    <option value="{{ $module }}" @selected(($filters['module'] ?? '') === $module)>
                        {{ $moduleLabels[$module] ?? \App\Support\ChmLabel::for('audit_module', $module) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="auditAction">Ação</label>
            <select id="auditAction" name="action">
                <option value="">Todas</option>
                @foreach($actions as $action)
                    <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>
                        {{ $actionLabels[$action] ?? \App\Support\ChmLabel::for('audit_action', $action) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="auditUser">Usuário</label>
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
            <label for="auditDateTo">Até</label>
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

        @forelse($auditEvents as $event)
            <article
                class="audit-event"
                x-data="{ open: false }"
                @keydown.escape.window="open = false"
            >
                <div class="audit-event-main">
                    <div class="audit-event-icon">
                        <i data-lucide="{{ $event['icon'] }}"></i>
                    </div>

                    <div>
                        <div class="audit-event-top">
                            @foreach($event['badges'] as $badge)
                                <span class="audit-badge {{ $badge['type'] }}">
                                    {{ $badge['label'] }}
                                </span>
                            @endforeach
                        </div>

                        <h3>{{ $event['title'] }}</h3>

                        <p class="audit-event-description">
                            {{ $event['narrative'] }}
                        </p>
                    </div>
                </div>

                <div class="audit-event-meta">
                    <strong>{{ $event['occurred_at_label'] }}</strong>
                    <span>{{ $event['actor_name'] }}</span>
                    <small>
                        {{ $event['division_label'] }}
                        <span>•</span>
                        {{ $event['location_label'] }}
                    </small>

                    @if($canViewAuditDetails)
                        <button type="button" class="audit-detail-button" @click="open = true">
                            Ver detalhes
                        </button>
                    @else
                        <span class="audit-empty-inline">Detalhes restritos</span>
                    @endif
                </div>

                @if($canViewAuditDetails)
                    <div
                        class="audit-modal-backdrop"
                        x-show="open"
                        x-cloak
                        x-transition.opacity
                        @click.self="open = false"
                    >
                    <div
                        class="audit-modal"
                        role="dialog"
                        aria-modal="true"
                        aria-label="Detalhes da auditoria"
                    >
                        <div class="audit-modal-header">
                            <div>
                                <span>{{ $event['module_label'] }}</span>
                                <h2>{{ $event['title'] }}</h2>
                                <p>{{ $event['subtitle'] }}</p>
                            </div>

                            <button type="button" @click="open = false" aria-label="Fechar detalhes">
                                <i data-lucide="x"></i>
                            </button>
                        </div>

                        <div class="audit-modal-body">
                            <section class="audit-narrative-card">
                                <span>O que aconteceu</span>
                                <p>{{ $event['narrative'] }}</p>
                            </section>

                            <section class="audit-fact-grid">
                                <div>
                                    <span>Usuário</span>
                                    <strong>{{ $event['actor_name'] }}</strong>
                                </div>

                                <div>
                                    <span>Ação</span>
                                    <strong>{{ $event['action_label'] }}</strong>
                                </div>

                                <div>
                                    <span>Registro afetado</span>
                                    <strong>{{ $event['context_label'] }}</strong>
                                </div>

                                <div>
                                    <span>Quando</span>
                                    <strong>{{ $event['occurred_at_label'] }}</strong>
                                </div>

                                <div>
                                    <span>Divisão</span>
                                    <strong>{{ $event['division_label'] }}</strong>
                                </div>

                                <div>
                                    <span>Unidade</span>
                                    <strong>{{ $event['location_label'] }}</strong>
                                </div>
                            </section>

                            @if($event['reason'])
                                <section class="audit-reason-card">
                                    <span>Motivo informado</span>
                                    <p>{{ $event['reason'] }}</p>
                                </section>
                            @endif

                            @if(! empty($event['changed_fields']))
                                <section class="audit-change-panel">
                                    <div class="audit-section-title">
                                        <span>Campos alterados</span>
                                        <small>{{ count($event['changed_fields']) }} campo(s)</small>
                                    </div>

                                    <div class="audit-change-table">
                                        <div class="audit-change-head">
                                            <span>Campo alterado</span>
                                            <span>Antes</span>
                                            <span>Depois</span>
                                        </div>

                                        @foreach($event['changed_fields'] as $change)
                                            <div class="audit-change-line">
                                                <strong>{{ $change['label'] }}</strong>
                                                <span>{{ $change['before'] }}</span>
                                                <span>{{ $change['after'] }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </section>
                            @else
                                <section class="audit-empty-inline">
                                    Nenhuma alteração campo a campo foi registrada neste log.
                                </section>
                            @endif

                            @if($canViewAuditTechnicalDetails)
                                <details class="audit-technical-details">
                                <summary>
                                    <span>Detalhes técnicos</span>
                                    <small>IDs, origem e payload bruto</small>
                                </summary>

                                <div class="audit-technical-grid">
                                    @foreach($event['technical_fields'] as $field)
                                        <div>
                                            <span>{{ $field['label'] }}</span>
                                            <strong>{{ $field['value'] }}</strong>
                                        </div>
                                    @endforeach
                                </div>

                                @foreach($event['technical_payloads'] as $payload)
                                    <div class="audit-technical-payload">
                                        <strong>{{ $payload['label'] }}</strong>
                                        <pre>{{ $payload['json'] }}</pre>
                                    </div>
                                @endforeach
                            </details>
                        </div>
                    </div>
                </div>
                    </div>
                @endif
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