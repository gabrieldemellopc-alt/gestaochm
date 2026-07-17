@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pages/fiscal-documents.css') }}?v=1">
@endpush

@section('content')
    <div class="fiscal-documents-page">
        <header class="fiscal-documents-hero">
            <div>
                <span class="fiscal-kicker">Gestão administrativa</span>
                <h1>Notas Fiscais</h1>
                <p>
                    Consolidação operacional de documentos fiscais já vinculados a abastecimentos,
                    estoque e pneus na unidade ativa.
                </p>
            </div>

            <div class="fiscal-context-card">
                <span>Contexto ativo</span>
                <strong>{{ $context['location']->name ?? 'Unidade não selecionada' }}</strong>
                <small>{{ $context['division']->name ?? 'Divisão não selecionada' }}</small>
            </div>
        </header>

        @if($period_error)
            <div class="fiscal-alert">
                <i data-lucide="triangle-alert"></i>
                <span>{{ $period_error }}</span>
            </div>
        @endif

        <form method="GET" action="{{ route('fiscal-documents.index') }}" class="fiscal-filter-panel">
            <div class="fiscal-filter-grid">
                <label>
                    <span>Data inicial</span>
                    <input type="date" name="start_date" value="{{ $filters['start_date'] }}">
                </label>

                <label>
                    <span>Data final</span>
                    <input type="date" name="end_date" value="{{ $filters['end_date'] }}">
                </label>

                <label>
                    <span>Módulo</span>
                    <select name="module">
                        <option value="">Todos</option>
                        @foreach($modules ?? [] as $value => $label)
                            <option value="{{ $value }}" @selected($filters['module'] === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>Tipo</span>
                    <select name="type">
                        <option value="">Todos</option>
                        @foreach($types ?? [] as $value => $label)
                            <option value="{{ $value }}" @selected($filters['type'] === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="fiscal-search-field">
                    <span>Buscar</span>
                    <input
                        type="search"
                        name="search"
                        value="{{ $filters['search'] }}"
                        placeholder="NF, fornecedor, veículo, item, tanque..."
                    >
                </label>
            </div>

            <div class="fiscal-filter-footer">
                <label class="fiscal-checkbox">
                    <input
                        type="checkbox"
                        name="include_without_document"
                        value="1"
                        @checked($filters['include_without_document'])
                    >
                    <span>Incluir lançamentos sem documento informado</span>
                </label>

                <div class="fiscal-filter-actions">
                    <a href="{{ route('fiscal-documents.index') }}" class="fiscal-button secondary">
                        Limpar
                    </a>
                    <button type="submit" class="fiscal-button primary">
                        <i data-lucide="search"></i>
                        Aplicar filtros
                    </button>
                </div>
            </div>
        </form>

        <section class="fiscal-summary-grid">
            <article class="fiscal-summary-card">
                <span>Documentos</span>
                <strong>{{ number_format($summary['total_documents'], 0, ',', '.') }}</strong>
                <small>No período filtrado</small>
            </article>

            <article class="fiscal-summary-card">
                <span>Valor vinculado</span>
                <strong>R$ {{ number_format($summary['total_amount'], 2, ',', '.') }}</strong>
                <small>Referência operacional</small>
            </article>

            <article class="fiscal-summary-card">
                <span>Módulos</span>
                <strong>{{ number_format($summary['modules_count'], 0, ',', '.') }}</strong>
                <small>Com documentos encontrados</small>
            </article>

            <article class="fiscal-summary-card muted">
                <span>Sem documento</span>
                <strong>{{ number_format($summary['without_document'], 0, ',', '.') }}</strong>
                <small>Visíveis apenas quando incluídos no filtro</small>
            </article>
        </section>

        <section class="fiscal-documents-panel">
            <div class="fiscal-panel-header">
                <div>
                    <span>Documentos consolidados</span>
                    <h2>Registros fiscais da unidade</h2>
                </div>
                <small>
                    Esta tela não cria nem altera notas fiscais; apenas organiza documentos já informados nos lançamentos.
                </small>
            </div>

            <div class="fiscal-table-wrap">
                <table class="fiscal-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Documento</th>
                            <th>Origem</th>
                            <th>Fornecedor</th>
                            <th>Registro vinculado</th>
                            <th>Valor</th>
                            <th>Responsável</th>
                            <th>Unidade</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($documents as $document)
                            <tr class="{{ $document['has_document'] ? '' : 'is-missing-document' }}">
                                <td>
                                    <strong>{{ $document['date']?->format('d/m/Y') ?? '-' }}</strong>
                                    <small>{{ $document['date']?->format('H:i') }}</small>
                                </td>
                                <td>
                                    <span class="fiscal-document-number">
                                        {{ $document['document_number'] }}
                                    </span>
                                    @unless($document['has_document'])
                                        <small class="fiscal-muted">Sem NF/documento</small>
                                    @endunless
                                </td>
                                <td>
                                    <span class="fiscal-module-badge module-{{ $document['module'] }}">
                                        {{ $document['module_label'] }}
                                    </span>
                                    <small>{{ $document['type_label'] }}</small>
                                </td>
                                <td>{{ $document['supplier_name'] }}</td>
                                <td class="fiscal-linked-record">{{ $document['linked_record'] }}</td>
                                <td>
                                    @if($document['amount'] !== null)
                                        R$ {{ number_format($document['amount'], 2, ',', '.') }}
                                    @else
                                        <span class="fiscal-muted">Não informado</span>
                                    @endif
                                </td>
                                <td>{{ $document['responsible_name'] }}</td>
                                <td>{{ $document['location_name'] }}</td>
                                <td>
                                    <a href="{{ $document['origin_url'] }}" class="fiscal-row-link">
                                        Ver origem
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9">
                                    <div class="fiscal-empty-state">
                                        <i data-lucide="file-search"></i>
                                        <strong>Nenhum documento encontrado</strong>
                                        <p>
                                            Ajuste o período, revise os filtros ou marque a opção para incluir
                                            lançamentos sem documento informado.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
