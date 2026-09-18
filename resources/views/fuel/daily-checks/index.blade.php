@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/pages/fuel.css') }}?v=8">
@endpush

@section('content')

<main class="fuel-page fuel-daily-check-page fuel-daily-v2">

<header class="fuel-daily-v2-header">

    <div class="fuel-daily-v2-heading">

        <span class="fuel-kicker">
            Conferência operacional
        </span>

        <h1>
            Conferência diária de abastecimento
        </h1>

        <p>
            Compare os lançamentos do CHM com a folha manual do operador.
        </p>

    </div>


    <div class="fuel-daily-v2-header-actions">

        <form
            method="GET"
            action="{{ route('fuel.daily-check.index') }}"
            class="fuel-daily-v2-date"
        >
            <label>
                <span>Data da operação</span>

                <input
                    type="date"
                    name="date"
                    value="{{ $date->format('Y-m-d') }}"
                    onchange="this.form.submit()"
                >
            </label>
        </form>


        <a
            href="{{ route('fuel.tanks.index', ['manual_sheet' => 1]) }}"
            class="fuel-secondary-action fuel-daily-v2-manual-sheet"
            title="Abrir ficha manual de abastecimento"
        >
            <i class="bi bi-printer"></i>
            Ficha manual
        </a>

        <a
            href="{{ route('fuel.tanks.index') }}"
            class="fuel-secondary-action fuel-daily-v2-back"
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


@if($changedAfterCheck)
    <div class="fuel-daily-alert is-warning">
        <strong>
            <i class="bi bi-exclamation-triangle"></i>
            Lançamentos alterados após a conferência
        </strong>

        <span>
            Houve inclusão, edição ou cancelamento de abastecimento
            depois da última conferência desta data.
        </span>
    </div>
@endif


<form
    method="POST"
    action="{{ route('fuel.daily-check.store') }}"
    enctype="multipart/form-data"
    class="fuel-daily-check-form"
>
@csrf

<input
    type="hidden"
    name="operation_date"
    value="{{ $date->format('Y-m-d') }}"
>


<section class="fuel-daily-v2-main-grid">

<section class="fuel-daily-v2-panel fuel-daily-v2-summary">

    <div class="fuel-daily-v2-section-head">
        <div>
            <span>Resumo automático</span>
            <h2>Abastecimentos registrados no CHM</h2>
        </div>
    </div>


    @if($summary->isEmpty())

        <div class="fuel-daily-v2-empty">
            Nenhum abastecimento válido encontrado em
            {{ $date->format('d/m/Y') }}.
        </div>

    @else

        <div class="fuel-daily-v2-products">

            @foreach($summary as $row)

                @php
                    $key =
                        $row['source'].'|'.$row['fuel_product_id'];

                    $saved =
                        $savedItems->get($key);

                    $manualValue =
                        old(
                            'manual.'.$key,
                            $saved?->manual_liters
                        );
                @endphp

                <article class="fuel-daily-v2-product">

                    <div class="fuel-daily-v2-product-main">

                        <span>
                            {{ $row['source_label'] }}
                        </span>

                        <strong>
                            {{ $row['product_name'] }}
                        </strong>

                        <small>
                            {{ $row['fillings_count'] }}
                            abastecimento(s)
                        </small>

                    </div>


                    <div class="fuel-daily-v2-chm-total">
                        <span>CHM</span>

                        <strong>
                            {{ number_format(
                                $row['system_liters'],
                                3,
                                ',',
                                '.'
                            ) }} L
                        </strong>
                    </div>


                    <label class="fuel-daily-v2-manual">
                        <span>Total na folha</span>

                        <div>
                            <input
                                type="number"
                                min="0"
                                step="0.001"
                                name="manual[{{ $key }}]"
                                value="{{ $manualValue }}"
                                data-system-liters="{{ $row['system_liters'] }}"
                                oninput="updateDailyDifference(this)"
                                required
                            >

                            <b>L</b>
                        </div>

                        <small data-difference>
                            @if($saved?->difference_liters !== null)
                                Diferença:
                                {{ number_format(
                                    (float) $saved->difference_liters,
                                    3,
                                    ',',
                                    '.'
                                ) }} L
                            @else
                                Informe o total anotado.
                            @endif
                        </small>
                    </label>

                </article>

            @endforeach

        </div>

    @endif

</section>


<section class="fuel-daily-v2-panel fuel-daily-v2-document">

    <div class="fuel-daily-v2-section-head">
        <div>
            <span>Documento de apoio</span>
            <h2>Folha manual preenchida</h2>
        </div>
    </div>


    <div class="fuel-daily-v2-upload-options">

        <div class="fuel-daily-v2-upload-option">

            <i class="bi bi-cloud-arrow-up"></i>

            <strong>Selecionar arquivo</strong>

            <p>
                Foto, imagem digitalizada ou PDF da folha preenchida.
            </p>

            <label class="fuel-daily-v2-file-picker">
                <span>Selecionar arquivo</span>

                <input
                    type="file"
                    name="files[]"
                    accept="image/jpeg,image/png,image/webp,application/pdf"
                    multiple
                    onchange="showDailySelectedFiles(this)"
                >
            </label>

            <small id="dailySelectedFiles">
                Nenhum arquivo selecionado.
            </small>

        </div>


        <div class="fuel-daily-v2-upload-option">

            <i class="bi bi-qr-code"></i>

            <strong>Enviar pelo celular</strong>

            <p>
                Gere um QR Code e fotografe a folha diretamente pelo celular.
            </p>

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
            alt="QR Code para envio da folha"
        >

        <div class="fuel-daily-v2-qr-content">
            <strong>Escaneie com o celular</strong>

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


    @if($check->files->isNotEmpty())

        <div
            class="fuel-daily-files fuel-daily-v2-files"
            id="fuelDailyFiles"
        >

            @foreach($check->files as $file)

                <div
                    class="fuel-daily-file-row"
                    data-file-id="{{ $file->id }}"
                >

                    <a
                        href="{{ route(
                            'fuel.daily-check.files.show',
                            [$check, $file]
                        ) }}"
                        target="_blank"
                        rel="noopener"
                        class="fuel-daily-file-link"
                    >
                        <i class="bi bi-paperclip"></i>

                        <span>
                            {{ $file->original_name }}
                        </span>

                        <small>
                            {{ $file->source === 'qr_mobile'
                                ? 'Celular'
                                : 'Computador' }}
                        </small>
                    </a>

                    <button
                        type="button"
                        class="fuel-daily-file-delete"
                        data-delete-url="{{ route(
                            'fuel.daily-check.files.delete',
                            [$check, $file]
                        ) }}"
                        title="Excluir arquivo"
                    >
                        <i class="bi bi-trash3"></i>
                        <span>Excluir</span>
                    </button>

                </div>

            @endforeach

        </div>

    @endif

</section>


</section>

<section class="fuel-daily-v2-panel fuel-daily-v2-observation">

    <label class="fuel-daily-v2-notes">
        <span>Observação</span>

        <textarea
            name="notes"
            rows="3"
            placeholder="Ex.: folha entregue pelo operador após encerramento dos abastecimentos."
        >{{ old('notes', $check->notes) }}</textarea>
    </label>


    @if($summary->isNotEmpty())

        <div class="fuel-daily-v2-save">

            @if($check->checked_at)

                <div class="fuel-daily-v2-last-check">
                    <span>Última conferência</span>

                    <strong>
                        {{ $check->checked_at->format('d/m/Y H:i') }}
                    </strong>

                    <small>
                        {{ $check->checker?->name ?? 'Usuário' }}
                    </small>
                </div>

            @endif

            <button class="fuel-primary-action">
                <i class="bi bi-check2-circle"></i>

                {{ $check->checked_at
                    ? 'Atualizar conferência'
                    : 'Salvar conferência' }}
            </button>

        </div>

    @endif

</section>

</form>





<section class="fuel-daily-v2-panel fuel-daily-v2-history">

    <div class="fuel-daily-v2-section-head">
        <div>
            <span>Histórico</span>
            <h2>Últimas conferências</h2>
        </div>
    </div>

    <div class="fuel-daily-history">

        @forelse($history as $item)

            <a
                href="{{ route(
                    'fuel.daily-check.index',
                    ['date' => $item->operation_date->format('Y-m-d')]
                ) }}"
            >

                <strong>
                    {{ $item->operation_date->format('d/m/Y') }}
                </strong>

                <span class="is-{{ $item->status }}">
                    {{ $item->status === 'checked'
                        ? 'Conferido'
                        : 'Com divergência' }}
                </span>

                <span>
                    {{ number_format(
                        (float) $item->difference_liters,
                        3,
                        ',',
                        '.'
                    ) }} L
                </span>

                <small>
                    {{ $item->checker?->name }}
                </small>

            </a>

        @empty

            <div class="fuel-daily-v2-empty">
                Nenhuma conferência concluída ainda.
            </div>

        @endforelse

    </div>

</section>

</main>


<script>
function updateDailyDifference(input) {
    const system = Number(input.dataset.systemLiters || 0);
    const manual = Number(input.value);

    const target = input
        .closest('.fuel-daily-v2-manual')
        .querySelector('[data-difference]');

    if (input.value === '' || Number.isNaN(manual)) {
        target.textContent = 'Informe o total anotado.';
        return;
    }

    const diff = manual - system;

    target.textContent =
        'Diferença: '
        + diff.toLocaleString('pt-BR', {
            minimumFractionDigits: 3,
            maximumFractionDigits: 3
        })
        + ' L';

    target.classList.toggle(
        'has-divergence',
        Math.abs(diff) > 0.001
    );
}

function showDailySelectedFiles(input) {
    const target =
        document.getElementById('dailySelectedFiles');

    const count = input.files.length;

    target.textContent =
        count === 0
            ? 'Nenhum arquivo selecionado.'
            : count === 1
                ? input.files[0].name
                : count + ' arquivos selecionados.';
}

(() => {
    const generate =
        document.getElementById('fuelDailyGenerateQr');

    const box =
        document.getElementById('fuelDailyQrBox');

    const image =
        document.getElementById('fuelDailyQrImage');

    const expiry =
        document.getElementById('fuelDailyQrExpiry');

    const urlInput =
        document.getElementById('fuelDailyUploadUrl');

    const copy =
        document.getElementById('fuelDailyCopyUrl');

    const status =
        document.getElementById('fuelDailyQrStatus');

    if (!generate || !box) {
        return;
    }

    let pollTimer = null;
    let previousCount =
        Number(box.dataset.filesCount || 0);


    function startPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
        }

        pollTimer = setInterval(async () => {
            try {
                const response = await fetch(
                    box.dataset.filesStatusUrl,
                    {
                        headers: {
                            'Accept': 'application/json'
                        }
                    }
                );

                if (!response.ok) return;

                const data = await response.json();

                if (data.count > previousCount) {
                    previousCount = data.count;

                    clearInterval(pollTimer);

                    status.innerHTML =
                        '<strong>✓ Arquivo recebido pelo celular.</strong>';

                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                }

            } catch (_) {}
        }, 2500);
    }


    generate.addEventListener('click', async () => {
        if (generate.disabled) return;

        const original = generate.innerHTML;

        generate.disabled = true;

        generate.innerHTML =
            '<span>Gerando QR Code...</span>';

        try {
            const response = await fetch(
                generate.dataset.url,
                {
                    method: 'POST',

                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN':
                            @json(csrf_token())
                    }
                }
            );

            const data = await response.json();

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

            urlInput.value = data.url;

            box.hidden = false;

            status.textContent =
                'Aguardando arquivo do celular...';

            previousCount =
                Number(data.files_count || 0);

            box.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });

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
    });


    copy.addEventListener('click', async () => {
        if (!urlInput.value) return;

        await navigator.clipboard.writeText(
            urlInput.value
        );

        const old = copy.innerHTML;

        copy.innerHTML =
            '<i class="bi bi-check2"></i> Copiado';

        setTimeout(() => {
            copy.innerHTML = old;
        }, 1400);
    });
})();

async function deleteDailyCheckFile(button) {
    if (
        !confirm(
            'Excluir este arquivo da conferência? Esta ação não poderá ser desfeita.'
        )
    ) {
        return;
    }

    const row = button.closest('.fuel-daily-file-row');

    const originalHtml = button.innerHTML;

    button.disabled = true;
    button.classList.add('is-loading');

    button.innerHTML =
        '<span class="fuel-daily-delete-spinner"></span>'
        + '<span>Excluindo...</span>';

    try {
        const response = await fetch(
            button.dataset.deleteUrl,
            {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': @json(csrf_token())
                }
            }
        );

        const data = await response.json();

        if (!response.ok) {
            throw new Error(
                data.message
                || 'Não foi possível excluir o arquivo.'
            );
        }

        if (row) {
            row.classList.add('is-removing');

            setTimeout(() => {
                row.remove();

                const list =
                    document.getElementById('fuelDailyFiles');

                if (
                    list
                    && !list.querySelector(
                        '.fuel-daily-file-row'
                    )
                ) {
                    list.remove();
                }
            }, 180);
        }

        const qrBox =
            document.getElementById('fuelDailyQrBox');

        if (qrBox) {
            qrBox.dataset.filesCount =
                String(data.files_count ?? 0);
        }

    } catch (error) {
        button.disabled = false;
        button.classList.remove('is-loading');
        button.innerHTML = originalHtml;

        alert(
            error.message
            || 'Não foi possível excluir o arquivo.'
        );
    }
}


document.addEventListener('click', event => {
    const button = event.target.closest(
        '.fuel-daily-file-delete'
    );

    if (!button) {
        return;
    }

    deleteDailyCheckFile(button);
});


</script>

@endsection
