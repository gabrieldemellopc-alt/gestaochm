@extends('layouts.app')



@php

    $pageTitle = 'Controle de Pneus';

    $pageSubtitle = $vehicle->plate . ' · ' . $vehicle->name;

@endphp



@push('styles')

<link

    rel="stylesheet"

    href="{{ asset('css/pages/vehicle-tires.css') }}?v=3"
>

@endpush



@section('content')

@if($tireControlDisabled ?? false)
    <div class="alert alert-info" role="status">Controle de pneus desativado para este veículo. O histórico foi preservado, mas não são permitidas novas posições, instalações ou medições operacionais.</div>
@endif

@php
    $hasFullTireAccess = (int) auth()->id() === 1
        || userHasProfile('admin')
        || userHasProfile('manager');

    $tirePermissions = array_merge([
        'view' => false,
        'create_entry' => false,
        'install' => false,
        'remove' => false,
        'measure' => false,
        'retread' => false,
        'cancel' => false,
        'view_costs' => false,
        'manage_inventory' => false,
    ], $tirePermissions ?? []);

    $canInstallTires = $hasFullTireAccess || (bool) $tirePermissions['install'];
    $canRemoveTires = $hasFullTireAccess || (bool) $tirePermissions['remove'];
    $canMeasureTires = $hasFullTireAccess || (bool) $tirePermissions['measure'];
@endphp



<div

    class="tire-page"

    x-data="vehicleTiresPage()"

>



    <div class="tire-hero">



        <div>



            <span>

                Gestão de pneus

            </span>



            <h1>

                {{ $vehicle->plate }} · {{ $vehicle->name }}

            </h1>



            <p>

                Controle de posições, instalação e medições de sulco dos pneus do veículo.

            </p>



        </div>

        <div class="tire-hero-actions">



        <a

            href="{{ route('vehicles.tires.report', $vehicle) }}"

            target="_blank"

            class="tire-back-btn"

        >

            <i class="bi bi-file-earmark-text"></i>

            Gerar relatório

        </a>

        <a

            href="{{ route('dashboard') }}"

            class="tire-back-btn"

        >

            <i class="bi bi-arrow-left"></i>

            Voltar ao dashboard

        </a>



        </div>

    </div>



    @php
        $monitoredTirePositions = $positions->count();

        $installedTirePositions =
            $positions
                ->filter(fn ($item) => ! empty($item['tire']))
                ->count();

        $goodTirePositions =
            $positions
                ->filter(
                    fn ($item) =>
                        ! empty($item['tire'])
                        && ! in_array(
                            $item['status'],
                            ['warning', 'danger'],
                            true
                        )
                )
                ->count();

        $attentionTirePositions =
            $positions
                ->filter(
                    fn ($item) =>
                        in_array(
                            $item['status'],
                            ['warning', 'danger'],
                            true
                        )
                )
                ->count();

        $latestFleetTireMeasurement =
            $positions
                ->pluck('latest_measurement')
                ->filter()
                ->sortByDesc(
                    fn ($item) =>
                        optional($item->measured_at)->timestamp ?? 0
                )
                ->first();

        $latestFleetTireMeasurementDate =
            optional(
                optional($latestFleetTireMeasurement)->measured_at
            )->format('d/m/Y') ?? '--';
    @endphp

    <div class="tire-overview-grid">

        <div class="tire-overview-card">
            <div class="tire-overview-icon">
                <i class="bi bi-record-circle"></i>
            </div>

            <div>
                <small>Posições monitoradas</small>
                <strong>{{ $monitoredTirePositions }} posições</strong>
                <span>{{ $installedTirePositions }} com pneu instalado</span>
            </div>
        </div>

        <div class="tire-overview-card">
            <div class="tire-overview-icon">
                <i class="bi bi-check2-circle"></i>
            </div>

            <div>
                <small>Em boas condições</small>
                <strong>{{ $goodTirePositions }} pneus</strong>
                <span>sem alerta operacional</span>
            </div>
        </div>

        <div class="tire-overview-card">
            <div class="tire-overview-icon is-accent">
                <i class="bi bi-exclamation-triangle"></i>
            </div>

            <div>
                <small>Em atenção</small>
                <strong>{{ $attentionTirePositions }} pneus</strong>
                <span>atenção ou situação crítica</span>
            </div>
        </div>

        <div class="tire-overview-card">
            <div class="tire-overview-icon">
                <i class="bi bi-graph-up"></i>
            </div>

            <div>
                <small>Última medição</small>
                <strong>{{ $latestFleetTireMeasurementDate }}</strong>
                <span>registro mais recente</span>
            </div>
        </div>

        <div class="tire-overview-card">
            <div class="tire-overview-icon">
                <i class="bi bi-signpost-split"></i>
            </div>

            <div>
                <small>KM do veículo</small>
                <strong>
                    {{ number_format($vehicle->current_km ?? 0, 0, ',', '.') }} km
                </strong>
                <span>leitura atual</span>
            </div>
        </div>

    </div>


    @php
    $sortedPositions = collect($positions)
        ->sortBy(function ($position) {
            $code = strtoupper(
                trim((string) ($position['code'] ?? ''))
            );

            $label = mb_strtolower(
                (string) ($position['label'] ?? '')
            );

            preg_match('/^(\d+)/', $code, $matches);

            $axleOrder =
                isset($matches[1])
                    ? (int) $matches[1]
                    : 999;

            $groupOrder =
                (
                    str_ends_with($code, 'EI')
                    || str_ends_with($code, 'DI')
                )
                    ? 1
                    : (
                        (
                            str_ends_with($code, 'EE')
                            || str_ends_with($code, 'DE')
                        )
                            ? 2
                            : 0
                    );

            $isLeftPosition =
                str_ends_with($code, 'EI')
                || str_ends_with($code, 'EE')
                || (
                    ! str_ends_with($code, 'DI')
                    && ! str_ends_with($code, 'DE')
                    && (
                        str_ends_with($code, 'E')
                        || str_contains($label, 'esquerd')
                    )
                );

            $sideOrder = $isLeftPosition ? 0 : 1;

            return sprintf(
                '%04d-%04d-%04d-%s',
                $axleOrder,
                $groupOrder,
                $sideOrder,
                $code
            );
        })
        ->values();
@endphp

    @php
        $tireMapAxles =
            $sortedPositions
                ->groupBy(function ($position) {
                    $code = strtoupper(
                        trim((string) ($position['code'] ?? ''))
                    );

                    preg_match('/^(\\d+)/', $code, $matches);

                    return isset($matches[1])
                        ? (int) $matches[1]
                        : 999;
                })
                ->sortKeys();
    @endphp

    <div
        class="tire-map-workspace"
        x-data="{
            activeTirePosition: null,

            toggleTirePosition(code) {
                this.activeTirePosition =
                    this.activeTirePosition === code
                        ? null
                        : code;

                if (this.activeTirePosition) {
                    this.$nextTick(() => {
                        const target =
                            document.getElementById(
                                `tire-position-${code}`
                            );

                        if (target) {
                            target.scrollIntoView({
                                behavior: 'smooth',
                                block: 'start'
                            });
                        }
                    });
                }
            }
        }"
    >

        <section class="tire-map-panel">

            <div class="tire-map-header">

                <div>
                    <span>
                        Distribuição dos pneus
                    </span>

                    <h2>
                        Selecione uma posição
                    </h2>

                    <p>
                        Clique em um pneu para abrir os detalhes,
                        medições e histórico daquela posição.
                    </p>
                </div>

                <div class="tire-map-legend">
                    <span>
                        <i class="tire-map-dot is-normal"></i>
                        Normal
                    </span>

                    <span>
                        <i class="tire-map-dot is-attention"></i>
                        Atenção
                    </span>

                    <span>
                        <i class="tire-map-dot is-selected"></i>
                        Selecionado
                    </span>
                </div>

            </div>


            <div class="tire-map-layout">

                <div class="tire-map-front">
                    <i class="bi bi-arrow-up"></i>
                    <span>Frente do veículo</span>
                </div>


                <div class="tire-map-body">

                    <div class="tire-map-chassis">
                        <span>
                            {{ $vehicle->plate }}
                        </span>

                        <small>
                            {{ $vehicle->name }}
                        </small>
                    </div>


                    @foreach(
                        $tireMapAxles
                        as $axleNumber => $axlePositions
                    )

                        @php
                            $axleByCode =
                                $axlePositions
                                    ->keyBy(
                                        fn ($item) =>
                                            strtoupper(
                                                trim(
                                                    (string)
                                                    ($item['code'] ?? '')
                                                )
                                            )
                                    );

                            $simpleLeft =
                                $axleByCode->get(
                                    $axleNumber . 'E'
                                );

                            $simpleRight =
                                $axleByCode->get(
                                    $axleNumber . 'D'
                                );

                            $leftOuter =
                                $axleByCode->get(
                                    $axleNumber . 'EE'
                                );

                            $leftInner =
                                $axleByCode->get(
                                    $axleNumber . 'EI'
                                );

                            $rightInner =
                                $axleByCode->get(
                                    $axleNumber . 'DI'
                                );

                            $rightOuter =
                                $axleByCode->get(
                                    $axleNumber . 'DE'
                                );

                            $isDualAxle =
                                $leftOuter
                                || $leftInner
                                || $rightInner
                                || $rightOuter;
                        @endphp


                        <div
                            class="tire-map-axle {{
                                $isDualAxle
                                    ? 'is-dual'
                                    : 'is-simple'
                            }}"
                        >

                            <div class="tire-map-axle-label">
                                {{ $axleNumber }}º eixo
                            </div>


                            <div class="tire-map-side is-left">

                                @foreach(
                                    $isDualAxle
                                        ? [$leftOuter, $leftInner]
                                        : [$simpleLeft]
                                    as $mapPosition
                                )

                                    @if($mapPosition)

                                        @php
                                            $mapCode =
                                                strtoupper(
                                                    (string)
                                                    $mapPosition['code']
                                                );

                                            $mapTire =
                                                $mapPosition['tire'];

                                            $mapStatus =
                                                $mapPosition['status'];

                                            $mapCurrentTread =
                                                $mapTire
                                                    ? $mapTire
                                                        ->current_tread_depth
                                                    : null;
                                        @endphp


                                        <button
                                            type="button"
                                            class="tire-map-position {{
                                                in_array(
                                                    $mapStatus,
                                                    ['warning', 'danger'],
                                                    true
                                                )
                                                    ? 'is-attention'
                                                    : (
                                                        $mapStatus === 'empty'
                                                            ? 'is-empty'
                                                            : 'is-normal'
                                                    )
                                            }}"
                                            :class="{
                                                'is-selected':
                                                    activeTirePosition
                                                    === @js($mapCode)
                                            }"
                                            :aria-expanded="
                                                activeTirePosition
                                                === @js($mapCode)
                                            "
                                            @click="
                                                toggleTirePosition(
                                                    @js($mapCode)
                                                )
                                            "
                                        >

                                            <span class="tire-map-position-code">
                                                {{ $mapCode }}
                                            </span>

                                            <span class="tire-map-wheel">
                                                <i></i>
                                                <i></i>
                                                <i></i>
                                            </span>

                                            <span class="tire-map-position-value">
                                                @if($mapTire)
                                                    {{
                                                        $mapCurrentTread
                                                            !== null
                                                            ? number_format(
                                                                (float)
                                                                $mapCurrentTread,
                                                                2,
                                                                ',',
                                                                '.'
                                                            ) . ' mm'
                                                            : 'Sem medição'
                                                    }}
                                                @else
                                                    Sem pneu
                                                @endif
                                            </span>

                                        </button>

                                    @endif

                                @endforeach

                            </div>


                            <div class="tire-map-axle-line"></div>


                            <div class="tire-map-side is-right">

                                @foreach(
                                    $isDualAxle
                                        ? [$rightInner, $rightOuter]
                                        : [$simpleRight]
                                    as $mapPosition
                                )

                                    @if($mapPosition)

                                        @php
                                            $mapCode =
                                                strtoupper(
                                                    (string)
                                                    $mapPosition['code']
                                                );

                                            $mapTire =
                                                $mapPosition['tire'];

                                            $mapStatus =
                                                $mapPosition['status'];

                                            $mapCurrentTread =
                                                $mapTire
                                                    ? $mapTire
                                                        ->current_tread_depth
                                                    : null;
                                        @endphp


                                        <button
                                            type="button"
                                            class="tire-map-position {{
                                                in_array(
                                                    $mapStatus,
                                                    ['warning', 'danger'],
                                                    true
                                                )
                                                    ? 'is-attention'
                                                    : (
                                                        $mapStatus === 'empty'
                                                            ? 'is-empty'
                                                            : 'is-normal'
                                                    )
                                            }}"
                                            :class="{
                                                'is-selected':
                                                    activeTirePosition
                                                    === @js($mapCode)
                                            }"
                                            :aria-expanded="
                                                activeTirePosition
                                                === @js($mapCode)
                                            "
                                            @click="
                                                toggleTirePosition(
                                                    @js($mapCode)
                                                )
                                            "
                                        >

                                            <span class="tire-map-position-code">
                                                {{ $mapCode }}
                                            </span>

                                            <span class="tire-map-wheel">
                                                <i></i>
                                                <i></i>
                                                <i></i>
                                            </span>

                                            <span class="tire-map-position-value">
                                                @if($mapTire)
                                                    {{
                                                        $mapCurrentTread
                                                            !== null
                                                            ? number_format(
                                                                (float)
                                                                $mapCurrentTread,
                                                                2,
                                                                ',',
                                                                '.'
                                                            ) . ' mm'
                                                            : 'Sem medição'
                                                    }}
                                                @else
                                                    Sem pneu
                                                @endif
                                            </span>

                                        </button>

                                    @endif

                                @endforeach

                            </div>

                        </div>

                    @endforeach

                </div>

            </div>

        </section>


        <div
            class="tire-map-empty-state"
            x-show="! activeTirePosition"
            x-transition.opacity.duration.180ms
            x-cloak
        >
            <div class="tire-map-empty-icon">
                <i class="bi bi-record-circle"></i>
            </div>

            <div>
                <h3>
                    Selecione um pneu no mapa
                </h3>

                <p>
                    Os dados, histórico e formulário de medição
                    aparecerão aqui.
                </p>
            </div>
        </div>


        <div class="tire-position-grid tire-position-grid-collapsible">

@foreach($sortedPositions as $position)



            @php

                $installation = $position['installation'];

                $tire = $position['tire'];

                $measurement =
                    $position['latest_measurement'];

                $measurementHistory =
                    $position['measurement_history']
                    ?? collect();

                $measurementHistoryCount =
                    (int) (
                        $position['measurement_history_count']
                        ?? 0
                    );

                $status = $position['status'];

            @endphp



            <section
                id="tire-position-{{ $position['code'] }}"
                class="tire-position-card {{ $status }}"
                x-show="
                    activeTirePosition
                    === @js(strtoupper((string) $position['code']))
                "
                x-transition.opacity.duration.180ms
                x-cloak
            >



                <div class="tire-position-header">

                    <div class="tire-position-header-left">
                        <span class="tire-position-badge">
                            {{ $position['code'] }}
                        </span>

                        <h2 class="tire-position-title">
                            {{ $position['label'] }}
                        </h2>
                    </div>

                    <div class="tire-position-header-actions">

                    <span
                        class="tire-status-badge
                            {{
                                in_array(
                                    $status,
                                    ['warning', 'danger'],
                                    true
                                )
                                    ? 'is-warn'
                                    : (
                                        in_array(
                                            $status,
                                            ['empty', 'pending'],
                                            true
                                        )
                                            ? 'is-neutral'
                                            : 'is-ok'
                                    )
                            }}"
                    >
                        @switch($status)
                            @case('empty')
                                <i class="bi bi-circle"></i>
                                Sem pneu
                                @break

                            @case('pending')
                                <i class="bi bi-clock"></i>
                                Sem medição
                                @break

                            @case('danger')
                                <i class="bi bi-exclamation-triangle"></i>
                                Crítico
                                @break

                            @case('warning')
                                <i class="bi bi-exclamation-circle"></i>
                                Atenção
                                @break

                            @default
                                <i class="bi bi-check2-circle"></i>
                                OK
                        @endswitch
                    </span>

                    <button
                        type="button"
                        class="tire-position-close"
                        title="Fechar detalhes"
                        aria-label="Fechar detalhes desta posição"
                        @click="
                            activeTirePosition = null;
                            window.scrollTo({
                                top:
                                    document.querySelector(
                                        '.tire-map-panel'
                                    )?.offsetTop
                                    - 90,
                                behavior: 'smooth'
                            });
                        "
                    >
                        <i class="bi bi-x-lg"></i>
                    </button>

                    </div>

                </div>

                @if($tire)



                    @php
                        $visualGrooveCount =
                            in_array(
                                (int) $tire->tread_grooves_count,
                                [3, 4],
                                true
                            )
                                ? (int) $tire->tread_grooves_count
                                : 0;

                        $positionCode =
                            strtoupper(
                                trim((string) $position['code'])
                            );

                        $positionLabel =
                            mb_strtolower(
                                (string) $position['label']
                            );

                        $isLeftTirePosition =
                            str_ends_with($positionCode, 'EI')
                            || str_ends_with($positionCode, 'EE')
                            || (
                                ! str_ends_with($positionCode, 'DI')
                                && ! str_ends_with($positionCode, 'DE')
                                && str_contains(
                                    $positionLabel,
                                    'esquerd'
                                )
                            );

                        $wearPercentValue =
                            $position['wear_percent'] !== null
                                ? max(
                                    0,
                                    min(
                                        100,
                                        (float) $position['wear_percent']
                                    )
                                )
                                : 0;


                        $treadDistribution = [];

                        if ($measurement) {
                            if ($measurement->outer_tread !== null) {
                                $treadDistribution['Externo'] =
                                    (float) $measurement->outer_tread;
                            }

                            if (
                                (int) ($tire->tread_grooves_count ?? 0) === 3
                                && $measurement->center_outer_tread !== null
                            ) {
                                $treadDistribution['Centro'] =
                                    (float) $measurement->center_outer_tread;
                            }

                            if (
                                (int) ($tire->tread_grooves_count ?? 0) === 4
                            ) {
                                if (
                                    $measurement->center_outer_tread !== null
                                ) {
                                    $treadDistribution['Centro externo'] =
                                        (float)
                                        $measurement->center_outer_tread;
                                }

                                if (
                                    $measurement->center_inner_tread !== null
                                ) {
                                    $treadDistribution['Centro interno'] =
                                        (float)
                                        $measurement->center_inner_tread;
                                }
                            }

                            if ($measurement->inner_tread !== null) {
                                $treadDistribution['Interno'] =
                                    (float) $measurement->inner_tread;
                            }
                        }

                        $mostWornTreadLabel = null;
                        $treadSpread = null;

                        if (count($treadDistribution) >= 2) {
                            $minimumDistribution =
                                min($treadDistribution);

                            $maximumDistribution =
                                max($treadDistribution);

                            $mostWornTreadLabel =
                                array_search(
                                    $minimumDistribution,
                                    $treadDistribution,
                                    true
                                );

                            $treadSpread =
                                round(
                                    $maximumDistribution
                                    - $minimumDistribution,
                                    2
                                );
                        }
                    @endphp

                    <div
                        class="tire-position-top {{
                            $isLeftTirePosition
                                ? 'is-left-position'
                                : 'is-right-position'
                        }}"
                    >

                        <div
                            class="tire-visual-card"
                            x-data="{
                                hoveredGrooveCode: null,
                                hoveredGrooveLabel: null
                            }"
                        >

                            <div class="tire-visual-svg-wrap">
                                @php
                                $isDualTirePosition =
                                    str_ends_with($positionCode, 'EI')
                                    || str_ends_with($positionCode, 'EE')
                                    || str_ends_with($positionCode, 'DI')
                                    || str_ends_with($positionCode, 'DE');

                                $isInnerTirePosition =
                                    str_ends_with($positionCode, 'EI')
                                    || str_ends_with($positionCode, 'DI');

                                $isOuterTirePosition =
                                    str_ends_with($positionCode, 'EE')
                                    || str_ends_with($positionCode, 'DE');
                            @endphp

                            @php
                                $grooveSemanticLabels =
                                    $visualGrooveCount === 4
                                        ? [
                                            'S1' => 'Lado externo',
                                            'S2' => 'Centro externo',
                                            'S3' => 'Centro interno',
                                            'S4' => 'Lado interno',
                                        ]
                                        : [
                                            'S1' => 'Lado externo',
                                            'S2' => 'Centro',
                                            'S3' => 'Lado interno',
                                        ];
                            @endphp

                            <svg
                                viewBox="0 0 240 250"
                                role="img"
                                aria-label="Representação da posição do pneu"
                                class="tire-technical-svg"
                            >

                                {{-- ========================================
                                     REFERÊNCIA DO VEÍCULO
                                     Esquerda: veículo à direita
                                     Direita: veículo à esquerda
                                     ======================================== --}}

                                @if($isLeftTirePosition)

                                    <path
                                        d="
                                            M226 70
                                            H210
                                            Q198 70 198 82
                                            V103
                                            Q198 114 210 114
                                            H226

                                            M226 160
                                            H210
                                            Q198 160 198 148
                                            V134
                                        "
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="3"
                                        stroke-linecap="round"
                                    />

                                @else

                                    <path
                                        d="
                                            M14 70
                                            H30
                                            Q42 70 42 82
                                            V103
                                            Q42 114 30 114
                                            H14

                                            M14 160
                                            H30
                                            Q42 160 42 148
                                            V134
                                        "
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="3"
                                        stroke-linecap="round"
                                    />

                                @endif


                                @if($isDualTirePosition)

                                    {{-- ====================================
                                         RODADO DUPLO
                                         ==================================== --}}

                                    @php
                                        /*
                                         * No lado esquerdo:
                                         *   externo = pneu da esquerda
                                         *   interno = pneu da direita
                                         *
                                         * No lado direito:
                                         *   interno = pneu da esquerda
                                         *   externo = pneu da direita
                                         */

                                        $leftTireIsSelected =
                                            $isLeftTirePosition
                                                ? $isOuterTirePosition
                                                : $isInnerTirePosition;

                                        $rightTireIsSelected =
                                            $isLeftTirePosition
                                                ? $isInnerTirePosition
                                                : $isOuterTirePosition;

                                        $leftTireLabel =
                                            $isLeftTirePosition
                                                ? 'EXTERNO'
                                                : 'INTERNO';

                                        $rightTireLabel =
                                            $isLeftTirePosition
                                                ? 'INTERNO'
                                                : 'EXTERNO';
                                    @endphp


                                    {{-- PNEU ESQUERDO DO PAR --}}

                                    <rect
                                        x="66"
                                        y="46"
                                        width="48"
                                        height="164"
                                        rx="22"
                                        fill="{{
                                            $leftTireIsSelected
                                                ? 'rgba(249,115,22,.08)'
                                                : 'rgba(148,163,184,.035)'
                                        }}"
                                        stroke="{{
                                            $leftTireIsSelected
                                                ? '#f97316'
                                                : '#94a3b8'
                                        }}"
                                        stroke-width="{{
                                            $leftTireIsSelected ? '3' : '2'
                                        }}"
                                    />

                                    <line
                                        x1="78"
                                        y1="56"
                                        x2="78"
                                        y2="200"
                                        stroke="#64748b"
                                        stroke-width="2"
                                    />

                                    <line
                                        x1="90"
                                        y1="56"
                                        x2="90"
                                        y2="200"
                                        stroke="#64748b"
                                        stroke-width="2"
                                    />

                                    <line
                                        x1="102"
                                        y1="56"
                                        x2="102"
                                        y2="200"
                                        stroke="#64748b"
                                        stroke-width="2"
                                    />


                                    {{-- PNEU DIREITO DO PAR --}}

                                    <rect
                                        x="122"
                                        y="46"
                                        width="48"
                                        height="164"
                                        rx="22"
                                        fill="{{
                                            $rightTireIsSelected
                                                ? 'rgba(249,115,22,.08)'
                                                : 'rgba(148,163,184,.035)'
                                        }}"
                                        stroke="{{
                                            $rightTireIsSelected
                                                ? '#f97316'
                                                : '#94a3b8'
                                        }}"
                                        stroke-width="{{
                                            $rightTireIsSelected ? '3' : '2'
                                        }}"
                                    />

                                    <line
                                        x1="134"
                                        y1="56"
                                        x2="134"
                                        y2="200"
                                        stroke="#64748b"
                                        stroke-width="2"
                                    />

                                    <line
                                        x1="146"
                                        y1="56"
                                        x2="146"
                                        y2="200"
                                        stroke="#64748b"
                                        stroke-width="2"
                                    />

                                    <line
                                        x1="158"
                                        y1="56"
                                        x2="158"
                                        y2="200"
                                        stroke="#64748b"
                                        stroke-width="2"
                                    />


                                    {{-- INDICADORES DO PNEU SELECIONADO --}}

                                    @if($leftTireIsSelected)
                                        <circle
                                            cx="90"
                                            cy="29"
                                            r="5"
                                            fill="#f97316"
                                        />

                                        <path
                                            d="M90 35 V43"
                                            stroke="#f97316"
                                            stroke-width="2"
                                        />
                                    @endif

                                    @if($rightTireIsSelected)
                                        <circle
                                            cx="146"
                                            cy="29"
                                            r="5"
                                            fill="#f97316"
                                        />

                                        <path
                                            d="M146 35 V43"
                                            stroke="#f97316"
                                            stroke-width="2"
                                        />
                                    @endif


                                    {{-- LEGENDAS --}}

                                    <text
                                        x="90"
                                        y="226"
                                        text-anchor="middle"
                                        fill="{{
                                            $leftTireIsSelected
                                                ? '#fdba74'
                                                : '#94a3b8'
                                        }}"
                                        font-size="9"
                                        font-weight="{{
                                            $leftTireIsSelected
                                                ? '900'
                                                : '700'
                                        }}"
                                    >
                                        {{ $leftTireLabel }}
                                    </text>

                                    <text
                                        x="146"
                                        y="226"
                                        text-anchor="middle"
                                        fill="{{
                                            $rightTireIsSelected
                                                ? '#fdba74'
                                                : '#94a3b8'
                                        }}"
                                        font-size="9"
                                        font-weight="{{
                                            $rightTireIsSelected
                                                ? '900'
                                                : '700'
                                        }}"
                                    >
                                        {{ $rightTireLabel }}
                                    </text>


                                    <text
                                        x="{{
                                            $leftTireIsSelected
                                                ? 90
                                                : 146
                                        }}"
                                        y="241"
                                        text-anchor="middle"
                                        fill="#fdba74"
                                        font-size="9"
                                        font-weight="900"
                                    >
                                        ESTE PNEU
                                    </text>

                                @else

                                    {{-- ====================================
                                         RODADO SIMPLES
                                         ==================================== --}}

                                    <rect
                                        x="78"
                                        y="38"
                                        width="84"
                                        height="180"
                                        rx="34"
                                        fill="rgba(148,163,184,.06)"
                                        stroke="#cbd5e1"
                                        stroke-width="3"
                                    />

                                    <path
                                        d="
                                            M84 60 L98 70
                                            M84 82 L98 92
                                            M84 104 L98 114
                                            M84 126 L98 136
                                            M84 148 L98 158
                                            M84 170 L98 180
                                            M84 192 L98 202

                                            M156 60 L142 70
                                            M156 82 L142 92
                                            M156 104 L142 114
                                            M156 126 L142 136
                                            M156 148 L142 158
                                            M156 170 L142 180
                                            M156 192 L142 202
                                        "
                                        fill="none"
                                        stroke="#64748b"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                    />


                                    @if($visualGrooveCount === 3)

                                        @php
                                            $svgGrooves3 =
                                                $isLeftTirePosition
                                                    ? [
                                                        ['x' => 102, 'label' => 'S1'],
                                                        ['x' => 120, 'label' => 'S2'],
                                                        ['x' => 138, 'label' => 'S3'],
                                                    ]
                                                    : [
                                                        ['x' => 102, 'label' => 'S3'],
                                                        ['x' => 120, 'label' => 'S2'],
                                                        ['x' => 138, 'label' => 'S1'],
                                                    ];
                                        @endphp

                                        @foreach($svgGrooves3 as $groove)

                                            <rect
                                                class="tire-groove-hit"
                                                x="{{ $groove['x'] - 9 }}"
                                                y="12"
                                                width="18"
                                                height="202"
                                                fill="transparent"
                                                @mouseenter="
                                                    hoveredGrooveCode =
                                                        @js($groove['label']);

                                                    hoveredGrooveLabel =
                                                        @js(
                                                            $grooveSemanticLabels[
                                                                $groove['label']
                                                            ]
                                                            ?? ''
                                                        );
                                                "
                                                @mouseleave="
                                                    hoveredGrooveCode = null;
                                                    hoveredGrooveLabel = null;
                                                "
                                            />

                                            <line
                                                x1="{{ $groove['x'] }}"
                                                y1="49"
                                                x2="{{ $groove['x'] }}"
                                                y2="207"
                                                stroke="#94a3b8"
                                                stroke-width="3"
                                                stroke-linecap="round"
                                            />

                                            <circle
                                                cx="{{ $groove['x'] }}"
                                                cy="28"
                                                r="4"
                                                fill="#f97316"
                                            />

                                            <text
                                                x="{{ $groove['x'] }}"
                                                y="17"
                                                text-anchor="middle"
                                                fill="#e2e8f0"
                                                font-size="11"
                                                font-weight="800"
                                            >
                                                {{ $groove['label'] }}
                                            </text>

                                        @endforeach

                                    @elseif($visualGrooveCount === 4)

                                        @php
                                            $svgGrooves4 =
                                                $isLeftTirePosition
                                                    ? [
                                                        ['x' => 96, 'label' => 'S1'],
                                                        ['x' => 112, 'label' => 'S2'],
                                                        ['x' => 128, 'label' => 'S3'],
                                                        ['x' => 144, 'label' => 'S4'],
                                                    ]
                                                    : [
                                                        ['x' => 96, 'label' => 'S4'],
                                                        ['x' => 112, 'label' => 'S3'],
                                                        ['x' => 128, 'label' => 'S2'],
                                                        ['x' => 144, 'label' => 'S1'],
                                                    ];
                                        @endphp

                                        @foreach($svgGrooves4 as $groove)

                                            <rect
                                                class="tire-groove-hit"
                                                x="{{ $groove['x'] - 8 }}"
                                                y="12"
                                                width="16"
                                                height="202"
                                                fill="transparent"
                                                @mouseenter="
                                                    hoveredGrooveCode =
                                                        @js($groove['label']);

                                                    hoveredGrooveLabel =
                                                        @js(
                                                            $grooveSemanticLabels[
                                                                $groove['label']
                                                            ]
                                                            ?? ''
                                                        );
                                                "
                                                @mouseleave="
                                                    hoveredGrooveCode = null;
                                                    hoveredGrooveLabel = null;
                                                "
                                            />

                                            <line
                                                x1="{{ $groove['x'] }}"
                                                y1="49"
                                                x2="{{ $groove['x'] }}"
                                                y2="207"
                                                stroke="#94a3b8"
                                                stroke-width="3"
                                                stroke-linecap="round"
                                            />

                                            <circle
                                                cx="{{ $groove['x'] }}"
                                                cy="28"
                                                r="4"
                                                fill="#f97316"
                                            />

                                            <text
                                                x="{{ $groove['x'] }}"
                                                y="17"
                                                text-anchor="middle"
                                                fill="#e2e8f0"
                                                font-size="11"
                                                font-weight="800"
                                            >
                                                {{ $groove['label'] }}
                                            </text>

                                        @endforeach

                                    @else

                                        <line
                                            x1="108"
                                            y1="49"
                                            x2="108"
                                            y2="207"
                                            stroke="#64748b"
                                            stroke-width="2"
                                            stroke-dasharray="5 6"
                                        />

                                        <line
                                            x1="132"
                                            y1="49"
                                            x2="132"
                                            y2="207"
                                            stroke="#64748b"
                                            stroke-width="2"
                                            stroke-dasharray="5 6"
                                        />

                                        <text
                                            x="120"
                                            y="232"
                                            text-anchor="middle"
                                            fill="#94a3b8"
                                            font-size="9"
                                            font-weight="700"
                                        >
                                            DEFINIR 3 OU 4 SULCOS
                                        </text>

                                    @endif

                                @endif

                            </svg>
                            </div>

                            <div
                                class="tire-groove-lens"
                                x-show="hoveredGrooveCode"
                                x-transition.opacity.duration.120ms
                                x-cloak
                            >
                                <strong
                                    x-text="hoveredGrooveCode"
                                ></strong>

                                <span
                                    x-text="hoveredGrooveLabel"
                                ></span>
                            </div>

                            <div class="tire-visual-side">
                                <i
                                    class="bi {{
                                        $isLeftTirePosition
                                            ? 'bi-arrow-right'
                                            : 'bi-arrow-left'
                                    }}"
                                ></i>

                                <small>
                                    Lado
                                    <br>
                                    {{
                                        $isLeftTirePosition
                                            ? 'esquerdo'
                                            : 'direito'
                                    }}
                                </small>
                            </div>

                        </div>


                        <div class="tire-position-details">

                            <div class="tire-meta-card">

                                <div class="tire-meta-grid">

                                    <div class="tire-meta-item">
                                        <small>Pneu</small>
                                        <strong>
                                            {{ $tire->code }}

                                            @if($tire->retreads_count > 0)
                                                <span class="tire-retread-tag">
                                                    R{{ $tire->retreads_count }}
                                                </span>
                                            @endif
                                        </strong>
                                    </div>

                                    <div class="tire-meta-item">
                                        <small>Marca / Modelo</small>
                                        <strong>
                                            {{ $tire->brand ?? 'Sem marca' }}
                                            {{
                                                $tire->model
                                                    ? '· ' . $tire->model
                                                    : ''
                                            }}
                                        </strong>
                                    </div>

                                    <div class="tire-meta-item">
                                        <small>Sulco inicial</small>
                                        <strong>
                                            {{ $tire->initial_tread_depth ?? '--' }}
                                            mm
                                        </strong>
                                    </div>

                                    <div class="tire-meta-item">
                                        <small>KM instalação</small>
                                        <strong>
                                            {{
                                                $installation?->installed_km
                                                    ? number_format(
                                                        $installation->installed_km,
                                                        0,
                                                        ',',
                                                        '.'
                                                    ) . ' km'
                                                    : '--'
                                            }}
                                        </strong>
                                    </div>

                                </div>

                            </div>


                            <div class="tire-reference-card">

                                <div class="tire-reference-title">
                                    Referência atual
                                </div>

                                @if($tire->current_tread_source !== 'initial')

                                    <div class="tire-reference-grid">

                                        <div class="tire-reference-item">
                                            <small>Menor sulco atual</small>

                                            <strong>
                                                {{ $tire->current_tread_depth }} mm
                                            </strong>

                                            <span>
                                                @if(
                                                    $tire->current_tread_source
                                                    === 'retread'
                                                )
                                                    Referência: Recapagem
                                                    R{{ $tire->retreads_count }}
                                                @else
                                                    Referência: Última medição
                                                @endif
                                            </span>
                                        </div>

                                        <div class="tire-reference-item">
                                            <small>Desgaste</small>

                                            <strong>
                                                {{
                                                    $position['wear_percent']
                                                    !== null
                                                        ? $position['wear_percent']
                                                            . '%'
                                                        : '--'
                                                }}
                                            </strong>

                                            @if(
                                                $position['wear_percent']
                                                !== null
                                            )
                                                <div class="tire-wear-bar">
                                                    <span
                                                        style="width: {{ $wearPercentValue }}%;"
                                                    ></span>
                                                </div>
                                            @endif
                                        </div>

                                        <div class="tire-reference-item">
                                            <small>KM</small>

                                            <strong>
                                                {{
                                                    $measurement?->vehicle_km
                                                        ? number_format(
                                                            $measurement->vehicle_km,
                                                            0,
                                                            ',',
                                                            '.'
                                                        )
                                                        : '--'
                                                }}
                                            </strong>
                                        </div>

                                        <div class="tire-reference-item">
                                            <small>Data</small>

                                            <strong>
                                                {{
                                                    optional(
                                                        $tire->current_tread_date
                                                    )->format('d/m/Y')
                                                    ?? '--'
                                                }}
                                            </strong>
                                        </div>

                                    </div>

                                @else

                                    <div class="tire-reference-empty">
                                        <i class="bi bi-info-circle"></i>

                                        <span>
                                            Nenhuma medição operacional
                                            registrada para este pneu nesta
                                            posição.
                                        </span>
                                    </div>

                                @endif

                            </div>

                            @if(
                                $mostWornTreadLabel
                                && $treadSpread !== null
                            )
                                <div class="tire-wear-distribution">
                                    <span>
                                        <i class="bi bi-distribute-horizontal"></i>
                                        Maior desgaste:
                                        <strong>
                                            {{ $mostWornTreadLabel }}
                                        </strong>
                                    </span>

                                    <span>
                                        Diferença entre sulcos:
                                        <strong>
                                            {{
                                                number_format(
                                                    $treadSpread,
                                                    2,
                                                    ',',
                                                    '.'
                                                )
                                            }}
                                            mm
                                        </strong>
                                    </span>
                                </div>
                            @endif

                        </div>

                    </div>

                    @if($measurementHistory->isNotEmpty())

                        <details class="tire-history-card">

                            <summary class="tire-history-summary">

                                <div>
                                    <i class="bi bi-clock-history"></i>

                                    <strong>
                                        Histórico de medições
                                    </strong>
                                </div>

                                <span>
                                    {{
                                        $measurementHistoryCount === 1
                                            ? '1 medição'
                                            : $measurementHistoryCount . ' medições'
                                    }}
                                </span>

                            </summary>


                            <div class="tire-history-list">

                                @foreach($measurementHistory as $historyMeasurement)

                                    @php
                                        $historyValues = [];

                                        if (
                                            $historyMeasurement->outer_tread
                                            !== null
                                        ) {
                                            $historyValues[] = [
                                                'label' => 'S1',
                                                'value' =>
                                                    $historyMeasurement
                                                        ->outer_tread,
                                            ];
                                        }

                                        if (
                                            $historyMeasurement
                                                ->center_outer_tread
                                            !== null
                                        ) {
                                            $historyValues[] = [
                                                'label' => 'S2',
                                                'value' =>
                                                    $historyMeasurement
                                                        ->center_outer_tread,
                                            ];
                                        }

                                        if (
                                            $historyMeasurement
                                                ->center_inner_tread
                                            !== null
                                        ) {
                                            $historyValues[] = [
                                                'label' => 'S3',
                                                'value' =>
                                                    $historyMeasurement
                                                        ->center_inner_tread,
                                            ];
                                        }

                                        if (
                                            $historyMeasurement->inner_tread
                                            !== null
                                        ) {
                                            $innerLabel =
                                                $historyMeasurement
                                                    ->center_inner_tread
                                                !== null
                                                    ? 'S4'
                                                    : (
                                                        $historyMeasurement
                                                            ->center_outer_tread
                                                        !== null
                                                            ? 'S3'
                                                            : null
                                                    );

                                            if ($innerLabel) {
                                                $historyValues[] = [
                                                    'label' => $innerLabel,
                                                    'value' =>
                                                        $historyMeasurement
                                                            ->inner_tread,
                                                ];
                                            }
                                        }

                                        $isLegacyHistoryMeasurement =
                                            count($historyValues) <= 1;
                                    @endphp


                                    <div class="tire-history-row">

                                        <div class="tire-history-main">

                                            <div class="tire-history-date">
                                                <strong>
                                                    {{
                                                        optional(
                                                            $historyMeasurement
                                                                ->measured_at
                                                        )->format('d/m/Y')
                                                        ?? '--'
                                                    }}
                                                </strong>

                                                <span>
                                                    {{
                                                        $historyMeasurement
                                                            ->vehicle_km
                                                            !== null
                                                            ? number_format(
                                                                $historyMeasurement
                                                                    ->vehicle_km,
                                                                0,
                                                                ',',
                                                                '.'
                                                            ) . ' km'
                                                            : 'KM não informado'
                                                    }}
                                                </span>
                                            </div>


                                            <div class="tire-history-grooves">

                                                @if(!$isLegacyHistoryMeasurement)

                                                    @foreach(
                                                        $historyValues
                                                        as $historyValue
                                                    )
                                                        <span>
                                                            <small>
                                                                {{
                                                                    $historyValue[
                                                                        'label'
                                                                    ]
                                                                }}
                                                            </small>

                                                            <strong>
                                                                {{
                                                                    number_format(
                                                                        (float)
                                                                        $historyValue[
                                                                            'value'
                                                                        ],
                                                                        2,
                                                                        ',',
                                                                        '.'
                                                                    )
                                                                }}
                                                                mm
                                                            </strong>
                                                        </span>
                                                    @endforeach

                                                @else

                                                    <span class="is-legacy">
                                                        <small>
                                                            Leitura
                                                        </small>

                                                        <strong>
                                                            {{
                                                                number_format(
                                                                    (float)
                                                                    $historyMeasurement
                                                                        ->minimum_tread,
                                                                    2,
                                                                    ',',
                                                                    '.'
                                                                )
                                                            }}
                                                            mm
                                                        </strong>
                                                    </span>

                                                @endif

                                            </div>


                                            <div class="tire-history-result">
                                                <span>
                                                    <small>Menor</small>

                                                    <strong>
                                                        {{
                                                            number_format(
                                                                (float)
                                                                $historyMeasurement
                                                                    ->minimum_tread,
                                                                2,
                                                                ',',
                                                                '.'
                                                            )
                                                        }}
                                                        mm
                                                    </strong>
                                                </span>

                                                <span>
                                                    <small>Média</small>

                                                    <strong>
                                                        {{
                                                            number_format(
                                                                (float)
                                                                $historyMeasurement
                                                                    ->average_tread,
                                                                2,
                                                                ',',
                                                                '.'
                                                            )
                                                        }}
                                                        mm
                                                    </strong>
                                                </span>
                                            </div>

                                        </div>


                                        @if($historyMeasurement->notes)
                                            <div class="tire-history-notes">
                                                <i class="bi bi-chat-left-text"></i>

                                                {{
                                                    $historyMeasurement->notes
                                                }}
                                            </div>
                                        @endif

                                    </div>

                                @endforeach


                                @if($measurementHistoryCount > 5)
                                    <div class="tire-history-more">
                                        Exibindo as 5 medições mais recentes de
                                        {{ $measurementHistoryCount }} registros.
                                    </div>
                                @endif

                            </div>

                        </details>

                    @endif


                    @if($canMeasureTires)

                    @php
                        $previousGrooveCount =
                            (int) ($tire->tread_grooves_count ?? 0);

                        $previousTread1 = null;
                        $previousTread2 = null;
                        $previousTread3 = null;
                        $previousTread4 = null;

                        $previousMeasurementComparable = false;

                        if (
                            $measurement
                            && $previousGrooveCount === 3
                            && $measurement->outer_tread !== null
                            && $measurement->center_outer_tread !== null
                            && $measurement->inner_tread !== null
                        ) {
                            $previousTread1 =
                                (float) $measurement->outer_tread;

                            $previousTread2 =
                                (float) $measurement->center_outer_tread;

                            $previousTread3 =
                                (float) $measurement->inner_tread;

                            $previousMeasurementComparable = true;
                        }

                        if (
                            $measurement
                            && $previousGrooveCount === 4
                            && $measurement->outer_tread !== null
                            && $measurement->center_outer_tread !== null
                            && $measurement->center_inner_tread !== null
                            && $measurement->inner_tread !== null
                        ) {
                            $previousTread1 =
                                (float) $measurement->outer_tread;

                            $previousTread2 =
                                (float) $measurement->center_outer_tread;

                            $previousTread3 =
                                (float) $measurement->center_inner_tread;

                            $previousTread4 =
                                (float) $measurement->inner_tread;

                            $previousMeasurementComparable = true;
                        }
                    @endphp

                    <form

                        method="POST"

                        action="{{ route('vehicles.tires.measurement', $vehicle) }}"

                        class="tire-form"
                        data-previous-comparable="{{ $previousMeasurementComparable ? '1' : '0' }}"
                        data-previous-groove-count="{{ $previousGrooveCount }}"
                        data-previous-tread-1="{{ $previousTread1 }}"
                        data-previous-tread-2="{{ $previousTread2 }}"
                        data-previous-tread-3="{{ $previousTread3 }}"
                        data-previous-tread-4="{{ $previousTread4 }}"
                        oninput="syncTireMeasurementSubmit(this)"
                        onchange="syncTireMeasurementSubmit(this)"
                        onsubmit="return confirmTireKmReading(this, 'vehicle_km');"

                    >



                        @csrf
                        <input type="hidden" name="km_reading_confirmed" value="0">



                        <input

                            type="hidden"

                            name="position_code"

                            value="{{ $position['code'] }}"

                        >



                        <input

                            type="hidden"

                            name="tire_id"

                            value="{{ $tire->id }}"

                        >



                        <div class="tire-measure-card-title">
                            <i class="bi bi-bar-chart tire-muted-icon"></i>
                            Nova medição de sulco
                        </div>

                        <div class="tire-form-grid simple-measurement">

                            <div class="tire-km-field">
                                <label>
                                    KM atual
                                </label>

                                <input
                                    type="number"
                                    name="vehicle_km"
                                    data-current-reading="{{ $vehicle->current_km ?? 0 }}"
                                    value="{{ old('vehicle_km', $vehicle->current_km ?? 0) }}"
                                    min="0"
                                    step="1"
                                    required
                                >
                            </div>


                            <div class="measurement-notes-field">
                                <label>
                                    Observações
                                </label>

                                <input
                                    type="text"
                                    name="notes"
                                    value="{{ old('notes') }}"
                                    placeholder="Opcional"
                                    maxlength="300"
                                >
                            </div>


                            <div
                                class="tire-grooves-measurement"
                                x-data="{
                                    grooveCount: @js(
                                        (int) old(
                                            'tread_grooves_count',
                                            $tire->tread_grooves_count ?? 0
                                        )
                                    )
                                }"
                                x-init="
                                    $nextTick(
                                        () =>
                                            syncTireMeasurementSubmit(
                                                $el.closest('form')
                                            )
                                    )
                                "
                            >

                                @if($tire->tread_grooves_count)

                                    <input
                                        type="hidden"
                                        name="tread_grooves_count"
                                        value="{{ $tire->tread_grooves_count }}"
                                    >

                                    <div class="tire-grooves-heading">
                                        <strong>
                                            Medição dos sulcos
                                        </strong>

                                        <small>
                                            {{ $tire->tread_grooves_count }}
                                            sulcos neste pneu
                                        </small>
                                    </div>

                                @else

                                    <div class="tire-grooves-config" :class="{ 'is-pending': !grooveCount }">

                                        <label>
                                            Quantos sulcos este pneu possui?
                                        </label>

                                        <select
                                            name="tread_grooves_count"
                                            x-model.number="grooveCount"
                                            required
                                        >
                                            <option value="">
                                                Selecione
                                            </option>

                                            <option value="3">
                                                3 sulcos
                                            </option>

                                            <option value="4">
                                                4 sulcos
                                            </option>
                                        </select>

                                        <small>
                                            A escolha ficará vinculada ao pneu.
                                        </small>

                                    </div>

                                @endif


                                <div
                                    class="tire-grooves-grid"
                                    x-show="
                                        grooveCount === 3
                                        ||
                                        grooveCount === 4
                                    "
                                    :style="
                                        `grid-template-columns:
                                        repeat(
                                            ${grooveCount},
                                            minmax(0, 1fr)
                                        );`
                                    "
                                    x-cloak
                                >

                                    <div>
                                        <label>S1 · Lado externo</label>

                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max="50"
                                            name="tread_1"
                                            value="{{ old('tread_1', $measurement?->outer_tread) }}"
                                            placeholder="mm"
                                            required
                                        >
                                    </div>


                                    <div>
                                        <label>
                                            <span
                                                x-show="grooveCount === 3"
                                            >
                                                S2 · Centro
                                            </span>

                                            <span
                                                x-show="grooveCount === 4"
                                            >
                                                S2 · Centro externo
                                            </span>
                                        </label>

                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max="50"
                                            name="tread_2"
                                            value="{{ old('tread_2', $measurement?->center_outer_tread) }}"
                                            placeholder="mm"
                                            required
                                        >
                                    </div>


                                    <div>
                                        <label>
                                            <span
                                                x-show="grooveCount === 3"
                                            >
                                                S3 · Lado interno
                                            </span>

                                            <span
                                                x-show="grooveCount === 4"
                                            >
                                                S3 · Centro interno
                                            </span>
                                        </label>

                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max="50"
                                            name="tread_3"
                                            value="{{ old(
                                                'tread_3',
                                                (int) ($tire->tread_grooves_count ?? 0) === 4
                                                    ? $measurement?->center_inner_tread
                                                    : $measurement?->inner_tread
                                            ) }}"
                                            placeholder="mm"
                                            required
                                        >
                                    </div>


                                    <div
                                        x-show="grooveCount === 4"
                                        x-cloak
                                    >
                                        <label>
                                            S4 · Lado interno
                                        </label>

                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max="50"
                                            name="tread_4"
                                            value="{{ old('tread_4', $measurement?->inner_tread) }}"
                                            placeholder="mm"
                                            :required="grooveCount === 4"
                                            :disabled="grooveCount !== 4"
                                        >
                                    </div>

                                </div>


                                <small class="tire-grooves-help">
                                    <i class="bi bi-info-circle"></i>
                                    O CHM usará o menor valor como referência
                                    operacional e calculará também a média dos
                                    sulcos.
                                </small>

                            </div>

                        </div>

                        <div class="tire-measure-actions">



                            @if($canRemoveTires)
                            <button

                                type="button"

                                class="tire-remove-action"

                                @click='openRemoveTireModal(

                                    @json($position["code"]),

                                    @json($position["label"]),

                                    @json($tire->id),

                                    @json($tire->code),

                                    @json($vehicle->current_km ?? 0)

                                )'

                            >

                                <i class="bi bi-arrow-clockwise"></i>

                                Remover / trocar pneu

                            </button>
                            @endif



                            <button

                                type="submit"

                                class="tire-save-action"


                                disabled
                                title="Preencha os sulcos e altere ao menos uma leitura para salvar.">

                                <i class="bi bi-floppy"></i>

                                Salvar medição

                            </button>



                        </div>

                    </form>
                    @elseif($canRemoveTires)
                        <div class="tire-measure-actions">
                            <button
                                type="button"
                                class="tire-remove-action"
                                @click='openRemoveTireModal(
                                    @json($position["code"]),
                                    @json($position["label"]),
                                    @json($tire->id),
                                    @json($tire->code),
                                    @json($vehicle->current_km ?? 0)
                                )'
                            >
                                <i class="bi bi-arrow-clockwise"></i>
                                Remover / trocar pneu
                            </button>
                        </div>
                    @endif

                @else



                    <div class="tire-empty-box">



                        <i class="bi bi-slash-circle"></i>



                        <strong>

                            Nenhum pneu instalado

                        </strong>



                        @if($canInstallTires)
                            <p>

                                Instale um pneu disponível nesta posição para iniciar o acompanhamento.

                            </p>
                        @endif



                    </div>



                    @if($canInstallTires)
                    <div class="tire-install-box">



                        <div class="tire-install-info">



                            <span>

                                Posição disponível

                            </span>



                            <strong>

                                {{ $position['code'] }} · {{ $position['label'] }}

                            </strong>



                            <p>

                                Selecione um pneu disponível no estoque para instalar nesta posição.

                            </p>



                        </div>



<button

                            type="button"

                            class="tire-select-stock-btn"

                            @click='openTirePicker(

                                @json($position["code"]),

                                @json($position["label"]),

                                @json($vehicle->current_km ?? 0)

                            )'

                        >

                            <i class="bi bi-search"></i>

                            Selecionar pneu do estoque

                        </button>



                    </div>
                    @endif

                @endif



            </section>



        @endforeach



        </div>

    </div>





{{-- MODAL: SELEÇÃO DE PNEU --}}
@if($canInstallTires)

<div

    class="modal-overlay tire-picker-overlay"

    x-show="tirePickerOpen"

    x-transition.opacity

    style="display:none;"

    @click.self="closeTirePicker()"

>

    <div

        class="tire-picker-modal"

        x-transition.scale.origin.center

    >



        <div class="tire-picker-header">



            <div>

                <small>

                    Estoque de pneus

                </small>



                <h2>

                    Selecionar pneu

                </h2>



                <p>

                    Instalando em

                    <strong x-text="selectedPositionCode"></strong>

                    ·

                    <span x-text="selectedPositionLabel"></span>

                </p>

            </div>



            <button

                type="button"

                class="tire-picker-close"

                @click="closeTirePicker()"

            >

                <i class="bi bi-x-lg"></i>

            </button>



        </div>



        <div class="tire-picker-body">



            <div class="tire-picker-search">



                <i class="bi bi-search"></i>



                <input

                    type="text"

                    x-model="tireSearch"

                    placeholder="Buscar por código, marca, modelo ou medida..."

                    autocomplete="off"

                >



            </div>



            <div class="tire-picker-count">



                <span>

                    Exibindo

                    <strong x-text="filteredTires().length"></strong>

                    pneu(s)

                </span>



                <small>

                    Digite para refinar a busca.

                </small>



            </div>



            <div class="tire-picker-list">



                <template

                    x-for="tire in filteredTires()"

                    :key="tire.id"

                >

                    <button

                        type="button"

                        class="tire-picker-item"

                        :class="{ selected: selectedTire && selectedTire.id === tire.id }"

                        @click="selectTire(tire)"

                    >



                        <div class="tire-picker-item-icon">

                            <i class="bi bi-circle"></i>

                        </div>



                        <div class="tire-picker-item-main">



                            <strong x-text="tire.code"></strong>



                            <span>

                                <template x-if="tire.brand">

                                    <span x-text="tire.brand"></span>

                                </template>



                                <template x-if="tire.model">

                                    <span>

                                        · <span x-text="tire.model"></span>

                                    </span>

                                </template>



                                <template x-if="tire.size">

                                    <span>

                                        · <span x-text="tire.size"></span>

                                    </span>

                                </template>

                            </span>



                        </div>



                        <div class="tire-picker-item-meta">



                            <small>

                                Sulco inicial

                            </small>



                            <b>

                                <span x-text="tire.initial_tread_depth ?? '--'"></span>

                                mm

                            </b>



                        </div>



                    </button>

                </template>



                <template x-if="filteredTires().length === 0">

                    <div class="tire-picker-empty">



                        <i class="bi bi-search"></i>



                        <strong>

                            Nenhum pneu encontrado

                        </strong>



                        <p>

                            Tente buscar por outro código, marca, modelo ou medida.

                        </p>



                    </div>

                </template>



            </div>



        </div>



        <form

            method="POST"

            action="{{ route('vehicles.tires.install', $vehicle) }}"

            class="tire-picker-footer"

        >



            @csrf



            <input

                type="hidden"

                name="position_code"

                :value="selectedPositionCode"

            >



            <input

                type="hidden"

                name="tire_id"

                :value="selectedTire ? selectedTire.id : ''"

            >



            <div class="tire-picker-km">



                <label>

                    KM instalação

                </label>



                <input

                    type="number"

                    name="installed_km"

                    x-model="installedKm"

                    min="0"

                    required

                >



            </div>



            <div class="tire-picker-selected">



                <template x-if="selectedTire">

                    <div>

                        <span>

                            Pneu selecionado

                        </span>



                        <strong x-text="selectedTire.label"></strong>

                    </div>

                </template>



                <template x-if="!selectedTire">

                    <div>

                        <span>

                            Nenhum pneu selecionado

                        </span>



                        <strong>

                            Escolha um pneu na lista.

                        </strong>

                    </div>

                </template>



            </div>



            <button

                type="submit"

                class="tire-picker-confirm"

                :disabled="!selectedTire"

            >

                <i class="bi bi-check-circle"></i>

                Confirmar instalação

            </button>



        </form>



    </div>

</div>



@endif

{{-- MODAL: REMOVER / TROCAR PNEU --}}
@if($canRemoveTires)

<div

    class="modal-overlay tire-remove-overlay"

    x-show="removeTireModalOpen"

    x-transition.opacity

    style="display:none;"

    @click.self="closeRemoveTireModal()"

>

    <div

        class="tire-remove-modal"

        x-transition.scale.origin.center

    >



        <div class="tire-remove-header">



            <div>

                <small>

                    Movimentação de pneu

                </small>



                <h2>

                    Remover / trocar pneu

                </h2>



                <p>

                    Pneu

                    <strong x-text="removeTireCode"></strong>

                    ·

                    posição

                    <strong x-text="removePositionCode"></strong>

                    <span x-text="removePositionLabel"></span>

                </p>

            </div>



            <button

                type="button"

                class="tire-picker-close"

                @click="closeRemoveTireModal()"

            >

                <i class="bi bi-x-lg"></i>

            </button>



        </div>



        <form

            method="POST"

            action="{{ route('vehicles.tires.remove', $vehicle) }}"

            class="tire-remove-form"
            onsubmit="return confirmTireKmReading(this, 'removed_km');"

        >

            @csrf
            <input type="hidden" name="km_reading_confirmed" value="0">



            <input

                type="hidden"

                name="position_code"

                :value="removePositionCode"

            >



            <input

                type="hidden"

                name="tire_id"

                :value="removeTireId"

            >



            <div class="tire-remove-grid">



                <div class="form-group">

                    <label>

                        KM de remoção

                    </label>



                    <input

                        type="number"

                        name="removed_km"
                        data-current-reading="{{ $vehicle->current_km ?? 0 }}"

                        x-model="removeKm"

                        min="0"

                        required

                    >

                </div>



                <div class="form-group">

                    <label>

                        Destino do pneu

                    </label>



                    <select

                        name="destination"

                        x-model="removeDestination"

                        required

                    >

                        <option value="available">

                            Voltar para estoque

                        </option>



                        <option value="maintenance">

                            Enviar para manutenção/reforma

                        </option>



                        <option value="discarded">

                            Descartar pneu

                        </option>

                    </select>

                </div>



                <div class="form-group">

                    <label>

                        Motivo

                    </label>



                    <select

                        name="removal_reason"

                        x-model="removeReason"

                        required

                    >

                        <option value="">

                            Selecione

                        </option>



                        <option value="Rodízio">

                            Rodízio

                        </option>



                        <option value="Substituição por desgaste">

                            Substituição por desgaste

                        </option>



                        <option value="Furo / dano">

                            Furo / dano

                        </option>



                        <option value="Recapagem / reforma">

                            Recapagem / reforma

                        </option>



                        <option value="Descarte">

                            Descarte

                        </option>



                        <option value="Outro">

                            Outro

                        </option>

                    </select>

                </div>



            </div>



            <div class="form-group">

                <label>

                    Observações

                </label>



                <textarea

                    name="notes"

                    rows="3"

                    placeholder="Detalhes adicionais da remoção, troca ou descarte..."

                ></textarea>

            </div>



            <div class="tire-remove-warning">

                <i class="bi bi-info-circle"></i>



                <span>

                    Ao confirmar, a posição ficará sem pneu. Para concluir uma troca, instale outro pneu disponível na mesma posição.

                </span>

            </div>



            <div class="tire-remove-footer">



                <button

                    type="button"

                    class="tire-remove-cancel"

                    @click="closeRemoveTireModal()"

                >

                    Cancelar

                </button>



                <button

                    type="submit"

                    class="tire-remove-confirm"

                >

                    <i class="bi bi-check-circle"></i>

                    Confirmar remoção

                </button>



            </div>



        </form>



    </div>

</div>



@endif
</div>

<script>

function syncTireMeasurementSubmit(form) {
    if (! form) {
        return;
    }

    const button =
        form.querySelector('.tire-save-action');

    if (! button) {
        return;
    }

    const grooveField =
        form.querySelector(
            '[name="tread_grooves_count"]'
        );

    const grooveCount =
        Number(grooveField?.value || 0);

    if (![3, 4].includes(grooveCount)) {
        button.disabled = true;

        button.title =
            'Informe se o pneu possui 3 ou 4 sulcos.';

        return;
    }

    const fieldNames =
        grooveCount === 4
            ? [
                'tread_1',
                'tread_2',
                'tread_3',
                'tread_4',
            ]
            : [
                'tread_1',
                'tread_2',
                'tread_3',
            ];

    const values =
        fieldNames.map((name) => {
            const field =
                form.querySelector(
                    `[name="${name}"]`
                );

            if (! field || field.disabled) {
                return null;
            }

            const raw =
                String(field.value ?? '').trim();

            if (raw === '') {
                return null;
            }

            const value =
                Number(
                    raw.replace(',', '.')
                );

            return Number.isFinite(value)
                ? value
                : null;
        });

    const complete =
        values.length === grooveCount
        &&
        values.every(
            (value) => value !== null
        );

    if (! complete) {
        button.disabled = true;

        button.title =
            'Preencha todas as leituras dos sulcos.';

        return;
    }

    const previousComparable =
        form.dataset.previousComparable === '1';

    const previousGrooveCount =
        Number(
            form.dataset.previousGrooveCount || 0
        );

    let changed = true;

    if (
        previousComparable
        &&
        previousGrooveCount === grooveCount
    ) {
        const previous = [
            Number(form.dataset.previousTread1),
            Number(form.dataset.previousTread2),
            Number(form.dataset.previousTread3),
            Number(form.dataset.previousTread4),
        ].slice(0, grooveCount);

        changed =
            values.some(
                (value, index) =>
                    Math.round(value * 100)
                    !==
                    Math.round(previous[index] * 100)
            );
    }

    button.disabled = ! changed;

    button.title =
        changed
            ? 'Salvar nova medição.'
            : 'As leituras são iguais à última medição registrada.';
}


function confirmTireKmReading(form, field) {
    const input = form.querySelector(`[name="${field}"]`);
    const confirmation = form.querySelector('[name="km_reading_confirmed"]');
    confirmation.value = '0';

    if (! input || input.value === '') return true;

    if (Number(input.value) - Number(input.dataset.currentReading || 0) <= 500) {
        return true;
    }

    const confirmed = confirm('O KM informado está muito acima da leitura atual. Confirma que a leitura está correta?');
    confirmation.value = confirmed ? '1' : '0';

    if (! confirmed) input.focus();

    return confirmed;
}

function vehicleTiresPage() {

    return {

        removeTireModalOpen: false,



        removePositionCode: null,



        removePositionLabel: null,



        removeTireId: null,



        removeTireCode: null,



        removeKm: 0,



        removeDestination: 'available',



        removeReason: '',

        tirePickerOpen: false,

        tireSearch: '',



        selectedPositionCode: null,



        selectedPositionLabel: null,



        selectedTire: null,



        installedKm: 0,



        availableTires: @json($availableTires),



        openRemoveTireModal(positionCode, positionLabel, tireId, tireCode, currentKm) {

            this.removePositionCode =

                positionCode;



            this.removePositionLabel =

                positionLabel;



            this.removeTireId =

                tireId;



            this.removeTireCode =

                tireCode;



            this.removeKm =

                currentKm || 0;



            this.removeDestination =

                'available';



            this.removeReason =

                '';



            this.removeTireModalOpen =

                true;



            this.$nextTick(() => {

            });

        },



        closeRemoveTireModal() {

            this.removeTireModalOpen =

                false;

        },



        openTirePicker(positionCode, positionLabel, currentKm) {

            this.selectedPositionCode =

                positionCode;



            this.selectedPositionLabel =

                positionLabel;



            this.installedKm =

                currentKm || 0;



            this.selectedTire =

                null;



            this.tireSearch =

                '';



            this.tirePickerOpen =

                true;



            this.$nextTick(() => {



                const input =

                    document.querySelector('.tire-picker-search input');



                if (input) {

                    input.focus();

                }

            });

        },



        closeTirePicker() {

            this.tirePickerOpen =

                false;

        },



        selectTire(tire) {

            this.selectedTire =

                tire;



            this.$nextTick(() => {

            });

        },



        filteredTires() {

            const term =

                String(this.tireSearch || '')

                    .toLowerCase()

                    .trim();



            return this.availableTires

                .filter(function (tire) {



                    if (!term) {

                        return true;

                    }



                    return [

                        tire.code,

                        tire.brand,

                        tire.model,

                        tire.size,

                    ]

                        .join(' ')

                        .toLowerCase()

                        .includes(term);

                })

                .slice(0, 50);

        },

    };

}

</script>

@endsection
