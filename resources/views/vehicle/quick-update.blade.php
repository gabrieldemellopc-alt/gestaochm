@extends('layouts.app')

@php
    $totalVehicles = $vehicles->count();

    $kmOutdatedCount = $vehicles
        ->filter(function ($vehicle) {
            return collect($vehicle->operational_update_alerts ?? [])
                ->contains(function ($alert) {
                    return str_contains(
                        mb_strtolower($alert['message'] ?? ''),
                        'km'
                    );
                });
        })
        ->count();

    $hoursOutdatedCount = $vehicles
        ->filter(function ($vehicle) {
            return collect($vehicle->operational_update_alerts ?? [])
                ->contains(function ($alert) {
                    $message = mb_strtolower($alert['message'] ?? '');

                    return str_contains($message, 'horímetro')
                        || str_contains($message, 'horimetro')
                        || str_contains($message, 'hr');
                });
        })
        ->count();
@endphp

@push('styles')
<link rel="stylesheet" href="{{ asset('css/pages/dashboard.css') }}">
<link rel="stylesheet" href="{{ asset('css/pages/quick-update.css') }}?v=1">
@endpush

@section('content')
<div class="quick-update-page quick-update-page-v2">
    <div class="quick-update-header quick-update-header-v2">
        <div class="quick-update-heading">
            <span class="quick-update-kicker">
                <i data-lucide="activity"></i>
                Operacional
            </span>

            <h1>Atualização rápida de KM/Horímetro</h1>

            <p>
                Atualize leituras operacionais da frota em massa, mantendo o controle
                de KM e horas de trabalho sempre confiável.
            </p>
        </div>

        <a href="{{ route('dashboard') }}" class="quick-update-back">
            <i data-lucide="arrow-left"></i>
            Voltar ao dashboard
        </a>
    </div>

    @if(session('success'))
        <div class="quick-update-alert success">
            <i data-lucide="check-circle"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="quick-update-alert danger">
            <i data-lucide="triangle-alert"></i>
            <span>
                Verifique os campos informados. Não é permitido lançar valores negativos
                ou menores que a leitura atual.
            </span>
        </div>
    @endif

    <div class="quick-update-summary">
        <div class="quick-summary-card">
            <span>Total listado</span>
            <strong>{{ $totalVehicles }}</strong>
            <small>veículo(s) da unidade ativa</small>
        </div>

        <div class="quick-summary-card warning">
            <span>KM desatualizado</span>
            <strong>{{ $kmOutdatedCount }}</strong>
            <small>com alerta operacional</small>
        </div>

        <div class="quick-summary-card info">
            <span>Horímetro desatualizado</span>
            <strong>{{ $hoursOutdatedCount }}</strong>
            <small>com alerta operacional</small>
        </div>
    </div>

    <form
        method="POST"
        action="{{ route('vehicle.quick-update.store') }}"
        onsubmit="return confirmBulkOperationalUpdate(this);"
    >
        @csrf

        <div class="quick-update-card quick-update-card-v2">
            <div class="quick-update-card-header quick-update-card-header-v2">
                <div>
                    <span>Leituras operacionais</span>
                    <h2>Veículos</h2>
                    <p>Altere somente os campos necessários. Valores menores que os atuais permanecem bloqueados pelas validações existentes.</p>
                </div>

                <button type="submit" class="quick-update-save">
                    <i data-lucide="save"></i>
                    Salvar atualizações
                </button>
            </div>

            <div class="quick-update-table-wrap quick-update-list-wrap">
                <table class="quick-update-table quick-update-table-v2">
                    <thead>
                        <tr>
                            <th>Veículo</th>
                            <th>Placa</th>
                            <th>KM atual</th>
                            <th>Horímetro atual</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($vehicles as $index => $vehicle)
                            <tr
                                class="{{
                                    isset($vehicle->operational_update_alerts)
                                    && $vehicle->operational_update_alerts->count()
                                        ? 'quick-row-has-alert'
                                        : ''
                                }}"
                            >
                                <td>
                                    <input
                                        type="hidden"
                                        name="vehicles[{{ $index }}][id]"
                                        value="{{ $vehicle->id }}"
                                    >

                                    <div class="quick-vehicle-main quick-vehicle-main-v2">
                                        <div class="quick-vehicle-icon">
                                            @if(!empty($vehicle->type_icon))
                                                <img
                                                    src="{{ asset('images/' . $vehicle->type_icon) }}"
                                                    alt="Veículo"
                                                >
                                            @else
                                                <i data-lucide="truck"></i>
                                            @endif
                                        </div>

                                        <div class="quick-vehicle-text">
                                            <strong>{{ $vehicle->name ?? $vehicle->model ?? 'Veículo' }}</strong>

                                            <small>
                                                {{ collect([$vehicle->brand, $vehicle->model, $vehicle->year])->filter()->join(' • ') ?: 'Sem modelo informado' }}
                                            </small>

                                            @if(
                                                isset($vehicle->operational_update_alerts)
                                                && $vehicle->operational_update_alerts->count()
                                            )
                                                <div class="quick-update-tags">
                                                    @foreach($vehicle->operational_update_alerts as $alert)
                                                        <span class="quick-update-tag {{ $alert['status'] }}">
                                                            <i data-lucide="triangle-alert"></i>
                                                            {{ $alert['message'] }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <span class="quick-plate">{{ $vehicle->plate ?? '-' }}</span>
                                </td>

                                <td>
                                    <label class="quick-input-label">
                                        <span>KM</span>

                                        <div class="quick-input-wrap">
                                            <input
                                                type="number"
                                                step="1"
                                                min="{{ $vehicle->current_km ?? 0 }}"
                                                name="vehicles[{{ $index }}][current_km]"
                                                class="quick-input"
                                                value="{{ $vehicle->current_km }}"
                                                data-original="{{ $vehicle->current_km ?? 0 }}"
                                                data-type="km"
                                                data-vehicle="{{ $vehicle->plate ?? $vehicle->name }}"
                                            >

                                            <span>km</span>
                                        </div>
                                    </label>
                                </td>

                                <td>
                                    <label class="quick-input-label">
                                        <span>Horas</span>

                                        <div class="quick-input-wrap">
                                            <input
                                                type="number"
                                                step="1"
                                                min="{{ $vehicle->current_hours ?? 0 }}"
                                                name="vehicles[{{ $index }}][current_hours]"
                                                class="quick-input"
                                                value="{{ $vehicle->current_hours }}"
                                                data-original="{{ $vehicle->current_hours ?? 0 }}"
                                                data-type="hours"
                                                data-vehicle="{{ $vehicle->plate ?? $vehicle->name }}"
                                            >

                                            <span>h</span>
                                        </div>
                                    </label>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <div class="quick-empty">
                                        <i data-lucide="inbox"></i>

                                        <strong>Nenhum veículo cadastrado</strong>

                                        <p>Cadastre veículos para utilizar a atualização rápida.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($vehicles->count())
                <div class="quick-update-footer quick-update-footer-v2">
                    <div>
                        <strong>Pronto para salvar?</strong>
                        <span>As alterações serão registradas no histórico de KM/Horímetro dos veículos.</span>
                    </div>

                    <button type="submit" class="quick-update-save large">
                        <i data-lucide="save"></i>
                        Salvar atualizações
                    </button>
                </div>
            @endif
        </div>
    </form>
</div>

<script>
    function confirmBulkOperationalUpdate(form) {
        const suspiciousChanges = [];

        form.querySelectorAll('.quick-input').forEach((input) => {
            const original = Number(input.dataset.original || 0);
            const current = Number(input.value || 0);
            const diff = current - original;

            if (diff <= 0) {
                return;
            }

            if (input.dataset.type === 'km' && diff > 1000) {
                suspiciousChanges.push(
                    `${input.dataset.vehicle}: +${diff.toLocaleString('pt-BR')} km`
                );
            }

            if (input.dataset.type === 'hours' && diff > 24) {
                suspiciousChanges.push(
                    `${input.dataset.vehicle}: +${diff.toLocaleString('pt-BR')} h`
                );
            }
        });

        if (! suspiciousChanges.length) {
            return true;
        }

        return confirm(
            'Atenção: foram encontradas atualizações operacionais altas:\n\n' +
            suspiciousChanges.join('\n') +
            '\n\nDeseja confirmar o envio dessas alterações?'
        );
    }
</script>
@endsection
