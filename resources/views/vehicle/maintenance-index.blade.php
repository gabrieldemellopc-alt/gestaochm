@extends('layouts.app')



@push('styles')

<link

    rel="stylesheet"

    href="{{ asset('css/pages/maintenance.css') }}?v=5"
>

@endpush



@section('content')
@php($maintenancePermissions = $maintenancePermissions ?? [])



<div class="maintenance-index-page">



    <div class="maintenance-create-header">



        <div>



            <span class="maintenance-kicker">

                Manutenção

            </span>



            <h1>

                Manutenção do veículo {{ $vehicle->name }}

            </h1>



            <p>

                Acompanhe a situação de manutenção, alertas e procedimentos disponíveis para este veículo.

            </p>



        </div>



        <button
            type="button"
            class="maintenance-back-button"
            onclick="history.back()"
        >
            <i data-lucide="arrow-left"></i>

            Voltar
        </button>



    </div>



    <div class="maintenance-context-card">



        <div class="maintenance-vehicle-info">



            <div class="maintenance-vehicle-icon">

                <img

                    src="{{ asset('images/lixo.png') }}"

                    alt="Veículo"

                >

            </div>



            <div>



                <h2>

                    {{ $vehicle->name }}

                </h2>



                <div class="maintenance-meta">



                    <span>

                        {{ $vehicle->plate }}

                    </span>



                    <span>

                        •

                    </span>



                    <span>

                        {{ $vehicle->brand }}

                        {{ $vehicle->model }}

                    </span>



                    @if($vehicle->year)

                        <span>•</span>

                        <span>{{ $vehicle->year }}</span>

                    @endif



                </div>



                <span class="maintenance-status-badge">

                    {{ $vehicle->operational_status === 'maintenance' ? 'Em manutenção' : 'Operacional' }}

                </span>



            </div>



        </div>



        <div class="maintenance-context-grid">



            <div class="maintenance-context-item">

                <span>Hodômetro</span>



                <strong>

                    {{ number_format($vehicle->current_km ?? 0, 0, ',', '.') }}

                    km

                </strong>

            </div>



            <div class="maintenance-context-item">

                <span>Horímetro</span>



                <strong>

                    {{ number_format($vehicle->current_hours ?? 0, 0, ',', '.') }}

                    h

                </strong>

            </div>



            <div class="maintenance-context-item">

                <span>Divisão</span>



                <strong>

                    {{ $vehicle->division->name ?? '—' }}

                </strong>

            </div>



            <div class="maintenance-context-item">

                <span>Localidade</span>



                <strong>

                    {{ $vehicle->location->name ?? '—' }}

                </strong>

            </div>



        </div>



    </div>

    @if($alertProcedures->count())
        <section class="maintenance-alert-strip">
            <div class="maintenance-alert-strip-head">
                <span>Alertas</span>
                <strong>Manutenções em atenção</strong>
            </div>

            <div class="maintenance-alert-strip-list">
                @foreach($alertProcedures as $alert)
                    <div class="maintenance-alert-pill {{ $alert['status'] }}">
                        <i data-lucide="{{ $alert['status'] === 'danger' ? 'circle-alert' : 'triangle-alert' }}"></i>

                        <div>
                            <strong>{{ $alert['procedure'] }}</strong>
                            <span>{{ $alert['message'] }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if($errors->any())
        <div class="chm-alert danger">
            <i data-lucide="circle-alert"></i>
            <span style="vertical-align: top;">{{ $errors->first() }}</span>
        </div>
    @endif

    @if($openMaintenance)
        <section
            class="maintenance-open-card"
            x-data="{ cancelModal: false, closeModal: false }"
        >
            <div class="maintenance-open-top">
                <div class="maintenance-open-main">
                    <div class="maintenance-open-icon">
                        <i data-lucide="wrench"></i>
                    </div>

                    <div>
                        <span class="maintenance-kicker">
                            Manutenção em andamento
                        </span>

                        <h2>
                            #{{ $openMaintenance->id }} — Veículo em manutenção
                        </h2>

                        <p>
                            Aberta em
                            {{ optional($openMaintenance->started_at)->format('d/m/Y H:i') }}
                        </p>
                    </div>
                </div>

                <div class="maintenance-open-buttons">
                    @if($maintenancePermissions['export_pdf'] ?? false)
<a
                        href="{{ route('vehicles.maintenance.order.pdf', [$vehicle->id, $openMaintenance->id]) }}"
                        class="chm-page-button maintenance-pdf-button"
                        target="_blank"
                    >
                        <i data-lucide="file-text"></i>
                        PDF da ordem
                    </a>
@endif

                    @if($maintenancePermissions['cancel'] ?? false)
<button
                        type="button"
                        class="chm-page-button maintenance-cancel-button"
                        @click="cancelModal = true"
                    >
                        <i data-lucide="x-circle"></i>
                        Cancelar manutenção
                    </button>
@endif

                    @if($maintenancePermissions['close'] ?? false)
<button
                        type="button"
                        class="chm-page-button maintenance-close-button"
                        @click="closeModal = true"
                    >
                        <i data-lucide="check-circle"></i>
                        Encerrar manutenção
                    </button>
@endif
                </div>
            </div>

            <div
                x-show="cancelModal"
                x-cloak
                class="maintenance-modal-backdrop"
                @click.self="cancelModal = false"
            >
                <div class="maintenance-close-modal">
                    <h3>Cancelar manutenção</h3>

                    <p>
                        Esta ação irá desfazer lançamentos de estoque e cancelar a manutenção.
                        Não poderá ser desfeita.
                    </p>

                    <form
                        method="POST"
                        action="{{ route('vehicles.maintenance.cancel', [$vehicle->id, $openMaintenance->id]) }}"
                    >
                        @csrf

                        <div class="form-group">
                            <label>Motivo do cancelamento</label>

                            <textarea
                                name="reason"
                                rows="4"
                                class="form-input"
                                required
                                placeholder="Ex.: manutenção aberta por engano..."
                            ></textarea>
                        </div>

                        <div class="maintenance-modal-actions">
                            <button
                                type="button"
                                class="maintenance-cancel-btn"
                                @click="cancelModal = false"
                            >
                                Voltar
                            </button>

                            <button
                                type="submit"
                                class="chm-page-button danger"
                            >
                                Confirmar cancelamento
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div
                x-show="closeModal"
                x-cloak
                class="maintenance-modal-backdrop"
                @click.self="closeModal = false"
            >
                <div class="maintenance-close-modal">
                    <h3>Encerrar manutenção</h3>
                    <p>Informe como o veículo ficará após o encerramento.</p>

                    <form
                        method="POST"
                        action="{{ route('vehicles.maintenance.close', [$vehicle->id, $openMaintenance->id]) }}"
                    >
                        @csrf

                        <div class="form-group">
                            <label>Status final do veículo</label>

                            <select name="vehicle_status_after" class="form-input" required>
                                <option value="operational">Operativo</option>
                                <option value="inactive">Inativo</option>
                                <option value="inoperant">Inoperante</option>
                                <option value="accident">Sinistro</option>
                                <option value="support">Socorro</option>
                                <option value="testing">Testes</option>
                                <option value="transfer">Transferência</option>
                                <option value="transferred">Transferido</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Observações de encerramento</label>

                            <textarea
                                name="closure_notes"
                                rows="4"
                                class="form-input"
                                placeholder="Descreva a conclusão da manutenção..."
                            ></textarea>
                        </div>

                        <div class="maintenance-modal-actions">
                            <button
                                type="button"
                                class="maintenance-cancel-btn"
                                @click="closeModal = false"
                            >
                                Cancelar
                            </button>

                            <button type="submit" class="chm-page-button danger">
                                Confirmar encerramento
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            </div>

            <div class="maintenance-open-badges">

                <span class="maintenance-service-badge status-{{ $openMaintenance->service_status }}">
                    {{ \App\Services\MaintenanceService::serviceStatuses()[$openMaintenance->service_status] ?? 'Não informado' }}
                </span>

                @if($openMaintenance->maintenance_category)
                    <span class="maintenance-info-badge maintenance-category-badge">
                        <i data-lucide="tag"></i>

                        {{ \App\Services\MaintenanceService::maintenanceCategories()[
                            $openMaintenance->maintenance_category
                        ] ?? 'Outros' }}
                    </span>
                @endif

                <span class="maintenance-info-badge">
                    <i data-lucide="clock"></i>

                    Parado há
                    {{ $openMaintenance->started_at
                        ? $openMaintenance->started_at->diffForHumans(null, true)
                        : '—'
                    }}
                </span>

                <span class="maintenance-info-badge">
                    <i data-lucide="circle-dollar-sign"></i>

                    Total @if($maintenancePermissions['view_costs'] ?? false)R$ {{ number_format(
                        $openMaintenance->total_cost ?? 0,
                        2,
                        ',',
                        '.'
                    ) }}@else Valor restrito @endif
                </span>

            </div>

            {{-- maintenance-open-actions-grid-permission-wrapper --}}
            @if($maintenancePermissions['change_status'] ?? false
                || $maintenancePermissions['add_items'] ?? false
                || $maintenancePermissions['add_extra_costs'] ?? false)
            <div class="maintenance-open-actions-grid">


                @if($maintenancePermissions['change_status'] ?? false)
                <div
                    class="maintenance-open-action-box"
                    x-data="{
                        currentStatus: @js($openMaintenance->service_status),
                        selectedStatus: @js($openMaintenance->service_status),
                        reset() { this.selectedStatus = this.currentStatus; }
                    }"
                    @keydown.escape.window="reset()"
                    @click.outside="reset()"
                >
                <div class="maintenance-card-accent"></div>

                    <div class="maintenance-card-content">

                        <div class="maintenance-action-title">
                            <span>Status</span>
                            <h3>Atualizar andamento</h3>
                            <p>Registre a mudança de fase da manutenção.</p>
                        </div>

<form
                            method="POST"
                            action="{{ route('vehicles.maintenance.status', [$vehicle->id, $openMaintenance->id]) }}"
                            class="maintenance-open-status-form"
                        >
                            @csrf

                            <select
                                name="service_status"
                                class="form-input"
                                x-model="selectedStatus"
                                required
                            >
                                @foreach(\App\Services\MaintenanceService::serviceStatuses() as $statusKey => $statusLabel)
                                    <option value="{{ $statusKey }}">
                                        {{ $statusLabel }}
                                    </option>
                                @endforeach
                            </select>

                            <div
                                class="maintenance-action-placeholder"
                                x-show="selectedStatus === currentStatus"
                            >
                                Selecione um novo status para registrar uma atualização.
                            </div>

                            <div
                                x-show="selectedStatus !== currentStatus"
                                x-cloak
                            >
                                <div class="form-group">
                                    <label>Motivo / observação</label>

                                    <input
                                        type="text"
                                        name="reason"
                                        class="form-input"
                                        placeholder="Ex.: aguardando peça, orçamento solicitado..."
                                    >
                                </div>

                                <button
                                    type="submit"
                                    class="chm-page-button primary full"
                                >
                                    <i data-lucide="refresh-cw"></i>
                                    Atualizar status
                                </button>
                            </div>
                        </form>

                    </div>
                </div>

                @endif

                @if($maintenancePermissions['add_items'] ?? false)
                <div
                    class="maintenance-open-action-box"
                    x-data="{ procedureId: '' }"
                    @keydown.escape.window="procedureId = ''"
                    @click.outside="procedureId = ''"
                >
                    <div class="maintenance-card-accent"></div>

                        <div class="maintenance-card-content">

                            <div class="maintenance-action-title">
                                <span>Procedimentos</span>
                                <h3>Adicionar serviço</h3>
                                <p>Inclua um procedimento executado nesta parada.</p>
                            </div>

<form
                                method="GET"
                                action="{{ route('vehicles.maintenance.items.create', [$vehicle->id, $openMaintenance->id]) }}"
                                class="maintenance-compact-add-form"
                            >
                                <div class="form-group">
                                    <select
                                        name="procedure_id"
                                        class="form-input"
                                        x-model="procedureId"
                                        required
                                    >
                                        <option value="">Selecione...</option>

                                        @foreach($procedures as $procedure)
                                            <option value="{{ $procedure->id }}">
                                                {{ $procedure->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div
                                    class="maintenance-action-placeholder"
                                    x-show="!procedureId"
                                >
                                    Selecione um procedimento para informar sua execução.
                                </div>

                                <div
                                    x-show="procedureId"
                                    x-cloak
                                >
                                    <div class="form-group">
                                        <label>Execução</label>

                                        <select
                                            name="execution_type"
                                            class="form-input"
                                            required
                                        >
                                            <option value="external">Terceirizado</option>
                                            <option value="internal">Oficina interna</option>
                                        </select>
                                    </div>

                                    <button
                                        type="submit"
                                        class="chm-page-button primary full"
                                    >
                                        <i data-lucide="plus"></i>
                                        Adicionar procedimento
                                    </button>
                                </div>
                            </form>

                        </div>
                </div>

                @endif

                @if($maintenancePermissions['add_extra_costs'] ?? false)
                <div
                    class="maintenance-open-action-box"
                    x-data="{ opened: false, description: '' }"
                    @keydown.escape.window="opened = false; description = ''"
                    @click.outside="opened = false; description = ''"
                >
                    <div class="maintenance-card-accent"></div>

                        <div class="maintenance-card-content">

                            <div class="maintenance-action-title">
                                <span>Custos</span>
                                <h3>Lançar custo avulso</h3>
                                <p>Gastos que não pertencem a um procedimento específico.</p>
                            </div>

<form
                                method="POST"
                                action="{{ route('vehicles.maintenance.extra-costs.store', [$vehicle->id, $openMaintenance->id]) }}"
                                class="maintenance-compact-add-form"
                            >
                                @csrf

                                <div class="form-group">
                                    <input
                                        type="text"
                                        name="description"
                                        class="form-input"
                                        required
                                        x-model="description"
                                        @focus="opened = true"
                                        placeholder="Ex.: guincho, pátio, taxa..."
                                    >
                                </div>

                                <div
                                    class="maintenance-action-placeholder"
                                    x-show="!opened && !description.length"
                                >
                                    Informe a descrição para lançar um custo complementar.
                                </div>

                                <div
                                    x-show="opened || description.length"
                                    x-cloak
                                >
                                    <div class="form-group">
                                        <label>Valor</label>

                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            name="amount"
                                            class="form-input"
                                            required
                                        >
                                    </div>

                                    <button
                                        type="submit"
                                        class="chm-page-button primary full"
                                    >
                                        <i data-lucide="plus-circle"></i>
                                        Lançar custo
                                    </button>
                                </div>
                            </form>

                        </div>
                </div>
                @endif
            </div>
            @endif
            <div class="maintenance-open-bottom-grid">
                    <section class="maintenance-timeline-card">
                        <div class="maintenance-section-title">
                            <div>
                                <span>Linha do tempo</span>
                                <h3>Acontecimentos da manutenção</h3>
                            </div>
                        </div>

                        <div class="maintenance-timeline-list">
                            <div class="maintenance-timeline-item">
                                <div class="maintenance-timeline-dot"></div>

                                <div>
                                    <strong>Abertura da manutenção</strong>
                                    <span>
                                        {{ optional($openMaintenance->started_at)->format('d/m/Y H:i') }}
                                    </span>
                                    <p>
                                        Status inicial:
                                        {{ \App\Services\MaintenanceService::serviceStatuses()[$openMaintenance->service_status] ?? 'Não informado' }}
                                    </p>
                                </div>
                            </div>

                            @foreach($openMaintenance->statusLogs->sortBy('created_at') as $log)
                                @if($log->old_status)
                                    <div class="maintenance-timeline-item">
                                        <div class="maintenance-timeline-dot"></div>

                                        <div>
                                            <strong>Status atualizado</strong>
                                            <span>{{ optional($log->created_at)->format('d/m/Y H:i') }}</span>
                                            <p>
                                                {{ \App\Services\MaintenanceService::serviceStatuses()[$log->old_status] ?? $log->old_status }}
                                                →
                                                {{ \App\Services\MaintenanceService::serviceStatuses()[$log->new_status] ?? $log->new_status }}
                                            </p>

                                            @if($log->reason)
                                                <small>{{ $log->reason }}</small>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            @endforeach

                            @foreach($openMaintenance->items->sortBy('created_at') as $item)
                                <div class="maintenance-timeline-item">
                                    <div class="maintenance-timeline-dot is-procedure"></div>

                                    <div>
                                        <strong>Procedimento realizado</strong>
                                        <span>{{ optional($item->created_at)->format('d/m/Y H:i') }}</span>
                                        <p>
                                            {{ $item->procedure->name ?? 'Procedimento não informado' }}
                                            —
                                            @if($maintenancePermissions['view_costs'] ?? false)R$ {{ number_format($item->total_cost ?? 0, 2, ',', '.') }}@else Valor restrito @endif
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                            @foreach($openMaintenance->extraCosts->sortBy('created_at') as $extraCost)
                                <div class="maintenance-timeline-item">
                                    <div class="maintenance-timeline-dot is-cost"></div>

                                    <div>
                                        <strong>Custo avulso lançado</strong>
                                        <span>{{ optional($extraCost->created_at)->format('d/m/Y H:i') }}</span>
                                        <p>
                                            {{ $extraCost->description }}
                                            —
                                            @if($maintenancePermissions['view_costs'] ?? false)R$ {{ number_format($extraCost->amount ?? 0, 2, ',', '.') }}@else Valor restrito @endif
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    <section class="maintenance-services-card">
                    <div class="maintenance-open-items-header">
                        <div>
                            <span>Procedimentos</span>
                            <h3>Serviços adicionados nesta manutenção</h3>
                        </div>

                        <strong>
                            Total: @if($maintenancePermissions['view_costs'] ?? false)R$ {{ number_format($openMaintenance->total_cost ?? 0, 2, ',', '.') }}@else Valor restrito @endif
                        </strong>
                    </div>

                    @if($openMaintenance->items->count())
                        <div class="maintenance-open-items-list">
                            @foreach($openMaintenance->items as $item)
                                <div
                                    class="maintenance-open-item-row"
                                    x-data="{ open: false }"
                                >
                                    <div class="maintenance-open-item-main">
                                        <div>
                                            <strong>{{ $item->procedure->name ?? 'Procedimento não informado' }}</strong>

                                            <span class="maintenance-item-badge {{ $item->maintenance_type }}">
                                                {{ $item->maintenance_type === 'internal' ? 'INTERNA' : 'TERCEIRIZADA' }}
                                            </span>

                                            <small>
                                                {{ optional($item->performed_at)->format('d/m/Y') }}
                                            </small>
                                        </div>

                                        <div class="maintenance-open-item-cost">
                                            @if($maintenancePermissions['view_costs'] ?? false)R$ {{ number_format($item->total_cost ?? 0, 2, ',', '.') }}@else Valor restrito @endif
                                        </div>
                                    </div>

                                    @if($item->values->count())
                                        <button
                                            type="button"
                                            class="maintenance-toggle-consumed"
                                            @click="open = !open"
                                        >
                                            <span x-text="open ? 'Ocultar detalhes' : 'Ver itens consumidos'"></span>
                                            <i data-lucide="chevron-down"></i>
                                        </button>

                                        <div
                                            class="maintenance-consumed-list"
                                            x-show="open"
                                            x-cloak
                                        >
                                            @foreach($item->values as $value)
                                                @if($value->value)
                                                    <div class="maintenance-consumed-row">
                                                        <span>
                                                            {{ $value->field->label ?? 'Campo' }}
                                                        </span>

                                                        <strong>
                                                            {{ $value->value }}
                                                            @if($value->quantity)
                                                                — qtd. {{ number_format($value->quantity, 2, ',', '.') }}
                                                            @endif
                                                        </strong>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="maintenance-open-items-empty">
                            Nenhum procedimento adicionado ainda.
                        </div>
                    @endif

                    </section>
                </div>

        </section>
    @endif


<div class="maintenance-workspace">
    {{-- PROCEDIMENTOS --}}

    @if(! $openMaintenance)
    <section class="maintenance-procedures-card">




        <section class="maintenance-start-card">
            <div>
                <span class="maintenance-kicker">Veículo operacional</span>
                <h2>Colocar veículo em manutenção</h2>
                <p>
                    Abra uma parada para registrar serviços, peças, custos e tempo de indisponibilidade.
                </p>
            </div>

            @if($maintenancePermissions['open'] ?? false)
<a
                href="{{ route('vehicle.maintenance.create', $vehicle->id) }}"
                class="chm-page-button primary"
            >
                <i data-lucide="wrench"></i>
                Abrir manutenção
            </a>
@endif
        </section>

        <div class="maintenance-section-title">
                <div>
                    <span>Procedimentos</span>
                    <h2>Procedimentos disponíveis</h2>
                    <p>
                        Estes são os procedimentos configurados para este veículo. Para executá-los, primeiro abra uma manutenção.
                    </p>
                </div>
        </div>
        <div class="maintenance-procedure-grid">
            @forelse($procedures as $procedure)
                <div class="maintenance-procedure-card">
                    <div class="maintenance-procedure-header">
                        <div class="maintenance-procedure-icon">
                            <i data-lucide="wrench"></i>
                        </div>
                        <div>
                            <h3>
                                {{ $procedure->name }}
                            </h3>
                            <div class="maintenance-procedure-rules">
                                @if($procedure->validity_km)
                                    <span>
                                        <i data-lucide="gauge"></i>
                                        {{ number_format($procedure->interval_km, 0, ',', '.') }} km
                                    </span>
                                @endif

                                @if($procedure->validity_hours)
                                    <span>
                                        <i data-lucide="clock"></i>
                                        {{ number_format($procedure->interval_hours, 0, ',', '.') }} h
                                    </span>
                                @endif

                                @if($procedure->validity_period)
                                    <span>
                                        <i data-lucide="calendar-days"></i>
                                        {{ $procedure->interval_days }} dias
                                    </span>
                                @endif

                                @if(
                                    !$procedure->validity_km
                                    &&
                                    !$procedure->validity_hours
                                    &&
                                    !$procedure->validity_period
                                )
                                    <span>
                                        <i data-lucide="settings"></i>
                                        Manual
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>

                </div>

            @empty
                <div class="maintenance-empty-fields">
                    <i data-lucide="info"></i>
                    <strong>

                        Nenhum procedimento vinculado

                    </strong>
                    <p>

                        Este veículo ainda não possui procedimentos configurados.

                    </p>
                </div>
            @endforelse

        </div>


    </section>
    @endif

</div>

<div class="maintenance-workspace">


<section class="maintenance-history-card">
    <div class="maintenance-section-title">
        <div>
            <span>Histórico</span>

            <h2>Manutenções anteriores</h2>

            <p>
                Consulte as últimas ordens encerradas e acesse o histórico completo deste veículo.
            </p>
        </div>

        <a href="{{ route('vehicles.history', $vehicle->id) }}" class="maintenance-back-button">
            Ver mais
        </a>
    </div>

    @forelse($recentMaintenances as $history)
        <div class="maintenance-history-row">
            <div>
                <strong>#{{ $history->id }} — Manutenção encerrada</strong>

                <span>
                    {{ optional($history->started_at)->format('d/m/Y') }}
                    @if($history->finished_at)
                        até {{ optional($history->finished_at)->format('d/m/Y') }}
                    @endif
                </span>
            </div>

            <div class="maintenance-history-actions">
                <strong>
                    @if($maintenancePermissions['view_costs'] ?? false)R$ {{ number_format($history->total_cost ?? 0, 2, ',', '.') }}@else Valor restrito @endif
                </strong>

                @if($maintenancePermissions['export_pdf'] ?? false)
<a
                    href="{{ route('vehicles.maintenance.order.pdf', [$vehicle->id, $history->id]) }}"
                    class="maintenance-back-button"
                    target="_blank"
                >
                    PDF
                </a>
@endif

                <a
                    href="{{ route(
                        'vehicles.maintenance.show',
                        [$vehicle->id, $history->id]
                    ) }}"
                    class="maintenance-back-button"
                >
                    Detalhes
                </a>
            </div>
        </div>
    @empty
        <div class="maintenance-open-items-empty">
            Nenhuma manutenção encerrada encontrada.
        </div>
    @endforelse
</section>

</div>


</div>



@endsection