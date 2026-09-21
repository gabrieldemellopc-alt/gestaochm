@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/pages/fuel.css') }}?v=9">
@endpush

@section('content')

<main class="fuel-page fuel-archive-page">

<header class="fuel-archive-header">

    <div>
        <span class="fuel-kicker">
            Arquivo operacional
        </span>

        <h1>
            Arquivo diário de abastecimentos
        </h1>

        <p>
            Consulte os lançamentos e preserve as fichas
            e documentos de cada dia.
        </p>
    </div>

    <div class="fuel-archive-header-actions">

        <a
            href="{{ route(
                'fuel.tanks.index',
                ['manual_sheet' => 1]
            ) }}"
            class="fuel-secondary-action"
        >
            <i class="bi bi-printer"></i>
            Ficha manual
        </a>

        <a
            href="{{ route('fuel.tanks.index') }}"
            class="fuel-secondary-action"
        >
            <i class="bi bi-arrow-left"></i>
            Voltar
        </a>

    </div>

</header>


@if(session('success'))
    <div class="fuel-daily-alert is-success">
        {{ session('success') }}
    </div>
@endif


@if($errors->any())
    <div class="fuel-daily-alert is-warning">
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif


<section class="fuel-archive-calendar-panel">

    <div class="fuel-archive-calendar-head">

        <a
            href="{{ route(
                'fuel.daily-check.index',
                [
                    'month' =>
                        $month->copy()
                            ->subMonth()
                            ->format('Y-m'),
                    'date' =>
                        $month->copy()
                            ->subMonth()
                            ->startOfMonth()
                            ->format('Y-m-d'),
                ]
            ) }}"
            class="fuel-archive-month-nav"
            title="Mês anterior"
        >
            <i class="bi bi-chevron-left"></i>
        </a>

        <div>
            <span>Calendário operacional</span>

            <h2>
                {{ ucfirst(
                    $month
                        ->locale('pt_BR')
                        ->translatedFormat('F Y')
                ) }}
            </h2>
        </div>

        <a
            href="{{ route(
                'fuel.daily-check.index',
                [
                    'month' =>
                        $month->copy()
                            ->addMonth()
                            ->format('Y-m'),
                    'date' =>
                        $month->copy()
                            ->addMonth()
                            ->startOfMonth()
                            ->format('Y-m-d'),
                ]
            ) }}"
            class="fuel-archive-month-nav"
            title="Próximo mês"
        >
            <i class="bi bi-chevron-right"></i>
        </a>

    </div>


    <div class="fuel-archive-weekdays">
        @foreach(
            ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb']
            as $weekday
        )
            <span>{{ $weekday }}</span>
        @endforeach
    </div>


    <div class="fuel-archive-calendar">

        @foreach($calendarWeeks as $week)

            @foreach($week as $day)

                <a
                    href="{{ route(
                        'fuel.daily-check.index',
                        [
                            'date' =>
                                $day['date']
                                    ->format('Y-m-d'),
                            'month' =>
                                $month->format('Y-m'),
                        ]
                    ) }}"
                    class="
                        fuel-archive-day
                        {{ $day['in_month']
                            ? ''
                            : 'is-outside' }}
                        {{ $day['selected']
                            ? 'is-selected'
                            : '' }}
                        {{ $day['fillings_count'] > 0
                            ? 'has-fillings'
                            : '' }}
                        {{ $day['files_count'] > 0
                            ? 'has-files'
                            : '' }}
                    "
                >

                    <strong>
                        {{ $day['date']->day }}
                    </strong>

                    @if($day['fillings_count'] > 0)
                        <small>
                            {{ $day['fillings_count'] }}
                            lançamento(s)
                        </small>
                    @endif

                    <span class="fuel-archive-day-status">

                        @if($day['files_count'] > 0)

                            <i
                                class="bi bi-paperclip"
                                title="Documento arquivado"
                            ></i>

                        @elseif($day['fillings_count'] > 0)

                            <i
                                class="bi bi-circle-fill"
                                title="Sem documento arquivado"
                            ></i>

                        @endif

                    </span>

                </a>

            @endforeach

        @endforeach

    </div>


    <div class="fuel-archive-calendar-legend">

        <span>
            <i class="bi bi-paperclip"></i>
            Documento arquivado
        </span>

        <span>
            <i class="bi bi-circle-fill"></i>
            Abastecimentos sem documento
        </span>

    </div>

</section>


<section class="fuel-archive-day-header">

    <div>
        <span>Dia selecionado</span>

        <h2>
            {{ $date->format('d/m/Y') }}
        </h2>
    </div>

    <form
        method="GET"
        action="{{ route('fuel.daily-check.index') }}"
        class="fuel-archive-date-jump"
    >
        <input
            type="date"
            name="date"
            value="{{ $date->format('Y-m-d') }}"
            onchange="this.form.submit()"
        >
    </form>

</section>


<section class="fuel-archive-kpis">

    <article>
        <span>Abastecimentos</span>
        <strong>{{ $fillings->count() }}</strong>
    </article>

    <article>
        <span>Veículos</span>
        <strong>{{ $vehicleCount }}</strong>
    </article>

    @foreach($summary as $row)
        <article>
            <span>
                {{ $row['product_name'] }}
            </span>

            <strong>
                {{ number_format(
                    $row['system_liters'],
                    3,
                    ',',
                    '.'
                ) }} L
            </strong>

            <small>
                {{ $row['fillings_count'] }}
                lançamento(s)
            </small>
        </article>
    @endforeach

</section>


<section class="fuel-archive-grid">

    <section class="fuel-archive-panel">

        <div class="fuel-archive-section-head">
            <div>
                <span>Documentos do dia</span>
                <h2>Fichas e comprovantes</h2>
            </div>

            <span class="fuel-archive-count">
                {{ $check->files->count() }}
            </span>
        </div>


        @if($check->files->isNotEmpty())

            <div
                class="fuel-archive-documents"
                id="fuelDailyFiles"
            >

                @foreach($check->files as $file)

                    @php
                        $isImage =
                            str_starts_with(
                                (string) $file->mime_type,
                                'image/'
                            );

                        $sourceLabel = match(
                            $file->source
                        ) {
                            'photo_import' =>
                                'Importação via foto (IA)',

                            'qr_mobile' =>
                                'Enviado pelo celular',

                            'manual_upload' =>
                                'Anexo manual',

                            'web' =>
                                'Anexo manual',

                            default =>
                                $file->source
                                ?: 'Documento',
                        };
                    @endphp

                    <article
                        class="fuel-archive-document"
                        data-file-id="{{ $file->id }}"
                    >

                        <a
                            href="{{ route(
                                'fuel.daily-check.files.show',
                                [$check, $file]
                            ) }}"
                            target="_blank"
                            rel="noopener"
                            class="fuel-archive-document-preview"
                        >

                            @if($isImage)

                                <img
                                    src="{{ route(
                                        'fuel.daily-check.files.show',
                                        [$check, $file]
                                    ) }}"
                                    alt="{{ $file->original_name }}"
                                >

                            @else

                                <div>
                                    <i class="bi bi-file-earmark-pdf"></i>
                                    <span>PDF</span>
                                </div>

                            @endif

                        </a>


                        <div class="fuel-archive-document-body">

                            <strong>
                                {{ $file->original_name }}
                            </strong>

                            <span>
                                {{ $sourceLabel }}
                            </span>

                            <small>
                                {{ $file->created_at
                                    ?->format('d/m/Y H:i') }}
                            </small>

                            <div>
                                <a
                                    href="{{ route(
                                        'fuel.daily-check.files.show',
                                        [$check, $file]
                                    ) }}"
                                    target="_blank"
                                    rel="noopener"
                                    class="fuel-secondary-action"
                                >
                                    <i class="bi bi-eye"></i>
                                    Visualizar
                                </a>

                                <button
                                    type="button"
                                    class="fuel-daily-file-delete"
                                    data-delete-url="{{ route(
                                        'fuel.daily-check.files.delete',
                                        [$check, $file]
                                    ) }}"
                                >
                                    <i class="bi bi-trash3"></i>
                                    Excluir
                                </button>
                            </div>

                        </div>

                    </article>

                @endforeach

            </div>

        @else

            <div class="fuel-archive-empty-document">

                <i class="bi bi-file-earmark-image"></i>

                <strong>
                    Nenhum documento arquivado
                </strong>

                <span>
                    Você pode anexar a ficha pelo computador
                    ou fotografá-la pelo celular.
                </span>

            </div>

        @endif


        <form
            method="POST"
            action="{{ route(
                'fuel.daily-check.files.store'
            ) }}"
            enctype="multipart/form-data"
            class="fuel-archive-upload-form"
        >
            @csrf

            <input
                type="hidden"
                name="operation_date"
                value="{{ $date->format('Y-m-d') }}"
            >

            <label
                class="fuel-archive-file-picker"
                id="fuelArchiveFilePicker"
            >

                <i class="bi bi-cloud-arrow-up"></i>

                <span>
                    <strong>Selecionar arquivo</strong>
                    <small>
                        JPG, PNG, WEBP ou PDF · até 12 MB
                    </small>
                </span>

                <input
                    type="file"
                    name="files[]"
                    accept="image/jpeg,image/png,image/webp,application/pdf"
                    multiple
                    onchange="showArchiveSelectedFiles(this)"
                >

            </label>

            <small id="dailySelectedFiles">
                Nenhum arquivo selecionado.
            </small>

            <button
                type="submit"
                id="fuelArchiveSubmit"
                class="fuel-primary-action"
                disabled
            >
                <i class="bi bi-archive"></i>
                Arquivar documento
            </button>

        </form>


        <div class="fuel-archive-mobile-upload">

            <div>
                <i class="bi bi-phone"></i>

                <span>
                    <strong>Enviar pelo celular</strong>

                    <small>
                        Gere um QR Code e fotografe a ficha.
                    </small>
                </span>
            </div>

            <button
                type="button"
                id="fuelDailyGenerateQr"
                class="fuel-secondary-action"
                data-url="{{ route(
                    'fuel.daily-check.upload-token',
                    $check
                ) }}"
            >
                <i class="bi bi-qr-code"></i>
                Gerar QR Code
            </button>

        </div>


        <div
            id="fuelDailyQrBox"
            class="fuel-daily-qr-box fuel-daily-v2-qr"
            data-files-status-url="{{ route(
                'fuel.daily-check.files.status',
                $check
            ) }}"
            data-files-count="{{ $check->files->count() }}"
            hidden
        >

            <img
                id="fuelDailyQrImage"
                alt="QR Code para envio da ficha"
            >

            <div class="fuel-daily-v2-qr-content">

                <strong>
                    Escaneie com o celular
                </strong>

                <p id="fuelDailyQrExpiry"></p>

                <input
                    id="fuelDailyUploadUrl"
                    readonly
                >

                <button
                    type="button"
                    id="fuelDailyCopyUrl"
                    class="fuel-secondary-action"
                >
                    <i class="bi bi-copy"></i>
                    Copiar link
                </button>

                <span id="fuelDailyQrStatus">
                    Aguardando arquivo do celular...
                </span>

            </div>

        </div>

    </section>


    <section class="fuel-archive-panel">

        <div class="fuel-archive-section-head">
            <div>
                <span>Lançamentos do dia</span>
                <h2>Abastecimentos registrados</h2>
            </div>
        </div>


        @if($fillings->isEmpty())

            <div class="fuel-archive-empty-document">

                <i class="bi bi-fuel-pump"></i>

                <strong>
                    Nenhum abastecimento registrado
                </strong>

                <span>
                    Não há lançamentos válidos nesta data.
                </span>

            </div>

        @else

            <div class="fuel-archive-fillings">

                @foreach($fillings as $filling)

                    <article>

                        <time>
                            {{ $filling->filled_at
                                ->format('H:i') }}
                        </time>

                        <div class="fuel-archive-filling-vehicle">

                            <strong>
                                {{ $filling->vehicle?->name
                                    ?? 'Veículo' }}
                            </strong>

                            <small>
                                {{ $filling->vehicle?->plate
                                    ?? '—' }}
                            </small>

                        </div>

                        <div>
                            <strong>
                                {{ $filling->product?->name
                                    ?? 'Produto' }}
                            </strong>

                            <small>
                                {{ $filling->source === 'internal_tank'
                                    ? 'Tanque da unidade'
                                    : 'Posto externo' }}
                            </small>
                        </div>

                        <strong class="fuel-archive-filling-liters">
                            {{ number_format(
                                (float) $filling->quantity_liters,
                                3,
                                ',',
                                '.'
                            ) }} L
                        </strong>

                        <span class="fuel-archive-filling-km">
                            @if($filling->vehicle_km !== null)

                                {{ number_format(
                                    (float) $filling->vehicle_km,
                                    0,
                                    ',',
                                    '.'
                                ) }} km

                            @else
                                —
                            @endif
                        </span>

                    </article>

                @endforeach

            </div>

        @endif

    </section>

</section>


@if(filled($check->notes))

<section class="fuel-archive-panel fuel-archive-notes">

    <div class="fuel-archive-section-head">
        <div>
            <span>Observação arquivada</span>
            <h2>Informações adicionais</h2>
        </div>
    </div>

    <p>
        {{ $check->notes }}
    </p>

</section>

@endif

</main>


<script>
function showArchiveSelectedFiles(input) {
    const target =
        document.getElementById(
            'dailySelectedFiles'
        );

    const submit =
        document.getElementById(
            'fuelArchiveSubmit'
        );

    const picker =
        document.getElementById(
            'fuelArchiveFilePicker'
        );

    const count =
        input.files?.length || 0;

    if (target) {
        target.textContent =
            count === 0
                ? 'Nenhum arquivo selecionado.'
                : count === 1
                    ? input.files[0].name
                    : count + ' arquivos selecionados.';
    }

    if (submit) {
        submit.disabled =
            count === 0;
    }

    if (picker) {
        picker.classList.toggle(
            'has-file',
            count > 0
        );
    }
}


(() => {
    const generate =
        document.getElementById(
            'fuelDailyGenerateQr'
        );

    const box =
        document.getElementById(
            'fuelDailyQrBox'
        );

    const image =
        document.getElementById(
            'fuelDailyQrImage'
        );

    const expiry =
        document.getElementById(
            'fuelDailyQrExpiry'
        );

    const urlInput =
        document.getElementById(
            'fuelDailyUploadUrl'
        );

    const copy =
        document.getElementById(
            'fuelDailyCopyUrl'
        );

    const status =
        document.getElementById(
            'fuelDailyQrStatus'
        );

    if (!generate || !box) {
        return;
    }

    let pollTimer = null;

    let previousCount =
        Number(
            box.dataset.filesCount || 0
        );


    function startPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
        }

        pollTimer =
            setInterval(
                async () => {
                    try {
                        const response =
                            await fetch(
                                box.dataset.filesStatusUrl,
                                {
                                    headers: {
                                        'Accept':
                                            'application/json'
                                    }
                                }
                            );

                        if (!response.ok) {
                            return;
                        }

                        const data =
                            await response.json();

                        if (
                            data.count
                            > previousCount
                        ) {
                            previousCount =
                                data.count;

                            clearInterval(
                                pollTimer
                            );

                            status.innerHTML =
                                '<strong>'
                                + '✓ Arquivo recebido pelo celular.'
                                + '</strong>';

                            setTimeout(
                                () =>
                                    window.location.reload(),
                                850
                            );
                        }

                    } catch (_) {}
                },
                2500
            );
    }


    generate.addEventListener(
        'click',
        async () => {
            if (generate.disabled) {
                return;
            }

            const original =
                generate.innerHTML;

            generate.disabled = true;

            generate.innerHTML =
                'Gerando QR Code...';

            try {
                const response =
                    await fetch(
                        generate.dataset.url,
                        {
                            method: 'POST',
                            headers: {
                                'Accept':
                                    'application/json',
                                'X-CSRF-TOKEN':
                                    @json(csrf_token())
                            }
                        }
                    );

                const data =
                    await response.json();

                if (!response.ok) {
                    throw new Error(
                        data.message
                        || 'Não foi possível gerar o QR Code.'
                    );
                }

                image.src = data.qr;

                expiry.textContent =
                    'Link válido até '
                    + data.expires_at
                    + '.';

                urlInput.value =
                    data.url;

                box.hidden = false;

                status.textContent =
                    'Aguardando arquivo do celular...';

                previousCount =
                    Number(
                        data.files_count || 0
                    );

                startPolling();

            } catch (error) {
                alert(
                    error.message
                    || 'Não foi possível gerar o QR Code.'
                );

            } finally {
                generate.disabled = false;
                generate.innerHTML = original;
            }
        }
    );


    if (copy) {
        copy.addEventListener(
            'click',
            async () => {
                if (!urlInput.value) {
                    return;
                }

                await navigator.clipboard
                    .writeText(
                        urlInput.value
                    );

                const old =
                    copy.innerHTML;

                copy.innerHTML =
                    '<i class="bi bi-check2"></i> Copiado';

                setTimeout(
                    () =>
                        copy.innerHTML = old,
                    1400
                );
            }
        );
    }
})();


async function deleteArchiveFile(button) {
    if (
        !confirm(
            'Excluir este documento do arquivo diário?'
        )
    ) {
        return;
    }

    const card =
        button.closest(
            '.fuel-archive-document'
        );

    const original =
        button.innerHTML;

    button.disabled = true;
    button.innerHTML =
        'Excluindo...';

    try {
        const response =
            await fetch(
                button.dataset.deleteUrl,
                {
                    method: 'DELETE',
                    headers: {
                        'Accept':
                            'application/json',
                        'X-CSRF-TOKEN':
                            @json(csrf_token())
                    }
                }
            );

        const data =
            await response.json();

        if (!response.ok) {
            throw new Error(
                data.message
                || 'Não foi possível excluir.'
            );
        }

        card?.remove();

    } catch (error) {
        button.disabled = false;
        button.innerHTML = original;

        alert(
            error.message
            || 'Não foi possível excluir.'
        );
    }
}


document.addEventListener(
    'click',
    event => {
        const button =
            event.target.closest(
                '.fuel-daily-file-delete'
            );

        if (button) {
            deleteArchiveFile(
                button
            );
        }
    }
);
</script>

@endsection
