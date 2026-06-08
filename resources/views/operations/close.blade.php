@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/pages/operations.css') }}?v=1">
@endpush

@section('content')

<div class="operations-page">

    <div class="operation-form-header">
        <div>
            <span class="operations-kicker">Encerrar Operação</span>

            <h1>{{ $operation->vehicle->plate ?? 'Veículo' }}</h1>

            <p>
                Informe os dados finais para encerrar a operação iniciada por {{ $operation->driver->name ?? 'motorista não identificado' }}.
            </p>
        </div>

        <a href="{{ route('operations.index') }}" class="chm-page-button secondary">
            <i data-lucide="arrow-left"></i>
            Voltar para operações
        </a>
    </div>

    <div class="operation-current-box">
        <div>
            <span>Início informado</span>
            <strong>{{ $operation->start_datetime_reported?->format('d/m/Y H:i') }}</strong>
        </div>

        <div>
            <span>KM inicial</span>
            <strong>{{ number_format($operation->start_vehicle_km ?? 0, 0, ',', '.') }}</strong>
        </div>

        <div>
            <span>HR inicial</span>
            <strong>{{ $operation->start_vehicle_hours ?? '-' }}</strong>
        </div>
    </div>

    <form method="POST" action="{{ route('operations.finish', $operation->id) }}" class="operation-form-card">
        @csrf
        @method('PUT')

        <div class="operation-form-grid">

            <div class="form-group">
                <label>KM final</label>
                <input
                    type="number"
                    step="0.01"
                    name="end_vehicle_km"
                    value="{{ old('end_vehicle_km') }}"
                    required
                >
                @error('end_vehicle_km')
                    <small>{{ $message }}</small>
                @enderror
            </div>

            <div class="form-group">
                <label>Horímetro final</label>
                <input
                    type="number"
                    step="0.01"
                    name="end_vehicle_hours"
                    value="{{ old('end_vehicle_hours') }}"
                >
                @error('end_vehicle_hours')
                    <small>{{ $message }}</small>
                @enderror
            </div>

            <div class="form-group">
                <label>Data/hora de fim</label>
                <input
                    type="datetime-local"
                    name="end_datetime_reported"
                    value="{{ old('end_datetime_reported', now()->format('Y-m-d\TH:i')) }}"
                    max="{{ now()->format('Y-m-d\TH:i') }}"
                    required
                >
                @error('end_datetime_reported')
                    <small>{{ $message }}</small>
                @enderror
            </div>

        </div>

        <div class="form-group">
            <label>Observações de encerramento</label>
            <textarea
                name="end_observation"
                rows="4"
                placeholder="Ex: operação finalizada sem ocorrência, abastecimento pendente, avaria observada..."
            >{{ old('end_observation') }}</textarea>
        </div>

        <div class="operation-delay-box">
            <div>
                <strong>Fechamento fora do horário</strong>
                <p>
                    Se a data/hora informada tiver diferença superior a 15 minutos em relação ao horário atual do sistema, será obrigatório justificar.
                </p>
            </div>

            <div class="operation-form-grid">

                <div class="form-group">
                    <label>Motivo</label>
                    <select name="end_delay_reason">
                        <option value="">Selecione se necessário</option>

                        @foreach($delayReasons as $value => $label)
                            <option value="{{ $value }}" @selected(old('end_delay_reason') === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>

                    @error('end_delay_reason')
                        <small>{{ $message }}</small>
                    @enderror
                </div>

                <div class="form-group">
                    <label>Justificativa</label>
                    <input
                        type="text"
                        name="end_delay_justification"
                        value="{{ old('end_delay_justification') }}"
                        placeholder="Descreva a autorização ou o motivo operacional"
                    >

                    @error('end_delay_justification')
                        <small>{{ $message }}</small>
                    @enderror
                </div>

            </div>
        </div>

        <div class="operation-form-footer">
            <a href="{{ route('operations.index') }}" class="chm-page-button secondary">
                Cancelar
            </a>

            <button type="submit" class="chm-page-button primary">
                <i data-lucide="check-circle-2"></i>
                Encerrar operação
            </button>
        </div>

    </form>

</div>

@endsection