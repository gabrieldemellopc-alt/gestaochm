@extends('layouts.app')

@push('styles')
    <link
        rel="stylesheet"
        href="{{ asset('css/pages/fuel.css') }}?v=3"
    >
@endpush

@section('content')

<div class="fuel-photo-import-page">

<div
    id="fuelPhotoImportModal"
    class="fuel-photo-ai-page-shell"
    data-standalone="1"
>
    <div
        class="fuel-photo-ai-modal"
        aria-labelledby="fuelPhotoAiTitle"
    >

        <div class="fuel-photo-ai-header">

            <div>
                <span class="fuel-photo-ai-kicker">
                    LEITURA ASSISTIDA
                </span>

                <h2 id="fuelPhotoAiTitle">
                    Importar abastecimentos via foto
                </h2>

                <p>
                    O Google Vision fará a leitura da ficha.
                    Revise todos os campos antes de qualquer lançamento.
                </p>
            </div>

            <a
                href="{{ route('fuel.tanks.index') }}"
                class="fuel-photo-ai-page-back"
                title="Voltar para Combustíveis"
            >
                <i class="bi bi-arrow-left"></i>
                <span>Voltar para Combustíveis</span>
            </a>

        </div>


        <div class="fuel-photo-ai-body">

            <section class="fuel-photo-ai-upload">

                <div class="fuel-photo-ai-upload-left">

                    <label
                        for="fuelPhotoAiFile"
                        class="fuel-photo-ai-drop"
                    >
                        <i class="bi bi-image"></i>

                        <span>
                            <strong>
                                Selecionar foto da ficha
                            </strong>

                            <small>
                                JPG, PNG ou PDF · até 12 MB
                            </small>
                        </span>
                    </label>

                    <input
                        id="fuelPhotoAiFile"
                        type="file"
                        accept="image/jpeg,image/png,application/pdf,.pdf"
                        hidden
                    >

                    <div
                        id="fuelPhotoAiFileInfo"
                        class="fuel-photo-ai-file-info"
                        hidden
                    ></div>

                </div>


                <div class="fuel-photo-ai-preview-wrap">

                    <img
                        id="fuelPhotoAiPreview"
                        class="fuel-photo-ai-preview"
                        alt="Prévia da ficha"
                        hidden
                    >

                    <div
                        id="fuelPhotoAiPreviewEmpty"
                        class="fuel-photo-ai-preview-empty"
                    >
                        <i class="bi bi-file-earmark-image"></i>
                        <span>Prévia da folha</span>
                    </div>

                </div>

            </section>


            <div class="fuel-photo-ai-analysis-bar">

                <div>
                    <strong>
                        Unidade:
                        {{ $activeLocation->name ?? '—' }}
                    </strong>

                    <span>
                        A imagem não gera lançamentos automaticamente.
                    </span>
                </div>

                <button
                    id="fuelPhotoAiAnalyze"
                    type="button"
                    class="fuel-primary-action"
                    onclick="analyzeFuelPhoto()"
                    disabled
                >
                    <i class="bi bi-stars"></i>
                    Analisar imagem
                </button>

            </div>





            <div
                id="fuelPhotoAiLoading"
                class="fuel-photo-ai-loading"
                hidden
            >
                <div class="fuel-photo-ai-loading-head">
                    <div class="fuel-photo-ai-loading-icon">
                        <span></span>
                    </div>

                    <div>
                        <strong id="fuelPhotoAiLoadingTitle">
                            Analisando ficha...
                        </strong>

                        <span id="fuelPhotoAiLoadingMessage">
                            Preparando o arquivo para leitura.
                        </span>
                    </div>

                    <div
                        id="fuelPhotoAiLoadingTime"
                        class="fuel-photo-ai-loading-time"
                    >
                        00:00
                    </div>
                </div>

                <div class="fuel-photo-ai-loading-track">
                    <div class="fuel-photo-ai-loading-progress"></div>
                </div>

                <div class="fuel-photo-ai-loading-foot">
                    <span>
                        <i class="bi bi-stars"></i>
                        Leitura assistida em andamento
                    </span>

                    <small id="fuelPhotoAiLoadingHint">
                        Não feche esta janela enquanto a ficha estiver sendo analisada.
                    </small>
                </div>
            </div>


            <section
                id="fuelPhotoAiResult"
                class="fuel-photo-ai-result"
                hidden
            >

                <div class="fuel-photo-ai-operation-fields">

                    <label class="fuel-photo-ai-operation-field">
                        <span>Data dos abastecimentos</span>

                        <input
                            type="date"
                            id="fuelPhotoAiOperationDate"
                            class="fuel-photo-ai-meta-input"
                        >
                    </label>

                    <label class="fuel-photo-ai-operation-field">
                        <span>Início</span>

                        <input
                            type="time"
                            id="fuelPhotoAiStartTime"
                            class="fuel-photo-ai-meta-input"
                            onchange="recalculateFuelPhotoSchedule(true)"
                        >
                    </label>

                    <label class="fuel-photo-ai-operation-field">
                        <span>Fim</span>

                        <input
                            type="time"
                            id="fuelPhotoAiEndTime"
                            class="fuel-photo-ai-meta-input"
                            onchange="recalculateFuelPhotoSchedule(true)"
                        >
                    </label>

                </div>


                <div class="fuel-photo-ai-tank-grid">

                    <div class="fuel-photo-ai-tank-card">

                        <div class="fuel-photo-ai-tank-heading">
                            <div>
                                <span>Origem do combustível</span>
                                <strong>Tanque dos abastecimentos</strong>
                            </div>

                            <i class="bi bi-fuel-pump"></i>
                        </div>

                        <select
                            id="fuelPhotoAiFuelTank"
                            class="fuel-photo-ai-meta-input"
                            onchange="recalculateFuelPhotoStocks()"
                        >
                            <option value="">
                                Selecione o tanque...
                            </option>
                        </select>

                        <div
                            id="fuelPhotoAiFuelStock"
                            class="fuel-photo-ai-stock-summary"
                        >
                            <span>
                                Selecione o tanque de origem.
                            </span>
                        </div>

                    </div>


                    <div
                        id="fuelPhotoAiArlaTankCard"
                        class="fuel-photo-ai-tank-card"
                        hidden
                    >

                        <div class="fuel-photo-ai-tank-heading">
                            <div>
                                <span>ARLA informado na ficha</span>
                                <strong>Tanque de ARLA</strong>
                            </div>

                            <i class="bi bi-droplet"></i>
                        </div>

                        <select
                            id="fuelPhotoAiArlaTank"
                            class="fuel-photo-ai-meta-input"
                            onchange="recalculateFuelPhotoStocks()"
                        >
                            <option value="">
                                Selecione o tanque de ARLA...
                            </option>
                        </select>

                        <div
                            id="fuelPhotoAiArlaStock"
                            class="fuel-photo-ai-stock-summary"
                        >
                            <span>
                                Selecione o tanque de ARLA.
                            </span>
                        </div>

                    </div>

                </div>


                <div class="fuel-photo-ai-summary">

                    <div>
                        <span>Linhas identificadas</span>
                        <strong id="fuelPhotoAiRowCount">—</strong>
                    </div>

                    <div>
                        <span>Soma dos litros</span>
                        <strong id="fuelPhotoAiCalculated">—</strong>
                    </div>

                    <div>
                        <span>Duração do período</span>
                        <strong id="fuelPhotoAiPeriodDuration">—</strong>
                    </div>

                    <div>
                        <span>Intervalo médio</span>
                        <strong id="fuelPhotoAiAverageInterval">—</strong>
                    </div>

                </div>


                <div class="fuel-photo-ai-meta">

                    <span>
                        Operador lido:
                        <strong id="fuelPhotoAiOperator">—</strong>
                    </span>

                    <span>
                        Os horários abaixo são estimados e podem ser corrigidos.
                    </span>

                </div>


                <datalist id="fuelPhotoVehiclePlateList">
                    @foreach($vehicles as $vehicle)
                        @if($vehicle->plate)
                            <option
                                value="{{ $vehicle->plate }}"
                            >
                                {{ $vehicle->name }}
                            </option>
                        @endif
                    @endforeach
                </datalist>

                <div class="fuel-photo-ai-table-wrap">

                    <table class="fuel-photo-ai-table">

                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Horário</th>
                                <th>Veículo</th>
                                <th>Placa lida</th>
                                <th>Litros</th>
                                <th>Último KM</th>
                                <th>Novo KM</th>
                                <th>KM rodado</th>
                                <th>ARLA</th>
                                <th>Situação</th>
                            </tr>
                        </thead>

                        <tbody id="fuelPhotoAiRows"></tbody>

                    </table>

                </div>


                <div
                    id="fuelPhotoAiLaunchConfirmation"
                    class="fuel-photo-ai-launch-confirmation"
                    hidden
                >

                    <div class="fuel-photo-ai-launch-confirmation-head">
                        <div>
                            <span>CONFIRMAÇÃO FINAL</span>
                            <strong>
                                Revise o resumo antes de lançar
                            </strong>
                        </div>

                        <i class="bi bi-shield-check"></i>
                    </div>

                    <div
                        id="fuelPhotoAiLaunchSummary"
                        class="fuel-photo-ai-launch-summary"
                    ></div>

                    <div
                        id="fuelPhotoAiDuplicateWarning"
                        class="fuel-photo-ai-duplicate-warning"
                        hidden
                    ></div>

                    <div class="fuel-photo-ai-launch-warning">
                        <i class="bi bi-exclamation-triangle"></i>

                        <span>
                            Esta operação alterará estoque,
                            histórico de abastecimentos e
                            quilometragem dos veículos.
                        </span>
                    </div>

                    <div class="fuel-photo-ai-launch-actions">

                        <button
                            type="button"
                            class="fuel-secondary-action"
                            onclick="cancelFuelPhotoLaunchConfirmation()"
                        >
                            Voltar à revisão
                        </button>

                        <button
                            type="button"
                            id="fuelPhotoAiLaunchButton"
                            class="fuel-primary-action"
                            onclick="storeFuelPhotoImport()"
                        >
                            <i class="bi bi-check2-circle"></i>

                            <span id="fuelPhotoAiLaunchButtonText">
                                Confirmar e lançar
                            </span>
                        </button>

                    </div>

                </div>


                <div class="fuel-photo-ai-footer">

                    <div class="fuel-photo-ai-footer-feedback">
                    <div
                id="fuelPhotoAiFeedback"
                class="fuel-photo-ai-feedback"
                hidden
            ></div>
                </div>

                <div class="fuel-photo-ai-footer-actions">

                        <button
                            type="button"
                            class="fuel-secondary-action"
                            onclick="resetFuelPhotoImport()"
                        >
                            Limpar
                        </button>

                        <button
                            type="button"
                            class="fuel-primary-action"
                            onclick="validateFuelPhotoReading()"
                        >
                            <i class="bi bi-check2-circle"></i>
                            Validar leitura
                        </button>

                    </div>

                </div>

            </section>

        </div>

    </div>
</div>

</div>

@endsection


<script>

@php
    $fuelPhotoVehiclesPayload = $vehicles
        ->map(function ($vehicle) {
            return [
                'id' => $vehicle->id,
                'name' => $vehicle->name,
                'plate' => $vehicle->plate,
                'current_km' => $vehicle->current_km,
                'current_hours' => $vehicle->current_hours,
                'km_control_enabled' => (bool) $vehicle->km_control_enabled,
                'hours_control_enabled' => (bool) $vehicle->hours_control_enabled,
            ];
        })
        ->values()
        ->all();
@endphp

const fuelPhotoVehicles = @json($fuelPhotoVehiclesPayload);

@php
    $fuelPhotoTanksPayload = $tanks
        ->where('active', true)
        ->map(function ($tank) {
            return [
                'id' => $tank->id,
                'name' => $tank->name,
                'fuel_product_id' => $tank->fuel_product_id,
                'product_name' => $tank->product?->name,
                'product_slug' => $tank->product?->slug,
                'balance_liters' => (float) $tank->current_balance_liters,
                'minimum_balance_liters' => (float) $tank->minimum_balance_liters,
            ];
        })
        ->values()
        ->all();
@endphp

const fuelPhotoTanks = @json($fuelPhotoTanksPayload);

const fuelPhotoAnalyzeUrl =
    @json(route('fuel.photo-import.analyze'));


const fuelPhotoStoreUrl =
    @json(route('fuel.photo-import.store'));


const fuelPhotoDuplicateCheckUrl =
    @json(route('fuel.photo-import.duplicates'));

let fuelPhotoDetectedDuplicates = [];

let fuelPhotoAnalysis = null;



let fuelPhotoLoadingTimer = null;
let fuelPhotoLoadingStartedAt = null;


function fuelPhotoLoadingText(seconds) {
    if (seconds < 8) {
        return {
            message: 'Preparando o arquivo para leitura.',
            hint: 'Aguarde enquanto a imagem é preparada.'
        };
    }

    if (seconds < 25) {
        return {
            message: 'Realizando a leitura assistida da ficha.',
            hint: 'O Google Vision está interpretando os dados da imagem.'
        };
    }

    if (seconds < 55) {
        return {
            message: 'Identificando linhas, veículos e valores.',
            hint: 'Estamos reconstruindo os registros encontrados na ficha.'
        };
    }

    if (seconds < 90) {
        return {
            message: 'Conferindo a estrutura e organizando os dados.',
            hint: 'Fichas maiores ou PDFs podem levar um pouco mais de tempo.'
        };
    }

    return {
        message: 'A leitura continua em andamento.',
        hint: 'PDFs podem levar cerca de 1 a 2 minutos.'
    };
}


function updateFuelPhotoLoading() {
    if (! fuelPhotoLoadingStartedAt) {
        return;
    }

    const elapsed =
        Math.max(
            0,
            Math.floor(
                (Date.now() - fuelPhotoLoadingStartedAt) / 1000
            )
        );

    const minutes =
        String(
            Math.floor(elapsed / 60)
        ).padStart(2, '0');

    const seconds =
        String(
            elapsed % 60
        ).padStart(2, '0');

    const time =
        document.getElementById(
            'fuelPhotoAiLoadingTime'
        );

    const message =
        document.getElementById(
            'fuelPhotoAiLoadingMessage'
        );

    const hint =
        document.getElementById(
            'fuelPhotoAiLoadingHint'
        );

    const copy =
        fuelPhotoLoadingText(elapsed);

    if (time) {
        time.textContent =
            `${minutes}:${seconds}`;
    }

    if (message) {
        message.textContent =
            copy.message;
    }

    if (hint) {
        hint.textContent =
            copy.hint;
    }
}


function startFuelPhotoLoading() {
    const box =
        document.getElementById(
            'fuelPhotoAiLoading'
        );

    if (! box) {
        return;
    }

    fuelPhotoLoadingStartedAt =
        Date.now();

    box.hidden = false;

    updateFuelPhotoLoading();

    if (fuelPhotoLoadingTimer) {
        clearInterval(
            fuelPhotoLoadingTimer
        );
    }

    fuelPhotoLoadingTimer =
        setInterval(
            updateFuelPhotoLoading,
            1000
        );
}


function stopFuelPhotoLoading() {
    const box =
        document.getElementById(
            'fuelPhotoAiLoading'
        );

    if (fuelPhotoLoadingTimer) {
        clearInterval(
            fuelPhotoLoadingTimer
        );

        fuelPhotoLoadingTimer =
            null;
    }

    fuelPhotoLoadingStartedAt =
        null;

    if (box) {
        box.hidden = true;
    }
}


function openFuelPhotoImport() {
    const modal =
        document.getElementById(
            'fuelPhotoImportModal'
        );

    if (!modal) return;

    /*
     * Coloca o overlay diretamente no body para não ficar
     * abaixo de sidebar/header ou preso em stacking contexts.
     */
    if (modal.parentElement !== document.body) {
        document.body.appendChild(
            modal
        );
    }

    modal.hidden = false;

    document.body.classList.add(
        'fuel-photo-ai-modal-open'
    );
}


function closeFuelPhotoImport() {
    const modal =
        document.getElementById(
            'fuelPhotoImportModal'
        );

    if (!modal) return;

    modal.hidden = true;

    document.body.classList.remove(
        'fuel-photo-ai-modal-open'
    );
}


function resetFuelPhotoImport() {
    fuelPhotoAnalysis = null;

    const operationDate =
        document.getElementById(
            'fuelPhotoAiOperationDate'
        );

    const startTime =
        document.getElementById(
            'fuelPhotoAiStartTime'
        );

    const endTime =
        document.getElementById(
            'fuelPhotoAiEndTime'
        );

    if (operationDate) {
        operationDate.value = '';
    }

    if (startTime) {
        startTime.value = '';
    }

    if (endTime) {
        endTime.value = '';
    }

    const file =
        document.getElementById(
            'fuelPhotoAiFile'
        );

    const preview =
        document.getElementById(
            'fuelPhotoAiPreview'
        );

    const empty =
        document.getElementById(
            'fuelPhotoAiPreviewEmpty'
        );

    const info =
        document.getElementById(
            'fuelPhotoAiFileInfo'
        );

    const result =
        document.getElementById(
            'fuelPhotoAiResult'
        );

    const feedback =
        document.getElementById(
            'fuelPhotoAiFeedback'
        );

    const button =
        document.getElementById(
            'fuelPhotoAiAnalyze'
        );

    file.value = '';

    preview.src = '';
    preview.hidden = true;

    empty.hidden = false;
    empty.innerHTML =
        '<i class="bi bi-file-earmark-image"></i>'
        + '<span>Prévia da folha</span>';

    info.hidden = true;
    info.innerHTML = '';

    result.hidden = true;

    feedback.hidden = true;
    feedback.innerHTML = '';

    button.disabled = true;
}


async function analyzeFuelPhoto() {
    const input =
        document.getElementById(
            'fuelPhotoAiFile'
        );

    const button =
        document.getElementById(
            'fuelPhotoAiAnalyze'
        );

    const feedback =
        document.getElementById(
            'fuelPhotoAiFeedback'
        );

    if (!input.files?.length) {
        showFuelPhotoFeedback(
            'Selecione uma imagem primeiro.',
            'error'
        );

        return;
    }

    const formData =
        new FormData();

    formData.append(
        'image',
        input.files[0]
    );

    const original =
        button.innerHTML;

    button.disabled = true;

    button.innerHTML =
        '<span class="fuel-photo-ai-spinner"></span>'
        + ' Analisando ficha...';

    startFuelPhotoLoading();

    feedback.hidden = false;
    feedback.className =
        'fuel-photo-ai-feedback is-loading';

    feedback.innerHTML =
        '<strong>Google Vision está lendo a ficha.</strong>'
        + '<span>Reconstruindo linhas e cruzando com os veículos da unidade...</span>';

    try {
        const response = await fetch(
            fuelPhotoAnalyzeUrl,
            {
                method: 'POST',

                headers: {
                    'Accept':
                        'application/json',

                    'X-CSRF-TOKEN':
                        @json(csrf_token())
                },

                body: formData
            }
        );

        const data =
            await response.json();

        if (
            !response.ok
            || !data.ok
        ) {
            throw new Error(
                data.message
                || 'Não foi possível analisar a imagem.'
            );
        }

        fuelPhotoAnalysis =
            data.analysis;

        renderFuelPhotoAnalysis(
            data.analysis
        );

        feedback.hidden = true;

    } catch (error) {
        showFuelPhotoFeedback(
            error.message,
            'error'
        );

    } finally {
        stopFuelPhotoLoading();

        button.disabled = false;
        button.innerHTML = original;
    }
}


function renderFuelPhotoAnalysis(data) {
    const result =
        document.getElementById(
            'fuelPhotoAiResult'
        );

    const rows =
        data.rows || [];

    const validation =
        data.validation || {};

    const header =
        data.header || {};

    document.getElementById(
        'fuelPhotoAiRowCount'
    ).textContent =
        rows.length;

    document.getElementById(
        'fuelPhotoAiCalculated'
    ).textContent =
        fuelPhotoLiters(
            validation.rows_liters_sum
        );

    document.getElementById(
        'fuelPhotoAiOperator'
    ).textContent =
        header.operator || '—';

    const tbody =
        document.getElementById(
            'fuelPhotoAiRows'
        );

    tbody.innerHTML = '';

    rows.forEach(
        (row, index) => {
            tbody.appendChild(
                buildFuelPhotoRow(
                    row,
                    index
                )
            );
        }
    );

    initializeFuelPhotoOperationMeta(
        data
    );

    initializeFuelPhotoTankOptions();

    recalculateFuelPhotoStocks();

    result.hidden = false;

    result.scrollIntoView({
        behavior: 'smooth',
        block: 'nearest'
    });
}


function buildFuelPhotoRow(
    row,
    index
) {
    const tr =
        document.createElement('tr');

    tr.dataset.index = index;

    const warnings =
        row.warnings || [];

    if (
        row.review_level === 'critical'
    ) {
        tr.classList.add(
            'has-critical'
        );

    } else if (
        row.review_level === 'warning'
        || warnings.length
    ) {
        tr.classList.add(
            'has-warning'
        );
    }

    const vehicleOptions =
        [
            '<option value="">Selecione...</option>',

            ...fuelPhotoVehicles.map(
                vehicle => {
                    const selected =
                        Number(
                            row.suggested_vehicle_id
                        ) === Number(
                            vehicle.id
                        );

                    const label =
                        `${vehicle.name}`
                        + (
                            vehicle.plate
                                ? ` · ${vehicle.plate}`
                                : ''
                        );

                    return `
                        <option
                            value="${vehicle.id}"
                            ${selected ? 'selected' : ''}
                        >
                            ${escapeFuelPhoto(label)}
                        </option>
                    `;
                }
            )
        ].join('');

    const confidence =
        Number(
            row.confidence || 0
        );

    const status =
        warnings.length
            ? '⚠ Revisar'
            : confidence >= .85
                ? '✓ OK'
                : '◌ Conferir';

    const plateDb =
        row.matched_vehicle?.plate
        || '—';

    const currentKmDb =
        row.matched_vehicle?.current_km
        ?? null;


    /*
     * Configuração operacional real do veículo.
     * Não presume que toda máquina utilize hodômetro.
     */
    const vehicleMeterConfig =
        fuelPhotoVehicles.find(
            vehicle =>
                Number(vehicle.id)
                === Number(
                    row.suggested_vehicle_id
                    ?? row.matched_vehicle?.id
                )
        )
        || null;

    const kmControlEnabled =
        vehicleMeterConfig
            ? Boolean(
                vehicleMeterConfig.km_control_enabled
            )
            : true;

    const hoursControlEnabled =
        vehicleMeterConfig
            ? Boolean(
                vehicleMeterConfig.hours_control_enabled
            )
            : false;

    const currentHoursDb =
        vehicleMeterConfig?.current_hours
        ?? null;

    /*
     * Comparação numérica.
     * 80032 e "80.032" exibido pelo locale representam
     * a mesma leitura e NÃO geram ação de correção.
     */
    const sheetPreviousKm =
        row.previous_km_printed === null
        || row.previous_km_printed === undefined
        || row.previous_km_printed === ''
            ? null
            : Number(
                row.previous_km_printed
            );

    const hasDifferentChmKm =
        kmControlEnabled
        && currentKmDb !== null
        && sheetPreviousKm !== null
        && Number.isFinite(
            Number(currentKmDb)
        )
        && Number.isFinite(
            Number(sheetPreviousKm)
        )
        && Number(currentKmDb)
            !== Number(sheetPreviousKm);

    tr.innerHTML = `
        <td class="fuel-photo-ai-line">
            ${row.line ?? index + 1}
        </td>

        <td class="fuel-photo-ai-time-cell">
            <input
                type="time"
                class="fuel-photo-ai-input fuel-photo-ai-time-input"
                data-field="estimated_time"
                data-auto-time="1"
                title="Horário estimado. Pode ser corrigido manualmente."
                onchange="this.dataset.autoTime='0'"
            >
        </td>

        <td>
            <select
                class="fuel-photo-ai-input fuel-photo-ai-vehicle"
                data-field="vehicle_id"
                onchange="fuelPhotoVehicleChanged(this)"
            >
                ${vehicleOptions}
            </select>

            <small class="fuel-photo-ai-cell-note">
                Lido:
                ${escapeFuelPhoto(
                    row.code_read || '—'
                )}
            </small>
        </td>

        <td>

            <div class="fuel-photo-ai-plate-confirm">

                <input
                    class="fuel-photo-ai-input fuel-photo-ai-plate ${
                        row.review_level === 'critical'
                            ? 'is-critical'
                            : row.review_level === 'warning'
                                ? 'is-warning'
                                : 'is-ok'
                    }"
                    data-field="plate_read"
                    data-ocr-plate="${escapeFuelPhoto(
                        row.plate_read || ''
                    )}"
                    list="fuelPhotoVehiclePlateList"
                    value="${escapeFuelPhoto(
                        row.plate_read || ''
                    )}"
                    oninput="fuelPhotoPlateChanged(this)"
                    autocomplete="off"
                >

                <button
                    type="button"
                    class="fuel-photo-ai-confirm-btn"
                    title="Confirmar que este é o veículo selecionado"
                    onclick="confirmFuelPhotoPlate(this)"
                >
                    <i class="bi bi-check-lg"></i>
                </button>

            </div>

            <small class="fuel-photo-ai-cell-note fuel-photo-ai-db-plate">
                CHM: ${escapeFuelPhoto(plateDb)}
            </small>

            ${
                row.plate_score != null
                    ? `
                        <small class="fuel-photo-ai-cell-note">
                            Similaridade:
                            ${Math.round(
                                Number(row.plate_score) * 100
                            )}%
                        </small>
                    `
                    : ''
            }
        </td>

        <td>
            <input
                type="number"
                step="0.001"
                min="0"
                class="fuel-photo-ai-input"
                data-field="liters"
                value="${
                    row.liters ?? ''
                }"

                oninput="recalculateFuelPhotoStocks()">
        </td>

        <td>
            <div class="fuel-photo-ai-km-confirm">
<input
                type="number"
                step="1"
                min="0"
                class="fuel-photo-ai-input"
                data-field="previous_km"
                value="${
                    row.previous_km_printed
                    ?? ''
                }"
                oninput="
                    const tr = this.closest('tr');

                    tr.dataset.previousKmConfirmed = '0';
                    this.dataset.kmConfirmed = '0';

                    tr.querySelector(
                        '.fuel-photo-ai-km-confirm-btn'
                    )?.classList.remove(
                        'is-confirmed'
                    );

                    recalculateFuelPhotoRow(tr);
                    refreshFuelPhotoHumanReview();
                    invalidateFuelPhotoLaunchConfirmation();
                "
            >
                <button
                    type="button"
                    class="fuel-photo-ai-confirm-btn fuel-photo-ai-km-confirm-btn"
                    title="Confirmar que este Último KM está correto"
                    onclick="confirmFuelPhotoPreviousKm(this)"
                >
                    <i class="bi bi-check-lg"></i>
                </button>
            </div>

            <small class="fuel-photo-ai-cell-note fuel-photo-ai-km-chm">

                ${
                    kmControlEnabled
                        ? `
                            <span>
                                CHM:
                                ${
                                    currentKmDb == null
                                        ? '—'
                                        : Number(
                                            currentKmDb
                                        ).toLocaleString(
                                            'pt-BR'
                                        ) + ' km'
                                }
                            </span>

                            ${
                                hasDifferentChmKm
                                    ? `
                                        <button
                                            type="button"
                                            class="fuel-photo-ai-use-chm-km"
                                            onclick="useFuelPhotoChmKm(
                                                this,
                                                ${Number(currentKmDb)}
                                            )"
                                            title="Substituir pela leitura atual registrada no CHM"
                                        >
                                            Usar este
                                        </button>
                                    `
                                    : ''
                            }
                        `
                        : (
                            hoursControlEnabled
                                ? `
                                    <span class="fuel-photo-ai-meter-hours">
                                        <i class="bi bi-clock"></i>
                                        Controle por horímetro
                                        ${
                                            currentHoursDb != null
                                                ? ' · CHM: '
                                                    + Number(
                                                        currentHoursDb
                                                    ).toLocaleString(
                                                        'pt-BR'
                                                    )
                                                    + ' h'
                                                : ''
                                        }
                                    </span>
                                `
                                : `
                                    <span class="fuel-photo-ai-meter-disabled">
                                        Controle de hodômetro desabilitado
                                    </span>
                                `
                        )
                }

            </small>

            ${
                kmControlEnabled
                && row.km_reference?.message
                    ? `
                        <small
                            class="fuel-photo-ai-km-reference is-${escapeFuelPhoto(
                                row.km_reference.level || 'info'
                            )}"
                        >
                            ${escapeFuelPhoto(
                                row.km_reference.message
                            )}
                        </small>
                    `
                    : ''
            }
        </td>

        <td>
            <div class="fuel-photo-ai-new-km-confirm">
<input
                type="number"
                step="1"
                min="0"
                class="fuel-photo-ai-input"
                data-field="new_km"
                value="${
                    row.km_at_filling
                    ?? ''
                }"
                oninput="
                    const tr = this.closest('tr');

                    tr.dataset.newKmConfirmed = '0';

                    const confirmButton =
                        tr.querySelector(
                            '.fuel-photo-ai-new-km-confirm-btn'
                        );

                    confirmButton?.classList.remove(
                        'is-confirmed'
                    );

                    if (confirmButton) {
                        delete confirmButton.dataset.confirmedValue;
                    }

                    recalculateFuelPhotoRow(tr);
                    refreshFuelPhotoHumanReview();
                    invalidateFuelPhotoLaunchConfirmation();
                "
            >
                <button
                    type="button"
                    class="fuel-photo-ai-confirm-btn fuel-photo-ai-new-km-confirm-btn"
                    title="Confirmar conscientemente este Novo KM"
                    onclick="confirmFuelPhotoNewKm(this)"
                >
                    <i class="bi bi-check-lg"></i>
                </button>
            </div>
        </td>

        <td class="fuel-photo-ai-distance">

            <strong class="fuel-photo-ai-distance-sheet">
                Folha:
                ${
                    row.distance_km == null
                        ? '—'
                        : Number(
                            row.distance_km
                        ).toLocaleString(
                            'pt-BR'
                        ) + ' km'
                }
            </strong>

            <span class="fuel-photo-ai-distance-db">
                CHM:
                ${
                    currentKmDb != null
                    && row.km_at_filling != null
                        ? Number(
                            Number(row.km_at_filling)
                            - Number(currentKmDb)
                        ).toLocaleString(
                            'pt-BR'
                        ) + ' km'
                        : '—'
                }
            </span>

            ${
                row.historical_km?.samples >= 3
                && row.historical_km?.average_km != null
                    ? `
                        <small>
                            Média:
                            ${Number(
                                row.historical_km.average_km
                            ).toLocaleString(
                                'pt-BR',
                                {
                                    maximumFractionDigits: 1
                                }
                            )} km
                            · ${row.historical_km.samples}
                            intervalos
                        </small>
                    `
                    : `
                        <small>
                            Histórico insuficiente
                        </small>
                    `
            }

        </td>

        <td>
            <input
                type="number"
                step="0.01"
                min="0"
                class="fuel-photo-ai-input"
                data-field="arla"
                value="${
                    row.arla_liters
                    ?? ''
                }"

                oninput="recalculateFuelPhotoStocks()">
        </td>

        <td class="fuel-photo-ai-status-cell">

            <strong>
                ${status}
            </strong>

            ${
                warnings.length
                    ? `
                        <div class="fuel-photo-ai-warnings">
                            ${warnings.map(
                                warning =>
                                    `<span>⚠ ${escapeFuelPhoto(
                                        warning
                                    )}</span>`
                            ).join('')}
                        </div>
                    `
                    : `
                        <small>
                            Confiança:
                            ${Math.round(
                                confidence * 100
                            )}%
                        </small>
                    `
            }

        </td>
    `;

    return tr;
}


function normalizeFuelPhotoPlate(value) {
    return String(value || '')
        .toUpperCase()
        .replace(/[^A-Z0-9]/g, '');
}


function fuelPhotoPlateChanged(input) {
    const tr = input.closest('tr');

    if (tr) {
        tr.dataset.vehicleConfirmed = '0';

        tr.querySelector(
            '.fuel-photo-ai-plate-confirm .fuel-photo-ai-confirm-btn'
        )?.classList.remove(
            'is-confirmed'
        );

        refreshFuelPhotoHumanReview();
        invalidateFuelPhotoLaunchConfirmation();
    }


    const typed =
        normalizeFuelPhotoPlate(
            input.value
        );

    const vehicle =
        fuelPhotoVehicles.find(
            item =>
                normalizeFuelPhotoPlate(
                    item.plate
                ) === typed
        );

    const select =
        tr.querySelector(
            '[data-field="vehicle_id"]'
        );

    const dbPlate =
        tr.querySelector(
            '.fuel-photo-ai-db-plate'
        );

    if (vehicle) {
        if (select) {
            select.value =
                String(vehicle.id);

            fuelPhotoVehicleChanged(
                select
            );
        }

        if (dbPlate) {
            dbPlate.textContent =
                'CHM: '
                + vehicle.plate;
        }

        input.classList.remove(
            'is-warning',
            'is-critical'
        );

        input.classList.add(
            'is-ok'
        );

        tr.classList.remove(
            'has-critical',
            'has-warning'
        );

        return;
    }

    /*
     * Ainda não encontramos placa exata.
     * Mantemos amarelo enquanto o usuário pesquisa.
     */
    input.classList.remove(
        'is-ok',
        'is-critical'
    );

    input.classList.add(
        'is-warning'
    );

    tr.classList.add(
        'has-warning'
    );

    refreshFuelPhotoDuplicatePreflight();

}


function confirmFuelPhotoNewKm(
    button
) {
    const tr =
        button.closest('tr');

    if (!tr) {
        return;
    }

    const input =
        tr.querySelector(
            '[data-field="new_km"]'
        );

    if (!input) {
        return;
    }

    const value =
        Number(
            input.value
        );

    if (
        !Number.isFinite(value)
        || value < 0
    ) {
        showFuelPhotoFeedback(
            'Informe um Novo KM válido antes de confirmar.',
            'error'
        );

        input.focus();
        return;
    }

    tr.dataset.newKmConfirmed =
        '1';

    button.dataset.confirmedValue =
        String(value);

    button.classList.add(
        'is-confirmed'
    );

    button.title =
        'Novo KM confirmado conscientemente';


    refreshFuelPhotoHumanReview();

    showFuelPhotoFeedback(
        'Novo KM confirmado manualmente para esta linha. '
        + 'A validação de segurança será registrada no lançamento.',
        'success'
    );

    invalidateFuelPhotoLaunchConfirmation();
}


function syncFuelPhotoRowMeterControl(
    tr
) {
    if (!tr) {
        return;
    }

    const vehicleSelect =
        tr.querySelector(
            '[data-field="vehicle_id"]'
        );

    const vehicle =
        fuelPhotoVehicles.find(
            item =>
                Number(item.id)
                === Number(
                    vehicleSelect?.value
                )
        )
        || null;

    const previousKmInput =
        tr.querySelector(
            '[data-field="previous_km"]'
        );

    if (!previousKmInput) {
        return;
    }

    /*
     * Algumas linhas nasceram sem veículo associado.
     * Nesses casos o bloco de referência CHM pode não ter
     * sido criado originalmente. Criamos sob demanda.
     */
    let reference =
        tr.querySelector(
            '.fuel-photo-ai-km-chm'
        );

    if (!reference) {

        reference =
            document.createElement(
                'small'
            );

        reference.className =
            'fuel-photo-ai-cell-note fuel-photo-ai-km-chm';

        previousKmInput.insertAdjacentElement(
            'afterend',
            reference
        );
    }


    /*
     * Nenhum veículo selecionado.
     */
    if (!vehicle) {

        reference.innerHTML =
            '<span>CHM: —</span>';

        tr.querySelectorAll(
            '.fuel-photo-ai-km-reference'
        ).forEach(
            element =>
                element.remove()
        );

        return;
    }


    const kmEnabled =
        Boolean(
            vehicle.km_control_enabled
        );

    const hoursEnabled =
        Boolean(
            vehicle.hours_control_enabled
        );

    const currentKm =
        fuelPhotoMeterNumber(
            vehicle.current_km
        );

    const currentHours =
        fuelPhotoMeterNumber(
            vehicle.current_hours
        );

    const sheetPreviousKm =
        fuelPhotoMeterNumber(
            previousKmInput.value
        );


    /*
     * =====================================================
     * CONTROLE POR HORÍMETRO
     * =====================================================
     */
    if (
        !kmEnabled
        && hoursEnabled
    ) {

        reference.innerHTML =
            `
                <span class="fuel-photo-ai-meter-hours">
                    <i class="bi bi-clock"></i>
                    Controle por horímetro
                    ${
                        currentHours !== null
                            ? ' · CHM: '
                                + Number(
                                    currentHours
                                ).toLocaleString(
                                    'pt-BR'
                                )
                                + ' h'
                            : ''
                    }
                </span>
            `;

        tr.querySelectorAll(
            '.fuel-photo-ai-km-reference'
        ).forEach(
            element =>
                element.remove()
        );

        previousKmInput.classList.remove(
            'is-warning',
            'is-critical'
        );

        return;
    }


    /*
     * =====================================================
     * CONTROLE POR KM
     * =====================================================
     */
    if (kmEnabled) {

        const different =
            currentKm !== null
            && sheetPreviousKm !== null
            && Number(currentKm)
                !== Number(sheetPreviousKm);

        reference.innerHTML =
            `
                <span>
                    CHM:
                    ${
                        currentKm === null
                            ? '—'
                            : Number(
                                currentKm
                            ).toLocaleString(
                                'pt-BR'
                            )
                                + ' km'
                    }
                </span>

                ${
                    different
                        ? `
                            <button
                                type="button"
                                class="fuel-photo-ai-use-chm-km"
                                onclick="useFuelPhotoChmKm(
                                    this,
                                    ${Number(currentKm)}
                                )"
                                title="Substituir pela leitura atual registrada no CHM"
                            >
                                Usar este
                            </button>
                        `
                        : ''
                }
            `;

        return;
    }


    /*
     * Nenhum controle habilitado.
     */
    reference.innerHTML =
        `
            <span class="fuel-photo-ai-meter-disabled">
                Medidor não controlado
            </span>
        `;

    tr.querySelectorAll(
        '.fuel-photo-ai-km-reference'
    ).forEach(
        element =>
            element.remove()
    );
}



/*
|--------------------------------------------------------------------------
| FOTO IA - GARANTIR AÇÃO "USAR ESTE" DO KM DO CHM
|--------------------------------------------------------------------------
|
| Algumas rotinas reconstruem a referência CHM depois da seleção
| manual do veículo. Esta função reaplica a ação no estado final
| da linha, sem alterar o valor OCR automaticamente.
|
*/

function ensureFuelPhotoUseChmAction(
    tr
) {
    if (!tr) {
        return;
    }

    const vehicleSelect =
        tr.querySelector(
            '[data-field="vehicle_id"]'
        );

    const previousInput =
        tr.querySelector(
            '[data-field="previous_km"]'
        );

    if (
        !vehicleSelect
        || !previousInput
    ) {
        return;
    }

    const vehicle =
        fuelPhotoVehicles.find(
            item =>
                Number(item.id)
                === Number(
                    vehicleSelect.value
                )
        )
        || null;

    if (!vehicle) {
        return;
    }

    /*
     * Para veículos controlados exclusivamente por horas,
     * não existe ação "Usar KM do CHM".
     */
    if (
        !Boolean(vehicle.km_control_enabled)
    ) {
        tr.querySelectorAll(
            '.fuel-photo-ai-use-chm-km'
        ).forEach(
            button =>
                button.remove()
        );

        return;
    }

    const currentKm =
        fuelPhotoMeterNumber(
            vehicle.current_km
        );

    const sheetKm =
        fuelPhotoMeterNumber(
            previousInput.value
        );

    /*
     * Localiza a referência CHM criada pela renderização
     * original ou pela sincronização posterior.
     */
    let reference =
        tr.querySelector(
            '.fuel-photo-ai-km-chm'
        );

    if (!reference) {
        const cell =
            previousInput.closest(
                'td'
            );

        reference =
            [
                ...(
                    cell?.querySelectorAll(
                        '.fuel-photo-ai-cell-note'
                    )
                    || []
                )
            ].find(
                element =>
                    element.textContent
                        ?.includes('CHM:')
            )
            || null;
    }

    if (!reference) {
        return;
    }

    const existingButton =
        reference.querySelector(
            '.fuel-photo-ai-use-chm-km'
        );

    /*
     * Mesmo valor:
     *
     * 80032 === 80.032 km
     *
     * portanto não deve existir botão.
     */
    if (
        currentKm === null
        || sheetKm === null
        || Number(currentKm)
            === Number(sheetKm)
    ) {
        existingButton?.remove();

        return;
    }

    /*
     * Já existe e está apontando para o mesmo KM.
     */
    if (existingButton) {
        existingButton.dataset.chmKm =
            String(currentKm);

        existingButton.onclick =
            function () {
                useFuelPhotoChmKm(
                    this,
                    currentKm
                );
            };

        return;
    }

    const button =
        document.createElement(
            'button'
        );

    button.type =
        'button';

    button.className =
        'fuel-photo-ai-use-chm-km';

    button.dataset.chmKm =
        String(currentKm);

    button.textContent =
        'Usar este';

    button.title =
        'Usar o KM atual registrado no CHM';

    button.onclick =
        function () {
            useFuelPhotoChmKm(
                this,
                currentKm
            );
        };

    reference.appendChild(
        button
    );
}


function ensureFuelPhotoUseChmActions() {
    document
        .querySelectorAll(
            '#fuelPhotoAiRows > tr'
        )
        .forEach(
            tr =>
                ensureFuelPhotoUseChmAction(
                    tr
                )
        );
}


/*
 * Seleção manual de veículo.
 */
document.addEventListener(
    'change',
    function (event) {

        if (
            !event.target.matches(
                '#fuelPhotoAiRows [data-field="vehicle_id"]'
            )
        ) {
            return;
        }

        const tr =
            event.target.closest(
                'tr'
            );

        /*
         * Aguarda as rotinas originais atualizarem placa,
         * CHM, similaridade e alertas.
         */
        requestAnimationFrame(
            () => {
                requestAnimationFrame(
                    () => {
                        ensureFuelPhotoUseChmAction(
                            tr
                        );
                    }
                );
            }
        );
    }
);


/*
 * Se o operador alterar o Último KM manualmente,
 * também recalcula se "Usar este" deve existir.
 */
document.addEventListener(
    'input',
    function (event) {

        if (
            !event.target.matches(
                '#fuelPhotoAiRows [data-field="previous_km"]'
            )
        ) {
            return;
        }

        ensureFuelPhotoUseChmAction(
            event.target.closest('tr')
        );
    }
);


/*
 * Algumas rotinas da Foto IA reescrevem partes da linha
 * depois do change. O observer garante que a ação continue
 * presente no estado FINAL do DOM.
 */
function setupFuelPhotoUseChmObserver() {

    const tbody =
        document.getElementById(
            'fuelPhotoAiRows'
        );

    if (
        !tbody
        || tbody.dataset.useChmObserver
            === '1'
    ) {
        return;
    }

    tbody.dataset.useChmObserver =
        '1';

    let scheduled =
        false;

    const observer =
        new MutationObserver(
            () => {

                if (scheduled) {
                    return;
                }

                scheduled =
                    true;

                requestAnimationFrame(
                    () => {
                        scheduled =
                            false;

                        ensureFuelPhotoUseChmActions();
                    }
                );
            }
        );

    observer.observe(
        tbody,
        {
            childList: true,
            subtree: true,
            characterData: true
        }
    );

    ensureFuelPhotoUseChmActions();
}


if (
    document.readyState
    === 'loading'
) {
    document.addEventListener(
        'DOMContentLoaded',
        setupFuelPhotoUseChmObserver
    );
} else {
    setupFuelPhotoUseChmObserver();
}


function useFuelPhotoChmKm(
    button,
    chmKm
) {
    const tr =
        button.closest('tr');

    if (!tr) {
        return;
    }

    const input =
        tr.querySelector(
            '[data-field="previous_km"]'
        );

    if (!input) {
        return;
    }

    const value =
        Number(chmKm);

    if (
        !Number.isFinite(value)
        || value < 0
    ) {
        showFuelPhotoFeedback(
            'O KM atual do CHM não é válido para esta linha.',
            'error'
        );

        return;
    }

    /*
     * O operador escolheu conscientemente
     * usar a leitura cadastrada no CHM.
     */
    input.value =
        String(
            Math.round(value)
        );

    /*
     * Como houve alteração de referência,
     * qualquer confirmação final anterior
     * deixa de ser válida.
     */
    invalidateFuelPhotoLaunchConfirmation();

    /*
     * Recalcula KM rodado, alertas e demais
     * informações dependentes da leitura.
     */
    recalculateFuelPhotoRow(
        tr
    );

    /*
     * Reaproveita a confirmação humana já
     * existente para o campo Último KM.
     */
    const confirmButton =
        tr.querySelector(
            '.fuel-photo-ai-km-confirm-btn'
        );

    if (confirmButton) {
        confirmFuelPhotoPreviousKm(
            confirmButton
        );
    }

    button.classList.add(
        'is-used'
    );

    button.textContent =
        'Usado';

    button.title =
        'KM do CHM aplicado nesta linha';

    refreshFuelPhotoHumanReview();
}


function confirmFuelPhotoPreviousKm(
    button
) {
    const tr =
        button.closest('tr');

    if (!tr) {
        return;
    }

    const input =
        tr.querySelector(
            '[data-field="previous_km"]'
        );

    if (!input) {
        return;
    }

    const value =
        Number(
            input.value
        );

    if (
        !Number.isFinite(value)
        || value <= 0
    ) {
        showFuelPhotoFeedback(
            'Informe um Último KM válido antes de confirmar.',
            'error'
        );

        input.focus();

        return;
    }

    /*
     * Confirmação humana:
     * o operador conferiu o KM na ficha.
     */
    input.dataset.kmConfirmed =
        '1';

    tr.dataset.previousKmConfirmed =
        '1';

    input.classList.remove(
        'is-warning',
        'is-critical'
    );

    input.classList.add(
        'is-ok'
    );

    button.classList.add(
        'is-confirmed'
    );

    button.title =
        'Último KM confirmado manualmente';


    refreshFuelPhotoHumanReview();

    /*
     * Remove somente a mensagem referente
     * à comparação Último KM da folha x CHM.
     */
    const kmReference =
        tr.querySelector(
            '.fuel-photo-ai-km-reference'
        );

    if (kmReference) {
        kmReference.textContent =
            '✓ KM conferido manualmente';

        kmReference.classList.remove(
            'is-warning',
            'is-info'
        );

        kmReference.classList.add(
            'is-confirmed'
        );
    }

    /*
     * Caso algum aviso desse tipo esteja também
     * na coluna Situação, removemos somente ele.
     */
    const statusCell =
        tr.querySelector(
            '.fuel-photo-ai-status-cell'
        )
        || tr.lastElementChild;

    if (statusCell) {
        const kmReferenceWarnings = [
            'último km da folha está abaixo',
            'último km da folha está acima',
            'folha abaixo do chm',
            'folha acima do chm',
            'revise se a folha foi preenchida com uma leitura antiga',
            'pode haver abastecimentos anteriores ainda não lançados'
        ];

        [
            ...statusCell.querySelectorAll(
                'li, p, small, span'
            )
        ].forEach(
            node => {
                const content =
                    (
                        node.textContent
                        || ''
                    )
                    .trim()
                    .toLowerCase();

                if (
                    kmReferenceWarnings.some(
                        warning =>
                            content.includes(
                                warning
                            )
                    )
                ) {
                    node.remove();
                }
            }
        );
    }
}


function confirmFuelPhotoPlate(
    button
) {
    const tr =
        button.closest('tr');

    if (!tr) {
        return;
    }

    const select =
        tr.querySelector(
            '[data-field="vehicle_id"]'
        );

    const input =
        tr.querySelector(
            '[data-field="plate_read"]'
        );

    if (!select || !input) {
        return;
    }

    const vehicle =
        fuelPhotoVehicles.find(
            item =>
                Number(item.id)
                === Number(
                    select.value
                )
        );

    if (!vehicle) {
        showFuelPhotoFeedback(
            'Selecione primeiro o veículo correto.',
            'error'
        );

        select.focus();
        return;
    }

    const confirmedValue =
        (
            vehicle.plate
            || vehicle.name
            || ''
        )
        .toUpperCase()
        .trim();

    if (!confirmedValue) {
        showFuelPhotoFeedback(
            'Este veículo não possui placa/código utilizável para confirmação.',
            'error'
        );

        return;
    }

    /*
     * O operador está confirmando manualmente
     * que o veículo selecionado é o correto.
     */
    input.value =
        confirmedValue;

    input.dataset.ocrPlate =
        confirmedValue;

    input.classList.remove(
        'is-warning',
        'is-critical'
    );

    input.classList.add(
        'is-ok'
    );

    /*
     * Executa a lógica normal da placa.
     */
    fuelPhotoPlateChanged(
        input
    );

    /*
     * Marca explicitamente como confirmação humana.
     */
    tr.dataset.vehicleConfirmed =
        '1';

    button.classList.add(
        'is-confirmed'
    );

    button.title =
        'Veículo confirmado manualmente';


    refreshFuelPhotoHumanReview();

    /*
     * Remove APENAS avisos relacionados
     * à identificação do veículo.
     *
     * KM, ARLA e demais avisos continuam.
     */
    const statusCell =
        tr.querySelector(
            '.fuel-photo-ai-status-cell'
        )
        || tr.lastElementChild;

    if (statusCell) {
        const identityWarnings = [
            'placa/identificador lido diverge',
            'veículo associado por aproximação',
            'nenhum veículo cadastrado foi identificado com segurança',
            'código e placa/identificador apontam para veículos diferentes',
            'placa lida na folha diverge',
            'confirme o cadastro'
        ];

        /*
         * Procura cada elemento textual da coluna Situação.
         */
        const candidates =
            [
                ...statusCell.querySelectorAll(
                    'li, p, small, span, div'
                )
            ];

        candidates
            .reverse()
            .forEach(
                node => {
                    const value =
                        (
                            node.textContent
                            || ''
                        )
                        .trim()
                        .toLowerCase();

                    if (!value) {
                        return;
                    }

                    const isIdentityWarning =
                        identityWarnings.some(
                            warning =>
                                value.includes(
                                    warning
                                )
                        );

                    if (!isIdentityWarning) {
                        return;
                    }

                    /*
                     * Não remove o container inteiro da situação.
                     */
                    if (
                        node === statusCell
                    ) {
                        return;
                    }

                    /*
                     * Se o elemento contém somente este aviso,
                     * removemos a linha inteira.
                     */
                    const childWarning =
                        [
                            ...node.children
                        ]
                        .some(
                            child => {
                                const childText =
                                    (
                                        child.textContent
                                        || ''
                                    )
                                    .trim()
                                    .toLowerCase();

                                return identityWarnings.some(
                                    warning =>
                                        childText.includes(
                                            warning
                                        )
                                );
                            }
                        );

                    if (
                        node.children.length === 0
                        || !childWarning
                    ) {
                        node.remove();
                    }
                }
            );

        /*
         * Caso o aviso esteja como texto direto dentro
         * de algum container, limpa especificamente
         * as frases conhecidas.
         */
        [
            ...statusCell.childNodes
        ].forEach(
            node => {
                if (
                    node.nodeType
                    !== Node.TEXT_NODE
                ) {
                    return;
                }

                const value =
                    (
                        node.textContent
                        || ''
                    )
                    .trim()
                    .toLowerCase();

                if (
                    identityWarnings.some(
                        warning =>
                            value.includes(
                                warning
                            )
                    )
                ) {
                    node.remove();
                }
            }
        );
    }

    /*
     * Recalcula KM/estado depois da confirmação.
     */
    recalculateFuelPhotoRow(
        tr
    );
}


function fuelPhotoVehicleChanged(
    select
) {
    const tr =
        select.closest('tr');

    if (tr) {
        tr.dataset.vehicleConfirmed = '0';

        tr.querySelector(
            '.fuel-photo-ai-plate-confirm .fuel-photo-ai-confirm-btn'
        )?.classList.remove(
            'is-confirmed'
        );

        refreshFuelPhotoHumanReview();
        invalidateFuelPhotoLaunchConfirmation();
    }


    if (!tr) {
        return;
    }

    const vehicle =
        fuelPhotoVehicles.find(
            item =>
                Number(item.id)
                === Number(
                    select.value
                )
        );

    const plate =
        tr.querySelector(
            '.fuel-photo-ai-db-plate'
        );

    const km =
        tr.querySelector(
            '.fuel-photo-ai-km-chm'
        );

    if (plate) {
        plate.textContent =
            'CHM: '
            + (
                vehicle?.plate
                || vehicle?.name
                || '—'
            );
    }

    if (km) {
        km.textContent =
            'CHM: '
            + (
                vehicle?.current_km == null
                    ? '—'
                    : Number(
                        vehicle.current_km
                    ).toLocaleString(
                        'pt-BR'
                    ) + ' km'
            );
    }

    /*
     * Se o usuário selecionou explicitamente um veículo,
     * resolvemos a pendência de identificação.
     */
    if (vehicle) {
        tr.classList.remove(
            'has-critical'
        );

        const plateInput =
            tr.querySelector(
                '[data-field="plate_read"]'
            );

        if (plateInput) {
            plateInput.classList.remove(
                'is-critical'
            );

            /*
             * Não pintamos automaticamente de verde:
             * pode continuar existindo divergência OCR x cadastro.
             */
            const typed =
                normalizeFuelPhotoPlate(
                    plateInput.value
                );

            const registered =
                normalizeFuelPhotoPlate(
                    vehicle.plate
                    || vehicle.name
                );

            plateInput.classList.toggle(
                'is-ok',
                typed !== ''
                && registered !== ''
                && typed === registered
            );

            plateInput.classList.toggle(
                'is-warning',
                typed === ''
                || registered === ''
                || typed !== registered
            );
        }
    }

    recalculateFuelPhotoRow(
        tr
    );

    refreshFuelPhotoDuplicatePreflight();

}


function recalculateFuelPhotoRow(
    tr
) {
    if (!tr) {
        return;
    }

    const previousInput =
        tr.querySelector(
            '[data-field="previous_km"]'
        );

    const newInput =
        tr.querySelector(
            '[data-field="new_km"]'
        );

    const select =
        tr.querySelector(
            '[data-field="vehicle_id"]'
        );

    const previous =
        previousInput?.value !== ''
            ? Number(previousInput.value)
            : null;

    const next =
        newInput?.value !== ''
            ? Number(newInput.value)
            : null;

    const vehicle =
        fuelPhotoVehicles.find(
            item =>
                Number(item.id)
                === Number(
                    select?.value
                )
        );

    const currentKm =
        vehicle?.current_km != null
            ? Number(vehicle.current_km)
            : null;

    const sheet =
        tr.querySelector(
            '.fuel-photo-ai-distance-sheet'
        );

    const database =
        tr.querySelector(
            '.fuel-photo-ai-distance-db'
        );

    const kmLabel =
        tr.querySelector(
            '.fuel-photo-ai-km-chm'
        );

    let kmReference =
        tr.querySelector(
            '.fuel-photo-ai-km-reference'
        );

    if (kmLabel) {
        kmLabel.textContent =
            currentKm == null
                ? 'CHM: —'
                : 'CHM: '
                    + currentKm.toLocaleString(
                        'pt-BR'
                    )
                    + ' km';
    }


    if (
        previousInput
        && currentKm !== null
        && previous !== null
        && Number.isFinite(previous)
    ) {
        let level = 'ok';
        let message = '';

        if (previous > currentKm) {
            level = 'info';
            message =
                'Folha acima do CHM: pode haver abastecimentos anteriores ainda não lançados.';

        } else if (previous < currentKm) {
            level = 'warning';
            message =
                'Folha abaixo do KM registrado no CHM: revisar leitura anterior.';
        }

        if (!kmReference) {
            kmReference =
                document.createElement(
                    'small'
                );

            kmReference.className =
                'fuel-photo-ai-km-reference';

            kmLabel?.insertAdjacentElement(
                'afterend',
                kmReference
            );
        }

        kmReference.className =
            'fuel-photo-ai-km-reference is-'
            + level;

        kmReference.textContent =
            message;

        kmReference.hidden =
            message === '';

    } else if (kmReference) {
        kmReference.hidden = true;
    }

    if (sheet) {
        if (
            previous !== null
            && next !== null
            && Number.isFinite(previous)
            && Number.isFinite(next)
        ) {
            const distance =
                next - previous;

            sheet.textContent =
                'Folha: '
                + distance.toLocaleString(
                    'pt-BR'
                )
                + ' km';

            sheet.classList.toggle(
                'is-invalid',
                distance < 0
            );
        } else {
            sheet.textContent =
                'Folha: —';

            sheet.classList.remove(
                'is-invalid'
            );
        }
    }

    if (database) {
        if (
            currentKm !== null
            && next !== null
            && Number.isFinite(currentKm)
            && Number.isFinite(next)
        ) {
            const distance =
                next - currentKm;

            database.textContent =
                'CHM: '
                + distance.toLocaleString(
                    'pt-BR'
                )
                + ' km';

            database.classList.toggle(
                'is-invalid',
                distance < 0
            );
        } else {
            database.textContent =
                'CHM: —';

            database.classList.remove(
                'is-invalid'
            );
        }
    }

    /*
     * Último KM da folha x último KM cadastrado.
     */
    if (
        previousInput
        && currentKm !== null
        && previous !== null
        && Number.isFinite(previous)
    ) {
        const differs =
            Number(previous)
            !== Number(currentKm);

        previousInput.classList.toggle(
            'is-warning',
            differs
        );

        previousInput.classList.toggle(
            'is-ok',
            !differs
        );
    }
}



function fuelPhotoTimeToMinutes(
    value
) {
    if (!value || !value.includes(':')) {
        return null;
    }

    const [
        hours,
        minutes
    ] = value
        .split(':')
        .map(Number);

    if (
        !Number.isFinite(hours)
        || !Number.isFinite(minutes)
    ) {
        return null;
    }

    return (
        hours * 60
        + minutes
    );
}


function fuelPhotoMinutesToTime(
    totalMinutes
) {
    /*
     * Mantém dentro de um dia.
     */
    totalMinutes =
        Math.round(
            totalMinutes
        );

    totalMinutes =
        (
            totalMinutes
            % 1440
            + 1440
        ) % 1440;

    const hours =
        Math.floor(
            totalMinutes / 60
        );

    const minutes =
        totalMinutes % 60;

    return String(hours)
        .padStart(2, '0')
        + ':'
        + String(minutes)
            .padStart(2, '0');
}


function recalculateFuelPhotoSchedule(
    forceAll = false
) {
    const startInput =
        document.getElementById(
            'fuelPhotoAiStartTime'
        );

    const endInput =
        document.getElementById(
            'fuelPhotoAiEndTime'
        );

    const durationEl =
        document.getElementById(
            'fuelPhotoAiPeriodDuration'
        );

    const averageEl =
        document.getElementById(
            'fuelPhotoAiAverageInterval'
        );

    const rows =
        [
            ...document.querySelectorAll(
                '#fuelPhotoAiRows tr'
            )
        ];

    const start =
        fuelPhotoTimeToMinutes(
            startInput?.value
        );

    let end =
        fuelPhotoTimeToMinutes(
            endInput?.value
        );

    if (
        start === null
        || end === null
        || !rows.length
    ) {
        if (durationEl) {
            durationEl.textContent = '—';
        }

        if (averageEl) {
            averageEl.textContent = '—';
        }

        return;
    }

    /*
     * Permite período atravessando meia-noite.
     * Ex.: 23:30 → 00:30 = 60 min.
     */
    if (end < start) {
        end += 1440;
    }

    const duration =
        end - start;

    /*
     * Conforme regra operacional:
     * 60 minutos / 20 abastecimentos = 3 min.
     *
     * Assim a primeira linha recebe o horário inicial;
     * a última ocorre antes do horário final.
     */
    const interval =
        duration / rows.length;

    if (durationEl) {
        const hours =
            Math.floor(
                duration / 60
            );

        const minutes =
            duration % 60;

        durationEl.textContent =
            hours > 0
                ? (
                    minutes > 0
                        ? `${hours}h ${String(minutes).padStart(2, '0')}min`
                        : `${hours}h`
                )
                : `${minutes} min`;
    }

    if (averageEl) {
        averageEl.textContent =
            interval >= 1
                ? `${interval.toLocaleString(
                    'pt-BR',
                    {
                        maximumFractionDigits: 1
                    }
                )} min`
                : `${Math.round(
                    interval * 60
                )} s`;
    }

    const stockTotals =
        fuelPhotoCurrentTotals();

    const fuelTankId =
        document.getElementById(
            'fuelPhotoAiFuelTank'
        )?.value;

    const arlaTankId =
        document.getElementById(
            'fuelPhotoAiArlaTank'
        )?.value;

    const selectedFuelTank =
        fuelPhotoTanks.find(
            tank =>
                Number(tank.id)
                === Number(fuelTankId)
        );

    const selectedArlaTank =
        fuelPhotoTanks.find(
            tank =>
                Number(tank.id)
                === Number(arlaTankId)
        );

    if (!selectedFuelTank) {
        problems.push(
            'Selecione o tanque de combustível.'
        );

    } else if (
        stockTotals.fuel_liters
        > Number(
            selectedFuelTank.balance_liters || 0
        )
    ) {
        problems.push(
            'O tanque de combustível não possui saldo suficiente para a soma dos abastecimentos.'
        );
    }

    if (
        stockTotals.arla_liters > 0
    ) {
        if (!selectedArlaTank) {
            problems.push(
                'Selecione o tanque de ARLA.'
            );

        } else if (
            stockTotals.arla_liters
            > Number(
                selectedArlaTank.balance_liters || 0
            )
        ) {
            problems.push(
                'O tanque de ARLA não possui saldo suficiente para a soma informada.'
            );
        }
    }

    rows.forEach(
        (tr, index) => {
            const input =
                tr.querySelector(
                    '[data-field="estimated_time"]'
                );

            if (!input) {
                return;
            }

            /*
             * Ao alterar início/fim, recalculamos tudo.
             * O usuário ainda pode depois corrigir uma linha.
             */
            if (
                forceAll
                || input.dataset.autoTime !== '0'
            ) {
                input.value =
                    fuelPhotoMinutesToTime(
                        start
                        + interval * index
                    );

                input.dataset.autoTime =
                    '1';
            }
        }
    );
}


function initializeFuelPhotoOperationMeta(
    data
) {
    const date =
        document.getElementById(
            'fuelPhotoAiOperationDate'
        );

    const start =
        document.getElementById(
            'fuelPhotoAiStartTime'
        );

    const end =
        document.getElementById(
            'fuelPhotoAiEndTime'
        );

    /*
     * Se o Vision reconheceu data, utiliza-a.
     * Caso contrário, usa a data local do navegador.
     */
    if (date) {
        let resolvedDate = '';

        const readDate =
            data?.header?.date
            || data?.date
            || data?.operation_date
            || '';

        const match =
            String(readDate).match(
                /^(\d{2})\/(\d{2})\/(\d{4})$/
            );

        if (match) {
            resolvedDate =
                `${match[3]}-${match[2]}-${match[1]}`;
        } else if (
            /^\d{4}-\d{2}-\d{2}$/.test(
                String(readDate)
            )
        ) {
            resolvedDate =
                String(readDate);
        }

        if (!resolvedDate) {
            const today =
                new Date();

            resolvedDate =
                [
                    today.getFullYear(),
                    String(
                        today.getMonth() + 1
                    ).padStart(2, '0'),
                    String(
                        today.getDate()
                    ).padStart(2, '0')
                ].join('-');
        }

        date.value =
            resolvedDate;
    }

    /*
     * Não inventamos início/fim.
     * O operador informa.
     */
    if (start) {
        start.value = '';
    }

    if (end) {
        end.value = '';
    }

    recalculateFuelPhotoSchedule(
        true
    );
}


function fuelPhotoNumber(
    value
) {
    if (
        value === null
        || value === undefined
        || value === ''
    ) {
        return 0;
    }

    if (typeof value === 'number') {
        return Number.isFinite(value)
            ? value
            : 0;
    }

    let normalized =
        String(value).trim();

    /*
     * Inputs HTML type=number retornam decimal com ponto:
     * 142.1 deve continuar 142.1.
     *
     * Se houver vírgula, tratamos como formato pt-BR:
     * 1.234,56 -> 1234.56
     * 23,66    -> 23.66
     */
    if (normalized.includes(',')) {
        normalized =
            normalized
                .replace(/\./g, '')
                .replace(',', '.');
    }

    const parsed =
        Number(normalized);

    return Number.isFinite(parsed)
        ? parsed
        : 0;
}


function fuelPhotoFormatLiters(
    value
) {
    return Number(value || 0)
        .toLocaleString(
            'pt-BR',
            {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }
        )
        + ' L';
}


function fuelPhotoTankIsArla(
    tank
) {
    const text =
        [
            tank?.product_name,
            tank?.product_slug,
            tank?.name
        ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase();

    return text.includes('arla');
}


function initializeFuelPhotoTankOptions() {
    const fuelSelect =
        document.getElementById(
            'fuelPhotoAiFuelTank'
        );

    const arlaSelect =
        document.getElementById(
            'fuelPhotoAiArlaTank'
        );

    if (!fuelSelect || !arlaSelect) {
        return;
    }

    const fuelTanks =
        fuelPhotoTanks.filter(
            tank =>
                !fuelPhotoTankIsArla(
                    tank
                )
        );

    const arlaTanks =
        fuelPhotoTanks.filter(
            tank =>
                fuelPhotoTankIsArla(
                    tank
                )
        );

    fuelSelect.innerHTML =
        '<option value="">Selecione o tanque...</option>'
        + fuelTanks.map(
            tank => `
                <option value="${tank.id}">
                    ${escapeFuelPhoto(
                        tank.name
                        + (
                            tank.product_name
                                ? ' · ' + tank.product_name
                                : ''
                        )
                    )}
                </option>
            `
        ).join('');

    arlaSelect.innerHTML =
        '<option value="">Selecione o tanque de ARLA...</option>'
        + arlaTanks.map(
            tank => `
                <option value="${tank.id}">
                    ${escapeFuelPhoto(
                        tank.name
                        + (
                            tank.product_name
                                ? ' · ' + tank.product_name
                                : ''
                        )
                    )}
                </option>
            `
        ).join('');

    /*
     * Se houver apenas uma opção possível,
     * pode pré-selecioná-la sem ambiguidade.
     */
    if (fuelTanks.length === 1) {
        fuelSelect.value =
            String(
                fuelTanks[0].id
            );
    }

    if (arlaTanks.length === 1) {
        arlaSelect.value =
            String(
                arlaTanks[0].id
            );
    }
}


function fuelPhotoCurrentTotals() {
    const rows =
        [
            ...document.querySelectorAll(
                '#fuelPhotoAiRows tr'
            )
        ];

    let fuelLiters = 0;
    let arlaLiters = 0;

    rows.forEach(
        tr => {
            fuelLiters +=
                fuelPhotoNumber(
                    tr.querySelector(
                        '[data-field="liters"]'
                    )?.value
                );

            arlaLiters +=
                fuelPhotoNumber(
                    tr.querySelector(
                        '[data-field="arla"]'
                    )?.value
                );
        }
    );

    return {
        fuel_liters:
            Math.max(
                0,
                fuelLiters
            ),

        arla_liters:
            Math.max(
                0,
                arlaLiters
            )
    };
}


function renderFuelPhotoTankStock(
    element,
    tank,
    required
) {
    if (!element) {
        return;
    }

    if (!tank) {
        element.className =
            'fuel-photo-ai-stock-summary';

        element.innerHTML =
            '<span>Selecione o tanque de origem.</span>';

        return;
    }

    const available =
        Number(
            tank.balance_liters || 0
        );

    const remaining =
        available - required;

    const sufficient =
        remaining >= 0;

    element.className =
        'fuel-photo-ai-stock-summary '
        + (
            sufficient
                ? 'is-ok'
                : 'is-critical'
        );

    element.innerHTML = `
        <div>
            <span>Saldo atual</span>
            <strong>
                ${fuelPhotoFormatLiters(
                    available
                )}
            </strong>
        </div>

        <div>
            <span>Necessário</span>
            <strong>
                ${fuelPhotoFormatLiters(
                    required
                )}
            </strong>
        </div>

        <div>
            <span>Saldo após</span>
            <strong>
                ${fuelPhotoFormatLiters(
                    remaining
                )}
            </strong>
        </div>

        <p>
            ${
                sufficient
                    ? '✓ Saldo suficiente para esta ficha.'
                    : '⚠ Saldo insuficiente para concluir estes abastecimentos.'
            }
        </p>
    `;
}


function recalculateFuelPhotoStocks() {
    const totals =
        fuelPhotoCurrentTotals();

    const fuelSelect =
        document.getElementById(
            'fuelPhotoAiFuelTank'
        );

    const arlaSelect =
        document.getElementById(
            'fuelPhotoAiArlaTank'
        );

    const arlaCard =
        document.getElementById(
            'fuelPhotoAiArlaTankCard'
        );

    const fuelTank =
        fuelPhotoTanks.find(
            tank =>
                Number(tank.id)
                === Number(
                    fuelSelect?.value
                )
        );

    const arlaTank =
        fuelPhotoTanks.find(
            tank =>
                Number(tank.id)
                === Number(
                    arlaSelect?.value
                )
        );

    renderFuelPhotoTankStock(
        document.getElementById(
            'fuelPhotoAiFuelStock'
        ),
        fuelTank,
        totals.fuel_liters
    );

    const hasArla =
        totals.arla_liters > 0.0001;

    if (arlaCard) {
        arlaCard.hidden =
            !hasArla;
    }

    if (hasArla) {
        renderFuelPhotoTankStock(
            document.getElementById(
                'fuelPhotoAiArlaStock'
            ),
            arlaTank,
            totals.arla_liters
        );
    }

    /*
     * Também mantém o KPI de litros coerente
     * com os valores efetivamente editados.
     */
    const calculated =
        document.getElementById(
            'fuelPhotoAiCalculated'
        );

    if (calculated) {
        calculated.textContent =
            fuelPhotoFormatLiters(
                totals.fuel_liters
            );
    }

    refreshFuelPhotoHumanReview();


    /*
     * Mantém o alerta de duplicidade atualizado
     * conforme veículo, litros ou tanque mudarem.
     */
    clearTimeout(
        window.fuelPhotoDuplicateTimer
    );

    window.fuelPhotoDuplicateTimer =
        setTimeout(
            refreshFuelPhotoDuplicatePreflight,
            350
        );

}


function invalidateFuelPhotoLaunchConfirmation() {
    const confirmation =
        document.getElementById(
            'fuelPhotoAiLaunchConfirmation'
        );

    if (
        confirmation
        && !confirmation.hidden
    ) {
        confirmation.hidden = true;

        showFuelPhotoFeedback(
            'A leitura foi alterada. Valide novamente antes de lançar.',
            'warning'
        );
    }
}



/*
|--------------------------------------------------------------------------
| FOTO IA - CORREÇÃO ESTRUTURAL DAS LINHAS
|--------------------------------------------------------------------------
|
| 1. Consolida automaticamente um caso seguro de linha quebrada pelo OCR.
| 2. Permite inserir linha acima/abaixo.
| 3. Permite excluir linha durante a revisão.
|
*/

let fuelPhotoStructuralToolsBusy = false;


function fuelPhotoMeterNumber(
    value
) {
    if (
        value === null
        || value === undefined
        || String(value).trim() === ''
    ) {
        return null;
    }

    let raw =
        String(value)
            .trim()
            .replace(/\s+/g, '');

    /*
     * Para medidores inteiros:
     * 51.117  -> 51117
     * 80.032  -> 80032
     */
    if (
        /^\d{1,3}(?:\.\d{3})+$/.test(raw)
    ) {
        raw =
            raw.replace(/\./g, '');
    }

    raw =
        raw.replace(',', '.');

    const number =
        Number(raw);

    return Number.isFinite(number)
        ? number
        : null;
}


function fuelPhotoVehicleForRow(
    tr
) {
    const id =
        tr.querySelector(
            '[data-field="vehicle_id"]'
        )?.value;

    if (!id) {
        return null;
    }

    return (
        fuelPhotoVehicles.find(
            vehicle =>
                Number(vehicle.id)
                === Number(id)
        )
        || null
    );
}


function fuelPhotoRowFieldValue(
    tr,
    field
) {
    return (
        tr.querySelector(
            `[data-field="${field}"]`
        )?.value
        ?? ''
    );
}


function fuelPhotoRowHasValue(
    tr,
    field
) {
    return (
        String(
            fuelPhotoRowFieldValue(
                tr,
                field
            )
        ).trim() !== ''
    );
}


function fuelPhotoIsMeterOnlyOrphanRow(
    tr
) {
    const vehicle =
        fuelPhotoRowFieldValue(
            tr,
            'vehicle_id'
        );

    const plate =
        fuelPhotoRowFieldValue(
            tr,
            'plate_read'
        );

    const liters =
        fuelPhotoMeterNumber(
            fuelPhotoRowFieldValue(
                tr,
                'liters'
            )
        );

    const previous =
        fuelPhotoMeterNumber(
            fuelPhotoRowFieldValue(
                tr,
                'previous_km'
            )
        );

    const next =
        fuelPhotoMeterNumber(
            fuelPhotoRowFieldValue(
                tr,
                'new_km'
            )
        );

    return (
        !String(vehicle).trim()
        && !String(plate).trim()
        && (
            liters === null
            || liters <= 0
        )
        && previous !== null
        && next !== null
        && previous > 0
        && next >= previous
    );
}


function fuelPhotoCanReceiveOrphanKm(
    tr
) {
    const vehicle =
        fuelPhotoVehicleForRow(
            tr
        );

    if (!vehicle) {
        return false;
    }

    /*
     * Esta fusão automática só vale para
     * veículos controlados por KM.
     *
     * Horímetro será tratado separadamente.
     */
    if (
        vehicle.km_control_enabled
        === false
    ) {
        return false;
    }

    const liters =
        fuelPhotoMeterNumber(
            fuelPhotoRowFieldValue(
                tr,
                'liters'
            )
        );

    return (
        liters !== null
        && liters > 0
        && !fuelPhotoRowHasValue(
            tr,
            'previous_km'
        )
        && !fuelPhotoRowHasValue(
            tr,
            'new_km'
        )
    );
}


function fuelPhotoAppendMergeNote(
    tr,
    sourceLine
) {
    const statusCell =
        tr.querySelector(
            '.fuel-photo-ai-status-cell'
        );

    if (
        !statusCell
        || statusCell.querySelector(
            '.fuel-photo-ai-merge-note'
        )
    ) {
        return;
    }

    const note =
        document.createElement(
            'small'
        );

    note.className =
        'fuel-photo-ai-merge-note';

    note.innerHTML =
        '<i class="bi bi-intersect"></i> '
        + 'KM recuperado automaticamente'
        + (
            sourceLine
                ? ` da linha ${sourceLine}.`
                : ' da linha anterior.'
        );

    statusCell.prepend(
        note
    );
}


function fuelPhotoAfterStructureChange() {

    if (
        typeof recalculateFuelPhotoStocks
        === 'function'
    ) {
        recalculateFuelPhotoStocks();
    }

    if (
        typeof refreshFuelPhotoHumanReview
        === 'function'
    ) {
        refreshFuelPhotoHumanReview();
    }

    if (
        typeof invalidateFuelPhotoLaunchConfirmation
        === 'function'
    ) {
        invalidateFuelPhotoLaunchConfirmation();
    }

    clearTimeout(
        window.fuelPhotoStructuralDuplicateTimer
    );

    window.fuelPhotoStructuralDuplicateTimer =
        setTimeout(
            () => {
                if (
                    typeof refreshFuelPhotoDuplicatePreflight
                    === 'function'
                ) {
                    refreshFuelPhotoDuplicatePreflight();
                }
            },
            250
        );
}


function fuelPhotoAutoMergeSplitRows() {

    if (fuelPhotoStructuralToolsBusy) {
        return 0;
    }

    const tbody =
        document.getElementById(
            'fuelPhotoAiRows'
        );

    if (!tbody) {
        return 0;
    }

    fuelPhotoStructuralToolsBusy =
        true;

    let merged =
        0;

    try {

        /*
         * Recomeça após cada fusão porque
         * a coleção de TRs muda.
         */
        let changed =
            true;

        while (changed) {

            changed =
                false;

            const rows =
                [
                    ...tbody.querySelectorAll(
                        ':scope > tr'
                    )
                ];

            for (
                let index = 0;
                index < rows.length - 1;
                index++
            ) {
                const orphan =
                    rows[index];

                const target =
                    rows[index + 1];

                if (
                    !fuelPhotoIsMeterOnlyOrphanRow(
                        orphan
                    )
                    || !fuelPhotoCanReceiveOrphanKm(
                        target
                    )
                ) {
                    continue;
                }

                const vehicle =
                    fuelPhotoVehicleForRow(
                        target
                    );

                const previous =
                    fuelPhotoMeterNumber(
                        fuelPhotoRowFieldValue(
                            orphan,
                            'previous_km'
                        )
                    );

                const next =
                    fuelPhotoMeterNumber(
                        fuelPhotoRowFieldValue(
                            orphan,
                            'new_km'
                        )
                    );

                const chm =
                    fuelPhotoMeterNumber(
                        vehicle?.current_km
                    );

                /*
                 * Critério forte:
                 * Último KM da linha órfã =
                 * KM atual cadastrado no CHM
                 * para o veículo da linha seguinte.
                 */
                if (
                    previous === null
                    || chm === null
                    || previous !== chm
                ) {
                    continue;
                }

                const targetPrevious =
                    target.querySelector(
                        '[data-field="previous_km"]'
                    );

                const targetNext =
                    target.querySelector(
                        '[data-field="new_km"]'
                    );

                if (
                    !targetPrevious
                    || !targetNext
                ) {
                    continue;
                }

                targetPrevious.value =
                    String(previous);

                targetNext.value =
                    String(next);

                target.dataset.previousKmConfirmed =
                    '0';

                target.dataset.newKmConfirmed =
                    '0';

                target.dataset.rowConfirmed =
                    '0';

                target.dataset.autoMerged =
                    '1';

                const sourceLine =
                    orphan.querySelector(
                        '.fuel-photo-ai-line'
                    )?.textContent?.trim();

                fuelPhotoAppendMergeNote(
                    target,
                    sourceLine
                );

                orphan.remove();

                if (
                    typeof recalculateFuelPhotoRow
                    === 'function'
                ) {
                    recalculateFuelPhotoRow(
                        target
                    );
                }

                merged++;
                changed = true;

                break;
            }
        }

    } finally {
        fuelPhotoStructuralToolsBusy =
            false;
    }

    if (merged > 0) {

        fuelPhotoAfterStructureChange();

        if (
            typeof showFuelPhotoFeedback
            === 'function'
        ) {
            showFuelPhotoFeedback(
                merged === 1
                    ? '1 linha dividida pelo OCR foi consolidada automaticamente.'
                    : `${merged} linhas divididas pelo OCR foram consolidadas automaticamente.`,
                'success'
            );
        }
    }

    return merged;
}


function fuelPhotoShiftVisibleLines(
    startRow,
    delta
) {
    let reached =
        false;

    document
        .querySelectorAll(
            '#fuelPhotoAiRows > tr'
        )
        .forEach(
            tr => {
                if (tr === startRow) {
                    reached = true;
                }

                if (!reached) {
                    return;
                }

                const cell =
                    tr.querySelector(
                        '.fuel-photo-ai-line'
                    );

                const number =
                    Number(
                        cell?.textContent
                    );

                if (
                    cell
                    && Number.isFinite(
                        number
                    )
                ) {
                    cell.textContent =
                        String(
                            Math.max(
                                1,
                                number + delta
                            )
                        );
                }
            }
        );
}


function fuelPhotoResetInsertedRow(
    tr
) {
    /*
     * cloneNode(true) também copia os data-* da linha original.
     * A linha nova precisa ser considerada estruturalmente nova,
     * para receber novamente + acima/abaixo e excluir.
     */
    delete tr.dataset.structureActions;
    delete tr.dataset.autoMerged;

    tr.classList.remove(
        'has-warning',
        'has-critical'
    );

    tr.dataset.rowConfirmed =
        '0';

    tr.dataset.vehicleConfirmed =
        '0';

    tr.dataset.previousKmConfirmed =
        '0';

    tr.dataset.newKmConfirmed =
        '0';

    tr.dataset.confirmingRow =
        '0';

    tr.dataset.manualRow =
        '1';

    [
        'estimated_time',
        'vehicle_id',
        'plate_read',
        'liters',
        'previous_km',
        'new_km',
        'arla'
    ].forEach(
        field => {
            const element =
                tr.querySelector(
                    `[data-field="${field}"]`
                );

            if (!element) {
                return;
            }

            element.value =
                '';

            element.classList.remove(
                'is-ok',
                'is-warning',
                'is-critical'
            );

            if (
                field === 'plate_read'
            ) {
                delete element.dataset
                    .ocrPlate;
            }
        }
    );

    tr.querySelectorAll(
        '.is-confirmed'
    ).forEach(
        element =>
            element.classList.remove(
                'is-confirmed'
            )
    );

    tr.querySelectorAll(
        '.fuel-photo-ai-km-reference,'
        + '.fuel-photo-ai-merge-note,'
        + '.fuel-photo-ai-duplicate-row-warning'
    ).forEach(
        element =>
            element.remove()
    );

    const distance =
        tr.querySelector(
            '.fuel-photo-ai-distance'
        );

    if (distance) {
        distance.innerHTML =
            '<strong class="fuel-photo-ai-distance-sheet">'
            + 'Folha: —'
            + '</strong>'
            + '<span class="fuel-photo-ai-distance-db">'
            + 'CHM: —'
            + '</span>'
            + '<small>Histórico insuficiente</small>';
    }

    const statusCell =
        tr.querySelector(
            '.fuel-photo-ai-status-cell'
        );

    if (statusCell) {
        statusCell.innerHTML =
            '<strong>⚠ Revisar</strong>'
            + '<small class="fuel-photo-ai-manual-row-note">'
            + 'Linha inserida manualmente.'
            + '</small>'
            + '<button'
            + ' type="button"'
            + ' class="fuel-photo-ai-row-confirm-btn"'
            + ' onclick="confirmFuelPhotoRow(this)"'
            + ' title="Confirmar todos os dados desta linha"'
            + '>'
            + '<i class="bi bi-check-lg"></i>'
            + '<span>Confirmar linha</span>'
            + '</button>';
    }
}


function fuelPhotoInsertReviewRow(
    button,
    position
) {
    const current =
        button.closest('tr');

    const tbody =
        current?.parentElement;

    if (
        !current
        || !tbody
    ) {
        return;
    }

    const clone =
        current.cloneNode(
            true
        );

    /*
     * Remove ações estruturais herdadas.
     * Elas serão recriadas após a inserção.
     */
    clone
        .querySelectorAll(
            '.fuel-photo-ai-structure-actions,'
            + '.fuel-photo-ai-row-delete-btn'
        )
        .forEach(
            element =>
                element.remove()
        );

    const currentLineCell =
        current.querySelector(
            '.fuel-photo-ai-line'
        );

    const currentLine =
        Number(
            currentLineCell?.textContent
        );

    if (
        position === 'before'
    ) {
        fuelPhotoShiftVisibleLines(
            current,
            1
        );

        tbody.insertBefore(
            clone,
            current
        );

        const line =
            clone.querySelector(
                '.fuel-photo-ai-line'
            );

        if (
            line
            && Number.isFinite(
                currentLine
            )
        ) {
            line.textContent =
                String(currentLine);
        }

    } else {

        const next =
            current.nextElementSibling;

        if (next) {
            fuelPhotoShiftVisibleLines(
                next,
                1
            );
        }

        tbody.insertBefore(
            clone,
            next
        );

        const line =
            clone.querySelector(
                '.fuel-photo-ai-line'
            );

        if (
            line
            && Number.isFinite(
                currentLine
            )
        ) {
            line.textContent =
                String(
                    currentLine + 1
                );
        }
    }

    fuelPhotoResetInsertedRow(
        clone
    );

    /*
     * O clone deve receber seus próprios controles:
     * + acima, + abaixo e excluir.
     */
    delete clone.dataset.structureActions;

    fuelPhotoEnhanceReviewRow(
        clone
    );

    fuelPhotoAfterStructureChange();

    clone.querySelector(
        '[data-field="vehicle_id"]'
    )?.focus();
}


function fuelPhotoDeleteReviewRow(
    button
) {
    const tr =
        button.closest('tr');

    if (!tr) {
        return;
    }

    const line =
        tr.querySelector(
            '.fuel-photo-ai-line'
        )?.textContent?.trim();

    if (
        !window.confirm(
            `Excluir a linha ${line || ''} desta revisão?`
        )
    ) {
        return;
    }

    const next =
        tr.nextElementSibling;

    tr.remove();

    if (next) {
        fuelPhotoShiftVisibleLines(
            next,
            -1
        );
    }

    fuelPhotoAfterStructureChange();
}


function fuelPhotoEnhanceReviewRow(
    tr
) {
    if (
        !tr
        || tr.dataset.structureActions
            === '1'
    ) {
        return;
    }

    const line =
        tr.querySelector(
            '.fuel-photo-ai-line'
        );

    const lineCell =
        line?.closest('td');

    const statusCell =
        tr.querySelector(
            '.fuel-photo-ai-status-cell'
        );

    if (
        !lineCell
        || !statusCell
    ) {
        return;
    }

    tr.dataset.structureActions =
        '1';

    lineCell.classList.add(
        'fuel-photo-ai-line-cell'
    );

    const actions =
        document.createElement(
            'div'
        );

    actions.className =
        'fuel-photo-ai-structure-actions';

    actions.innerHTML =
        `
            <button
                type="button"
                class="fuel-photo-ai-structure-btn"
                onclick="fuelPhotoInsertReviewRow(this, 'before')"
                title="Inserir linha acima"
                aria-label="Inserir linha acima"
            >
                <i class="bi bi-plus-lg"></i>
            </button>

            <button
                type="button"
                class="fuel-photo-ai-structure-btn"
                onclick="fuelPhotoInsertReviewRow(this, 'after')"
                title="Inserir linha abaixo"
                aria-label="Inserir linha abaixo"
            >
                <i class="bi bi-plus-lg"></i>
            </button>
        `;

    lineCell.appendChild(
        actions
    );

    if (
        !statusCell.querySelector(
            '.fuel-photo-ai-row-delete-btn'
        )
    ) {
        const remove =
            document.createElement(
                'button'
            );

        remove.type =
            'button';

        remove.className =
            'fuel-photo-ai-row-delete-btn';

        remove.title =
            'Excluir esta linha';

        remove.setAttribute(
            'aria-label',
            'Excluir esta linha'
        );

        remove.onclick =
            function () {
                fuelPhotoDeleteReviewRow(
                    this
                );
            };

        remove.innerHTML =
            '<i class="bi bi-trash3"></i>';

        statusCell.appendChild(
            remove
        );
    }
}


function fuelPhotoEnhanceReviewRows() {
    document
        .querySelectorAll(
            '#fuelPhotoAiRows > tr'
        )
        .forEach(
            tr => {
                fuelPhotoEnhanceReviewRow(
                    tr
                );

                syncFuelPhotoRowMeterControl(
                    tr
                );
            }
        );
}


/*
 * Quando o operador troca manualmente o veículo,
 * a regra KM/Horas deve acompanhar o cadastro
 * do veículo atualmente selecionado.
 */
if (
    !window.fuelPhotoMeterControlListenerInstalled
) {
    window.fuelPhotoMeterControlListenerInstalled =
        true;

    document.addEventListener(
        'change',
        function (event) {
            if (
                !event.target.matches(
                    '#fuelPhotoAiRows [data-field="vehicle_id"]'
                )
            ) {
                return;
            }

            const tr =
                event.target.closest(
                    'tr'
                );

            /*
             * Outras rotinas do sistema também
             * processam o change. Rodamos depois
             * delas para corrigir a apresentação
             * final do medidor.
             */
            /*
             * Primeiro deixa as rotinas de associação do
             * veículo atualizarem placa, confiança e demais
             * referências. Depois recalcula o medidor.
             */
            setTimeout(
                () => {
                    syncFuelPhotoRowMeterControl(
                        tr
                    );
                },
                30
            );

            /*
             * Segunda sincronização defensiva para linhas
             * que nasceram sem veículo e são reconstruídas
             * pela rotina de associação manual.
             */
            setTimeout(
                () => {
                    syncFuelPhotoRowMeterControl(
                        tr
                    );
                },
                120
            );
        }
    );
}


function setupFuelPhotoStructuralTools() {
    const tbody =
        document.getElementById(
            'fuelPhotoAiRows'
        );

    if (
        !tbody
        || tbody.dataset.structuralObserver
            === '1'
    ) {
        return;
    }

    tbody.dataset.structuralObserver =
        '1';

    let timer =
        null;

    const run =
        () => {
            clearTimeout(
                timer
            );

            timer =
                setTimeout(
                    () => {
                        if (
                            fuelPhotoStructuralToolsBusy
                        ) {
                            return;
                        }

                        fuelPhotoAutoMergeSplitRows();
                        fuelPhotoEnhanceReviewRows();
                    },
                    20
                );
        };

    const observer =
        new MutationObserver(
            run
        );

    observer.observe(
        tbody,
        {
            childList: true
        }
    );

    fuelPhotoEnhanceReviewRows();
    fuelPhotoAutoMergeSplitRows();
}


if (
    document.readyState
    === 'loading'
) {
    document.addEventListener(
        'DOMContentLoaded',
        setupFuelPhotoStructuralTools
    );
} else {
    setupFuelPhotoStructuralTools();
}


function fuelPhotoHumanReviewStatus() {
    const rows =
        [
            ...document.querySelectorAll(
                '#fuelPhotoAiRows tr'
            )
        ];

    return {
        total:
            rows.length,

        confirmed:
            rows.filter(
                tr =>
                    tr.dataset.rowConfirmed
                    === '1'
            ).length,

        rows:
            rows.map(
                (tr, index) => ({
                    tr,

                    line:
                        Number(
                            tr.querySelector(
                                '.fuel-photo-ai-line'
                            )?.textContent
                            || index + 1
                        ),

                    confirmed:
                        tr.dataset.rowConfirmed
                        === '1'
                })
            )
    };
}


function invalidateFuelPhotoRowConfirmation(
    tr
) {
    if (!tr) {
        return;
    }

    tr.dataset.rowConfirmed =
        '0';

    const button =
        tr.querySelector(
            '.fuel-photo-ai-row-confirm-btn'
        );

    if (button) {
        button.classList.remove(
            'is-confirmed'
        );

        button.innerHTML =
            '<i class="bi bi-check-lg"></i>'
            + '<span>Confirmar linha</span>';

        button.title =
            'Confirmar todos os dados desta linha';
    }

    refreshFuelPhotoHumanReview();
    invalidateFuelPhotoLaunchConfirmation();
}


function confirmFuelPhotoRow(
    button
) {
    const tr =
        button.closest('tr');

    if (!tr) {
        return;
    }

    tr.dataset.confirmingRow =
        '1';

    const line =
        Number(
            tr.querySelector(
                '.fuel-photo-ai-line'
            )?.textContent
            || 0
        );

    const vehicle =
        tr.querySelector(
            '[data-field="vehicle_id"]'
        );

    const plate =
        tr.querySelector(
            '[data-field="plate_read"]'
        );

    const liters =
        tr.querySelector(
            '[data-field="liters"]'
        );

    const previousKm =
        tr.querySelector(
            '[data-field="previous_km"]'
        );

    const newKm =
        tr.querySelector(
            '[data-field="new_km"]'
        );

    const arla =
        tr.querySelector(
            '[data-field="arla"]'
        );

    const problems = [];

    if (!vehicle?.value) {
        problems.push(
            'veículo'
        );
    }

    if (!plate?.value?.trim()) {
        problems.push(
            'placa'
        );
    }

    const litersValue =
        Number(
            liters?.value
        );

    if (
        !Number.isFinite(litersValue)
        || litersValue <= 0
    ) {
        problems.push(
            'litros'
        );
    }

    const previousKmValue =
        Number(
            previousKm?.value
        );

    if (
        previousKm?.value === ''
        || !Number.isFinite(
            previousKmValue
        )
        || previousKmValue < 0
    ) {
        problems.push(
            'Último KM'
        );
    }

    const newKmValue =
        Number(
            newKm?.value
        );

    if (
        newKm?.value === ''
        || !Number.isFinite(
            newKmValue
        )
        || newKmValue < 0
    ) {
        problems.push(
            'Novo KM'
        );
    }

    if (
        arla?.value !== ''
        && arla?.value != null
    ) {
        const arlaValue =
            Number(
                arla.value
            );

        if (
            !Number.isFinite(
                arlaValue
            )
            || arlaValue < 0
        ) {
            problems.push(
                'ARLA'
            );
        }
    }

    if (problems.length) {
        delete tr.dataset.confirmingRow;

        showFuelPhotoFeedback(
            `Linha ${line}: revise ${problems.join(', ')} antes de confirmar.`,
            'error'
        );

        return;
    }

    /*
     * Confirma internamente o veículo/placa.
     * Mantemos a rotina existente para usar a placa
     * canônica do cadastro do CHM.
     */
    const plateButton =
        tr.querySelector(
            '.fuel-photo-ai-plate-confirm .fuel-photo-ai-confirm-btn'
        );

    if (plateButton) {
        confirmFuelPhotoPlate(
            plateButton
        );
    }

    /*
     * Confirma Último KM.
     */
    const previousKmButton =
        tr.querySelector(
            '.fuel-photo-ai-km-confirm-btn'
        );

    if (previousKmButton) {
        confirmFuelPhotoPreviousKm(
            previousKmButton
        );
    }

    /*
     * Confirma conscientemente o Novo KM.
     * Isso é necessário para que um salto suspeito
     * possa ser aceito pelo VehicleReadingService.
     */
    const newKmButton =
        tr.querySelector(
            '.fuel-photo-ai-new-km-confirm-btn'
        );

    if (newKmButton) {
        confirmFuelPhotoNewKm(
            newKmButton
        );
    }

    /*
     * Depois das rotinas internas, a linha inteira
     * passa a ser considerada revisada pelo operador.
     */
    tr.dataset.rowConfirmed =
        '1';

    delete tr.dataset.confirmingRow;

    button.classList.add(
        'is-confirmed'
    );

    button.innerHTML =
        '<i class="bi bi-check-circle-fill"></i>'
        + '<span>Linha confirmada</span>';

    button.title =
        'Todos os dados desta linha foram conferidos';

    refreshFuelPhotoHumanReview();

    showFuelPhotoFeedback(
        `Linha ${line} confirmada pelo operador.`,
        'success'
    );
}


function ensureFuelPhotoRowConfirmButtons() {
    const rows =
        [
            ...document.querySelectorAll(
                '#fuelPhotoAiRows tr'
            )
        ];

    rows.forEach(
        tr => {
            if (
                tr.querySelector(
                    '.fuel-photo-ai-row-confirm-btn'
                )
            ) {
                return;
            }

            const statusCell =
                tr.querySelector(
                    '.fuel-photo-ai-status-cell'
                )
                || tr.lastElementChild;

            if (!statusCell) {
                return;
            }

            const button =
                document.createElement(
                    'button'
                );

            button.type =
                'button';

            button.className =
                'fuel-photo-ai-row-confirm-btn';

            button.title =
                'Confirmar todos os dados desta linha';

            button.innerHTML =
                '<i class="bi bi-check-lg"></i>'
                + '<span>Confirmar linha</span>';

            button.addEventListener(
                'click',
                () =>
                    confirmFuelPhotoRow(
                        button
                    )
            );

            statusCell.appendChild(
                button
            );
        }
    );
}


function refreshFuelPhotoHumanReview() {
    ensureFuelPhotoRowConfirmButtons();

    const review =
        fuelPhotoHumanReviewStatus();

    const tbody =
        document.getElementById(
            'fuelPhotoAiRows'
        );

    const table =
        tbody?.closest(
            'table'
        );

    if (!table) {
        return;
    }

    let box =
        document.getElementById(
            'fuelPhotoAiHumanReview'
        );

    if (!box) {
        box =
            document.createElement(
                'div'
            );

        box.id =
            'fuelPhotoAiHumanReview';

        box.className =
            'fuel-photo-ai-human-review';

        table.parentElement
            ?.insertBefore(
                box,
                table
            );
    }

    const complete =
        review.total > 0
        && review.confirmed
            === review.total;

    const percent =
        review.total
            ? Math.round(
                review.confirmed
                / review.total
                * 100
            )
            : 0;

    box.classList.toggle(
        'is-complete',
        complete
    );

    box.innerHTML = `
        <div class="fuel-photo-ai-human-review-head">

            <div>
                <span>REVISÃO HUMANA</span>

                <strong>
                    ${review.confirmed}
                    de
                    ${review.total}
                    linhas confirmadas
                </strong>
            </div>

            <div class="fuel-photo-ai-human-review-count">
                ${
                    complete
                        ? '✓ Revisão concluída'
                        : percent + '%'
                }
            </div>

        </div>

        <div class="fuel-photo-ai-human-review-track">
            <span
                style="width: ${percent}%"
            ></span>
        </div>

        <small>
            Revise todos os dados de cada abastecimento
            e use “Confirmar linha” antes de validar a ficha.
        </small>
    `;
}


function buildFuelPhotoLaunchPayload() {
    const operationDate =
        document.getElementById(
            'fuelPhotoAiOperationDate'
        )?.value;

    const fuelTankId =
        document.getElementById(
            'fuelPhotoAiFuelTank'
        )?.value;

    const arlaTankId =
        document.getElementById(
            'fuelPhotoAiArlaTank'
        )?.value;

    const rows =
        [
            ...document.querySelectorAll(
                '#fuelPhotoAiRows tr'
            )
        ];

    return {
        confirm_duplicates:
            Boolean(
                document.getElementById(
                    'fuelPhotoAiDuplicateConfirm'
                )?.checked
            ),

        fuel_tank_id:
            fuelTankId
                ? Number(fuelTankId)
                : null,

        arla_tank_id:
            arlaTankId
                ? Number(arlaTankId)
                : null,

        rows:
            rows.map(
                (tr, index) => {
                    const time =
                        tr.querySelector(
                            '[data-field="estimated_time"]'
                        )?.value;

                    const vehicle =
                        tr.querySelector(
                            '[data-field="vehicle_id"]'
                        )?.value;

                    const newKm =
                        tr.querySelector(
                            '[data-field="new_km"]'
                        )?.value;

                    const newKmConfirmButton =
                        tr.querySelector(
                            '.fuel-photo-ai-new-km-confirm-btn'
                        );

                    const newKmConfirmed =
                        Boolean(
                            newKmConfirmButton
                            && newKmConfirmButton.classList.contains(
                                'is-confirmed'
                            )
                            && newKm !== ''
                            && newKm != null
                            && Number(
                                newKmConfirmButton.dataset.confirmedValue
                            ) === Number(newKm)
                        );

                    const liters =
                        tr.querySelector(
                            '[data-field="liters"]'
                        )?.value;

                    const arla =
                        tr.querySelector(
                            '[data-field="arla"]'
                        )?.value;

                    const lineText =
                        tr.querySelector(
                            '.fuel-photo-ai-line'
                        )?.textContent;

                    return {
                        line:
                            Number(
                                String(
                                    lineText
                                    || index + 1
                                ).trim()
                            ),

                        vehicle_id:
                            vehicle
                                ? Number(vehicle)
                                : null,

                        filled_at:
                            (
                                operationDate
                                && time
                            )
                                ? `${operationDate} ${time}:00`
                                : null,

                        vehicle_km:
                            newKm === ''
                            || newKm == null
                                ? null
                                : Number(newKm),

                        liters:
                            liters === ''
                            || liters == null
                                ? null
                                : Number(liters),

                        arla_liters:
                            arla === ''
                            || arla == null
                                ? 0
                                : Number(arla),

                        /*
                         * O ✓ atual confirma o KM anterior
                         * impresso na folha, não o Novo KM.
                         *
                         * Portanto não usamos essa confirmação
                         * para ignorar validações do hodômetro.
                         */
                        km_reading_confirmed:
                            newKmConfirmed
                    };
                }
            )
    };
}


async function refreshFuelPhotoDuplicatePreflight() {
    const rows =
        [
            ...document.querySelectorAll(
                '#fuelPhotoAiRows tr'
            )
        ];

    if (!rows.length) {
        return;
    }

    const operationDate =
        document.getElementById(
            'fuelPhotoAiOperationDate'
        )?.value;

    const fuelTankId =
        document.getElementById(
            'fuelPhotoAiFuelTank'
        )?.value;

    if (
        !operationDate
        || !fuelTankId
    ) {
        return;
    }

    const arlaTankId =
        document.getElementById(
            'fuelPhotoAiArlaTank'
        )?.value;

    const payload = {
        fuel_tank_id:
            Number(fuelTankId),

        arla_tank_id:
            arlaTankId
                ? Number(arlaTankId)
                : null,

        rows:
            rows
                .map(
                    (tr, index) => {
                        const vehicle =
                            tr.querySelector(
                                '[data-field="vehicle_id"]'
                            )?.value;

                        const liters =
                            tr.querySelector(
                                '[data-field="liters"]'
                            )?.value;

                        const arla =
                            tr.querySelector(
                                '[data-field="arla"]'
                            )?.value;

                        const line =
                            Number(
                                tr.querySelector(
                                    '.fuel-photo-ai-line'
                                )?.textContent
                                || index + 1
                            );

                        if (
                            !vehicle
                            || liters === ''
                            || !Number.isFinite(
                                Number(liters)
                            )
                            || Number(liters) <= 0
                        ) {
                            return null;
                        }

                        return {
                            line,
                            vehicle_id:
                                Number(vehicle),

                            /*
                             * O backend usa apenas a data
                             * na busca de duplicidade.
                             */
                            filled_at:
                                `${operationDate} 00:00:00`,

                            liters:
                                Number(liters),

                            arla_liters:
                                arla === ''
                                || arla == null
                                    ? 0
                                    : Number(arla)
                        };
                    }
                )
                .filter(Boolean)
    };

    if (!payload.rows.length) {
        return;
    }

    try {
        const duplicates =
            await checkFuelPhotoDuplicates(
                payload
            );

        renderFuelPhotoDuplicates(
            duplicates
        );

    } catch (error) {
        /*
         * A pré-conferência não deve impedir
         * o restante da revisão da ficha.
         */
        console.warn(
            'Não foi possível pré-conferir duplicidades.',
            error
        );
    }
}


async function checkFuelPhotoDuplicates(
    payload
) {
    const csrf =
        document.querySelector(
            'meta[name="csrf-token"]'
        )?.content;

    const response =
        await fetch(
            fuelPhotoDuplicateCheckUrl,
            {
                method: 'POST',

                headers: {
                    'Accept':
                        'application/json',

                    'Content-Type':
                        'application/json',

                    'X-CSRF-TOKEN':
                        csrf || ''
                },

                body:
                    JSON.stringify(
                        payload
                    )
            }
        );

    const data =
        await response.json();

    if (!response.ok) {
        throw new Error(
            data.message
            || 'Não foi possível conferir duplicidades.'
        );
    }

    return data.duplicates || [];
}


function syncFuelPhotoDuplicateRowWarnings(
    duplicates
) {
    const rows =
        [
            ...document.querySelectorAll(
                '#fuelPhotoAiRows tr'
            )
        ];

    /*
     * Primeiro remove somente os avisos de
     * duplicidade inseridos por esta rotina.
     */
    rows.forEach(
        tr => {
            tr.querySelectorAll(
                '.fuel-photo-ai-duplicate-row-warning'
            ).forEach(
                element =>
                    element.remove()
            );

            const title =
                tr.querySelector(
                    '.fuel-photo-ai-status-cell strong'
                )
                || tr.lastElementChild
                    ?.querySelector(
                        'strong'
                    );

            if (
                title
                && title.dataset.duplicateStatus
                    === '1'
            ) {
                title.textContent =
                    '✓ OK';

                delete title.dataset
                    .duplicateStatus;
            }
        }
    );

    /*
     * Agrupa porque uma mesma linha pode,
     * em tese, possuir duplicidade de Diesel
     * e também de ARLA.
     */
    const grouped = {};

    (duplicates || []).forEach(
        duplicate => {
            const line =
                Number(
                    duplicate.line
                );

            if (!grouped[line]) {
                grouped[line] = [];
            }

            grouped[line].push(
                duplicate
            );
        }
    );

    Object.entries(
        grouped
    ).forEach(
        ([line, lineDuplicates]) => {
            const tr =
                rows.find(
                    row =>
                        Number(
                            row.querySelector(
                                '.fuel-photo-ai-line'
                            )?.textContent
                        ) === Number(line)
                );

            if (!tr) {
                return;
            }

            const statusCell =
                tr.querySelector(
                    '.fuel-photo-ai-status-cell'
                )
                || tr.lastElementChild;

            if (!statusCell) {
                return;
            }

            const title =
                statusCell.querySelector(
                    'strong'
                );

            if (title) {
                title.textContent =
                    '⚠ Possível duplicidade';

                title.dataset.duplicateStatus =
                    '1';
            }

            lineDuplicates.forEach(
                duplicate => {
                    const warning =
                        document.createElement(
                            'div'
                        );

                    warning.className =
                        'fuel-photo-ai-duplicate-row-warning';

                    const type =
                        duplicate.type === 'arla'
                            ? 'ARLA'
                            : 'Combustível';

                    const km =
                        duplicate.vehicle_km != null
                            ? ` · KM ${Number(
                                duplicate.vehicle_km
                            ).toLocaleString(
                                'pt-BR'
                            )}`
                            : '';

                    warning.innerHTML = `
                        <i class="bi bi-exclamation-triangle"></i>

                        <span>
                            ${type}: já existe neste dia
                            um lançamento de
                            <strong>
                                ${fuelPhotoFormatLiters(
                                    duplicate.quantity_liters
                                )}
                            </strong>
                            ${km}.
                        </span>
                    `;

                    statusCell.insertBefore(
                        warning,
                        statusCell.querySelector(
                            '.fuel-photo-ai-row-confirm-btn'
                        )
                        || null
                    );
                }
            );
        }
    );
}


function renderFuelPhotoDuplicates(
    duplicates
) {
    fuelPhotoDetectedDuplicates =
        duplicates || [];


    syncFuelPhotoDuplicateRowWarnings(
        fuelPhotoDetectedDuplicates
    );

    const box =
        document.getElementById(
            'fuelPhotoAiDuplicateWarning'
        );

    const launchButton =
        document.getElementById(
            'fuelPhotoAiLaunchButton'
        );

    if (!box || !launchButton) {
        return;
    }

    if (!fuelPhotoDetectedDuplicates.length) {
        box.hidden = true;
        box.innerHTML = '';
        launchButton.disabled = false;
        return;
    }

    const items =
        fuelPhotoDetectedDuplicates
            .map(
                duplicate => {
                    const type =
                        duplicate.type === 'arla'
                            ? 'ARLA'
                            : 'Combustível';

                    return `
                        <li>
                            <strong>
                                Linha ${duplicate.line} · ${type}
                            </strong>

                            <span>
                                Já existe o lançamento
                                #${duplicate.filling_id}
                                em ${escapeFuelPhoto(
                                    duplicate.filled_at
                                )}
                                com ${fuelPhotoFormatLiters(
                                    duplicate.quantity_liters
                                )}
                                ${
                                    duplicate.vehicle_km != null
                                        ? ` · KM ${Number(
                                            duplicate.vehicle_km
                                        ).toLocaleString('pt-BR')}`
                                        : ''
                                }.
                            </span>
                        </li>
                    `;
                }
            )
            .join('');

    box.hidden = false;

    box.innerHTML = `
        <div class="fuel-photo-ai-duplicate-title">
            <i class="bi bi-exclamation-triangle"></i>

            <div>
                <strong>
                    Possível duplicidade
                </strong>

                <span>
                    Mesmo veículo, mesma data e mesma quantidade.
                    Confira antes de continuar.
                </span>
            </div>
        </div>

        <ul>
            ${items}
        </ul>

        <label class="fuel-photo-ai-duplicate-confirm">
            <input
                type="checkbox"
                id="fuelPhotoAiDuplicateConfirm"
                onchange="syncFuelPhotoDuplicateConfirmation()"
            >

            <span>
                Estou ciente e desejo registrar
                mesmo assim.
            </span>
        </label>
    `;

    launchButton.disabled = true;
}


function syncFuelPhotoDuplicateConfirmation() {
    const checkbox =
        document.getElementById(
            'fuelPhotoAiDuplicateConfirm'
        );

    const button =
        document.getElementById(
            'fuelPhotoAiLaunchButton'
        );

    if (!button) {
        return;
    }

    button.disabled =
        fuelPhotoDetectedDuplicates.length > 0
        && !checkbox?.checked;
}


async function validateFuelPhotoReading() {
    const payload =
        buildFuelPhotoLaunchPayload();

    const rows =
        [
            ...document.querySelectorAll(
                '#fuelPhotoAiRows tr'
            )
        ];

    if (!rows.length) {
        showFuelPhotoFeedback(
            'Não há linhas para validar.',
            'error'
        );

        return;
    }

    const problems = [];

    /*
     * A IA apenas sugere os dados.
     * Cada linha precisa de confirmação humana explícita.
     */
    const humanReview =
        fuelPhotoHumanReviewStatus();

    humanReview.rows.forEach(
        row => {
            if (!row.confirmed) {
                problems.push(
                    `Linha ${row.line}: revise os dados e confirme a linha.`
                );
            }
        }
    );

    const operationDate =
        document.getElementById(
            'fuelPhotoAiOperationDate'
        )?.value;

    const startTime =
        document.getElementById(
            'fuelPhotoAiStartTime'
        )?.value;

    const endTime =
        document.getElementById(
            'fuelPhotoAiEndTime'
        )?.value;

    if (!operationDate) {
        problems.push(
            'Informe a data dos abastecimentos.'
        );
    }

    if (!startTime) {
        problems.push(
            'Informe a hora de início.'
        );
    }

    if (!endTime) {
        problems.push(
            'Informe a hora de fim.'
        );
    }

    const fuelTank =
        fuelPhotoTanks.find(
            tank =>
                Number(tank.id)
                === Number(
                    payload.fuel_tank_id
                )
        );

    if (!fuelTank) {
        problems.push(
            'Selecione o tanque de combustível.'
        );
    }

    const totals =
        fuelPhotoCurrentTotals();

    if (
        fuelTank
        && totals.fuel_liters
            > Number(
                fuelTank.balance_liters
                || 0
            )
    ) {
        problems.push(
            'Saldo insuficiente no tanque de combustível.'
        );
    }

    const hasArla =
        totals.arla_liters > 0.0001;

    let arlaTank = null;

    if (hasArla) {
        arlaTank =
            fuelPhotoTanks.find(
                tank =>
                    Number(tank.id)
                    === Number(
                        payload.arla_tank_id
                    )
            );

        if (!arlaTank) {
            problems.push(
                'Selecione o tanque de ARLA.'
            );

        } else if (
            totals.arla_liters
            > Number(
                arlaTank.balance_liters
                || 0
            )
        ) {
            problems.push(
                'Saldo insuficiente no tanque de ARLA.'
            );
        }
    }

    payload.rows.forEach(
        (row, index) => {
            if (!row.vehicle_id) {
                problems.push(
                    `Linha ${index + 1}: selecione o veículo.`
                );
            }

            if (
                !Number.isFinite(
                    row.liters
                )
                || row.liters <= 0
            ) {
                problems.push(
                    `Linha ${index + 1}: informe os litros.`
                );
            }

            if (!row.filled_at) {
                problems.push(
                    `Linha ${index + 1}: informe o horário.`
                );
            }

            if (
                row.vehicle_km !== null
                && (
                    !Number.isFinite(
                        row.vehicle_km
                    )
                    || row.vehicle_km < 0
                )
            ) {
                problems.push(
                    `Linha ${index + 1}: Novo KM inválido.`
                );
            }

            if (
                !Number.isFinite(
                    row.arla_liters
                )
                || row.arla_liters < 0
            ) {
                problems.push(
                    `Linha ${index + 1}: ARLA inválido.`
                );
            }
        }
    );

    if (problems.length) {
        cancelFuelPhotoLaunchConfirmation();

        showFuelPhotoFeedback(
            problems.join('<br>'),
            'error',
            true
        );

        return;
    }

    try {
        const duplicates =
            await checkFuelPhotoDuplicates(
                payload
            );

        renderFuelPhotoDuplicates(
            duplicates
        );

    } catch (error) {
        showFuelPhotoFeedback(
            error.message
            || 'Não foi possível conferir duplicidades.',
            'error'
        );

        return;
    }


    const confirmation =
        document.getElementById(
            'fuelPhotoAiLaunchConfirmation'
        );

    const summary =
        document.getElementById(
            'fuelPhotoAiLaunchSummary'
        );

    const buttonText =
        document.getElementById(
            'fuelPhotoAiLaunchButtonText'
        );

    const firstTime =
        payload.rows[0]?.filled_at
            ?.slice(11, 16)
        || '—';

    const lastTime =
        payload.rows[
            payload.rows.length - 1
        ]?.filled_at
            ?.slice(11, 16)
        || '—';

    const fuelName =
        fuelTank
            ? fuelTank.name
                + (
                    fuelTank.product_name
                        ? ' · '
                            + fuelTank.product_name
                        : ''
                )
            : '—';

    const arlaBlock =
        hasArla
            ? `
                <div>
                    <span>ARLA</span>
                    <strong>
                        ${fuelPhotoFormatLiters(
                            totals.arla_liters
                        )}
                    </strong>
                    <small>
                        ${escapeFuelPhoto(
                            arlaTank?.name
                            || 'Tanque não informado'
                        )}
                    </small>
                </div>
            `
            : `
                <div>
                    <span>ARLA</span>
                    <strong>0,00 L</strong>
                    <small>
                        Nenhum ARLA nesta ficha
                    </small>
                </div>
            `;

    summary.innerHTML = `
        <div>
            <span>Abastecimentos</span>
            <strong>
                ${payload.rows.length}
            </strong>
            <small>
                linhas da ficha
            </small>
        </div>

        <div>
            <span>Combustível</span>
            <strong>
                ${fuelPhotoFormatLiters(
                    totals.fuel_liters
                )}
            </strong>
            <small>
                ${escapeFuelPhoto(
                    fuelName
                )}
            </small>
        </div>

        ${arlaBlock}

        <div>
            <span>Data</span>
            <strong>
                ${operationDate
                    .split('-')
                    .reverse()
                    .join('/')}
            </strong>
            <small>
                ${firstTime}
                → ${lastTime}
            </small>
        </div>
    `;

    if (buttonText) {
        buttonText.textContent =
            `Confirmar e lançar ${payload.rows.length} abastecimento`
            + (
                payload.rows.length === 1
                    ? ''
                    : 's'
            );
    }

    confirmation.hidden = false;

    showFuelPhotoFeedback(
        'Leitura validada. '
        + 'Revise o resumo final antes de confirmar o lançamento.',
        'success'
    );

    confirmation.scrollIntoView({
        behavior: 'smooth',
        block: 'nearest'
    });
}


function cancelFuelPhotoLaunchConfirmation() {
    const confirmation =
        document.getElementById(
            'fuelPhotoAiLaunchConfirmation'
        );

    if (confirmation) {
        confirmation.hidden = true;
    }
}


async function storeFuelPhotoImport() {
    const confirmation =
        document.getElementById(
            'fuelPhotoAiLaunchConfirmation'
        );

    const button =
        document.getElementById(
            'fuelPhotoAiLaunchButton'
        );

    if (
        !confirmation
        || confirmation.hidden
    ) {
        return;
    }

    const payload =
        buildFuelPhotoLaunchPayload();

    if (!payload.rows.length) {
        return;
    }

    button.disabled = true;

    const originalHtml =
        button.innerHTML;

    button.innerHTML =
        '<span class="spinner-border spinner-border-sm"></span>'
        + '<span>Lançando...</span>';

    try {
        const csrf =
            document.querySelector(
                'meta[name="csrf-token"]'
            )?.content;

        const formData =
            new FormData();

        formData.append(
            'payload',
            JSON.stringify(
                payload
            )
        );

        const sourceInput =
            document.getElementById(
                'fuelPhotoAiFile'
            );

        if (
            sourceInput?.files?.length
        ) {
            formData.append(
                'source_file',
                sourceInput.files[0]
            );
        }

        const response =
            await fetch(
                fuelPhotoStoreUrl,
                {
                    method: 'POST',

                    headers: {
                        'Accept':
                            'application/json',

                        /*
                         * Não definir Content-Type aqui.
                         * O navegador adiciona o boundary
                         * correto do multipart/form-data.
                         */
                        'X-CSRF-TOKEN':
                            csrf || ''
                    },

                    body:
                        formData
                }
            );

        const data =
            await response.json();

        if (
            !response.ok
            || !data.ok
        ) {
            throw new Error(
                data.message
                || 'Não foi possível lançar a ficha.'
            );
        }

        confirmation.hidden = true;

        const successMessage =
            data.message
            + (
                data.arla_fillings > 0
                    ? ` ARLA: ${data.arla_fillings} lançamento(s).`
                    : ''
            )
            + (
                data.archive_file_id
                    ? ' Ficha arquivada automaticamente.'
                    : ''
            )
            + (
                data.archive_warning
                    ? ' ' + data.archive_warning
                    : ''
            );

        showFuelPhotoFeedback(
            successMessage,
            'success'
        );

        if (data.archive_warning) {
            setTimeout(
                () => {
                    alert(
                        data.archive_warning
                    );
                },
                250
            );
        }

        /*
         * Evita clique duplo depois do sucesso.
         */
        const validateButton =
            document.querySelector(
                '[onclick="validateFuelPhotoReading()"]'
            );

        if (validateButton) {
            validateButton.disabled = true;

            validateButton.innerHTML =
                '<i class="bi bi-check2-circle"></i>'
                + ' Lançado no CHM';
        }

        button.disabled = true;

        /*
         * Atualiza a página depois de um pequeno intervalo
         * para refletir estoque e últimos abastecimentos.
         */
        setTimeout(
            () => {
                window.location.reload();
            },
            1600
        );

    } catch (error) {
        showFuelPhotoFeedback(
            error.message
            || 'Não foi possível lançar a ficha.',
            'error'
        );

        button.disabled = false;
        button.innerHTML =
            originalHtml;
    }
}


function showFuelPhotoFeedback(
    message,
    type = 'success',
    allowHtml = false
) {
    const box =
        document.getElementById(
            'fuelPhotoAiFeedback'
        );

    box.hidden = false;

    box.className =
        'fuel-photo-ai-feedback is-'
        + type;

    if (allowHtml) {
        box.innerHTML = message;
    } else {
        box.textContent = message;
    }
}


function fuelPhotoLiters(
    value,
    suffix = true
) {
    if (
        value === null
        || value === undefined
        || value === ''
    ) {
        return '—';
    }

    const text =
        Number(value).toLocaleString(
            'pt-BR',
            {
                minimumFractionDigits: 2,
                maximumFractionDigits: 3
            }
        );

    return suffix
        ? text + ' L'
        : text + ' L';
}


function escapeFuelPhoto(value) {
    const div =
        document.createElement('div');

    div.textContent =
        String(value ?? '');

    return div.innerHTML;
}


document.addEventListener(
    'DOMContentLoaded',
    () => {

        const fuelPhotoRowConfirmationGuard =
            event => {
                const field =
                    event.target.closest(
                        '#fuelPhotoAiRows [data-field]'
                    );

                if (!field) {
                    return;
                }

                const tr =
                    field.closest('tr');

                if (
                    !tr
                    || tr.dataset.confirmingRow
                        === '1'
                ) {
                    return;
                }

                invalidateFuelPhotoRowConfirmation(
                    tr
                );
            };

        document.addEventListener(
            'input',
            fuelPhotoRowConfirmationGuard
        );

        document.addEventListener(
            'change',
            fuelPhotoRowConfirmationGuard
        );


        document.addEventListener(
            'input',
            event => {
                /*
                 * O aceite consciente de duplicidade faz parte
                 * da confirmação final e não é uma edição da ficha.
                 */
                if (
                    event.target.id
                    === 'fuelPhotoAiDuplicateConfirm'
                ) {
                    return;
                }

                if (
                    event.target.closest(
                        '#fuelPhotoAiResult'
                    )
                ) {
                    invalidateFuelPhotoLaunchConfirmation();
                }
            }
        );

        document.addEventListener(
            'change',
            event => {
                /*
                 * Marcar/desmarcar o aceite da duplicidade
                 * não deve devolver o operador à revisão.
                 */
                if (
                    event.target.id
                    === 'fuelPhotoAiDuplicateConfirm'
                ) {
                    return;
                }

                if (
                    event.target.closest(
                        '#fuelPhotoAiResult'
                    )
                ) {
                    invalidateFuelPhotoLaunchConfirmation();
                }
            }
        );

        const input =
            document.getElementById(
                'fuelPhotoAiFile'
            );

        const modal =
            document.getElementById(
                'fuelPhotoImportModal'
            );

        if (!input || !modal) {
            return;
        }

        input.addEventListener(
            'change',
            event => {
                const file =
                    event.target.files?.[0];

                if (!file) {
                    resetFuelPhotoImport();
                    return;
                }

                const preview =
                    document.getElementById(
                        'fuelPhotoAiPreview'
                    );

                const empty =
                    document.getElementById(
                        'fuelPhotoAiPreviewEmpty'
                    );

                const info =
                    document.getElementById(
                        'fuelPhotoAiFileInfo'
                    );

                const button =
                    document.getElementById(
                        'fuelPhotoAiAnalyze'
                    );

                const isPdf =
                    file.type === 'application/pdf'
                    || file.name
                        .toLowerCase()
                        .endsWith('.pdf');

                if (isPdf) {
                    preview.src = '';
                    preview.hidden = true;

                    empty.hidden = false;
                    empty.innerHTML =
                        '<i class="bi bi-file-earmark-pdf"></i>'
                        + '<span>PDF selecionado · a primeira página será analisada</span>';

                } else {
                    preview.src =
                        URL.createObjectURL(file);

                    preview.hidden = false;
                    empty.hidden = true;
                }

                info.hidden = false;

                info.innerHTML =
                    `<strong>${escapeFuelPhoto(
                        file.name
                    )}</strong>`
                    + `<span>${(
                        file.size / 1024 / 1024
                    ).toFixed(2)} MB</span>`;

                button.disabled = false;

                document.getElementById(
                    'fuelPhotoAiResult'
                ).hidden = true;

                document.getElementById(
                    'fuelPhotoAiFeedback'
                ).hidden = true;
            }
        );


        /*
         * Página dedicada:
         * não permite fechamento acidental por
         * clique fora da área ou tecla ESC.
         */
        if (
            modal.dataset.standalone
                === '1'
        ) {
            return;
        }


        modal.addEventListener(
            'click',
            event => {
                if (event.target === modal) {
                    closeFuelPhotoImport();
                }
            }
        );


        document.addEventListener(
            'keydown',
            event => {
                if (
                    event.key === 'Escape'
                    && !modal.hidden
                ) {
                    closeFuelPhotoImport();
                }
            }
        );
    }
);

</script>

