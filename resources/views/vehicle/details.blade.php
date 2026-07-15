@extends('layouts.app')



@php

    $pageTitle = 'Veículo';

    $pageSubtitle = $vehicle->plate . ' · ' . $vehicle->name;

    $operationsEnabled = (bool) config('chm.features.operations_enabled', false);

    $fuelEnabled = (bool) config('chm.features.fuel_enabled', true);

@endphp


@push('styles')

<link

    rel="stylesheet"

    href="{{ asset('css/pages/vehicle-center.css') }}?v=4"
>

@endpush



@section('content')



<div class="vehicle-details-page">


    {{-- HERO --}}

    <div class="vehicle-details-hero">



        <div class="vehicle-center-identity">



            <div class="vehicle-center-icon">



                <img

                    src="{{ asset('images/' . ($vehicle->type_icon ?? 'lixo.png')) }}"

                    alt="Veículo"

                >



            </div>



            <div>



                <div class="vehicle-center-title-row">



                    <h2>

                        {{ $vehicle->name }}

                    </h2>



                    @php
                        $statusConfig = match($vehicle->operational_status) {
                            'maintenance' => [
                                'class' => 'maintenance',
                                'icon' => 'wrench',
                                'label' => 'Em manutenção',
                            ],

                            'inactive' => [
                                'class' => 'inactive',
                                'icon' => 'circle-off',
                                'label' => 'Inativo',
                            ],

                            'inoperant' => [
                                'class' => 'danger',
                                'icon' => 'x-circle',
                                'label' => 'Inoperante',
                            ],

                            'accident' => [
                                'class' => 'danger',
                                'icon' => 'triangle-alert',
                                'label' => 'Sinistro',
                            ],

                            'support' => [
                                'class' => 'warning',
                                'icon' => 'truck',
                                'label' => 'Socorro',
                            ],

                            'testing' => [
                                'class' => 'info',
                                'icon' => 'flask-conical',
                                'label' => 'Em testes',
                            ],

                            'transfer' => [
                                'class' => 'warning',
                                'icon' => 'arrow-right-left',
                                'label' => 'Transferência',
                            ],

                            'transferred' => [
                                'class' => 'inactive',
                                'icon' => 'route',
                                'label' => 'Transferido',
                            ],

                            default => [
                                'class' => 'operational',
                                'icon' => 'check-circle',
                                'label' => 'Operacional',
                            ],
                        };
                    @endphp

                    <span class="vehicle-center-status {{ $statusConfig['class'] }}">
                        <i data-lucide="{{ $statusConfig['icon'] }}"></i>
                        {{ $statusConfig['label'] }}
                    </span>



                </div>



                <div class="vehicle-center-meta">



                    <span>{{ $vehicle->plate }}</span>



                    <span>•</span>



                    <span>{{ $vehicle->brand ?? 'Sem marca' }}</span>



                    @if($vehicle->year)

                        <span>• {{ $vehicle->year }}</span>

                    @endif



                    @if($vehicle->currentAllocation?->location)

                        <span>• {{ $vehicle->currentAllocation->location->name }}</span>

                    @endif



                </div>



            </div>



        </div>



        <div class="vehicle-details-hero-actions">



            <a

                href="{{ route('vehicles.index') }}"

                class="vehicle-center-action"

            >

                <i data-lucide="arrow-left"></i>

                <span>Veículos</span>

            </a>



            <a

                href="{{ route('vehicles.edit', $vehicle) }}"

                class="vehicle-center-action"

            >

                <i data-lucide="pencil"></i>

                <span>Editar</span>

            </a>
            <button
                id="vehicleReportModalButton"
                type="button"
                class="vehicle-center-action"
                aria-haspopup="dialog"
                aria-expanded="false"
                onclick="openVehicleReportModal()"
            >
                <i data-lucide="file-text"></i>
                <span>Relat&oacute;rio</span>
            </button>



        </div>



    </div>

    {{-- KPI BAR --}}
    <section class="vehicle-kpi-strip">

        <div class="vehicle-kpi-card">
            <small>Hodômetro</small>
            <strong>{{ number_format($vehicle->current_km ?? 0, 0, ',', '.') }} km</strong>
        </div>

        <div class="vehicle-kpi-card">
            <small>Horímetro</small>
            <strong>{{ $vehicle->current_hours ?? 0 }} h</strong>
        </div>

        <div class="vehicle-kpi-card">
            <small>Tempo parado</small>
            <strong>{{ $totalDowntimeText ?? '--' }}</strong>

            @if($totalDowntimeSubtext)
                <span>{{ $totalDowntimeSubtext }}</span>
            @endif
        </div>

        <div class="vehicle-kpi-card">
            <small>Disponível</small>
            <strong>{{ $availabilityText ?? '--' }}</strong>

            @if($availabilitySubtext)
                <span>{{ $availabilitySubtext }}</span>
            @endif
        </div>

        <div class="vehicle-kpi-card {{
            $vehicle->alert_status === 'danger'
                ? 'danger'
                : ($vehicle->alert_status === 'warning' ? 'warning' : 'success')
        }}">
            <small>Alertas</small>

            <strong>
                @if($vehicle->alert_status === 'danger')
                    Crítico
                @elseif($vehicle->alert_status === 'warning')
                    Atenção
                @else
                    OK
                @endif
            </strong>

            <span>
                {{ count($vehicle->alerts ?? []) }}
                {{ count($vehicle->alerts ?? []) === 1 ? 'alerta ativo' : 'alertas ativos' }}
            </span>
        </div>

    </section>

    {{-- BODY --}}
{{-- DASHBOARD BODY --}}
<div class="vehicle-dash-layout">

    {{-- LINHA 1: CENTRAL + KM + STATUS --}}
    <div class="vehicle-dash-top-row">

        {{-- CENTRAL --}}
        <section class="vehicle-center-card vehicle-dash-actions-card">
            <div class="vehicle-center-card-header">
                <div>
                    <small>Ações rápidas</small>
                    <h3>Central do veículo</h3>
                </div>
                <i data-lucide="zap"></i>
            </div>

            <div class="vehicle-dash-actions-grid">
                <a href="{{ route('vehicle.maintenance.index', $vehicle) }}" class="vehicle-dash-action">
                    <i data-lucide="wrench"></i>
                    <span>Manutenções</span>
                </a>

                <a href="{{ route('vehicles.tires.index', $vehicle) }}" class="vehicle-dash-action">
                    <i data-lucide="circle-dot"></i>
                    <span>Pneus</span>
                </a>

                @if($fuelEnabled)
                    <a href="{{ route('fuel.tanks.index', ['fuel_modal' => 'filling', 'fuel_vehicle_id' => $vehicle->id]) }}" class="vehicle-dash-action">
                        <i data-lucide="fuel"></i>
                        <span>Combustível</span>
                    </a>
                @endif

                <a href="{{ route('vehicles.history', $vehicle) }}" class="vehicle-dash-action">
                    <i data-lucide="history"></i>
                    <span>Histórico</span>
                </a>
            </div>
        </section>

        {{-- KM / HORÍMETRO --}}
        <section class="vehicle-center-card vehicle-dash-km-card">
            <div class="vehicle-center-card-header">
                <div>
                    <small>Atualização rápida</small>
                    <h3>KM e Horímetro</h3>
                </div>
                <i data-lucide="gauge"></i>
            </div>

            <div class="vehicle-center-fields">
                <form
                    method="POST"
                    action="{{ route('vehicles.update-km', $vehicle) }}"
                    class="vehicle-center-field"
                    onsubmit="return confirmLargeKmUpdate(this, {{ (float) ($vehicle->current_km ?? 0) }});"
                >
                    @csrf

                    <label>Hodômetro atual</label>

                    <div class="vehicle-center-input-row">
                        <input
                            type="number"
                            name="km"
                            value="{{ $vehicle->current_km ?? 0 }}"
                            min="{{ $vehicle->current_km ?? 0 }}"
                            step="1"
                        >

                        <span>KM</span>

                        <button type="submit">
                            Atualizar
                        </button>
                    </div>
                </form>

                <form
                    method="POST"
                    action="{{ route('vehicles.update-hours', $vehicle) }}"
                    class="vehicle-center-field"
                    onsubmit="return confirmLargeHoursUpdate(this, {{ (float) ($vehicle->current_hours ?? 0) }});"
                >
                    @csrf

                    <label>Horímetro atual</label>

                    <div class="vehicle-center-input-row">
                        <input
                            type="number"
                            name="hours"
                            value="{{ $vehicle->current_hours ?? 0 }}"
                            min="{{ $vehicle->current_hours ?? 0 }}"
                            step="1"
                        >

                        <span>H</span>

                        <button type="submit">
                            Atualizar
                        </button>
                    </div>
                </form>
            </div>
        </section>

        {{-- STATUS --}}
        <section
            class="vehicle-center-card vehicle-dash-status-card"
            x-data="{
                editing: false,
                currentStatus: @js($vehicle->operational_status),
                selectedStatus: @js($vehicle->operational_status)
            }"
        >           
            <div class="vehicle-center-card-header">
                <div>
                    <small>Situação do veículo</small>
                    <h3>Status operacional</h3>
                </div>

                <div class="vehicle-dash-status-badge status-{{ $vehicle->operational_status }}">
                    <i data-lucide="{{ $statusConfig['icon'] }}"></i>
                    {{ $statusConfig['label'] }}
                </div>
            </div>


           <div class="vehicle-dash-status-metrics is-two">
                <div>
                    <small>Desde</small>

                    <strong>
                        {{ $vehicle->status_changed_at?->format('d/m/Y') ?? '--' }}
                    </strong>

                    @if($vehicle->status_changed_at)
                        <span>{{ $vehicle->status_changed_at->format('H:i') }}</span>
                    @else
                        <span>Última alteração de status</span>
                    @endif
                </div>

                <div>
                    <small>Tempo neste status</small>

                    <strong>{{ $downTimeText ?? '--' }}</strong>

                    @if($downTimeSubtext)
                        <span>{{ $downTimeSubtext }}</span>
                    @else
                        <span>Desde a última alteração</span>
                    @endif
                </div>
            </div>
            @if($openDowntime?->reason)
                <div class="vehicle-dash-status-reason">
                    {{ $openDowntime->reason }}
                </div>
            @endif
            @if($vehicle->operational_status === 'maintenance')
            
                <div class="vehicle-dash-maintenance-lock">
            
                    <div class="vehicle-dash-maintenance-lock-text">
            
                        <i data-lucide="lock-keyhole"></i>
            
                        <div>
                            <strong>
                                Status controlado pela manutenção
                            </strong>
            
                            <p>
                                Para alterar o status deste veículo, encerre primeiro
                                a ordem de manutenção aberta.
                            </p>
                        </div>
            
                    </div>
            
                    <a
                        href="{{ route('vehicle.maintenance.index', $vehicle) }}"
                        class="vehicle-dash-maintenance-link"
                    >
                        <i data-lucide="wrench"></i>
            
                        Ir para manutenção
                    </a>
            
                </div>
            
            @else
            
                <button
                    type="button"
                    class="vehicle-dash-status-action-btn"
                    @click="
                        selectedStatus = currentStatus;
                        editing = true;
                    "
                >
                    <i data-lucide="pencil"></i>
            
                    Alterar status
                </button>
            
            @endif

            
            @if($vehicle->operational_status !== 'maintenance')
        
            <div
                x-show="editing"
                x-cloak
                class="vehicle-status-modal-backdrop"
                @click.self="editing = false"
            >
                <div class="vehicle-status-modal">
                    <div class="vehicle-status-modal-header">
                        <div>
                            <small>Situação do veículo</small>
                            <h3>Alterar status operacional</h3>
                        </div>

                        <button type="button" @click="editing = false">
                            <i data-lucide="x"></i>
                        </button>
                    </div>

                    <form
                        method="POST"
                        action="{{ route('vehicles.operational-status.update', $vehicle) }}"
                        class="vehicle-status-modal-form"
                    >
                        @csrf

                        <div class="form-group">
                            <label>Status</label>

                            <select
                                name="operational_status"
                                class="form-input"
                                x-model="selectedStatus"
                            >
                                <option value="operational">Operacional</option>
                                <option value="inactive">Inativo</option>
                                <option value="inoperant">Inoperante</option>
                                <option value="accident">Sinistro</option>
                                <option value="support">Socorro</option>
                                <option value="testing">Testes</option>
                                <option value="transfer">Transferência</option>
                                <option value="transferred">Transferido</option>
                            </select>
                        </div>

                        <div
                            class="form-group"
                            x-show="selectedStatus !== currentStatus"
                            x-cloak
                        >
                            <label>
                                Motivo / observação
                            </label>
                        
                            <textarea
                                name="status_reason"
                                rows="4"
                                class="form-input"
                                :required="selectedStatus !== currentStatus"
                                placeholder="Informe o motivo da alteração de status..."
                            ></textarea>
                        </div>

                        <div class="vehicle-status-modal-actions">
                            <button type="button" @click="editing = false">
                                Cancelar
                            </button>

                            <button
                                type="submit"
                                :disabled="selectedStatus === currentStatus"
                            >
                                Salvar status
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            @endif

        </section>

    </div>

    {{-- LINHA 2: ALERTAS + MANUTENÇÕES --}}
    <div class="vehicle-dash-mid-row">

        {{-- ALERTAS --}}
        <section class="vehicle-center-card vehicle-dash-alerts-card">
            <div class="vehicle-center-card-header">
                <div>
                    <small>Monitoramento</small>
                    <h3>Alertas do veículo</h3>
                </div>
                <i data-lucide="triangle-alert"></i>
            </div>

            @if(empty($vehicle->alerts))
                <div class="vehicle-center-empty">
                    <i data-lucide="check-circle"></i>
                    <strong>Nenhum alerta ativo</strong>
                    <p>Este veículo não possui pendências no momento.</p>
                </div>
            @else
                <div class="vehicle-center-alert-list">
                    @foreach($vehicle->alerts as $alert)
                        <div class="vehicle-center-alert {{ $alert['status'] ?? 'warning' }}">
                            <i data-lucide="triangle-alert"></i>

                            <div>
                                <strong>{{ $alert['message'] ?? 'Alerta operacional' }}</strong>

                                @if(!empty($alert['procedure']))
                                    <small>{{ $alert['procedure'] }}</small>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- MANUTENÇÕES --}}
        <section class="vehicle-center-card vehicle-dash-maintenance-card">
            <div class="vehicle-center-card-header">
                <div>
                    <small>Manutenção</small>
                    <h3>Últimos registros</h3>
                </div>
                <i data-lucide="wrench"></i>
            </div>

            @if($vehicle->maintenances->isEmpty())
                <div class="vehicle-center-empty">
                    <i data-lucide="clipboard-x"></i>
                    <strong>Nenhuma manutenção registrada</strong>
                    <p>Os lançamentos de manutenção aparecerão aqui.</p>
                </div>
            @else
                <div class="vehicle-center-maintenance-list">
                    @foreach($vehicle->maintenances->take(6) as $maintenance)
                        <div class="vehicle-center-maintenance">
                            <div>
                                <strong>{{ $maintenance->procedure?->name ?? 'Procedimento' }}</strong>
                                <small>{{ $maintenance->reason ?? 'Preventiva' }}</small>
                            </div>

                            <span>{{ optional($maintenance->performed_at)->format('d/m/Y') ?? '--' }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

    </div>

    {{-- LINHA 3: HISTÓRICO --}}
    <section class="vehicle-center-card vehicle-dash-timeline-card">
        <div class="vehicle-center-card-header">
            <div>
                <small>Histórico recente</small>
                <h3>Atualizações operacionais</h3>
            </div>
            <i data-lucide="clock-3"></i>
        </div>

        @if($vehicle->updateLogs->isEmpty())
            <div class="vehicle-center-empty">
                <i data-lucide="inbox"></i>
                <strong>Nenhuma atualização registrada</strong>
                <p>Alterações de KM, HR e status aparecerão aqui.</p>
            </div>
        @else
            <div class="vehicle-dash-timeline-line">
                @foreach($vehicle->updateLogs->take(6) as $log)
                    <div class="vehicle-dash-timeline-item">
                        <div class="vehicle-dash-timeline-dot"></div>

                        <strong>
                            @if($log->type === 'km')
                                Hodômetro
                            @elseif($log->type === 'hours')
                                Horímetro
                            @else
                                Status
                            @endif
                        </strong>

                        <span>{{ $log->old_value ?? '--' }} ➔ {{ $log->new_value }}</span>

                        <small>{{ optional($log->created_at)->format('d/m/Y H:i') }}</small>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

</div>

</div>


<div
    id="vehicleReportModal"
    class="vehicle-report-modal-backdrop"
    aria-hidden="true"
>
    <div class="vehicle-report-modal" role="dialog" aria-modal="true" aria-labelledby="vehicleReportModalTitle">
        <div class="vehicle-report-modal-header">
            <div>
                <small>Prontu&aacute;rio operacional</small>
                <h3 id="vehicleReportModalTitle">Relat&oacute;rio do Ve&iacute;culo</h3>
                <p>Configure o per&iacute;odo e os blocos que ser&atilde;o exibidos no Dossi&ecirc; Individual.</p>
            </div>

            <button type="button" onclick="closeVehicleReportModal()" aria-label="Fechar relat&oacute;rio do ve&iacute;culo">
                <i data-lucide="x"></i>
            </button>
        </div>

        <form
            method="GET"
            action="{{ route('reports.vehicle-dossier.index') }}"
            class="vehicle-report-form"
            onsubmit="return validateVehicleReportForm(this)"
        >
            <input type="hidden" name="vehicle_id" value="{{ $vehicle->id }}">
            <input type="hidden" name="section_config" value="1">

            <section class="vehicle-report-modal-section">
                <div class="vehicle-report-section-title">
                    <span>1</span>
                    <strong>Per&iacute;odo</strong>
                </div>

                <div class="vehicle-report-shortcuts">
                    <button type="button" onclick="setVehicleReportPeriod('last_30')">&Uacute;ltimos 30 dias</button>
                    <button type="button" onclick="setVehicleReportPeriod('current_month')">M&ecirc;s atual</button>
                    <button type="button" onclick="setVehicleReportPeriod('last_90')">&Uacute;ltimos 90 dias</button>
                    <button type="button" onclick="setVehicleReportPeriod('custom')">Personalizado</button>
                </div>

                <div class="vehicle-report-date-grid">
                    <label>
                        Data inicial
                        <input type="date" name="start_date" id="vehicleReportStartDate" required>
                    </label>

                    <label>
                        Data final
                        <input type="date" name="end_date" id="vehicleReportEndDate" required>
                    </label>
                </div>
            </section>

            <section class="vehicle-report-modal-section">
                <div class="vehicle-report-section-title">
                    <span>2</span>
                    <strong>Conte&uacute;do do relat&oacute;rio</strong>
                </div>

                <div class="vehicle-report-check-grid">
                    <label><input type="checkbox" name="sections[]" value="summary" checked> Resumo executivo</label>
                    <label><input type="checkbox" name="sections[]" value="maintenances" checked> Manuten&ccedil;&otilde;es</label>
                    <label><input type="checkbox" name="sections[]" value="maintenance_costs" checked> Custos registrados da ordem</label>
                    <label><input type="checkbox" name="sections[]" value="stock" checked> Pe&ccedil;as e consumo de estoque</label>
                    <label><input type="checkbox" name="sections[]" value="tires" checked> Pneus e sulcagem</label>
                    <label><input type="checkbox" name="sections[]" value="fuel" checked> Abastecimentos</label>
                    <label><input type="checkbox" name="sections[]" value="fuel_consumption" checked> Consumo de combust&iacute;vel</label>
                    <label><input type="checkbox" name="sections[]" value="km_hr"> Atualiza&ccedil;&otilde;es de KM e hor&iacute;metro</label>
                    <label><input type="checkbox" name="sections[]" value="downtime"> Status operacional e indisponibilidade</label>
                    <label><input type="checkbox" name="sections[]" value="alerts"> Alertas e preventivas</label>
                </div>
            </section>

            @can('viewAuditLogs')
                <section class="vehicle-report-modal-section">
                    <div class="vehicle-report-section-title">
                        <span>3</span>
                        <strong>Op&ccedil;&otilde;es avan&ccedil;adas</strong>
                    </div>

                    <div class="vehicle-report-check-grid is-advanced">
                        <label><input type="checkbox" name="include_cancelled" value="1"> Incluir registros cancelados</label>
                        <label><input type="checkbox" name="include_audit" value="1"> Incluir detalhes de auditoria</label>
                    </div>
                </section>
            @endcan

            <p id="vehicleReportValidation" class="vehicle-report-validation" hidden>Selecione pelo menos um conte&uacute;do para visualizar no relat&oacute;rio.</p>

            <div class="vehicle-report-modal-actions">
                <button type="button" class="vehicle-report-secondary" onclick="closeVehicleReportModal()">
                    Cancelar
                </button>

                <button type="button" class="vehicle-report-secondary" disabled title="PDF do Dossi&ecirc; ainda n&atilde;o implementado">
                    Gerar PDF
                </button>

                <button type="submit" class="vehicle-report-primary">
                    Visualizar relat&oacute;rio
                </button>
            </div>
        </form>
    </div>
</div>
<script>
    const vehicleReportModal = document.getElementById('vehicleReportModal');
    const vehicleReportModalButton = document.getElementById('vehicleReportModalButton');
    const vehicleReportStartDate = document.getElementById('vehicleReportStartDate');
    const vehicleReportEndDate = document.getElementById('vehicleReportEndDate');
    const vehicleReportValidation = document.getElementById('vehicleReportValidation');

    function formatVehicleReportDate(date) {
        return date.toISOString().split('T')[0];
    }

    function openVehicleReportModal() {
        if (!vehicleReportStartDate.value || !vehicleReportEndDate.value) {
            setVehicleReportPeriod('last_30');
        }

        vehicleReportModal.classList.add('active');
        vehicleReportModal.setAttribute('aria-hidden', 'false');
        vehicleReportModalButton.setAttribute('aria-expanded', 'true');
    }

    function closeVehicleReportModal() {
        vehicleReportModal.classList.remove('active');
        vehicleReportModal.setAttribute('aria-hidden', 'true');
        vehicleReportModalButton.setAttribute('aria-expanded', 'false');
        vehicleReportValidation.hidden = true;
    }

    function setVehicleReportPeriod(period) {
        const today = new Date();
        let start = new Date(today);
        const end = new Date(today);

        if (period === 'current_month') {
            start = new Date(today.getFullYear(), today.getMonth(), 1);
        } else if (period === 'last_90') {
            start.setDate(today.getDate() - 90);
        } else if (period === 'custom') {
            vehicleReportStartDate.focus();
            return;
        } else {
            start.setDate(today.getDate() - 30);
        }

        vehicleReportStartDate.value = formatVehicleReportDate(start);
        vehicleReportEndDate.value = formatVehicleReportDate(end);
    }

    function validateVehicleReportForm(form) {
        const selectedSections = form.querySelectorAll('input[name="sections[]"]:checked');

        if (selectedSections.length === 0) {
            vehicleReportValidation.hidden = false;
            return false;
        }

        vehicleReportValidation.hidden = true;
        return true;
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && vehicleReportModal.classList.contains('active')) {
            closeVehicleReportModal();
        }
    });

    vehicleReportModal.addEventListener('click', function (event) {
        if (event.target === vehicleReportModal) {
            closeVehicleReportModal();
        }
    });
    function confirmLargeKmUpdate(form, originalKm) {
        const input = form.querySelector('input[name="km"]');
        const currentKm = Number(input.value);
        const diffKm = currentKm - Number(originalKm);

        if (currentKm < Number(originalKm)) {
            alert(`O novo KM não pode ser menor que o KM atual (${Number(originalKm).toLocaleString('pt-BR')}).`);
            input.value = originalKm;
            return false;
        }

        if (diffKm > 1000) {
            return confirm(
                `Atenção: você está aumentando o hodômetro em ${diffKm.toLocaleString('pt-BR')} km.\n\n` +
                `KM atual: ${Number(originalKm).toLocaleString('pt-BR')}\n` +
                `Novo KM: ${currentKm.toLocaleString('pt-BR')}\n\n` +
                `Deseja confirmar esta atualização?`
            );
        }

        return true;
    }

    function confirmLargeHoursUpdate(form, originalHours) {
        const input = form.querySelector('input[name="hours"]');
        const currentHours = Number(input.value);
        const diffHours = currentHours - Number(originalHours);

        if (currentHours < Number(originalHours)) {
            alert(`O novo horímetro não pode ser menor que o horímetro atual (${Number(originalHours).toLocaleString('pt-BR')}).`);
            input.value = originalHours;
            return false;
        }

        if (diffHours > 24) {
            return confirm(
                `Atenção: você está aumentando o horímetro em ${diffHours.toLocaleString('pt-BR')} hora(s).\n\n` +
                `Horímetro atual: ${Number(originalHours).toLocaleString('pt-BR')}\n` +
                `Novo horímetro: ${currentHours.toLocaleString('pt-BR')}\n\n` +
                `Deseja confirmar esta atualização?`
            );
        }

        return true;
    }
</script>

@endsection
