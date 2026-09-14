@php
    $kmEnabled = (bool) old(
        'km_control_enabled',
        isset($vehicle) ? $vehicle->km_control_enabled : true
    );

    $hoursEnabled = (bool) old(
        'hours_control_enabled',
        isset($vehicle) ? $vehicle->hours_control_enabled : false
    );

    $tiresEnabled = (bool) old(
        'tire_control_enabled',
        isset($vehicle) ? $vehicle->tire_control_enabled : true
    );
@endphp

<section class="vehicle-operational-controls">

    <div class="vehicle-operational-controls__header">
        <div>
            <h3>Controles operacionais</h3>

            <p>
                Defina quais controles e alertas serão utilizados para este veículo.
            </p>
        </div>
    </div>

    <div class="vehicle-operational-controls__grid">

        {{-- KM --}}
        <label class="operational-control-card">

            <input
                type="hidden"
                name="km_control_enabled"
                value="0"
            >

            <input
                type="checkbox"
                name="km_control_enabled"
                value="1"
                @checked($kmEnabled)
            >

            <span
                class="operational-control-card__check"
                aria-hidden="true"
            >
                <i class="bi bi-check-lg"></i>
            </span>

            <span class="operational-control-card__content">
                <strong>Controle por KM</strong>

                <small>
                    Usar hodômetro para atualizações operacionais,
                    alertas e indicadores do veículo.
                </small>
            </span>

        </label>


        {{-- HORÍMETRO --}}
        <label class="operational-control-card">

            <input
                type="hidden"
                name="hours_control_enabled"
                value="0"
            >

            <input
                type="checkbox"
                name="hours_control_enabled"
                value="1"
                @checked($hoursEnabled)
            >

            <span
                class="operational-control-card__check"
                aria-hidden="true"
            >
                <i class="bi bi-check-lg"></i>
            </span>

            <span class="operational-control-card__content">
                <strong>Controle por Horímetro</strong>

                <small>
                    Usar horas de operação para máquinas e equipamentos
                    controlados por horímetro.
                </small>
            </span>

        </label>


        {{-- PNEUS --}}
        <label class="operational-control-card">

            <input
                type="hidden"
                name="tire_control_enabled"
                value="0"
            >

            <input
                type="checkbox"
                name="tire_control_enabled"
                value="1"
                @checked($tiresEnabled)
            >

            <span
                class="operational-control-card__check"
                aria-hidden="true"
            >
                <i class="bi bi-check-lg"></i>
            </span>

            <span class="operational-control-card__content">
                <strong>Controle de Pneus</strong>

                <small>
                    Habilitar posições, medições de sulco e alertas
                    de pneus para este veículo.
                </small>
            </span>

        </label>

    </div>

    <div class="vehicle-operational-controls__grid">
        <label class="operational-control-card">
            <span class="operational-control-card__content">
                <strong>Confiabilidade do hodômetro</strong>
                <select name="km_meter_status" class="form-input">
                    <option value="normal" @selected(old('km_meter_status', $vehicle->km_meter_status ?? 'normal') === 'normal')>Normal</option>
                    <option value="faulty" @selected(old('km_meter_status', $vehicle->km_meter_status ?? 'normal') === 'faulty')>Com defeito</option>
                    <option value="unreliable" @selected(old('km_meter_status', $vehicle->km_meter_status ?? 'normal') === 'unreliable')>Não confiável</option>
                </select>
            </span>
        </label>
        <label class="operational-control-card">
            <span class="operational-control-card__content">
                <strong>Confiabilidade do horímetro</strong>
                <select name="hours_meter_status" class="form-input">
                    <option value="normal" @selected(old('hours_meter_status', $vehicle->hours_meter_status ?? 'normal') === 'normal')>Normal</option>
                    <option value="faulty" @selected(old('hours_meter_status', $vehicle->hours_meter_status ?? 'normal') === 'faulty')>Com defeito</option>
                    <option value="unreliable" @selected(old('hours_meter_status', $vehicle->hours_meter_status ?? 'normal') === 'unreliable')>Não confiável</option>
                </select>
            </span>
        </label>
    </div>

</section>
