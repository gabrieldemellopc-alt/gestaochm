@extends('layouts.app')

@push('styles')
<link
    rel="stylesheet"
    href="{{ asset('css/pages/maintenance.css') }}?v=6"
>
@endpush

@section('content')
@php($maintenancePermissions = $maintenancePermissions ?? [])

<div
    class="maintenance-index-page maintenance-details-page"
    x-data="{
        reopenModal: false,
        deleteModal: false
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
                            @if($maintenancePermissions['view_costs'] ?? false)R$ {{ number_format(
                                $item->total_cost ?? 0,
                                2,
                                ',',
                                '.'
                            ) }}@else Valor restrito @endif
                        </div>

                    </div>

                </div>

            @empty

                <div class="maintenance-open-items-empty">
                    Nenhum procedimento registrado.
                </div>

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