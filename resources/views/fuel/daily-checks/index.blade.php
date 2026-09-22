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
                            $isImage = str_starts_with(
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

                                'manual_upload',
                                'web' =>
                                    'Anexo manual',

                                default =>
                                    $file->source
                                    ?: 'Documento',
                            };

                            $documentTypeLabel = match(
                                $file->document_type
                            ) {
                                'fuel_invoice' =>
                                    'NF de abastecimento',

                                'fuel_sheet' =>
                                    'Folha de abastecimentos',

                                'other' =>
                                    'Outro documento',

                                default =>
                                    'Documento não classificado',
                            };

                            $linkedCount =
                                $file->receipts->count();
                        @endphp

                        <article
                            class="fuel-archive-document fuel-document-card"
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

                                <div class="fuel-document-card-heading">

                                    <strong>
                                        {{ $documentTypeLabel }}
                                    </strong>

                                    @if(
                                        $file->document_type
                                        === 'fuel_invoice'
                                        && filled(
                                            $file->invoice_number
                                        )
                                    )
                                        <span
                                            class="fuel-document-card-number"
                                        >
                                            NF {{ $file->invoice_number }}
                                        </span>
                                    @endif

                                </div>

                                <div class="fuel-document-card-summary">

                                    @if($file->document_date)
                                        <span>
                                            <i class="bi bi-calendar3"></i>

                                            {{ $file->document_date
                                                ->format('d/m/Y') }}
                                        </span>
                                    @endif

                                    @if(filled($file->supplier_name))
                                        <span>
                                            <i class="bi bi-building"></i>

                                            {{ $file->supplier_name }}
                                        </span>
                                    @endif

                                    @if(
                                        $file->document_type
                                        === 'fuel_invoice'
                                    )
                                        <span>
                                            <i class="bi bi-link-45deg"></i>

                                            {{ $linkedCount }}

                                            {{ $linkedCount === 1
                                                ? 'recebimento vinculado'
                                                : 'recebimentos vinculados' }}
                                        </span>
                                    @endif

                                </div>

                                <small class="fuel-document-card-filename">
                                    Arquivo:
                                    {{ $file->original_name }}
                                </small>

                                <small class="fuel-document-card-origin">
                                    {{ $sourceLabel }}
                                    ·
                                    {{ $file->created_at
                                        ?->format('d/m/Y H:i') }}
                                </small>

                                <div class="fuel-document-card-actions">

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
                                        class="fuel-secondary-action"
                                        onclick="document.getElementById(
                                            'fuelDocumentDetails{{ $file->id }}'
                                        ).showModal()"
                                    >
                                        <i class="bi bi-info-circle"></i>
                                        Detalhes
                                    </button>

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

                        <dialog
                            id="fuelDocumentDetails{{ $file->id }}"
                            class="fuel-document-details-modal"
                        >

                            <div class="fuel-document-details-card">

                                <header>

                                    <div>
                                        <small>
                                            Documento arquivado
                                        </small>

                                        <h3>
                                            {{ $documentTypeLabel }}
                                        </h3>

                                        @if(
                                            $file->document_type
                                            === 'fuel_invoice'
                                            && filled(
                                                $file->invoice_number
                                            )
                                        )
                                            <span>
                                                NF
                                                {{ $file->invoice_number }}
                                            </span>
                                        @endif
                                    </div>

                                    <button
                                        type="button"
                                        class="fuel-document-details-close"
                                        onclick="this.closest('dialog').close()"
                                        aria-label="Fechar"
                                    >
                                        <i class="bi bi-x-lg"></i>
                                    </button>

                                </header>

                                <div class="fuel-document-details-grid">

                                    <div>
                                        <small>Tipo</small>
                                        <strong>
                                            {{ $documentTypeLabel }}
                                        </strong>
                                    </div>

                                    <div>
                                        <small>Data do documento</small>
                                        <strong>
                                            {{ $file->document_date
                                                ?->format('d/m/Y')
                                                ?? '—' }}
                                        </strong>
                                    </div>

                                    @if(
                                        $file->document_type
                                        === 'fuel_invoice'
                                    )

                                        <div>
                                            <small>Número da NF</small>
                                            <strong>
                                                {{ $file->invoice_number
                                                    ?: '—' }}
                                            </strong>
                                        </div>

                                        <div>
                                            <small>Fornecedor</small>
                                            <strong>
                                                {{ $file->supplier_name
                                                    ?: '—' }}
                                            </strong>
                                        </div>

                                        <div>
                                            <small>CNPJ / CPF</small>
                                            <strong>
                                                {{ $file->supplier_document
                                                    ?: '—' }}
                                            </strong>
                                        </div>

                                    @endif

                                    <div>
                                        <small>Origem do arquivo</small>
                                        <strong>
                                            {{ $sourceLabel }}
                                        </strong>
                                    </div>

                                    <div>
                                        <small>Arquivado em</small>
                                        <strong>
                                            {{ $file->created_at
                                                ?->format('d/m/Y H:i')
                                                ?? '—' }}
                                        </strong>
                                    </div>

                                    <div>
                                        <small>Tamanho</small>
                                        <strong>
                                            @if($file->size_bytes)
                                                {{ number_format(
                                                    $file->size_bytes
                                                        / 1024
                                                        / 1024,
                                                    2,
                                                    ',',
                                                    '.'
                                                ) }} MB
                                            @else
                                                —
                                            @endif
                                        </strong>
                                    </div>

                                </div>

                                <div class="fuel-document-details-file">

                                    <small>Nome do arquivo</small>

                                    <strong>
                                        {{ $file->original_name }}
                                    </strong>

                                </div>

                                @if(
                                    $file->document_type
                                    === 'fuel_invoice'
                                )

                                    <div class="fuel-document-details-receipts">

                                        <div class="fuel-document-details-receipts-head">
                                            <strong>
                                                Recebimentos vinculados
                                            </strong>

                                            <span>
                                                {{ $linkedCount }}
                                            </span>
                                        </div>

                                        @forelse(
                                            $file->receipts
                                            as $receipt
                                        )

                                            <details
                                                class="fuel-document-receipt-detail"
                                            >

                                                <summary>

                                                    <div>
                                                        <strong>
                                                            {{ $receipt->received_at
                                                                ?->format(
                                                                    'd/m/Y H:i'
                                                                ) }}
                                                        </strong>

                                                        <small>
                                                            {{ $receipt->tank?->name
                                                                ?? 'Tanque' }}

                                                            @if(
                                                                filled(
                                                                    $receipt
                                                                        ->supplier_name
                                                                )
                                                            )
                                                                ·
                                                                {{ $receipt
                                                                    ->supplier_name }}
                                                            @endif
                                                        </small>
                                                    </div>

                                                    <div class="fuel-document-receipt-summary-right">

                                                        <strong>
                                                            {{ number_format(
                                                                (float) $receipt
                                                                    ->quantity_liters,
                                                                3,
                                                                ',',
                                                                '.'
                                                            ) }} L
                                                        </strong>

                                                        <i class="bi bi-chevron-down"></i>

                                                    </div>

                                                </summary>

                                                <div class="fuel-document-receipt-expanded">

                                                    <div>
                                                        <small>Tanque</small>
                                                        <strong>
                                                            {{ $receipt->tank?->name
                                                                ?? '—' }}
                                                        </strong>
                                                    </div>

                                                    <div>
                                                        <small>Quantidade recebida</small>
                                                        <strong>
                                                            {{ number_format(
                                                                (float) $receipt
                                                                    ->quantity_liters,
                                                                3,
                                                                ',',
                                                                '.'
                                                            ) }} L
                                                        </strong>
                                                    </div>

                                                    <div>
                                                        <small>Fornecedor</small>
                                                        <strong>
                                                            {{ $receipt->supplier_name
                                                                ?: '—' }}
                                                        </strong>
                                                    </div>

                                                    <div>
                                                        <small>CNPJ / CPF</small>
                                                        <strong>
                                                            {{ $receipt->supplier_document
                                                                ?: '—' }}
                                                        </strong>
                                                    </div>

                                                    <div>
                                                        <small>NF informada no recebimento</small>
                                                        <strong>
                                                            {{ $receipt->invoice_number
                                                                ?: '—' }}
                                                        </strong>
                                                    </div>

                                                    <div>
                                                        <small>Data da NF</small>
                                                        <strong>
                                                            {{ $receipt->invoice_date
                                                                ?->format('d/m/Y')
                                                                ?? '—' }}
                                                        </strong>
                                                    </div>

                                                    <div>
                                                        <small>Custo unitário</small>
                                                        <strong>
                                                            @if(
                                                                $receipt->unit_cost
                                                                !== null
                                                            )
                                                                R$
                                                                {{ number_format(
                                                                    (float) $receipt
                                                                        ->unit_cost,
                                                                    4,
                                                                    ',',
                                                                    '.'
                                                                ) }}
                                                            @else
                                                                —
                                                            @endif
                                                        </strong>
                                                    </div>

                                                    <div>
                                                        <small>Custo total</small>
                                                        <strong>
                                                            @if(
                                                                $receipt->total_cost
                                                                !== null
                                                            )
                                                                R$
                                                                {{ number_format(
                                                                    (float) $receipt
                                                                        ->total_cost,
                                                                    2,
                                                                    ',',
                                                                    '.'
                                                                ) }}
                                                            @else
                                                                —
                                                            @endif
                                                        </strong>
                                                    </div>

                                                    @if(
                                                        filled($receipt->notes)
                                                    )
                                                        <div class="fuel-document-receipt-notes">
                                                            <small>Observações</small>
                                                            <strong>
                                                                {{ $receipt->notes }}
                                                            </strong>
                                                        </div>
                                                    @endif

                                                </div>

                                            </details>

                                        @empty

                                            <div class="fuel-document-details-empty">
                                                Nenhum recebimento vinculado.
                                            </div>

                                        @endforelse

                                    </div>

                                @endif

                                <footer>

                                    <button
                                        type="button"
                                        class="fuel-secondary-action"
                                        onclick="this.closest('dialog').close()"
                                    >
                                        Fechar
                                    </button>

                                    <a
                                        href="{{ route(
                                            'fuel.daily-check.files.show',
                                            [$check, $file]
                                        ) }}"
                                        target="_blank"
                                        rel="noopener"
                                        class="fuel-primary-action fuel-document-open-action"
                                    >
                                        <i class="bi bi-eye"></i>
                                        Abrir anexo
                                    </a>

                                </footer>

                            </div>

                        </dialog>

                        @if(
                            (int) request('open_document')
                            === (int) $file->id
                        )
                            <script>
                                document.addEventListener(
                                    'DOMContentLoaded',
                                    function () {
                                        const dialog =
                                            document.getElementById(
                                                'fuelDocumentDetails{{ $file->id }}'
                                            );

                                        if (
                                            dialog
                                            && ! dialog.open
                                        ) {
                                            dialog.showModal();
                                        }
                                    }
                                );
                            </script>
                        @endif

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


        @php
            $focusedReceiptId =
                request()->integer('receipt_id');

            $focusedReceipt =
                $focusedReceiptId > 0
                    ? $receiptCandidates->firstWhere(
                        'id',
                        $focusedReceiptId
                    )
                    : null;
        @endphp

        <form
            id="fuelArchiveUploadForm"
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

            <div class="fuel-document-fields fuel-document-type-only">

                    <div class="fuel-document-field">
                        <span>Tipo de documento</span>

                        <div class="fuel-document-toggle-group">

                            <label class="fuel-document-toggle-option">
                                <input
                                    type="radio"
                                    name="document_type"
                                    value="fuel_invoice"
                                    @checked(
                                        old(
                                            'document_type',
                                            request(
                                                'document_type'
                                            )
                                        )
                                        === 'fuel_invoice'
                                    )
                                >

                                <span>NF Abastecimento</span>
                            </label>

                            <label class="fuel-document-toggle-option">
                                <input
                                    type="radio"
                                    name="document_type"
                                    value="fuel_sheet"
                                    @checked(
                                        old('document_type')
                                        === 'fuel_sheet'
                                    )
                                >

                                <span>Folha de Abastecimentos</span>
                            </label>

                            <label class="fuel-document-toggle-option">
                                <input
                                    type="radio"
                                    name="document_type"
                                    value="other"
                                    @checked(
                                        old('document_type')
                                        === 'other'
                                    )
                                >

                                <span>Outros</span>
                            </label>

                        </div>
                    </div>

                </div>

              <div
                  id="fuelDocumentDetails"
                  class="fuel-document-details"
                  hidden
              >
                  <div class="fuel-document-fields">

                      <label class="fuel-document-field">
                          <span id="fuelDocumentDateLabel">
                              Data do documento
                          </span>

                          <input
                              type="date"
                              name="document_date"
                              id="fuelDocumentDate"
                              value="{{ old(
                                  'document_date',
                                  $date->format('Y-m-d')
                              ) }}"
                          >
                      </label>

                  </div>

                  <div
                      id="fuelInvoiceFields"
                      class="fuel-invoice-fields"
                      hidden
                  >
                      <label class="fuel-document-field">
                          <span>Número da NF</span>

                          <input
                              type="text"
                              name="invoice_number"
                              id="fuelInvoiceNumber"
                              maxlength="120"
                              value="{{ old('invoice_number') }}"
                              placeholder="Ex.: 7256"
                          >
                      </label>

                      <div class="fuel-invoice-supplier">
                          <div class="fuel-invoice-supplier-head">
                              <span>Fornecedor</span>
                              <span>CNPJ</span>
                          </div>

                          <x-supplier-autocomplete
                                value="{{ old(
                                    'supplier_name',
                                    $focusedReceipt?->supplier_name
                                ) }}"
                                document-name="supplier_document"
                                document-value="{{ old(
                                    'supplier_document',
                                    $focusedReceipt?->supplier_document
                                ) }}"
                                id-name="supplier_id"
                                id-value="{{ old(
                                    'supplier_id',
                                    $focusedReceipt?->supplier_id
                                ) }}"
                                placeholder="Digite para buscar ou cadastrar"
                            />
                      </div>

                      <div class="fuel-receipt-linker">

                          <div class="fuel-receipt-linker-head">

                              <div>
                                  <strong>
                                      Recebimentos vinculados
                                  </strong>

                                  <small>
                                      Últimos 5 recebimentos registrados
                                      ainda sem NF vinculada.
                                  </small>
                              </div>

                              <div class="fuel-receipt-date-search">

                                  <label for="fuelReceiptSearchDate">
                                      Data do recebimento
                                  </label>

                                  <div>
                                      <input
                                          type="date"
                                          id="fuelReceiptSearchDate"
                                            value="{{ $focusedReceipt
                                                ?->received_at
                                                ?->format('Y-m-d') }}"
                                        >

                                      <button
                                          type="button"
                                          id="fuelReceiptSearchButton"
                                          class="fuel-receipt-search-button"
                                          data-url="{{ route(
                                              'fuel.daily-check.receipts.search'
                                          ) }}"
                                          title="Buscar recebimentos"
                                          aria-label="Buscar recebimentos"
                                      >
                                          <i class="bi bi-search"></i>
                                      </button>
                                  </div>

                                  <small id="fuelReceiptSearchStatus">
                                      Informe uma data e clique na lupa
                                      para buscar outros recebimentos.
                                  </small>

                              </div>

                          </div>

                          <div
                              id="fuelReceiptCandidates"
                              class="fuel-receipt-candidates"
                          >
                              @forelse($receiptCandidates as $receipt)

                                  <label
                                      class="fuel-receipt-option"
                                      data-receipt-id="{{ $receipt->id }}"
                                  >
                                      <input
                                          type="checkbox"
                                          name="receipt_ids[]"
                                          value="{{ $receipt->id }}"
                                          @checked(
                                              in_array(
                                                  (string) $receipt->id,
                                                  array_map(
                                                      'strval',
                                                      old(
                                                            'receipt_ids',
                                                            $focusedReceipt
                                                                ? [
                                                                    (string) $focusedReceipt->id
                                                                ]
                                                                : []
                                                        )
                                                  ),
                                                  true
                                              )
                                          )
                                      >

                                      <span>
                                          <strong>
                                              {{ $receipt->received_at
                                                  ?->format('d/m/Y H:i') }}
                                              ·
                                              {{ $receipt->tank?->name
                                                  ?? 'Tanque' }}
                                          </strong>

                                          <small>
                                              {{ number_format(
                                                  (float) $receipt
                                                      ->quantity_liters,
                                                  3,
                                                  ',',
                                                  '.'
                                              ) }} L

                                              @if(
                                                  filled(
                                                      $receipt->supplier_name
                                                  )
                                              )
                                                  ·
                                                  {{ $receipt->supplier_name }}
                                              @endif
                                          </small>
                                      </span>
                                  </label>

                              @empty

                                  <div
                                      class="fuel-archive-empty-document fuel-receipt-empty"
                                  >
                                      <span>
                                          Não há recebimentos recentes
                                          aguardando NF.
                                      </span>
                                  </div>

                              @endforelse
                          </div>

                      </div>

                  </div>

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

              </div>

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

        <details class="fuel-fillings-accordion">

            <summary class="fuel-fillings-accordion-summary">

                <div>
                    <span>Lançamentos do dia</span>
                    <h2>Abastecimentos registrados</h2>
                </div>

                <div class="fuel-fillings-accordion-actions">

                    <span class="fuel-archive-count">
                        {{ $fillings->count() }}
                    </span>

                    <i class="bi bi-chevron-down"></i>

                </div>

            </summary>

            <div class="fuel-fillings-accordion-content">

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

                    <div class="fuel-archive-fillings fuel-fillings-scroll">

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
                                        {{ $filling->source
                                            === 'internal_tank'
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

                                    @if(
                                        $filling->vehicle_km
                                        !== null
                                    )

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

            </div>

        </details>


        <div class="fuel-day-receipts-block">

            <div class="fuel-day-receipts-head">

                <div>
                    <span>Entradas no tanque</span>
                    <h3>Recebimentos de combustível</h3>
                </div>

                <span class="fuel-archive-count">
                    {{ $dayReceipts->count() }}
                </span>

            </div>

            @if($dayReceipts->isEmpty())

                <div class="fuel-day-receipts-empty">
                    <i class="bi bi-box-arrow-in-down"></i>

                    <span>
                        Nenhum recebimento de combustível nesta data.
                    </span>
                </div>

            @else

                <div class="fuel-day-receipts-list">

                    @foreach($dayReceipts as $receipt)

                        @php
                            $invoiceFile =
                                $receipt->invoiceFiles
                                    ->sortByDesc('id')
                                    ->first();
                        @endphp

                        <article>

                            <div class="fuel-day-receipt-main">

                                <strong>
                                    {{ $receipt->received_at
                                        ?->format('H:i') }}

                                    ·

                                    {{ $receipt->tank?->name
                                        ?? 'Tanque' }}
                                </strong>

                                <small>
                                    {{ number_format(
                                        (float) $receipt
                                            ->quantity_liters,
                                        3,
                                        ',',
                                        '.'
                                    ) }} L

                                    @if(
                                        filled(
                                            $receipt->supplier_name
                                        )
                                    )
                                        ·
                                        {{ $receipt->supplier_name }}
                                    @endif
                                </small>

                            </div>

                            @if($invoiceFile)

                                <a
                                    href="{{ route(
                                        'fuel.daily-check.index',
                                        [
                                            'date' =>
                                                $invoiceFile->check?->operation_date
                                                    ? \Illuminate\Support\Carbon::parse(
                                                        $invoiceFile->check
                                                            ->operation_date
                                                    )->format('Y-m-d')
                                                    : $date->format('Y-m-d'),
                                            'open_document' =>
                                                $invoiceFile->id,
                                        ]
                                    ) }}#fuelDocumentDetails{{ $invoiceFile->id }}"
                                    class="fuel-receipt-document-status is-linked fuel-receipt-document-link"
                                    title="Ver detalhes desta NF"
                                >
                                    <i class="bi bi-paperclip"></i>

                                    NF
                                    {{ $invoiceFile->invoice_number
                                        ?: 'anexada' }}
                                </a>

                            @else

                                <a
                                    href="{{ route(
                                        'fuel.daily-check.index',
                                        [
                                            'date' => $receipt->received_at
                                                ?->format('Y-m-d'),
                                            'document_type' => 'fuel_invoice',
                                            'receipt_id' => $receipt->id,
                                        ]
                                    ) }}#fuelArchiveUploadForm"
                                    class="fuel-receipt-document-status is-pending fuel-receipt-document-link"
                                    title="Anexar NF a este recebimento"
                                >
                                    <i class="bi bi-paperclip"></i>
                                    NF pendente
                                </a>

                            @endif

                        </article>

                    @endforeach

                </div>

            @endif

        </div>

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
function getSelectedFuelDocumentType() {
    return document.querySelector(
        'input[name="document_type"]:checked'
    )?.value || '';
}

function updateFuelDocumentForm() {
    const type =
        getSelectedFuelDocumentType();

    const details =
        document.getElementById(
            'fuelDocumentDetails'
        );

    const invoiceFields =
        document.getElementById(
            'fuelInvoiceFields'
        );

    const invoiceNumber =
        document.getElementById(
            'fuelInvoiceNumber'
        );

    const documentDate =
        document.getElementById(
            'fuelDocumentDate'
        );

    const dateLabel =
        document.getElementById(
            'fuelDocumentDateLabel'
        );

    const receiptInputs =
        document.querySelectorAll(
            'input[name="receipt_ids[]"]'
        );

    const fileInput =
        document.querySelector(
            '#fuelArchiveFilePicker input[type="file"]'
        );

    const submit =
        document.getElementById(
            'fuelArchiveSubmit'
        );

    if (
        !details
        || !invoiceFields
    ) {
        return;
    }

    const hasType =
        type !== '';

    const isInvoice =
        type === 'fuel_invoice';

    details.hidden = !hasType;
    invoiceFields.hidden = !isInvoice;

    if (dateLabel) {
        dateLabel.textContent =
            isInvoice
                ? 'Data da NF'
                : type === 'fuel_sheet'
                    ? 'Data da folha'
                    : 'Data do documento';
    }

    if (documentDate) {
        documentDate.required = hasType;
    }

    if (invoiceNumber) {
        invoiceNumber.required = isInvoice;
    }

    receiptInputs.forEach(input => {
        input.disabled = !isInvoice;
    });

    if (fileInput) {
        fileInput.disabled = !hasType;
        fileInput.multiple = !isInvoice;
    }

    if (submit && !hasType) {
        submit.disabled = true;
    }
}

function createFuelReceiptOption(receipt) {
    const label =
        document.createElement('label');

    label.className =
        'fuel-receipt-option';

    label.dataset.receiptId =
        String(receipt.id);

    const input =
        document.createElement('input');

    input.type = 'checkbox';
    input.name = 'receipt_ids[]';
    input.value = receipt.id;

    const body =
        document.createElement('span');

    const title =
        document.createElement('strong');

    title.textContent =
        `${receipt.received_at || ''} · ${receipt.tank || 'Tanque'}`;

    const meta =
        document.createElement('small');

    let metaText =
        `${receipt.quantity_liters || '0,000'} L`;

    if (receipt.supplier_name) {
        metaText +=
            ` · ${receipt.supplier_name}`;
    }

    meta.textContent = metaText;

    body.appendChild(title);
    body.appendChild(meta);

    label.appendChild(input);
    label.appendChild(body);

    return label;
}

async function searchFuelReceiptCandidates() {
    const dateInput =
        document.getElementById(
            'fuelReceiptSearchDate'
        );

    const button =
        document.getElementById(
            'fuelReceiptSearchButton'
        );

    const status =
        document.getElementById(
            'fuelReceiptSearchStatus'
        );

    const container =
        document.getElementById(
            'fuelReceiptCandidates'
        );

    if (
        !dateInput
        || !button
        || !container
    ) {
        return;
    }

    if (!dateInput.value) {
        if (status) {
            status.textContent =
                'Informe a data do recebimento antes de pesquisar.';
        }

        dateInput.focus();
        return;
    }

    const selected = new Map();

    container
        .querySelectorAll(
            '.fuel-receipt-option'
        )
        .forEach(option => {
            const input =
                option.querySelector(
                    'input[name="receipt_ids[]"]'
                );

            if (input?.checked) {
                selected.set(
                    String(input.value),
                    option.cloneNode(true)
                );
            }
        });

    button.disabled = true;

    if (status) {
        status.textContent =
            'Buscando recebimentos...';
    }

    try {
        const url =
            new URL(
                button.dataset.url,
                window.location.origin
            );

        url.searchParams.set(
            'date',
            dateInput.value
        );

        const response =
            await fetch(
                url.toString(),
                {
                    headers: {
                        Accept: 'application/json',
                    },
                }
            );

        if (!response.ok) {
            throw new Error(
                'Falha na pesquisa.'
            );
        }

        const payload =
            await response.json();

        container.innerHTML = '';

        selected.forEach(node => {
            container.appendChild(node);
        });

        const added =
            new Set(selected.keys());

        (payload.receipts || [])
            .forEach(receipt => {
                const id =
                    String(receipt.id);

                if (added.has(id)) {
                    return;
                }

                container.appendChild(
                    createFuelReceiptOption(
                        receipt
                    )
                );

                added.add(id);
            });

        if (added.size === 0) {
            const empty =
                document.createElement('div');

            empty.className =
                'fuel-archive-empty-document fuel-receipt-empty';

            const message =
                document.createElement('span');

            message.textContent =
                'Nenhum recebimento sem NF foi encontrado nesta data.';

            empty.appendChild(message);

            container.appendChild(empty);
        }

        if (status) {
            status.textContent =
                payload.count === 1
                    ? '1 recebimento encontrado.'
                    : `${payload.count || 0} recebimentos encontrados.`;
        }
    } catch (error) {
        if (status) {
            status.textContent =
                'Não foi possível realizar a pesquisa.';
        }
    } finally {
        button.disabled = false;
    }
}

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
        const type =
            getSelectedFuelDocumentType();

        submit.disabled =
            count === 0
            || !type;
    }

    if (picker) {
        picker.classList.toggle(
            'has-file',
            count > 0
        );
    }
}


document.addEventListener(
    'DOMContentLoaded',
    () => {
        updateFuelDocumentForm();

        document
            .querySelectorAll(
                'input[name="document_type"]'
            )
            .forEach(input => {
                input.addEventListener(
                    'change',
                    updateFuelDocumentForm
                );
            });

        document
            .getElementById(
                'fuelReceiptSearchButton'
            )
            ?.addEventListener(
                'click',
                searchFuelReceiptCandidates
            );
    }
);

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
