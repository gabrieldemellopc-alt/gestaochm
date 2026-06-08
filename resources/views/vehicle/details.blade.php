@extends('layouts.app')

@php
    $pageTitle = 'Veículo';
    $pageSubtitle = $vehicle->plate . ' · ' . $vehicle->name;
@endphp

@push('styles')
<link
    rel="stylesheet"
    href="{{ asset('css/pages/vehicle-center.css') }}?v=2"
>
@endpush

@section('content')

<div class="vehicle-details-page">

    @if(session('success'))
        <div class="vehicle-details-alert success">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="vehicle-details-alert danger">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- HERO --}}
    <div class="vehicle-details-hero">

        <div class="vehicle-center-identity">

            <div class="vehicle-center-icon">

                <img
                    src="{{ asset('images/' . ($vehicle->type_icon ?? 'lixo.png')) }}"
                    alt="Veículo"
                >

            </div>

            <div>

                <div class="vehicle-center-title-row">

                    <h2>
                        {{ $vehicle->name }}
                    </h2>

                    <span
                        class="vehicle-center-status {{
                            $vehicle->operational_status === 'maintenance'
                                ? 'maintenance'
                                : (
                                    $vehicle->status === 'inactive'
                                        ? 'inactive'
                                        : 'operational'
                                )
                        }}"
                    >
                        @if($vehicle->operational_status === 'maintenance')
                            <i data-lucide="wrench"></i>
                            Em manutenção
                        @elseif($vehicle->status === 'inactive')
                            <i data-lucide="circle-off"></i>
                            Inativo
                        @else
                            <i data-lucide="check-circle"></i>
                            Operacional
                        @endif
                    </span>

                </div>

                <div class="vehicle-center-meta">

                    <span>{{ $vehicle->plate }}</span>

                    <span>•</span>

                    <span>{{ $vehicle->brand ?? 'Sem marca' }}</span>

                    @if($vehicle->year)
                        <span>• {{ $vehicle->year }}</span>
                    @endif

                    @if($vehicle->currentAllocation?->location)
                        <span>• {{ $vehicle->currentAllocation->location->name }}</span>
                    @endif

                </div>

            </div>

        </div>

        <div class="vehicle-details-hero-actions">

            <a
                href="{{ route('vehicles.index') }}"
                class="vehicle-center-action"
            >
                <i data-lucide="arrow-left"></i>
                <span>Veículos</span>
            </a>

            <a
                href="{{ route('vehicles.edit', $vehicle) }}"
                class="vehicle-center-action"
            >
                <i data-lucide="pencil"></i>
                <span>Editar</span>
            </a>

        </div>

    </div>

    {{-- RESUMO --}}
    <div class="vehicle-center-summary-line">

        <div class="vehicle-center-summary-item">
            <span>Hodômetro:</span>

            <strong>
                {{ number_format($vehicle->current_km ?? 0, 0, ',', '.') }} KM
            </strong>
        </div>

        <div class="vehicle-center-summary-divider"></div>

        <div class="vehicle-center-summary-item">
            <span>Horímetro:</span>

            <strong>
                {{ $vehicle->current_hours ?? 0 }} Horas
            </strong>
        </div>

        <div class="vehicle-center-summary-divider"></div>

        <div class="vehicle-center-summary-item">
            <span>Alertas:</span>

            <strong>
                {{ count($vehicle->alerts ?? []) }} ativo(s)
            </strong>
        </div>

        <div class="vehicle-center-summary-divider"></div>

        <div
            class="vehicle-center-summary-item status {{
                $vehicle->alert_status === 'danger'
                    ? 'danger'
                    : (
                        $vehicle->alert_status === 'warning'
                            ? 'warning'
                            : 'success'
                    )
            }}"
        >
            <span>Status:</span>

            <strong>
                @if($vehicle->alert_status === 'danger')
                    Crítico
                @elseif($vehicle->alert_status === 'warning')
                    Atenção
                @else
                    OK
                @endif
            </strong>
        </div>

    </div>
    {{-- AÇÕES --}}
    <section class="vehicle-center-card">
    
                    <div class="vehicle-center-card-header">
    
                        <div>
                            <small>Ações</small>
    
                            <h3>Central do veículo</h3>
                        </div>
    
                        <i data-lucide="zap"></i>
    
                    </div>
    
                    {{-- ATALHOS DO VEÍCULO --}}
                    <div class="vehicle-details-actions-strip">
                    
                        <a
                            href="{{ route('vehicle.maintenance.index', $vehicle) }}"
                            class="vehicle-center-action primary"
                        >
                            <i data-lucide="wrench"></i>
                            <span>Lançar manutenção</span>
                        </a>
                    
                        <a
                            href="{{ route('vehicles.history', $vehicle) }}"
                            class="vehicle-center-action"
                        >
                            <i data-lucide="history"></i>
                            <span>Histórico completo</span>
                        </a>
                    
                        <a
                            href="{{ route('vehicles.tires.index', $vehicle) }}"
                            class="vehicle-center-action"
                        >
                            <i data-lucide="circle-dot"></i>
                            <span>Controle de pneus</span>
                        </a>
                    
                        <a
                            href="{{ route('vehicles.edit', $vehicle) }}"
                            class="vehicle-center-action"
                        >
                            <i data-lucide="pencil"></i>
                            <span>Editar veículo</span>
                        </a>
                        @php
                            $openOperation = \App\Models\VehicleOperation::where('vehicle_id', $vehicle->id)
                                ->where('status', 'open')
                                ->first();
                        @endphp
                        
                        @if($openOperation)
                            <a href="{{ route('operations.close', $openOperation->id) }}" class="vehicle-center-action-card">
                                <i data-lucide="square"></i>
                                <span>Encerrar operação</span>
                            </a>
                        @else
                            <a href="{{ route('vehicles.operations.start', $vehicle->id) }}" class="vehicle-center-action-card">
                                <i data-lucide="play"></i>
                                <span>Iniciar operação</span>
                            </a>
                        @endif
                    
                    </div>
    
                </section>

    {{-- BODY --}}
    <div class="vehicle-center-body vehicle-details-body">

        {{-- COLUNA ESQUERDA --}}
        <div class="vehicle-center-left">

            {{-- ATUALIZAÇÃO OPERACIONAL --}}
            <section class="vehicle-center-card">

                <div class="vehicle-center-card-header">

                    <div>
                        <small>Atualização rápida</small>

                        <h3>KM e Horímetro</h3>
                    </div>

                    <i data-lucide="gauge"></i>

                </div>

                <div class="vehicle-center-fields">

                    <form
                        method="POST"
                        action="{{ route('vehicles.update-km', $vehicle) }}"
                        class="vehicle-center-field"
                    >
                        @csrf

                        <label>Hodômetro atual</label>

                        <div class="vehicle-center-input-row">

                            <input
                                type="number"
                                name="km"
                                value="{{ $vehicle->current_km ?? 0 }}"
                                min="{{ $vehicle->current_km ?? 0 }}"
                                step="1"
                            >

                            <span>KM</span>

                            <button type="submit">
                                Atualizar
                            </button>

                        </div>

                    </form>

                    <form
                        method="POST"
                        action="{{ route('vehicles.update-hours', $vehicle) }}"
                        class="vehicle-center-field"
                    >
                        @csrf

                        <label>Horímetro atual</label>

                        <div class="vehicle-center-input-row">

                            <input
                                type="number"
                                name="hours"
                                value="{{ $vehicle->current_hours ?? 0 }}"
                                min="{{ $vehicle->current_hours ?? 0 }}"
                                step="0.1"
                            >

                            <span>H</span>

                            <button type="submit">
                                Atualizar
                            </button>

                        </div>

                    </form>

                </div>

            </section>

            {{-- STATUS OPERACIONAL --}}
            <section class="vehicle-center-card">

                <div class="vehicle-center-card-header">

                    <div>
                        <small>Situação do veículo</small>

                        <h3>Status operacional</h3>
                    </div>

                    <i data-lucide="activity"></i>

                </div>

                <form
                    method="POST"
                    action="{{ route('vehicles.operational-status.update', $vehicle) }}"
                    class="vehicle-center-status-form"
                >
                    @csrf

                    <select name="operational_status">

                        <option
                            value="operational"
                            @selected($vehicle->operational_status === 'operational')
                        >
                            Operacional
                        </option>

                        <option
                            value="maintenance"
                            @selected($vehicle->operational_status === 'maintenance')
                        >
                            Em manutenção
                        </option>

                    </select>

                    <button type="submit">
                        Salvar status
                    </button>

                </form>

            </section>

            
        </div>

        {{-- COLUNA DIREITA --}}
        <div class="vehicle-center-right">

            {{-- ALERTAS --}}
            <section class="vehicle-center-card">

                <div class="vehicle-center-card-header">

                    <div>
                        <small>Monitoramento</small>

                        <h3>Alertas do veículo</h3>
                    </div>

                    <i data-lucide="triangle-alert"></i>

                </div>

                @if(empty($vehicle->alerts))

                    <div class="vehicle-center-empty">

                        <i data-lucide="check-circle"></i>

                        <strong>Nenhum alerta ativo</strong>

                        <p>
                            Este veículo não possui pendências no momento.
                        </p>

                    </div>

                @else

                    <div class="vehicle-center-alert-list">

                        @foreach($vehicle->alerts as $alert)

                            <div
                                class="vehicle-center-alert {{ $alert['status'] ?? 'warning' }}"
                            >

                                <i data-lucide="triangle-alert"></i>

                                <div>
                                    <strong>
                                        {{ $alert['message'] ?? 'Alerta operacional' }}
                                    </strong>

                                    <small>
                                        {{ $alert['procedure'] ?? 'Alerta operacional' }}
                                    </small>
                                </div>

                            </div>

                        @endforeach

                    </div>

                @endif

            </section>

            <div class="vehicle-center-right-split">

                {{-- ÚLTIMAS ATUALIZAÇÕES --}}
                <section class="vehicle-center-card">

                    <div class="vehicle-center-card-header">

                        <div>
                            <small>Histórico recente</small>

                            <h3>Atualizações operacionais</h3>
                        </div>

                        <i data-lucide="clock-3"></i>

                    </div>

                    @if($vehicle->updateLogs->isEmpty())

                        <div class="vehicle-center-empty">

                            <i data-lucide="inbox"></i>

                            <strong>Nenhuma atualização registrada</strong>

                            <p>
                                Alterações de KM, HR e status aparecerão aqui.
                            </p>

                        </div>

                    @else

                        <div class="vehicle-center-timeline">

                            @foreach($vehicle->updateLogs->take(6) as $log)

                                <div class="vehicle-center-log">

                                    <div class="log-dot"></div>

                                    <div>
                                        <strong>
                                            @if($log->type === 'km')
                                                Hodômetro atualizado
                                            @elseif($log->type === 'hours')
                                                Horímetro atualizado
                                            @else
                                                Atualização operacional
                                            @endif
                                        </strong>

                                        <p>
                                            {{ $log->old_value ?? '--' }}
                                            ➔
                                            {{ $log->new_value }}
                                        </p>

                                        <small>
                                            {{ optional($log->created_at)->format('d/m/Y H:i') }}
                                        </small>
                                    </div>

                                </div>

                            @endforeach

                        </div>

                    @endif

                </section>

                {{-- ÚLTIMAS MANUTENÇÕES --}}
                <section class="vehicle-center-card">

                    <div class="vehicle-center-card-header">

                        <div>
                            <small>Manutenção</small>

                            <h3>Últimos registros</h3>
                        </div>

                        <i data-lucide="wrench"></i>

                    </div>

                    @if($vehicle->maintenances->isEmpty())

                        <div class="vehicle-center-empty">

                            <i data-lucide="clipboard-x"></i>

                            <strong>Nenhuma manutenção registrada</strong>

                            <p>
                                Os lançamentos de manutenção aparecerão aqui.
                            </p>

                        </div>

                    @else

                        <div class="vehicle-center-maintenance-list">

                            @foreach($vehicle->maintenances->take(6) as $maintenance)

                                <div class="vehicle-center-maintenance">

                                    <div>
                                        <strong>
                                            {{ $maintenance->procedure?->name ?? 'Procedimento' }}
                                        </strong>

                                        <small>
                                            {{ $maintenance->reason ?? 'Preventiva' }}
                                        </small>
                                    </div>

                                    <span>
                                        {{ optional($maintenance->performed_at)->format('d/m/Y') ?? '--' }}
                                    </span>

                                </div>

                            @endforeach

                        </div>

                    @endif

                </section>

            </div>

        </div>

    </div>

</div>

@endsection
