@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/pages/operations.css') }}?v=1">
@endpush

@section('content')

<div class="operations-page">

    <div class="operations-header">
        <div>
            <span class="operations-kicker">Operacional</span>

            <h1>Controle de Operações</h1>

            <p>
                Acompanhe veículos em operação, motoristas responsáveis, horários de início e encerramento.
            </p>
        </div>
    </div>

    <div class="operations-summary-grid">

        <div class="operations-summary-card">
            <div class="operations-summary-icon active">
                <i data-lucide="radio-tower"></i>
            </div>

            <div>
                <span>Em operação</span>
                <strong>{{ $openCount }}</strong>
                <p>Veículos rodando agora</p>
            </div>
        </div>

        <div class="operations-summary-card">
            <div class="operations-summary-icon">
                <i data-lucide="users"></i>
            </div>

            <div>
                <span>Motoristas</span>
                <strong>{{ $driversInOperationCount }}</strong>
                <p>Com operação aberta</p>
            </div>
        </div>

        <div class="operations-summary-card">
            <div class="operations-summary-icon">
                <i data-lucide="check-circle-2"></i>
            </div>

            <div>
                <span>Encerradas hoje</span>
                <strong>{{ $closedTodayCount }}</strong>
                <p>Operações finalizadas</p>
            </div>
        </div>

    </div>

    <div class="operations-tabs">
        <a href="{{ route('operations.index', ['status' => 'open']) }}" class="{{ $status === 'open' ? 'active' : '' }}">
            Em operação
        </a>

        <a href="{{ route('operations.index', ['status' => 'closed']) }}" class="{{ $status === 'closed' ? 'active' : '' }}">
            Encerradas
        </a>

        <a href="{{ route('operations.index', ['status' => 'all']) }}" class="{{ $status === 'all' ? 'active' : '' }}">
            Todas
        </a>
    </div>

    <div class="operations-panel">

        @forelse($operations as $operation)

            <div class="operation-row">

                <div class="operation-vehicle">
                    <div class="operation-vehicle-icon">
                        <i data-lucide="truck"></i>
                    </div>

                    <div>
                        <strong>{{ $operation->vehicle->plate ?? 'Sem placa' }}</strong>
                        <span>{{ $operation->vehicle->name ?? 'Veículo' }}</span>
                    </div>
                </div>

                <div class="operation-info">
                    <span>Motorista</span>
                    <strong>{{ $operation->driver->name ?? 'Não informado' }}</strong>
                </div>

                <div class="operation-info">
                    <span>Início informado</span>
                    <strong>{{ $operation->start_datetime_reported?->format('d/m/Y H:i') }}</strong>
                </div>

                <div class="operation-info">
                    <span>KM inicial</span>
                    <strong>{{ number_format($operation->start_vehicle_km ?? 0, 0, ',', '.') }}</strong>
                </div>

                <div class="operation-info">
                    <span>Status</span>

                    @if($operation->status === 'open')
                        <strong class="operation-badge open">Em operação</strong>
                    @else
                        <strong class="operation-badge closed">Encerrada</strong>
                    @endif
                </div>

                <div class="operation-actions">
                    @if($operation->status === 'open')
                        <a href="{{ route('operations.close', $operation->id) }}" class="operation-action-btn primary">
                            Encerrar
                        </a>
                    @else
                        <span class="operation-closed-at">
                            {{ $operation->end_datetime_reported?->format('d/m/Y H:i') }}
                        </span>
                    @endif
                </div>

            </div>

        @empty

            <div class="operations-empty">
                <div>
                    <i data-lucide="radio-tower"></i>
                </div>

                <strong>Nenhuma operação encontrada</strong>

                <p>
                    Quando um motorista iniciar operação com um veículo, o registro aparecerá aqui.
                </p>
            </div>

        @endforelse

    </div>

    <div>
        {{ $operations->links() }}
    </div>

</div>

@endsection