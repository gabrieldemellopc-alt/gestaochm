@extends('layouts.app')

    @php
        $pageTitle = 'Auditoria';
        $pageSubtitle = 'Rastros do sistema';
    
        $fieldLabels = [
        'id' => 'Identificador',
        'tenant_id' => 'Tenant',
        'division_id' => 'Divisão',
        'location_id' => 'Unidade',
        'vehicle_id' => 'Veículo',
        'procedure_id' => 'Procedimento',
        'stock_item_id' => 'Item de estoque',
        'maintenance_record_id' => 'Ordem de manutenção',
        'maintenance_record_item_id' => 'Serviço da manutenção',
    
        'name' => 'Nome',
        'plate' => 'Placa',
        'status' => 'Status',
        'operational_status' => 'Status operacional',
        'workflow_status' => 'Situação da ordem',
        'service_status' => 'Fase da manutenção',
        'maintenance_category' => 'Categoria',
        'maintenance_type' => 'Tipo de execução',
    
        'current_km' => 'Hodômetro atual',
        'current_hours' => 'Horímetro atual',
        'performed_km' => 'Hodômetro registrado',
        'performed_hours' => 'Horímetro registrado',
    
        'started_at' => 'Data de início',
        'finished_at' => 'Data de encerramento',
        'performed_at' => 'Data de execução',
        'created_at' => 'Data de criação',
        'updated_at' => 'Última alteração',
        'cancelled_at' => 'Cancelado em',
        'deleted_at' => 'Data da exclusão',
    
        'opened_by' => 'Aberta por',
        'closed_by' => 'Encerrada por',
        'cancelled_by' => 'Cancelada por',
        'deleted_by' => 'Excluída por',
    
        'reason' => 'Motivo',
        'notes' => 'Observações',
        'closure_notes' => 'Observações do encerramento',
        'cancel_reason' => 'Motivo do cancelamento',
        'delete_reason' => 'Motivo da exclusão',
        'status_reason' => 'Motivo do status',
    
        'total_cost' => 'Custo total',
        'extra_cost' => 'Custo adicional',
        'amount' => 'Valor',
        'unit_cost' => 'Custo unitário',
        'quantity' => 'Quantidade',
        'movement_type' => 'Tipo de movimentação',
        'description' => 'Descrição',
    
        'active' => 'Ativo',
    ];

    $valueLabels = [
        'open' => 'Aberta',
        'closed' => 'Encerrada',
        'cancelled' => 'Cancelada',
    
        'operational' => 'Operacional',
        'maintenance' => 'Em manutenção',
        'inactive' => 'Inativo',
        'inoperant' => 'Inoperante',
        'accident' => 'Sinistro',
        'support' => 'Socorro',
        'testing' => 'Em testes',
        'transfer' => 'Em transferência',
        'transferred' => 'Transferido',
    
        'technical_analysis' => 'Análise técnica',
        'service_in_progress' => 'Andamento do serviço',
        'awaiting_material' => 'Aguardando material',
        'material_survey' => 'Levantamento de material',
        'purchase_request' => 'Solicitação de compra',
        'awaiting_labor' => 'Aguardando mão de obra',
        'awaiting_resource' => 'Aguardando recurso',
        'awaiting_approval' => 'Pendente de aprovação',
        'awaiting_budget' => 'Pendente de orçamento',
        'supplier_warranty' => 'Garantia do fornecedor',
        'third_party_responsibility' => 'Responsabilidade de terceiros',
        'scheduled_commitment' => 'Compromisso lançado',
    
        'internal' => 'Oficina interna',
        'external' => 'Terceirizado',
    
        'in' => 'Entrada',
        'out' => 'Saída',
    
        'preventive' => 'Preventiva',
        'corrective' => 'Corretiva',
        'inspection' => 'Inspeção',
        'other' => 'Outros',
    
        'true' => 'Sim',
        'false' => 'Não',
    ];

    $flattenAuditData = function ($value, string $prefix = '') use (&$flattenAuditData) {
        if (! is_array($value)) {
            return collect();
        }

        return collect($value)->flatMap(function ($item, $key) use (
            &$flattenAuditData,
            $prefix
        ) {
            $fullKey = $prefix
                ? $prefix . '.' . $key
                : (string) $key;

            if (is_array($item)) {
                return $flattenAuditData($item, $fullKey);
            }

            return [$fullKey => $item];
        });
    };

    $humanFieldLabel = function (string $field) use ($fieldLabels) {
        $fieldName = str_contains($field, '.')
            ? last(explode('.', $field))
            : $field;

        return $fieldLabels[$fieldName]
            ?? \Illuminate\Support\Str::headline($fieldName);
    };

    $humanValue = function ($value, string $field = '') use ($valueLabels) {
        if ($value === null || $value === '') {
        return 'Não informado';
        }

        if (is_bool($value)) {
            return $value ? 'Sim' : 'Não';
        }

        $fieldName = str_contains($field, '.')
            ? last(explode('.', $field))
            : $field;

        if (
            in_array($fieldName, [
                'total_cost',
                'extra_cost',
                'amount',
                'unit_cost',
            ], true)
            && is_numeric($value)
        ) {
            return 'R$ ' . number_format((float) $value, 2, ',', '.');
        }

        if (
            str_ends_with($fieldName, '_at')
            && is_string($value)
        ) {
            try {
                return \Illuminate\Support\Carbon::parse($value)
                    ->format('d/m/Y H:i');
            } catch (\Throwable $exception) {
                return $value;
            }
        }

        $normalizedValue = strtolower((string) $value);

        if (array_key_exists($normalizedValue, $valueLabels)) {
            return $valueLabels[$normalizedValue];
        }

        if (is_numeric($value)) {
            return number_format((float) $value, 0, ',', '.');
        }

        return (string) $value;
    };

    $browserLabel = function (?string $userAgent) {
        if (! $userAgent) {
            return 'Navegador não informado';
        }
    
        $browser = match (true) {
            str_contains($userAgent, 'OPR/') => 'Opera',
            str_contains($userAgent, 'Edg/') => 'Microsoft Edge',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Chrome/') => 'Google Chrome',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => 'Navegador não identificado',
        };
    
        $system = match (true) {
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone') => 'iPhone',
            str_contains($userAgent, 'iPad') => 'iPad',
            str_contains($userAgent, 'Mac OS') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'Sistema não identificado',
        };
    
        return $browser . ' em ' . $system;
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
                        {{ $moduleLabels[$module] ?? \Illuminate\Support\Str::headline($module) }}
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
                        {{ $actionLabels[$action] ?? \Illuminate\Support\Str::headline($action) }}
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
            <label for="auditDateTo">Até</dlabel>
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
                $entityClass = $log->auditable_type
                    ? class_basename($log->auditable_type)
                    : null;
            
                $entityName = $entityClass
                    ? ($entityLabels[$entityClass]
                        ?? \Illuminate\Support\Str::headline($entityClass))
                    : 'Registro do sistema';
            
                $entityLabel = $entityName
                    . ($log->auditable_id
                        ? ' #' . $log->auditable_id
                        : '');
            
                $moduleLabel = $moduleLabels[$log->module]
                    ?? \Illuminate\Support\Str::headline($log->module ?? 'sistema');
            
                $actionLabel = $actionLabels[$log->action]
                    ?? \Illuminate\Support\Str::headline($log->action ?? 'evento');
            
                $profileLabel = $profileLabels[$log->user_profile]
                    ?? \Illuminate\Support\Str::headline(
                        $log->user_profile ?? ''
                    );
            
                $beforeData = $flattenAuditData($log->before_data ?? []);
                $afterData = $flattenAuditData($log->after_data ?? []);
            
                $allChangedKeys = $beforeData
                    ->keys()
                    ->merge($afterData->keys())
                    ->unique()
                    ->filter(function ($key) use ($beforeData, $afterData) {
                        return $beforeData->get($key) !== $afterData->get($key);
                    })
                    ->values();
            
                $technicalMetadata = $log->metadata
                    ? json_encode(
                        $log->metadata,
                        JSON_PRETTY_PRINT
                        | JSON_UNESCAPED_UNICODE
                        | JSON_UNESCAPED_SLASHES
                    )
                    : null;
            @endphp

            <article class="audit-event">
                <div class="audit-event-main">
                    <div class="audit-event-icon">
                        <i data-lucide="fingerprint"></i>
                    </div>

                    <div>
                        <div class="audit-event-top">
                            <span class="audit-badge module">
                                {{ $moduleLabel }}
                            </span>
                            
                            <span class="audit-badge action">
                                {{ $actionLabel }}
                            </span>
                            
                            @if($log->user_profile)
                                <span class="audit-badge profile">
                                    {{ $profileLabel }}
                                </span>
                            @endif
                        </div>

                        <h3>{{ $log->summary ?? $entityLabel }}</h3>

                        <p class="audit-event-description">
                            <span>{{ $entityLabel }}</span>
                        
                            @if($log->reason)
                                <span>
                                    <strong>Motivo:</strong>
                                    {{ $log->reason }}
                                </span>
                            @endif
                        </p>
                    </div>
                </div>

                <div class="audit-event-meta">
                    <strong>{{ optional($log->created_at)->format('d/m/Y H:i') }}</strong>
                    <span>{{ $log->user?->name ?? 'Usuário não informado' }}</span>
                    <small>
                        {{ $log->division?->name ?? 'Divisão não informada' }}
                    
                        <span>•</span>
                    
                        {{ $log->location?->name ?? 'Todas as unidades' }}
                    </small>
                </div>

                <div class="audit-event-details">

                    @if($allChangedKeys->isNotEmpty())
                        <details class="audit-change-details">
                            <summary>
                                <span>Alterações registradas</span>                
                                <small>
                                    {{ $allChangedKeys->count() }}
                                    campo(s)
                                </small>
                            </summary>
                
                            <div class="audit-change-list">
                
                                @foreach($allChangedKeys as $changedKey)
                                    @php
                                        $hasBefore = $beforeData->has($changedKey);
                                        $hasAfter = $afterData->has($changedKey);
                
                                        $beforeValue = $humanValue(
                                            $beforeData->get($changedKey),
                                            $changedKey
                                        );
                
                                        $afterValue = $humanValue(
                                            $afterData->get($changedKey),
                                            $changedKey
                                        );
                                    @endphp
                
                                    <div class="audit-change-row">
                
                                        <strong>
                                            {{ $humanFieldLabel($changedKey) }}
                                        </strong>
                
                                        <div class="audit-change-values">
                
                                            @if($hasBefore)
                                                <span class="audit-change-old">
                                                    {{ $beforeValue }}
                                                </span>
                                            @endif
                
                                            @if($hasBefore && $hasAfter)
                                                <i data-lucide="arrow-right"></i>
                                            @endif
                
                                            @if($hasAfter)
                                                <span class="audit-change-new">
                                                    {{ $afterValue }}
                                                </span>
                                            @endif
                
                                        </div>
                
                                    </div>
                                @endforeach
                
                            </div>
                        </details>
                    @endif
                
                    @if($technicalMetadata)
                        <details>
                            <summary>Dados técnicos</summary>                
                            <pre>{{ $technicalMetadata }}</pre>
                        </details>
                    @endif
                
                    <details>
                        <summary>Origem do acesso</summary>
                
                        <div class="audit-origin">
                            <span>
                                <strong>Endereço IP:</strong>
                            {{ $log->ip_address ?? 'Não informado' }}
                            </span>
                
                            <span>
                                <strong>Dispositivo:</strong>
                                {{ $browserLabel($log->user_agent) }}
                            </span>
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
