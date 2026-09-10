@extends('layouts.app')



@php

    $pageTitle = 'Oficina';

    $pageSubtitle = 'Pneus';

@endphp



@push('styles')

<link

    rel="stylesheet"

href="{{ asset('css/pages/workshop-tires.css') }}?v=5"
>

<style>
    .workshop-hero-actions { display:grid; grid-template-columns:repeat(3, minmax(150px, 1fr)); gap:10px; width:min(100%, 520px); }
    .workshop-hero-actions .workshop-hero-btn { width:100%; white-space:nowrap; }
    .workshop-hero-btn-primary { color:#052e16; background:#86efac; border-color:#86efac; }
    .workshop-hero-btn-primary:hover { color:#052e16; background:#bbf7d0; border-color:#bbf7d0; }
    .workshop-card-header.workshop-recent-entries-toggle { width:100%; padding:0; margin:0; border:0; background:transparent; text-align:left; cursor:pointer; }
    .workshop-recent-entries-icon { display:inline-flex; align-items:center; gap:12px; color:#86efac; }
    .workshop-recent-entries-icon .bi-chevron-up, .workshop-recent-entries-icon .bi-chevron-down { color:#94a3b8; font-size:13px; }
    .workshop-recent-entries-card .workshop-entry-list { padding-top:2px; border-top:1px solid rgba(148, 163, 184, .18); }
    @media (max-width:900px) { .workshop-hero-actions { width:100%; grid-template-columns:repeat(3, minmax(0, 1fr)); } }
    @media (max-width:640px) { .workshop-hero-actions { grid-template-columns:1fr; } }

    .tire-dashboard-modal { position:fixed; inset:0; z-index:9999; display:grid; place-items:center; padding:24px; background:rgba(15, 23, 42, .68); backdrop-filter:blur(6px); }
    .tire-dashboard-modal[hidden] { display:none; }
    .tire-dashboard-card { --tire-dashboard-surface:var(--chm-theme-card, #111827); --tire-dashboard-surface-soft:var(--chm-theme-card-elevated, #172235); --tire-dashboard-border:var(--chm-theme-border, rgba(148, 163, 184, .18)); --tire-dashboard-border-strong:var(--chm-theme-border-strong, rgba(148, 163, 184, .3)); --tire-dashboard-text:var(--chm-theme-text, #f1f5f9); --tire-dashboard-muted:var(--chm-theme-muted, #a8b1c1); width:min(1080px, 100%); max-height:calc(100vh - 48px); overflow:auto; padding:24px; border:1px solid var(--tire-dashboard-border-strong); border-radius:22px; background:var(--tire-dashboard-surface); color:var(--tire-dashboard-text); box-shadow:0 24px 64px rgba(15, 23, 42, .38); }
    .tire-dashboard-header { display:flex; align-items:flex-start; justify-content:space-between; gap:18px; padding-bottom:18px; border-bottom:1px solid var(--tire-dashboard-border); }
    .tire-dashboard-heading h2 { margin:0; font-size:24px; line-height:1.15; color:var(--tire-dashboard-text); }
    .tire-dashboard-heading p { margin:6px 0 0; color:var(--tire-dashboard-muted); font-size:13px; }
    .tire-dashboard-close { width:38px; height:38px; display:inline-grid; place-items:center; flex:0 0 auto; border:1px solid var(--tire-dashboard-border); border-radius:10px; background:var(--tire-dashboard-surface-soft); color:var(--tire-dashboard-text); font-size:25px; line-height:1; cursor:pointer; }
    .tire-dashboard-close:hover { border-color:rgba(224, 82, 91, .55); color:#fff; background:#b72d36; }
    .tire-dashboard-filter { display:flex; align-items:center; gap:10px; margin:18px 0; color:var(--tire-dashboard-muted); font-size:12px; font-weight:800; }
    .tire-dashboard-filter select { min-height:38px; padding:0 32px 0 11px; border:1px solid var(--tire-dashboard-border-strong); border-radius:9px; outline:none; background:var(--chm-theme-input, #0f172a); color:var(--tire-dashboard-text); font:inherit; }
    .tire-dashboard-kpis { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:12px; }
    .tire-dashboard-kpis article, .tire-dashboard-section { border:1px solid var(--tire-dashboard-border); border-radius:14px; background:var(--tire-dashboard-surface-soft); }
    .tire-dashboard-kpis article { padding:14px; }
    .tire-dashboard-kpis span { display:block; color:var(--tire-dashboard-muted); font-size:11px; font-weight:700; }
    .tire-dashboard-kpis strong { display:block; margin-top:5px; color:var(--tire-dashboard-text); font-size:24px; line-height:1.05; }
    .tire-dashboard-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:12px; margin-top:12px; }
    .tire-dashboard-section { min-width:0; padding:16px; }
    .tire-dashboard-section h3 { margin:0 0 11px; color:var(--tire-dashboard-text); font-size:16px; }
    .tire-dashboard-section p { display:flex; justify-content:space-between; gap:12px; margin:6px 0; color:var(--tire-dashboard-muted); font-size:13px; }
    .tire-dashboard-section p b { color:var(--tire-dashboard-text); }
    .tire-dashboard-loading { padding:26px 0; color:var(--tire-dashboard-muted); text-align:center; }
    @media (max-width:760px) { .tire-dashboard-modal { padding:12px; } .tire-dashboard-card { padding:18px; max-height:calc(100vh - 24px); } .tire-dashboard-kpis, .tire-dashboard-grid { grid-template-columns:1fr; } }
</style>

@endpush



@section('content')

@php
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

    $canCreateTireEntry = (bool) $tirePermissions['create_entry'];
    $canRetreadTires = (bool) $tirePermissions['retread'];
    $canCancelTireRecords = (bool) $tirePermissions['cancel'];
    $canViewTireCosts = (bool) $tirePermissions['view_costs'];
    $canManageTireInventory = (bool) $tirePermissions['manage_inventory'];
@endphp



<div class="workshop-tires-page">





    <div class="workshop-hero">



        <div>

            <span>

                Oficina

            </span>



            <h1>

                Controle de pneus

            </h1>



            <p>

                Cadastre entradas, acompanhe estoque, pneus instalados e status operacional.

            </p>

        </div>



        <div class="workshop-hero-actions">
            @if($canCreateTireEntry)
                <button type="button" class="workshop-hero-btn workshop-hero-btn-primary" onclick="window.dispatchEvent(new CustomEvent('open-tire-entry'))">
                    <i class="bi bi-box-seam"></i>
                    Nova entrada
                </button>
            @endif

            <button type="button" class="workshop-hero-btn tire-dashboard-trigger" onclick="openTireDashboard()"><i class="bi bi-speedometer2"></i> Painel de pneus</button>
            <a href="{{ route('dashboard') }}" class="workshop-hero-btn"><i class="bi bi-arrow-left"></i> Voltar ao dashboard</a>
        </div>



    </div>



    <div class="workshop-summary-grid">



        <div class="workshop-summary-card">

            <small>Total</small>

            <strong>{{ $summary['total'] }}</strong>

        </div>



        <div class="workshop-summary-card available">

            <small>Disponíveis</small>

            <strong>{{ $summary['available'] }}</strong>

        </div>



        <div class="workshop-summary-card installed">

            <small>Instalados</small>

            <strong>{{ $summary['installed'] }}</strong>

        </div>



        <div class="workshop-summary-card maintenance">

            <small>Manutenção</small>

            <strong>{{ $summary['maintenance'] }}</strong>

        </div>



        <div class="workshop-summary-card discarded">

            <small>Descartados</small>

            <strong>{{ $summary['discarded'] }}</strong>

        </div>



    </div>



    <div class="workshop-grid">



        @if($canCreateTireEntry)
<section
            class="workshop-card workshop-entry-card"
            x-data="{
                open: {{ $errors->any() ? 'true' : 'false' }}
            }"
            @open-tire-entry.window="open = true; $nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
        >

            <div class="workshop-card-header workshop-entry-card-header">

                <div>
                    <h2>
                        Entrada de lote
                    </h2>

                    <p>
                        Registre uma compra e gere automaticamente os pneus individuais no estoque.
                    </p>
                </div>

                <button type="button" class="workshop-entry-toggle" @click="open = !open" :class="{ 'is-open': open }" :aria-expanded="open.toString()">
                    <i class="bi" :class="open ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                    <span x-text="open ? 'Recolher' : 'Expandir'"></span>
                </button>

            </div>


            <div
                class="workshop-entry-form-wrapper"
                x-show="open"
                x-collapse
                x-cloak
            >
            <form

                method="POST"

                action="{{ route('workshop.tires.entries.store') }}"

                class="workshop-entry-form"

            >



                @csrf



                <div class="workshop-form-grid">



                    <div class="form-group">

                        <label>Data da entrada</label>

                        <input

                            type="date"

                            name="entry_date"

                            value="{{ old('entry_date', now()->format('Y-m-d')) }}"

                            required

                        >

                    </div>



                    <div class="form-group">

                        <label>Quantidade</label>

                        <input

                            type="number"

                            name="quantity"

                            value="{{ old('quantity', 1) }}"

                            min="1"

                            required

                        >

                    </div>



                    <div class="form-group">

                        <label>Prefixo do código</label>

                        <input

                            type="text"

                            name="code_prefix"

                            value="{{ old('code_prefix', 'PN') }}"

                            placeholder="Ex: PN, AKSA-PN"

                            required

                        >

                    </div>



                    <div class="form-group">

                        <label>Marca</label>

                        <input

                            type="text"

                            name="brand"

                            value="{{ old('brand') }}"

                            placeholder="Ex: Michelin"

                        >

                    </div>



                    <div class="form-group">

                        <label>Modelo/Banda</label>

                        <input

                            type="text"

                            name="model"

                            value="{{ old('model') }}"

                            placeholder="Ex: X Multi"

                        >

                    </div>



                    <div class="form-group">

                        <label>Medida</label>

                        <input

                            type="text"

                            name="size"

                            value="{{ old('size') }}"

                            placeholder="Ex: 275/80 R22.5"

                        >

                    </div>



                    <div class="form-group">

                        <label>

                            Sulco inicial

                        </label>



                        <div class="input-with-suffix">

                            <input

                                type="number"

                                step="0.01"

                                name="initial_tread_depth"

                                value="{{ old('initial_tread_depth') }}"

                                placeholder="Ex: 15"

                            >



                            <span>

                                mm

                            </span>

                        </div>

                    </div>



                    <div class="form-group">

                        <label>

                            Alerta de atenção

                        </label>



                        <div class="input-with-suffix">

                            <input

                                type="number"

                                step="0.01"

                                name="warning_tread_depth"

                                value="{{ old('warning_tread_depth', 5) }}"

                                placeholder="Ex: 5.00"

                            >



                            <span>

                                mm

                            </span>

                        </div>



                        <small class="form-help">

                            Valor sugerido: 5 mm. Abaixo ou igual a este sulco, o pneu entra em atenção.

                        </small>

                    </div>



                    <div class="form-group">

                        <label>

                            Alerta crítico

                        </label>



                        <div class="input-with-suffix">

                            <input

                                type="number"

                                step="0.01"

                                name="critical_tread_depth"

                                value="{{ old('critical_tread_depth', 3) }}"

                                placeholder="Ex: 3.00"

                            >



                            <span>

                                mm

                            </span>

                        </div>



                        <small class="form-help">

                            Valor sugerido: 3 mm. Abaixo ou igual a este sulco, o pneu fica crítico.

                        </small>

                    </div>



                    @if($canViewTireCosts)
<div class="form-group">

                        <label>Valor unitário</label>

                        <input

                            type="number"

                            step="0.01"

                            name="unit_cost"

                            value="{{ old('unit_cost') }}"

                            placeholder="Ex: 1200.00"

                        >

                    </div>
@endif



                    <div class="form-group">

                        <label>Fornecedor</label>

                        <x-supplier-autocomplete value="{{ old('supplier_name') }}" document-name="supplier_document" document-value="{{ old('supplier_document') }}" placeholder="Ex.: Pneus Bahia" />

                    </div>



                    <div class="form-group">

                        <label>Nota fiscal</label>

                        <input

                            type="text"

                            name="invoice_number"

                            value="{{ old('invoice_number') }}"

                            placeholder="Ex: NF 12345"

                        >

                    </div>



                </div>



                <div class="form-group">

                    <label>Observações</label>

                    <textarea

                        name="notes"

                        rows="3"

                        placeholder="Informações adicionais sobre a entrada..."

                    >{{ old('notes') }}</textarea>

                </div>



                <button

                    type="submit"

                    class="workshop-submit-btn"

                >

                    <i class="bi bi-floppy"></i>

                    Registrar entrada

                </button>



            </form>
            </div>



        </section>



        @endif
<aside class="workshop-card workshop-recent-entries-card" x-data="{ open: false }">



            <button type="button" class="workshop-card-header workshop-recent-entries-toggle" @click="open = !open" :aria-expanded="open.toString()">



                <div>

                    <h2>

                        Últimas entradas

                    </h2>



                    <p>

                        Lotes registrados recentemente.

                    </p>

                </div>



                <span class="workshop-recent-entries-icon">
                    <i class="bi bi-clock-history"></i>
                    <i class="bi" :class="open ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                </span>



            </button>



            <div class="workshop-entry-list" x-show="open" x-collapse x-cloak>



                @forelse($entries as $entry)



                    <div class="workshop-entry-item">



                        <div>

                            <strong>

                                {{ optional($entry->entry_date)->format('d/m/Y') }}

                            </strong>



                            <span>

                                {{ $entry->items_count }} pneu(s)

                                ·

                                {{ $entry->brand ?? 'Sem marca' }}

                            </span>

                        </div>



                        <small>

                            {{ $entry->invoice_number ?? 'Sem NF' }}

                        </small>

                        @if($entry->is_cancelled)
                            <span class="workshop-entry-status cancelled">Cancelada</span>
                            @can('viewAuditLogs')
                                @if($entry->cancel_reason)
                                    <span class="workshop-entry-cancel-reason">{{ $entry->cancel_reason }}</span>
                                @endif
                            @endcan
                        @else
                            @if($canCancelTireRecords)
                            <div
                                class="workshop-entry-cancel"
                                x-data="{ cancelling: false }"
                            >

                                <button
                                    type="button"
                                    class="workshop-entry-cancel-trigger"
                                    x-show="! cancelling"
                                    @click="cancelling = true"
                                >
                                    <i class="bi bi-slash-circle"></i>
                                    Cancelar entrada
                                </button>

                                <form
                                    method="POST"
                                    action="{{ route('workshop.tires.entries.cancel', $entry) }}"
                                    class="workshop-entry-cancel-form"
                                    x-show="cancelling"
                                    x-collapse
                                    x-cloak
                                >
                                    @csrf

                                    <label>
                                        Motivo do cancelamento
                                    </label>

                                    <textarea
                                        name="reason"
                                        rows="2"
                                        minlength="5"
                                        maxlength="2000"
                                        required
                                        placeholder="Descreva por que esta entrada será cancelada..."
                                    ></textarea>

                                    <div class="workshop-entry-cancel-actions">

                                        <button
                                            type="button"
                                            class="workshop-entry-cancel-back"
                                            @click="cancelling = false"
                                        >
                                            Voltar
                                        </button>

                                        <button
                                            type="submit"
                                            class="workshop-entry-cancel-confirm"
                                            onclick="return confirm('Confirma o cancelamento desta entrada?');"
                                        >
                                            <i class="bi bi-slash-circle"></i>
                                            Confirmar cancelamento
                                        </button>

                                    </div>

                                </form>

                            </div>
                        @endif
                        @endif

                    </div>



                @empty



                    <div class="workshop-empty">

                        Nenhuma entrada registrada.

                    </div>



                @endforelse



            </div>



        </aside>



    </div>



    <section class="workshop-card">



        <div class="workshop-card-header">



            <div>

                <h2>

                    Pneus cadastrados

                </h2>



                <p>

                    Estoque, pneus instalados e situação atual.

                </p>

            </div>



            <i class="bi bi-circle"></i>



        </div>



        <form

                method="GET"

                action="{{ route('workshop.tires.index') }}"

                class="workshop-tire-filters"

            >



                <div class="workshop-tire-search">

                    <i class="bi bi-search"></i>



                    <input

                        type="text"

                        name="search"

                        value="{{ request('search') }}"

                        placeholder="Buscar por código, marca, modelo ou medida..."

                    >

                </div>



                <div class="workshop-tire-status-filters">



                    <a

                        href="{{ route('workshop.tires.index') }}"

                        class="{{ ! request('status') ? 'active' : '' }}"

                    >

                        Todos

                    </a>



                    <a

                        href="{{ route('workshop.tires.index', ['status' => 'available', 'search' => request('search')]) }}"

                        class="{{ request('status') === 'available' ? 'active' : '' }}"

                    >

                        Disponíveis

                    </a>



                    <a

                        href="{{ route('workshop.tires.index', ['status' => 'installed', 'search' => request('search')]) }}"

                        class="{{ request('status') === 'installed' ? 'active' : '' }}"

                    >

                        Instalados

                    </a>



                    <a

                        href="{{ route('workshop.tires.index', ['status' => 'maintenance', 'search' => request('search')]) }}"

                        class="{{ request('status') === 'maintenance' ? 'active' : '' }}"

                    >

                        Manutenção

                    </a>



                    <a

                        href="{{ route('workshop.tires.index', ['status' => 'discarded', 'search' => request('search')]) }}"

                        class="{{ request('status') === 'discarded' ? 'active' : '' }}"

                    >

                        Descartados

                    </a>



                </div>



                <button

                    type="submit"

                    class="workshop-filter-submit"

                >

                    Filtrar

                </button>



            </form>



<div class="workshop-table">



    <div class="workshop-table-head">



        <div>Pneu</div>

        <div>Marca/Modelo</div>

        <div>Status</div>

        <div>Localização</div>

        <div>Sulco atual</div>

        <div>Ações</div>



    </div>



    <div class="workshop-table-body">



        @forelse($tires as $tire)



            @php

                $installation = $tire->activeInstallation;

            @endphp



            <div class="workshop-table-row">



                <div>

                    <strong>

                        {{ $tire->code }}

                    </strong>



                    <span>

                        {{ $tire->size ?? 'Sem medida' }}

                    </span>

                </div>



                <div>

                    {{ $tire->brand ?? 'Sem marca' }}

                    {{ $tire->model ? '· ' . $tire->model : '' }}

                </div>



                <div class="workshop-status-head">

                    <span class="workshop-status {{ $tire->status }}">

                        @switch($tire->status)

                            @case('available')

                                Disponível{{ $tire->retreads_count > 0 ? '-R' . $tire->retreads_count : '' }}
                                @break



                            @case('installed')

                                Instalado{{ $tire->retreads_count > 0 ? '-R' . $tire->retreads_count : '' }}
                                @break



                            @case('maintenance')

                                Manutenção{{ $tire->retreads_count > 0 ? '-R' . $tire->retreads_count : '' }}
                                @break



                            @case('discarded')

                                Descartado{{ $tire->retreads_count > 0 ? '-R' . $tire->retreads_count : '' }}
                                @break



                            @default

                                {{ $tire->status }}

                        @endswitch

                    </span>

                </div>



                <div>

                    @if($installation)

                        {{ $installation->vehicle?->plate }}

                        ·

                        {{ $installation->position_code }}

                    @else

                        Estoque

                    @endif

                </div>



                <div>

                    @if($tire->current_tread_depth !== null)
                        {{ number_format($tire->current_tread_depth, 2, ',', '.') }} mm
                    @else

                        --

                    @endif

                </div>



                <div>

                    @php

                        $tirePayload = [

                            'id' => $tire->id,

                            'code' => $tire->code,

                            'brand' => $tire->brand,

                            'model' => $tire->model,

                            'size' => $tire->size,

                            'initial_tread_depth' => $tire->initial_tread_depth,

                            'warning_tread_depth' => $tire->warning_tread_depth,

                            'critical_tread_depth' => $tire->critical_tread_depth,

                            'status' => $tire->status,

                            'notes' => $tire->notes,

                        ];

                    @endphp



                    @if($tire->status === 'maintenance' && $canRetreadTires)

                        <button

                            type="button"

                            class="workshop-table-action retread"

                            onclick="openRetreadModal({{ $tire->id }}, {{ Illuminate\Support\Js::from($tire->code) }})"

                        >

                            <i class="bi bi-arrow-clockwise"></i>

                            Recapar

                        </button>

                    @endif


                    <a
                        href="{{ route('workshop.tires.history', $tire) }}"
                        class="workshop-table-action"
                    >
                        <i class="bi bi-clock-history"></i>
                        Histórico
                    </a>


                    @if($canManageTireInventory)
<button
                        type="button"

                        class="workshop-table-action"

                        onclick="openEditTireModal({{ Illuminate\Support\Js::from($tirePayload) }})"

                    >

                        <i class="bi bi-pencil"></i>

                        Editar

                    </button>
@endif

                </div>



            </div>



        @empty



            <div class="workshop-empty">

                Nenhum pneu cadastrado.

            </div>



        @endforelse



    </div>



</div>

        @if($tires->hasPages())



            <div class="workshop-pagination">



                <div class="workshop-pagination-info">

                    Mostrando

                    <strong>{{ $tires->firstItem() }}</strong>

                    a

                    <strong>{{ $tires->lastItem() }}</strong>

                    de

                    <strong>{{ $tires->total() }}</strong>

                    pneus

                </div>



                <div class="workshop-pagination-links">



                    @if($tires->onFirstPage())

                        <span class="disabled">

                            Anterior

                        </span>

                    @else

                        <a href="{{ $tires->previousPageUrl() }}">

                            Anterior

                        </a>

                    @endif



                    @foreach($tires->getUrlRange(1, $tires->lastPage()) as $page => $url)



                        @if($page == $tires->currentPage())

                            <span class="active">

                                {{ $page }}

                            </span>

                        @else

                            <a href="{{ $url }}">

                                {{ $page }}

                            </a>

                        @endif



                    @endforeach



                    @if($tires->hasMorePages())

                        <a href="{{ $tires->nextPageUrl() }}">

                            Próxima

                        </a>

                    @else

                        <span class="disabled">

                            Próxima

                        </span>

                    @endif



                </div>



            </div>



        @endif



    </section>



</div>

<div

    class="workshop-modal-overlay"

    id="editTireModal"

    style="display:none;"

>

    <div class="workshop-tire-modal">



        <div class="workshop-tire-modal-header">



            <div>

                <span>

                    Oficina / Pneus

                </span>



                <h2>

                    Editar pneu

                </h2>



                <p id="editTireCode">

                    —

                </p>

            </div>



            <button

                type="button"

                onclick="closeEditTireModal()"

            >

                <i class="bi bi-x-lg"></i>

            </button>



        </div>



        <form

            method="POST"

            id="editTireForm"

            class="workshop-tire-modal-form"

        >

            @csrf

            @method('PUT')



            <div class="workshop-form-grid">



                <div class="form-group">

                    <label>Marca</label>

                    <input type="text" name="brand" id="editTireBrand">

                </div>



                <div class="form-group">

                    <label>Modelo/Banda</label>

                    <input type="text" name="model" id="editTireModel">

                </div>



                <div class="form-group">

                    <label>Medida</label>

                    <input type="text" name="size" id="editTireSize">

                </div>



                <div class="form-group">

                    <label>Status</label>



                    <select name="status" id="editTireStatus">

                        <option value="available">Disponível</option>

                        <option value="maintenance">Manutenção/Reforma</option>

                        <option value="discarded">Descartado</option>

                        <option value="installed">Instalado</option>

                    </select>



                    <small class="form-help">

                        Pneus instalados devem ser removidos pela tela do veículo.

                    </small>

                </div>



                <div class="form-group">

                    <label>Sulco atual / inicial</label>



                    <div class="input-with-suffix">

                        <input

                            type="number"

                            step="0.01"

                            name="initial_tread_depth"

                            id="editTireInitialTread"

                        >



                        <span>mm</span>

                    </div>



                    <small class="form-help">

                        Em caso de reforma, informe o novo sulco após retorno.

                    </small>

                </div>



                <div class="form-group">

                    <label>Alerta de atenção</label>



                    <div class="input-with-suffix">

                        <input

                            type="number"

                            step="0.01"

                            name="warning_tread_depth"

                            id="editTireWarningTread"

                        >



                        <span>mm</span>

                    </div>

                </div>



                <div class="form-group">

                    <label>Alerta crítico</label>



                    <div class="input-with-suffix">

                        <input

                            type="number"

                            step="0.01"

                            name="critical_tread_depth"

                            id="editTireCriticalTread"

                        >



                        <span>mm</span>

                    </div>

                </div>



            </div>



            <div class="form-group">

                <label>Observações</label>



                <textarea

                    name="notes"

                    id="editTireNotes"

                    rows="3"

                    placeholder="Ex: pneu retornou de reforma, nova banda aplicada..."

                ></textarea>

            </div>



            <div class="workshop-tire-modal-footer">



                <button

                    type="button"

                    class="workshop-modal-cancel"

                    onclick="closeEditTireModal()"

                >

                    Cancelar

                </button>



                <button

                    type="submit"

                    class="workshop-modal-save"

                >

                    <i class="bi bi-floppy"></i>

                    Salvar alterações

                </button>



            </div>



        </form>



    </div>

</div>

<div class="workshop-modal-overlay" id="retreadTireModal" style="display:none;">

    <div class="workshop-tire-modal workshop-retread-modal">

        <div class="workshop-tire-modal-header">

            <div>

                <span>Oficina / Pneus</span>

                <h2>Registrar recapagem</h2>

                <p id="retreadTireCode">Pneu</p>

            </div>

            <button type="button" onclick="closeRetreadModal()">

                <i class="bi bi-x-lg"></i>

            </button>

        </div>

        <form method="POST" id="retreadTireForm" class="workshop-tire-modal-form">

            @csrf

            <div class="workshop-retread-form-grid">

                <div class="form-group">

                    <label>Novo sulco</label>

                    <div class="input-with-suffix">

                        <input type="number" name="new_tread_depth" step="0.01" min="0.01" max="50" required>

                        <span>mm</span>

                    </div>

                </div>

                <div class="form-group">

                    <label>Data da recapagem</label>

                    <input type="date" name="retreaded_at" value="{{ now()->format('Y-m-d') }}" required>

                </div>

                <div class="form-group full-width">

                    <label>Fornecedor / quem recapou</label>

                    <x-supplier-autocomplete text-name="provider_name" document-name="supplier_document" />

                </div>

                <div class="form-group full-width">

                    <label>Observação</label>

                    <textarea name="notes" rows="3" maxlength="2000"></textarea>

                </div>

            </div>

            <div class="workshop-tire-modal-footer">

                <button type="button" class="workshop-modal-cancel" onclick="closeRetreadModal()">

                    Cancelar

                </button>

                <button type="submit" class="workshop-modal-save retread">

                    <i class="bi bi-arrow-clockwise"></i>

                    Registrar recapagem

                </button>

            </div>

        </form>

    </div>

</div>



<script>
function openRetreadModal(tireId, tireCode) {

    document.getElementById('retreadTireForm').action =

        "{{ url('/workshop/tires') }}/" + tireId + "/retreads";



    document.getElementById('retreadTireCode').innerText =

        tireCode || 'Pneu';



    document.getElementById('retreadTireModal').style.display =

        'flex';

}



function closeRetreadModal() {

    document.getElementById('retreadTireModal').style.display =

        'none';

}



function openEditTireModal(tire) {
    const modal =

        document.getElementById('editTireModal');



    const form =

        document.getElementById('editTireForm');



    form.action =

        "{{ url('/workshop/tires') }}/" + tire.id;



    document.getElementById('editTireCode').innerText =

        tire.code || '—';



    document.getElementById('editTireBrand').value =

        tire.brand || '';



    document.getElementById('editTireModel').value =

        tire.model || '';



    document.getElementById('editTireSize').value =

        tire.size || '';



    document.getElementById('editTireInitialTread').value =

        tire.initial_tread_depth || '';



    document.getElementById('editTireWarningTread').value =

        tire.warning_tread_depth || 5;



    document.getElementById('editTireCriticalTread').value =

        tire.critical_tread_depth || 3;



    document.getElementById('editTireStatus').value =

        tire.status || 'available';



    document.getElementById('editTireNotes').value =

        tire.notes || '';



    modal.style.display =

        'flex';

}



function closeEditTireModal() {

    document.getElementById('editTireModal').style.display =

        'none';

}

</script>


<div id="tireDashboard" class="tire-dashboard-modal" hidden role="dialog" aria-modal="true" aria-labelledby="tireDashboardTitle">
    <div class="tire-dashboard-card">
        <div class="tire-dashboard-header">
            <div class="tire-dashboard-heading">
                <h2 id="tireDashboardTitle">Painel de pneus</h2>
                <p id="tireDashboardSubtitle">Indicadores de pneus, sulcagem, recapagens e descartes — período selecionado</p>
            </div>
            <button type="button" class="tire-dashboard-close" onclick="closeTireDashboard()" aria-label="Fechar painel">×</button>
        </div>
        <label class="tire-dashboard-filter">Período <select id="tireDashboardPeriod"><option value="last_30_days">Últimos 30 dias</option><option value="current_month">Mês atual</option><option value="last_90_days">Últimos 90 dias</option><option value="current_year">Ano atual</option></select></label>
        <div id="tireDashboardContent" class="tire-dashboard-loading">Carregando indicadores…</div>
    </div>
</div>
@endsection
@push('scripts')
<script>
(() => { const tireDashboardUrl=@json(route('workshop.tires.dashboard')); const n=v=>Number(v||0); const friendly=v=>({available:'Disponível',installed:'Em uso',maintenance:'Manutenção',discarded:'Descartado',retread:'Recapagem'})[v]||String(v||'Outros').replace(/[_-]+/g,' ').replace(/\b\w/g,c=>c.toUpperCase()); const bars=(title,rows)=>`<section class="tire-dashboard-section"><h3>${title}</h3>${rows?.length?rows.map(r=>`<p>${friendly(r.status||r.label||r.code)} <b>${n(r.count)}</b></p>`).join(''):'<p>Dados não disponíveis.</p>'}</section>`; window.openTireDashboard=async()=>{const modal=document.getElementById('tireDashboard'),content=document.getElementById('tireDashboardContent'),period=document.getElementById('tireDashboardPeriod').value;modal.hidden=false;content.textContent='Carregando indicadores…';try{const response=await fetch(tireDashboardUrl+'?period='+encodeURIComponent(period),{headers:{Accept:'application/json'}});if(!response.ok)throw Error();const d=await response.json(),s=d.summary,k=(l,v)=>`<article><span>${l}</span><strong>${v??'N/D'}</strong></article>`;document.getElementById('tireDashboardSubtitle').textContent='Indicadores de pneus — '+d.period_label;content.innerHTML=`<div class="tire-dashboard-kpis">${k('Pneus cadastrados',s.total_tires)}${k('Pneus em uso',s.in_use)}${k('Pneus em estoque',s.in_stock)}${k('Pneus críticos',s.critical)}${k('Medições no período',s.measurements)}${k('Sulco médio atual',s.average_tread===null?'N/D':s.average_tread+' mm')}${k('Recapagens no período',s.retreaded)}${k('Descartes no período',s.discarded)}</div><div class="tire-dashboard-grid">${bars('Pneus por status',d.by_status)}${bars('Pneus críticos por veículo',d.critical_by_vehicle)}${bars('Pneus com menor sulco',d.lowest_tread)}${bars('Evolução da sulcagem média',d.tread_trend)}</div>`}catch(e){content.textContent='Não foi possível carregar o painel de pneus.'}};window.closeTireDashboard=()=>document.getElementById('tireDashboard').hidden=true;document.getElementById('tireDashboardPeriod').addEventListener('change',window.openTireDashboard);document.addEventListener('keydown',e=>{if(e.key==='Escape')window.closeTireDashboard()});})();
</script>
@endpush
