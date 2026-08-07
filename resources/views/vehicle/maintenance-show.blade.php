@extends('layouts.app')

@push('styles')
<link
    rel="stylesheet"
    href="{{ asset('css/pages/maintenance.css') }}?v=7"
>
@endpush

@section('content')
@php($maintenancePermissions = $maintenancePermissions ?? [])
@php($canEditItems = $canEditItems ?? false)
@php($canEditExtraCosts = $canEditExtraCosts ?? false)
@php($canViewCosts = $canViewCosts ?? false)

<div
    class="maintenance-index-page maintenance-details-page"
    x-data="{
        reopenModal: false,
        deleteModal: false,
        itemModal: false,
        extraCostModal: false,
        itemAction: '',
        extraCostAction: '',
        itemForm: {},
        extraCostForm: {},
        editItem(item, action) {
            this.itemForm = item;
            this.itemAction = action;
            this.itemModal = true;
        },
        editExtraCost(cost, action) {
            this.extraCostForm = cost;
            this.extraCostAction = action;
            this.extraCostModal = true;
        }
    }"
>

    <div class="maintenance-create-header">

        <div>
            <span class="maintenance-kicker">
                Ordem de manutenção
            </span>

            <h1>
                Ordem #{{ $maintenance->id }}
            </h1>

            <p>
                Consulte os dados, custos, serviços e registros desta manutenção.
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

    @if($errors->any())
        <div class="chm-alert danger">
            <i data-lucide="circle-alert"></i>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <section class="maintenance-details-hero">

        <div class="maintenance-details-vehicle">

            <div class="maintenance-open-icon">
                <i data-lucide="wrench"></i>
            </div>

            <div>
                <span>
                    {{ $maintenance->workflow_status === 'closed'
                        ? 'Manutenção encerrada'
                        : 'Manutenção aberta'
                    }}
                </span>

                <h2>
                    {{ $vehicle->plate ?? 'Sem placa' }}
                    — {{ $vehicle->name }}
                </h2>

                <p>
                    Aberta em
                    {{ optional($maintenance->started_at)->format('d/m/Y H:i') ?? '—' }}

                    @if($maintenance->finished_at)
                        · encerrada em
                        {{ optional($maintenance->finished_at)->format('d/m/Y H:i') }}
                    @endif
                </p>
            </div>

        </div>

        <div class="maintenance-details-actions">

            @if($maintenancePermissions['export_pdf'] ?? false)
<a
                href="{{ route(
                    'vehicles.maintenance.order.pdf',
                    [$vehicle->id, $maintenance->id]
                ) }}"
                class="chm-page-button maintenance-pdf-button"
                target="_blank"
            >
                <i data-lucide="file-text"></i>
                PDF da ordem
            </a>
@endif

            @if(
                $canManageMaintenance
                && $maintenance->workflow_status === 'closed'
                && ! $maintenance->cancelled_at
            )
                @if($maintenancePermissions['reopen'] ?? false)
<button
                    type="button"
                    class="chm-page-button maintenance-reopen-button"
                    @click="reopenModal = true"
                >
                    <i data-lucide="rotate-ccw"></i>
                    Reabrir
                </button>
@endif

                @if($maintenancePermissions['delete'] ?? false)
<button
                    type="button"
                    class="chm-page-button maintenance-delete-button"
                    @click="deleteModal = true"
                >
                    <i data-lucide="trash-2"></i>
                    Apagar
                </button>
@endif
            @endif

        </div>

    </section>

    <div class="maintenance-details-summary">

        <div>
            <span>Status do serviço</span>
            <strong>
                {{ \App\Services\MaintenanceService::serviceStatuses()[
                    $maintenance->service_status
                ] ?? 'Não informado' }}
            </strong>
        </div>

        <div>
            <span>Custo total</span>
            <strong>
                @if($maintenancePermissions['view_costs'] ?? false)R$ {{ number_format(
                    $maintenance->total_cost ?? 0,
                    2,
                    ',',
                    '.'
                ) }}@else Valor restrito @endif
            </strong>
        </div>

        <div>
            <span>Aberta por</span>
            <strong>{{ $maintenance->opener?->name ?? '—' }}</strong>
        </div>

        <div>
            <span>Encerrada por</span>
            <strong>{{ $maintenance->closer?->name ?? '—' }}</strong>
        </div>

    </div>

    <div class="maintenance-details-grid">

        <section class="maintenance-services-card">

            <div class="maintenance-open-items-header">
                <div>
                    <span>Procedimentos</span>
                    <h3>Serviços executados</h3>
                </div>

                <strong>
                    {{ $maintenance->items->count() }}
                    registro(s)
                </strong>
            </div>

            @forelse($maintenance->items as $item)

                <div class="maintenance-open-item-row">

                    <div class="maintenance-open-item-main">

                        <div>
                            <strong>
                                {{ $item->procedure?->name
                                    ?? 'Procedimento não informado'
                                }}
                            </strong>

                            <span>
                                {{ $item->maintenance_type === 'internal'
                                    ? 'Oficina interna'
                                    : 'Terceirizado'
                                }}
                            </span>

                            <small>
                                {{ optional($item->performed_at)->format('d/m/Y') ?? '—' }}
                            </small>
                        </div>

                        <div class="maintenance-open-item-cost">
                            @if($canViewCosts)R$ {{ number_format(
                                $item->total_cost ?? 0,
                                2,
                                ',',
                                '.'
                            ) }}@else Valor restrito @endif

                            @if(
                                $canEditItems
                                && $maintenance->workflow_status === 'open'
                                && ! $maintenance->cancelled_at
                            )
                                <button
                                    type="button"
                                    class="maintenance-inline-edit-button"
                                    @click="editItem(@js([
                                        "maintenance_type" => $item->maintenance_type,
                                        "performed_at" => optional($item->performed_at)->format("Y-m-d"),
                                        "provider_name" => $item->provider_name,
                                        "notes" => $item->notes,
                                        "extra_cost" => (float) ($item->extra_cost ?? 0),
                                    ]), @js(route("vehicles.maintenance.items.update", [$vehicle->id, $maintenance->id, $item->id])))"
                                >
                                    <i data-lucide="pencil"></i>
                                    Editar
                                </button>
                            @endif
                        </div>

                    </div>

                </div>

            @empty

                <div class="maintenance-open-items-empty">
                    Nenhum procedimento registrado.
                </div>

            @endforelse

        </section>

        <section class="maintenance-services-card maintenance-extra-costs-card">
            <div class="maintenance-open-items-header">
                <div>
                    <span>Composição da ordem</span>
                    <h3>Custos avulsos lançados</h3>
                </div>
                <strong>{{ $maintenance->extraCosts->count() }} registro(s)</strong>
            </div>

            @forelse($maintenance->extraCosts as $extraCost)
                <div class="maintenance-open-item-row">
                    <div class="maintenance-open-item-main">
                        <div>
                            <strong>{{ $extraCost->description }}</strong>
                            <span>
                                {{ optional($extraCost->created_at)->format('d/m/Y H:i') }}
                                · {{ $extraCost->creator?->name ?? 'Responsável não informado' }}
                            </span>
                        </div>
                        <div class="maintenance-open-item-cost">
                            @if($canViewCosts)
                                R$ {{ number_format($extraCost->amount, 2, ',', '.') }}
                            @else
                                Valor restrito
                            @endif

                            @if(
                                $canEditExtraCosts
                                && $canViewCosts
                                && $maintenance->workflow_status === 'open'
                                && ! $maintenance->cancelled_at
                            )
                                <button
                                    type="button"
                                    class="maintenance-inline-edit-button"
                                    @click="editExtraCost(@js([
                                        "description" => $extraCost->description,
                                        "amount" => (float) $extraCost->amount,
                                    ]), @js(route("vehicles.maintenance.extra-costs.update", [$vehicle->id, $maintenance->id, $extraCost->id])))"
                                >
                                    <i data-lucide="pencil"></i>
                                    Editar
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="maintenance-open-items-empty">Nenhum custo avulso registrado.</div>
            @endforelse
        </section>

        <section class="maintenance-timeline-card">

            <div class="maintenance-section-title">
                <div>
                    <span>Linha do tempo</span>
                    <h3>Alterações de status</h3>
                </div>
            </div>

            <div class="maintenance-timeline-list">

                @forelse($maintenance->statusLogs->sortBy('created_at') as $log)

                    <div class="maintenance-timeline-item">

                        <div class="maintenance-timeline-dot"></div>

                        <div>
                            <strong>
                                {{ $log->old_status
                                    ? 'Status atualizado'
                                    : 'Abertura da manutenção'
                                }}
                            </strong>

                            <span>
                                {{ optional($log->created_at)->format('d/m/Y H:i') }}
                                @if($log->user)
                                    · {{ $log->user->name }}
                                @endif
                            </span>

                            <p>
                                @if($log->old_status)
                                    {{ \App\Services\MaintenanceService::serviceStatuses()[
                                        $log->old_status
                                    ] ?? $log->old_status }}

                                    →

                                    {{ \App\Services\MaintenanceService::serviceStatuses()[
                                        $log->new_status
                                    ] ?? $log->new_status }}
                                @else
                                    Status inicial:
                                    {{ \App\Services\MaintenanceService::serviceStatuses()[
                                        $log->new_status
                                    ] ?? $log->new_status }}
                                @endif
                            </p>

                            @if($log->reason)
                                <small>{{ $log->reason }}</small>
                            @endif
                        </div>

                    </div>

                @empty

                    <div class="maintenance-open-items-empty">
                        Nenhuma alteração de status registrada.
                    </div>

                @endforelse

            </div>

        </section>

    </div>

    <section class="maintenance-details-notes">

        <div>
            <span>Observações da manutenção</span>
            <p>{{ $maintenance->notes ?: 'Nenhuma observação registrada.' }}</p>
        </div>

        <div>
            <span>Observações do encerramento</span>
            <p>
                {{ $maintenance->closure_notes
                    ?: 'Nenhuma observação de encerramento registrada.'
                }}
            </p>
        </div>

    </section>

    <div
        x-show="itemModal"
        x-cloak
        class="maintenance-modal-backdrop"
        @click.self="itemModal = false"
    >
        <div class="maintenance-close-modal">
            <h3>Editar serviço</h3>
            <p>Corrija os dados operacionais. Campos dinâmicos e consumo de estoque não serão alterados.</p>

            <form method="POST" :action="itemAction">
                @csrf
                @method('PATCH')

                <div class="form-group">
                    <label>Tipo de execução</label>
                    <select name="maintenance_type" class="form-input" x-model="itemForm.maintenance_type" required>
                        <option value="internal">Oficina interna</option>
                        <option value="external">Terceirizado</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Data de execução</label>
                    <input type="date" name="performed_at" class="form-input" x-model="itemForm.performed_at" required>
                </div>

                <div class="form-group">
                    <label>Prestador</label>
                    <input type="text" name="provider_name" class="form-input" maxlength="255" x-model="itemForm.provider_name">
                </div>

                @if($canViewCosts)
                    <div class="form-group">
                        <label>Custo adicional do serviço</label>
                        <input type="number" name="extra_cost" class="form-input" min="0" step="0.01" x-model="itemForm.extra_cost" required>
                    </div>
                @endif

                <div class="form-group">
                    <label>Observação</label>
                    <textarea name="notes" rows="3" class="form-input" maxlength="2000" x-model="itemForm.notes"></textarea>
                </div>

                <div class="form-group">
                    <label>Motivo da alteração</label>
                    <textarea name="change_reason" rows="3" class="form-input" minlength="10" maxlength="2000" required></textarea>
                </div>

                <div class="maintenance-modal-actions">
                    <button type="button" class="maintenance-cancel-btn" @click="itemModal = false">Cancelar</button>
                    <button type="submit" class="chm-page-button">Salvar alteração</button>
                </div>
            </form>
        </div>
    </div>

    <div
        x-show="extraCostModal"
        x-cloak
        class="maintenance-modal-backdrop"
        @click.self="extraCostModal = false"
    >
        <div class="maintenance-close-modal">
            <h3>Editar custo avulso</h3>

            <form method="POST" :action="extraCostAction">
                @csrf
                @method('PATCH')

                <div class="form-group">
                    <label>Descrição</label>
                    <input type="text" name="description" class="form-input" maxlength="255" x-model="extraCostForm.description" required>
                </div>

                <div class="form-group">
                    <label>Valor</label>
                    <input type="number" name="amount" class="form-input" min="0" step="0.01" x-model="extraCostForm.amount" required>
                </div>

                <div class="form-group">
                    <label>Motivo da alteração</label>
                    <textarea name="change_reason" rows="3" class="form-input" minlength="10" maxlength="2000" required></textarea>
                </div>

                <div class="maintenance-modal-actions">
                    <button type="button" class="maintenance-cancel-btn" @click="extraCostModal = false">Cancelar</button>
                    <button type="submit" class="chm-page-button">Salvar alteração</button>
                </div>
            </form>
        </div>
    </div>

    <div
        x-show="reopenModal"
        x-cloak
        class="maintenance-modal-backdrop"
        @click.self="reopenModal = false"
    >
        <div class="maintenance-close-modal">

            <h3>Reabrir ordem de manutenção</h3>

            <p>
                O veículo voltará ao status de manutenção e um novo período
                de indisponibilidade será iniciado.
            </p>

            @if($maintenancePermissions['reopen'] ?? false)
<form
                method="POST"
                action="{{ route(
                    'vehicles.maintenance.reopen',
                    [$vehicle->id, $maintenance->id]
                ) }}"
            >
                @csrf

                <div class="form-group">
                    <label>Motivo da reabertura</label>

                    <textarea
                        name="reason"
                        rows="4"
                        class="form-input"
                        required
                        minlength="5"
                        placeholder="Informe por que esta ordem está sendo reaberta..."
                    ></textarea>
                </div>

                <div class="maintenance-modal-actions">

                    <button
                        type="button"
                        class="maintenance-cancel-btn"
                        @click="reopenModal = false"
                    >
                        Cancelar
                    </button>

                    <button
                        type="submit"
                        class="chm-page-button maintenance-reopen-button"
                    >
                        Confirmar reabertura
                    </button>

                </div>

            </form>
@endif

        </div>
    </div>

    <div
        x-show="deleteModal"
        x-cloak
        class="maintenance-modal-backdrop"
        @click.self="deleteModal = false"
    >
        <div class="maintenance-close-modal">

            <h3>Apagar ordem de manutenção</h3>

            <p>
                A ordem será ocultada das listagens, mas seus dados, custos,
                movimentos de estoque e registros de auditoria serão preservados.
            </p>

            @if($maintenancePermissions['delete'] ?? false)
<form
                method="POST"
                action="{{ route(
                    'vehicles.maintenance.destroy',
                    [$vehicle->id, $maintenance->id]
                ) }}"
            >
                @csrf

                <div class="form-group">
                    <label>Motivo da exclusão</label>

                    <textarea
                        name="reason"
                        rows="4"
                        class="form-input"
                        required
                        minlength="5"
                        placeholder="Informe por que esta ordem deve ser apagada..."
                    ></textarea>
                </div>

                <div class="maintenance-modal-actions">

                    <button
                        type="button"
                        class="maintenance-cancel-btn"
                        @click="deleteModal = false"
                    >
                        Cancelar
                    </button>

                    <button
                        type="submit"
                        class="chm-page-button danger"
                    >
                        Confirmar exclusão
                    </button>

                </div>

            </form>
@endif

        </div>
    </div>

</div>

@endsection
