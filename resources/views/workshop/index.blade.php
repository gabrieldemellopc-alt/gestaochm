@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/pages/workshop.css') }}?v=5">
@endpush

@section('content')

<div class="workshop-home-page">

    <div class="workshop-command-hero">

        <div class="workshop-command-main">
            <span class="workshop-home-kicker">
                Oficina
            </span>

            <h1>
                Central da Oficina
            </h1>

            <p>
                Acompanhe veículos em manutenção, alertas técnicos, estoque crítico, pneus e procedimentos operacionais em um único painel.
            </p>

            <div class="workshop-hero-actions">
                <a href="{{ route('workshop.tires.index') }}" class="workshop-action-button primary">
                    <i data-lucide="circle-dot"></i>
                    Controle de pneus
                </a>

                <a href="{{ route('stock.index') }}" class="workshop-action-button secondary">
                    <i data-lucide="boxes"></i>
                    Estoque
                </a>

                <a href="{{ route('procedures.index') }}" class="workshop-action-button secondary">
                    <i data-lucide="clipboard-list"></i>
                    Procedimentos
                </a>
            </div>
        </div>

        <div class="workshop-hero-panel">
            <div class="workshop-hero-panel-icon">
                <i data-lucide="wrench"></i>
            </div>

            <div>
                <span>Status operacional</span>
                <strong>
                    {{ $maintenanceVehiclesCount > 0 ? $maintenanceVehiclesCount . ' veículo(s) em atenção' : 'Operação estável' }}
                </strong>
                <p>
                    Dados consolidados da oficina, estoque, pneus e procedimentos.
                </p>
            </div>
        </div>

    </div>

    <div class="workshop-summary-grid">

        <div class="workshop-summary-card">
            <div class="workshop-summary-icon">
                <i data-lucide="truck"></i>
            </div>

            <div>
                <span>Veículos</span>
                <strong>{{ $maintenanceVehiclesCount }}</strong>
                <p>Em manutenção ou indisponíveis</p>
            </div>
        </div>

        <div class="workshop-summary-card">
            <div class="workshop-summary-icon danger">
                <i data-lucide="triangle-alert"></i>
            </div>

            <div>
                <span>Estoque</span>
                <strong>{{ $lowStockCount }}</strong>
                <p>Itens abaixo do mínimo</p>
            </div>
        </div>

        <div class="workshop-summary-card">
            <div class="workshop-summary-icon warning">
                <i data-lucide="circle-dot"></i>
            </div>

            <div>
                <span>Pneus</span>
                <strong>{{ $tiresAttentionCount }}</strong>
                <p>Com alerta ou manutenção</p>
            </div>
        </div>

        <div class="workshop-summary-card">
            <div class="workshop-summary-icon">
                <i data-lucide="clipboard-list"></i>
            </div>

            <div>
                <span>Procedimentos</span>
                <strong>{{ $proceduresCount }}</strong>
                <p>Regras operacionais cadastradas</p>
            </div>
        </div>

    </div>

    <div class="workshop-content-grid">

        <section class="workshop-panel workshop-panel-large">

            <div class="workshop-panel-header">
                <div>
                    <span>Manutenção</span>
                    <h2>Veículos em atenção</h2>
                </div>

                <a href="{{ route('vehicles.index') }}">
                    Ver frota
                    <i data-lucide="arrow-right"></i>
                </a>
            </div>

            @if($vehiclesInMaintenance->count())
                <div class="workshop-vehicle-list">
                    @foreach($vehiclesInMaintenance as $vehicle)
                    <a href="{{ route('vehicles.details', $vehicle->id) }}" class="workshop-vehicle-card">
                            <div class="workshop-vehicle-avatar">
                                <i data-lucide="truck"></i>
                            </div>

                            <div class="workshop-vehicle-info">
                                <strong>
                                    {{ $vehicle->plate ?? 'Sem placa' }}
                                </strong>

                                <span>
                                    {{ $vehicle->name ?? 'Veículo sem nome' }}
                                </span>
                            </div>

                            <div class="workshop-vehicle-status">
                                {{ $vehicle->status === 'maintenance' ? 'Manutenção' : 'Indisponível' }}
                            </div>

                        </a>
                    @endforeach
                </div>
            @else
                <div class="workshop-empty-state">
                    <div>
                        <i data-lucide="check-circle-2"></i>
                    </div>

                    <strong>Nenhum veículo em manutenção agora</strong>

                    <p>
                        Quando algum veículo entrar em manutenção ou ficar indisponível, ele aparecerá aqui.
                    </p>
                </div>
            @endif

        </section>

        <section class="workshop-panel">

            <div class="workshop-panel-header">
                <div>
                    <span>Acesso rápido</span>
                    <h2>Módulos da oficina</h2>
                </div>
            </div>

            <div class="workshop-shortcut-list">

                <a href="{{ route('workshop.tires.index') }}" class="workshop-shortcut-card">
                    <div>
                        <i data-lucide="circle-dot"></i>
                    </div>

                    <section>
                        <strong>Pneus</strong>
                        <span>Estoque, instalações, sulco e alertas.</span>
                    </section>

                    <i data-lucide="arrow-up-right"></i>
                </a>

                <a href="{{ route('stock.index') }}" class="workshop-shortcut-card">
                    <div>
                        <i data-lucide="boxes"></i>
                    </div>

                    <section>
                        <strong>Estoque</strong>
                        <span>Itens, categorias e movimentações.</span>
                    </section>

                    <i data-lucide="arrow-up-right"></i>
                </a>

                <a href="{{ route('procedures.index') }}" class="workshop-shortcut-card">
                    <div>
                        <i data-lucide="clipboard-list"></i>
                    </div>

                    <section>
                        <strong>Procedimentos</strong>
                        <span>Regras de manutenção e execução.</span>
                    </section>

                    <i data-lucide="arrow-up-right"></i>
                </a>

            </div>

        </section>

    </div>

    <div class="workshop-preview-grid">

        <section class="workshop-panel">

            <div class="workshop-panel-header">
                <div>
                    <span>Estoque</span>
                    <h2>Itens em atenção</h2>
                </div>

                <a href="{{ route('stock.index') }}">
                    Abrir
                    <i data-lucide="arrow-right"></i>
                </a>
            </div>

            @if($lowStockItems->count())
                <div class="workshop-mini-list">
                    @foreach($lowStockItems as $item)
                        <div class="workshop-mini-row">
                            <div>
                                <strong>{{ $item->name }}</strong>
                                <span>
                                    Atual: {{ number_format($item->quantity ?? 0, 2, ',', '.') }}
                                    {{ $item->unit ?? '' }}
                                    · mínimo:
                                    {{ number_format($item->minimum_quantity ?? 0, 2, ',', '.') }}
                                    {{ $item->unit ?? '' }}
                                </span>
                            </div>
                    
                            <small>baixo</small>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="workshop-mini-empty">
                    Nenhum item abaixo do mínimo.
                </div>
            @endif

        </section>

        <section class="workshop-panel">

            <div class="workshop-panel-header">
                <div>
                    <span>Pneus</span>
                    <h2>Alertas recentes</h2>
                </div>

                <a href="{{ route('workshop.tires.index') }}">
                    Abrir
                    <i data-lucide="arrow-right"></i>
                </a>
            </div>

            @if($tiresAttention->count())
                <div class="workshop-mini-list">
                    @foreach($tiresAttention as $tire)
                        <div class="workshop-mini-row">
                            <div>
                                <strong>{{ $tire->code ?? 'Pneu' }}</strong>
                                <span>
                                    Sulco atual:
                                    {{ $tire->minimum_tread ?? $tire->initial_tread_depth ?? '-' }} mm
                                </span>
                            </div>

                            <small>alerta</small>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="workshop-mini-empty">
                    Nenhum pneu em alerta.
                </div>
            @endif

        </section>

        <section class="workshop-panel">

            <div class="workshop-panel-header">
                <div>
                    <span>Procedimentos</span>
                    <h2>Últimas regras</h2>
                </div>

                <a href="{{ route('procedures.index') }}">
                    Abrir
                    <i data-lucide="arrow-right"></i>
                </a>
            </div>

            @if($proceduresPreview->count())
                <div class="workshop-mini-list">
                    @foreach($proceduresPreview as $procedure)
                        <div class="workshop-mini-row">
                            <div>
                                <strong>{{ $procedure->name ?? $procedure->title ?? 'Procedimento' }}</strong>
                                <span>
                                    Regra operacional cadastrada
                                </span>
                            </div>

                            <small>ativo</small>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="workshop-mini-empty">
                    Nenhum procedimento cadastrado.
                </div>
            @endif

        </section>

    </div>

</div>

@endsection
