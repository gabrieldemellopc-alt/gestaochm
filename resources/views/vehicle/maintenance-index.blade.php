@extends('layouts.app')



@push('styles')

<link

    rel="stylesheet"

 href="{{ asset('css/pages/maintenance.css') }}?v=13"
>

@endpush



@section('content')
@php($maintenancePermissions = $maintenancePermissions ?? [])
@php($canEditItems = $canEditItems ?? false)
@php($canEditExtraCosts = $canEditExtraCosts ?? false)
@php($canViewCosts = $canViewCosts ?? false)
@php($isMaintenanceDetail = $isMaintenanceDetail ?? false)
@php($isMaintenanceOpen = $isMaintenanceOpen ?? (bool) $openMaintenance)
@php($maintenanceRestrictionReason = $maintenanceRestrictionReason ?? app(\App\Services\AggregatedVehiclePolicy::class)->maintenanceRestrictionReason($vehicle, $vehicle->location))



<div
    class="maintenance-index-page"
    x-data="{ previousMaintenancesOpen: false }"
>



    <div class="maintenance-create-header">



        <div>



            <span class="maintenance-kicker">

                {{ $isMaintenanceDetail ? 'Ordem de manutenção' : 'Manutenção' }}

            </span>



            <h1>

                {{ $isMaintenanceDetail ? 'Ordem #'.$openMaintenance->id : 'Manutenção do veículo '.$vehicle->name }}

            </h1>



            <p>

                {{ $isMaintenanceDetail ? 'Consulte os dados, custos, fotos e acontecimentos desta manutenção.' : 'Acompanhe a situação de manutenção, alertas e procedimentos disponíveis para este veículo.' }}

            </p>



        </div>



        <button
            type="button"
            class="maintenance-back-button"
            onclick="history.back()"
        >
            <i class="bi bi-arrow-left"></i>

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

    @if(! $isMaintenanceDetail && $alertProcedures->count())
        <section class="maintenance-alert-strip">
            <div class="maintenance-alert-strip-head">
                <span>Alertas</span>
                <strong>Manutenções em atenção</strong>
            </div>

            <div class="maintenance-alert-strip-list">
                @foreach($alertProcedures as $alert)
                    <div class="maintenance-alert-pill {{ $alert['status'] }}">
                        <i class="{{ chm_icon($alert['status'] === 'danger' ? 'circle-alert' : 'triangle-alert') }}"></i>

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
            <i class="bi bi-exclamation-circle"></i>
            <span style="vertical-align: top;">{{ $errors->first() }}</span>
        </div>
    @endif

    @if($openMaintenance)
        @php($minPhotos = \App\Services\MaintenancePhotoService::MIN_REQUIRED_PHOTOS)
        @php($maxPhotos = \App\Services\MaintenancePhotoService::MAX_PHOTOS_PER_MAINTENANCE)
        @php($photoCount = $openMaintenance->photos->count())
        <section
            class="maintenance-open-card"
            x-data="{ cancelModal: false, closeModal: false, reopenModal: false }"
        >
            <div class="maintenance-open-top">
                <div class="maintenance-open-main">
                    <div class="maintenance-open-icon">
                        <i class="bi bi-wrench-adjustable"></i>
                    </div>

                    <div>
                        <span class="maintenance-kicker">
                            {{ $openMaintenance->cancelled_at ? 'Manutenção cancelada' : ($isMaintenanceOpen ? 'Manutenção em andamento' : 'Manutenção encerrada') }}
                        </span>

                        <h2>
                            #{{ $openMaintenance->id }} — {{ $openMaintenance->cancelled_at ? 'Ordem cancelada' : ($isMaintenanceOpen ? 'Veículo em manutenção' : 'Ordem encerrada') }}
                        </h2>

                        <p>
                            Aberta em
                            {{ optional($openMaintenance->started_at)->format('d/m/Y H:i') }}
                            @if($openMaintenance->finished_at)
                                · encerrada em {{ optional($openMaintenance->finished_at)->format('d/m/Y H:i') }}
                            @endif
                            @if($openMaintenance->cancelled_at)
                                · cancelada em {{ optional($openMaintenance->cancelled_at)->format('d/m/Y H:i') }} por {{ $openMaintenance->canceller?->name ?? 'responsável não informado' }}
                            @endif
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
                        <i class="bi bi-file-earmark-text"></i>
                        PDF da ordem
                    </a>
@endif

                    @if($maintenancePermissions['cancel'] ?? false)
<button
                        type="button"
                        class="chm-page-button maintenance-cancel-button"
                        @click="cancelModal = true"
                    >
                        <i class="bi bi-x-circle"></i>
                        Cancelar manutenção
                    </button>
@endif

                    @if($maintenancePermissions['close'] ?? false)
<button
                        type="button"
                        class="chm-page-button maintenance-close-button"
                        @click="closeModal = true"
                    >
                        <i class="bi bi-check-circle"></i>
                        Encerrar manutenção
                    </button>
@endif

                    @if(! $isMaintenanceOpen && ! $openMaintenance->cancelled_at && ($maintenancePermissions['reopen'] ?? false))
                        <button type="button" class="chm-page-button maintenance-reopen-button" @click="reopenModal = true">
                            <i class="bi bi-arrow-counterclockwise"></i> Reabrir manutenção
                        </button>
                    @endif
                </div>
            </div>

            <div class="maintenance-open-badges maintenance-open-summary">
                <span class="maintenance-service-badge status-{{ $openMaintenance->service_status }}">{{ \App\Services\MaintenanceService::serviceStatuses()[$openMaintenance->service_status] ?? 'Não informado' }}</span>
                @if($openMaintenance->maintenance_category)
                    <span class="maintenance-info-badge maintenance-category-badge"><i class="bi bi-tag"></i>{{ \App\Services\MaintenanceService::maintenanceCategories()[$openMaintenance->maintenance_category] ?? 'Outros' }}</span>
                @endif
                <span class="maintenance-info-badge"><i class="bi bi-clock"></i>{{ $isMaintenanceOpen ? 'Parado há '.($openMaintenance->started_at ? $openMaintenance->started_at->diffForHumans(null, true) : '—') : ($openMaintenance->finished_at ? 'Encerrada em '.$openMaintenance->finished_at->format('d/m/Y H:i') : 'Ordem cancelada') }}</span>
                <span class="maintenance-info-badge"><i class="bi bi-currency-dollar"></i>Total @if($maintenancePermissions['view_costs'] ?? false)<span data-maintenance-total>R$ {{ number_format($openMaintenance->total_cost ?? 0, 2, ',', '.') }}</span>@else Valor restrito @endif</span>
                <span class="maintenance-info-badge"><i class="bi bi-images"></i>Fotos {{ $photoCount }}/{{ $maxPhotos }}</span>
            </div>

            <nav class="maintenance-tabs" aria-label="Seções da ordem de manutenção" data-maintenance-tabs>
                @foreach(['general' => 'Geral', 'services' => 'Serviços', 'materials' => 'Materiais', 'costs' => 'Custos'] as $tabKey => $tabLabel)
                    <button type="button" class="maintenance-tab {{ $tabKey === 'general' ? 'is-active' : '' }}" data-maintenance-tab="{{ $tabKey }}" aria-selected="{{ $tabKey === 'general' ? 'true' : 'false' }}">{{ $tabLabel }}</button>
                @endforeach
            </nav>

            <section class="maintenance-photo-card maintenance-evidence-compact" data-maintenance-panel="general" x-data="{ expanded: @js($photoCount < $minPhotos), showAllPhotos: false }">
                <div class="maintenance-photo-heading">
                    <div>
                        <span class="maintenance-kicker">Evidências da ordem</span>
                        <h3>Fotos da manutenção</h3>
                        <p class="maintenance-photo-guidance">{{ $isMaintenanceOpen ? 'Inclua pelo menos 2 fotos para encerrar a ordem. Se houver mais de um problema ou serviço, envie uma imagem de cada item a ser corrigido.' : 'As fotos desta ordem ficam disponíveis para consulta nesta página.' }}</p>
                    </div>
                    <div class="maintenance-photo-limits">
                    <span class="maintenance-photo-counter {{ $photoCount >= $minPhotos ? 'complete' : 'pending' }}">{{ $photoCount < $minPhotos ? $photoCount.'/'.$minPhotos.' fotos obrigatórias' : $photoCount.'/'.$maxPhotos.' fotos enviadas' }}</span>
                        <small>{{ $photoCount >= $minPhotos ? 'Mínimo obrigatório atendido' : 'Obrigatório: mínimo '.$minPhotos }} · Limite: máximo {{ $maxPhotos }}</small>
                        <button type="button" class="maintenance-evidence-toggle" @click="expanded = !expanded" :aria-expanded="expanded" x-text="expanded ? 'Ocultar fotos' : 'Ver fotos'">{{ $photoCount < $minPhotos ? 'Ocultar fotos' : 'Ver fotos' }}</button>
                    </div>
                </div>
                <div class="maintenance-evidence-body" x-show="expanded" x-cloak>
                <div class="maintenance-photo-gallery">
                    @forelse($openMaintenance->photos as $photo)
                        <article class="maintenance-photo-item" x-show="showAllPhotos || {{ $loop->index }} < 4" x-cloak>
                            <a class="maintenance-photo-preview" href="{{ $photo->url }}" target="_blank" rel="noopener">
                                <img src="{{ $photo->url }}" alt="Foto da manutenção" onerror="this.hidden=true;this.nextElementSibling.hidden=false">
                                <span class="maintenance-photo-fallback" hidden><i class="bi bi-image"></i>Imagem indisponível</span>
                            </a>
                            <small>{{ $photo->created_at->format('d/m/Y H:i') }}</small>
                            @if($maintenancePermissions['delete_photos'] ?? false)
                                <form method="POST" action="{{ route('vehicles.maintenance.photos.destroy', [$vehicle, $openMaintenance, $photo]) }}" onsubmit="return confirm('Remover esta foto?')">@csrf @method('DELETE')<button type="submit">Remover</button></form>
                            @endif
                        </article>
                    @empty
                        <p class="maintenance-photo-empty">Nenhuma foto anexada ainda.</p>
                    @endforelse
                    @if($photoCount > 4)
                        <button type="button" class="maintenance-photo-more" @click="showAllPhotos = !showAllPhotos" x-text="showAllPhotos ? 'Mostrar menos' : '+{{ $photoCount - 4 }} fotos'">+{{ $photoCount - 4 }} fotos</button>
                    @endif
                </div>
                <div class="maintenance-photo-actions">
                    @if(($maintenancePermissions['upload_photos'] ?? false) && $photoCount < $maxPhotos)
                        <form class="maintenance-photo-upload-form" method="POST" enctype="multipart/form-data" action="{{ route('vehicles.maintenance.photos.store', [$vehicle, $openMaintenance]) }}"
                            x-data="{ selectedCount: 0, isSubmitting: false, updateSelectedFiles(event) { this.selectedCount = event.target.files.length }, selectedText() { return this.selectedCount === 0 ? 'Nenhum arquivo selecionado' : (this.selectedCount === 1 ? '1 foto selecionada' : this.selectedCount + ' fotos selecionadas') }, submitText() { if (this.isSubmitting) return 'Enviando...'; if (this.selectedCount === 0) return 'Selecione fotos para enviar'; return this.selectedCount === 1 ? 'Enviar 1 foto' : 'Enviar ' + this.selectedCount + ' fotos' } }"
                            @submit="isSubmitting = true">@csrf
                            <div class="maintenance-photo-picker-group">
                                <label class="maintenance-file-picker" for="maintenance-photos-input"><i class="bi bi-images"></i><span>Escolher fotos</span></label>
                                <input id="maintenance-photos-input" class="maintenance-file-input" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required @change="updateSelectedFiles($event)">
                                <span class="maintenance-file-label" x-text="selectedText()">Nenhum arquivo selecionado</span>
                            </div>
                            <button class="maintenance-photo-submit" :class="{ 'is-ready': selectedCount > 0 && !isSubmitting, 'is-loading': isSubmitting }" type="submit" :disabled="selectedCount === 0 || isSubmitting">
                                <i class="bi bi-cloud-upload"></i><span x-text="submitText()">Selecione fotos para enviar</span>
                            </button>
                        </form>
                    @endif
                    @if(($maintenancePermissions['generate_photo_qr'] ?? false) && !session('photo_upload_url') && $photoCount < $maxPhotos)
                        <form method="POST" action="{{ route('vehicles.maintenance.photos.token', [$vehicle, $openMaintenance]) }}">@csrf
                            <button class="chm-page-button maintenance-qr-generate" type="submit"><i class="bi bi-qr-code"></i>Gerar QR para celular</button>
                        </form>
                    @endif
                    @if($photoCount >= $maxPhotos)<p class="maintenance-photo-limit-reached"><i class="bi bi-check-circle"></i>Limite de {{ $maxPhotos }} fotos atingido.</p>@endif
                </div>
                @if(session('photo_upload_url'))
                    <div class="maintenance-qr-box">
                        <div class="maintenance-qr-image"><img src="{{ session('photo_upload_qr') }}" alt="QR Code para envio de fotos"></div>
                        <div class="maintenance-qr-content"><strong>Escaneie com o celular</strong><p>Este link expira em 30 minutos. Válido até {{ session('photo_upload_expires_at') }}.</p>
                            <div class="maintenance-qr-link"><input id="maintenance-photo-url" readonly value="{{ session('photo_upload_url') }}"><button type="button" onclick="navigator.clipboard.writeText(document.getElementById('maintenance-photo-url').value)"><i class="bi bi-copy"></i>Copiar link</button></div>
                            @if(($maintenancePermissions['generate_photo_qr'] ?? false) && $photoCount < $maxPhotos)<form method="POST" action="{{ route('vehicles.maintenance.photos.token', [$vehicle, $openMaintenance]) }}">@csrf<button class="maintenance-new-qr" type="submit"><i class="bi bi-arrow-clockwise"></i>Gerar novo QR</button></form>@endif
                        </div>
                    </div>
                @endif
                </div>
            </section>

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

            @if(! $isMaintenanceOpen && ! $openMaintenance->cancelled_at && ($maintenancePermissions['reopen'] ?? false))
                <div x-show="reopenModal" x-cloak class="maintenance-modal-backdrop" @click.self="reopenModal = false">
                    <div class="maintenance-close-modal">
                        <h3>Reabrir ordem de manutenção</h3>
                        <p>O veículo voltará ao status de manutenção e um novo período de indisponibilidade será iniciado.</p>
                        <form method="POST" action="{{ route('vehicles.maintenance.reopen', [$vehicle->id, $openMaintenance->id]) }}">
                            @csrf
                            <div class="form-group"><label>Motivo da reabertura</label><textarea name="reason" rows="4" class="form-input" required minlength="5" placeholder="Informe por que esta ordem está sendo reaberta..."></textarea></div>
                            <div class="maintenance-modal-actions"><button type="button" class="maintenance-cancel-btn" @click="reopenModal = false">Cancelar</button><button type="submit" class="chm-page-button maintenance-reopen-button">Confirmar reabertura</button></div>
                        </form>
                    </div>
                </div>
            @endif

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
                            <label for="maintenance-finished-at">Data e hora do encerramento</label>

                            <input
                                id="maintenance-finished-at"
                                name="finished_at"
                                type="datetime-local"
                                class="form-input"
                                value="{{ old('finished_at', now()->format('Y-m-d\TH:i')) }}"
                                required
                            >
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

            {{-- maintenance-open-actions-grid-permission-wrapper --}}
            @if($isMaintenanceOpen && ($maintenancePermissions['change_status'] ?? false
                || $maintenancePermissions['add_items'] ?? false
                || $maintenancePermissions['add_extra_costs'] ?? false))
            <div class="maintenance-open-actions-grid">


                @if($maintenancePermissions['change_status'] ?? false)
                <div
                    class="maintenance-open-action-box maintenance-form-panel"
                    data-maintenance-panel="general"
                    x-data="{
                        currentStatus: @js($openMaintenance->service_status),
                        selectedStatus: @js($openMaintenance->service_status)
                    }"
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

                            <div class="maintenance-form-grid maintenance-form-grid--status">
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
                                    :disabled="selectedStatus === currentStatus"
                                >
                                    <i class="bi bi-arrow-clockwise"></i>
                                    Atualizar status
                                </button>
                            </div>
                        </form>

                    </div>
                </div>

                @endif

                @if($maintenancePermissions['add_items'] ?? false)
                <div
                    class="maintenance-open-action-box maintenance-form-panel"
                    data-maintenance-panel="services"
                    x-data="{
                        procedureId: @js((string) old('procedure_id', '')),
                        executionType: @js(old('execution_type', 'external')),
                        procedureSearch: '',
                        internalAvailability: @js($procedures->mapWithKeys(fn ($procedure) => [(string) $procedure->id => (bool) $procedure->can_be_internal])),
                        normalizeProcedureName(value) {
                            return String(value || '')
                                .normalize('NFD')
                                .replace(/[\u0300-\u036f]/g, '')
                                .toLowerCase()
                                .trim();
                        },
                        matchesProcedure(name) {
                            const search = this.normalizeProcedureName(this.procedureSearch);
                            return !search || this.normalizeProcedureName(name).includes(search);
                        },
                        canBeInternal() {
                            return this.internalAvailability[this.procedureId] === true;
                        },
                        selectProcedure(id) {
                            this.procedureId = String(id);
                            if (!this.canBeInternal()) this.executionType = 'external';
                        },
                        resetSelection() {
                            this.procedureId = '';
                            this.executionType = 'external';
                        }
                    }"
                    @keydown.escape.window="resetSelection()"
                    @click.outside="resetSelection()"
                >
                    <div class="maintenance-card-accent"></div>

                        <div class="maintenance-card-content">

                            <div class="maintenance-action-title">
                                <span>Procedimentos</span>
                                <h3>Adicionar serviço</h3>
                                <p>Inclua um procedimento executado nesta parada.</p>
                            </div>

                            @if($procedures->isNotEmpty())
                                <div class="maintenance-procedure-search">
                                    <i class="bi bi-search" aria-hidden="true"></i>
                                    <input
                                        type="search"
                                        class="form-input"
                                        placeholder="Buscar procedimento..."
                                        aria-label="Buscar procedimento"
                                        x-model="procedureSearch"
                                    >
                                    <button
                                        type="button"
                                        class="maintenance-procedure-search-clear"
                                        x-show="procedureSearch"
                                        x-cloak
                                        @click="procedureSearch = ''"
                                        aria-label="Limpar busca de procedimento"
                                    >
                                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                                    </button>
                                </div>
                            @endif

<form
                                method="GET"
                                action="{{ route('vehicles.maintenance.items.create', [$vehicle->id, $openMaintenance->id]) }}"
                                class="maintenance-compact-add-form"
                            >
                                <input type="hidden" name="procedure_id" x-model="procedureId">

                                <div class="maintenance-procedure-picker" x-ref="procedurePicker">
                                    @forelse($procedures as $procedure)
                                        <button
                                            type="button"
                                            class="maintenance-procedure-option"
                                            :class="{ 'is-active': procedureId === @js((string) $procedure->id) }"
                                            x-show="matchesProcedure(@js($procedure->name))"
                                            @click="selectProcedure(@js((string) $procedure->id))"
                                            :aria-pressed="procedureId === @js((string) $procedure->id)"
                                        >
                                            <strong>{{ $procedure->name }}</strong>
                                            <span>Selecionar procedimento</span>
                                        </button>
                                    @empty
                                        <div class="maintenance-open-items-empty">Nenhum procedimento disponível para este veículo.</div>
                                    @endforelse
                                </div>
                                @if($procedures->isNotEmpty())
                                    <p class="maintenance-procedure-search-empty" x-show="procedureSearch && ![...$refs.procedurePicker.querySelectorAll('.maintenance-procedure-option')].some(option => option.style.display !== 'none')" x-cloak>
                                        Nenhum procedimento encontrado.
                                    </p>
                                @endif

                                <div
                                    class="maintenance-action-placeholder"
                                    x-show="!procedureId"
                                >
                                    Selecione um procedimento para informar sua execução.
                                </div>

                                <div class="maintenance-form-grid" x-show="procedureId" x-cloak>
                                    <div class="form-group">
                                        <label>Execução</label>

                                        <input type="hidden" name="execution_type" x-model="executionType">
                                        <div class="maintenance-execution-toggle" role="group" aria-label="Tipo de execução">
                                            <button
                                                type="button"
                                                class="maintenance-execution-option"
                                                :class="{ 'is-active': executionType === 'internal' }"
                                                :disabled="!canBeInternal()"
                                                @click="executionType = 'internal'"
                                            >Oficina interna</button>
                                            <button
                                                type="button"
                                                class="maintenance-execution-option"
                                                :class="{ 'is-active': executionType === 'external' }"
                                                @click="executionType = 'external'"
                                            >Terceirizado</button>
                                        </div>
                                        <small x-show="!canBeInternal()" class="maintenance-field-help">
                                            Este procedimento permite somente execução terceirizada.
                                        </small>
                                    </div>

                                    <button
                                        type="submit"
                                        class="chm-page-button primary full"
                                    >
                                        <i class="bi bi-plus-lg"></i>
                                        Adicionar procedimento
                                    </button>
                                </div>
                            </form>

                        </div>
                </div>

                @endif

                @if($maintenancePermissions['add_extra_costs'] ?? false)
                <div
                    class="maintenance-open-action-box maintenance-form-panel"
                    data-maintenance-panel="costs"
                    x-data="{ description: '' }"
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

                                <div class="maintenance-form-grid maintenance-form-grid--cost">
                                <div class="form-group maintenance-form-field-wide">
                                    <label>Descrição</label>
                                    <input
                                        type="text"
                                        name="description"
                                        class="form-input"
                                        required
                                        x-model="description"
                                        placeholder="Ex.: guincho, pátio, taxa..."
                                    >
                                </div>
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

                                    <div class="form-group">
                                        <label>Data do custo</label>
                                        <input
                                            type="date"
                                            name="cost_date"
                                            class="form-input"
                                            value="{{ old('cost_date', now()->format('Y-m-d')) }}"
                                            required
                                        >
                                    </div>

                                    <div class="maintenance-form-actions">
                                    <button
                                        type="submit"
                                        class="chm-page-button primary full"
                                    >
                                        <i class="bi bi-plus-circle"></i>
                                        Lançar custo
                                    </button>
                                    </div>
                                </div>
                            </form>

                        </div>
                </div>
                @endif
            </div>
            @endif
            <div class="maintenance-open-bottom-grid">
                    <section class="maintenance-timeline-card" data-maintenance-panel="general">
                        <div class="maintenance-section-title">
                            <div>
                                <span>Linha do tempo</span>
                                <h3>Acontecimentos da manutenção</h3>
                            </div>
                        </div>

                        <div class="maintenance-timeline-list maintenance-scroll-body">
                            @foreach($maintenanceTimeline as $event)
                                <div class="maintenance-timeline-item">
                                    <div class="maintenance-timeline-dot is-{{ $event['type'] }}"></div>
                                    <div>
                                        <strong>{{ $event['title'] }}</strong>
                                        <span>{{ optional($event['at'])->format('d/m/Y H:i') }}</span>
                                        <p>{{ $event['detail'] }}</p>
                                        @if($event['complement'])<small>{{ $event['complement'] }}</small>@endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    <section class="maintenance-services-card" data-maintenance-panel="services">
                    <div class="maintenance-open-items-header">
                        <div>
                            <span>Procedimentos</span>
                            <h3>Serviços adicionados nesta manutenção</h3>
                        </div>

                        <strong>
                            Total: @if($maintenancePermissions['view_costs'] ?? false)<span data-maintenance-total>R$ {{ number_format($openMaintenance->total_cost ?? 0, 2, ',', '.') }}</span>@else Valor restrito @endif
                        </strong>
                    </div>

                    @if($openMaintenance->items->count())
                        <div class="maintenance-open-items-list maintenance-scroll-body">
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
                                            @if($item->maintenance_type === 'external' && ($item->provider_name || $item->provider_document || $item->fiscal_document_number || $item->fiscal_document_issued_at))
                                                <small class="maintenance-external-service-detail">
                                                    @if($item->provider_name)
                                                        <span>{{ $item->provider_name }}</span>
                                                    @endif

                                                    @if($item->provider_document)
                                                        <span>
                                                            {{ strlen($item->provider_document) === 14
                                                                ? preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $item->provider_document)
                                                                : (strlen($item->provider_document) === 11
                                                                    ? preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $item->provider_document)
                                                                    : $item->provider_document) }}
                                                        </span>
                                                    @endif

                                                    @if($item->fiscal_document_number)
                                                        <span>NFS-e {{ $item->fiscal_document_number }}</span>
                                                    @endif

                                                    @if($item->fiscal_document_issued_at)
                                                        <span>{{ optional($item->fiscal_document_issued_at)->format('d/m/Y') }}</span>
                                                    @endif

                                                    @if($canViewCosts)
                                                        <span>R$ {{ number_format($item->extra_cost ?? 0, 2, ',', '.') }}</span>
                                                    @endif
                                                </small>
                                            @endif
                                        </div>

                                        <div class="maintenance-open-item-cost">
                                            @if($canViewCosts)R$ {{ number_format($item->total_cost ?? 0, 2, ',', '.') }}@else Valor restrito @endif

                                            @if($canEditItems)
                                                <button
                                                    type="button"
                                                    class="maintenance-inline-edit-button"
                                                    @click.stop="$dispatch('edit-maintenance-item', {
                                                        item: @js([
                                                            'maintenance_type' => $item->maintenance_type,
                                                            'can_be_internal' => (bool) $item->procedure?->can_be_internal,
                                                            'performed_at' => optional($item->performed_at)->format('Y-m-d'),
                                                            'provider_name' => $item->provider_name,
                                                            'supplier_id' => $item->supplier_id,
                                                            'provider_document' => $item->provider_document,
                                                            'notes' => $item->notes,
                                                            'extra_cost' => (float) ($item->extra_cost ?? 0),
                                                            'has_stock_consumption' => $item->stockMovements
                                                                ->where('movement_type', 'out')
                                                                ->whereNull('cancelled_at')
                                                                ->whereNull('reversed_from_movement_id')
                                                                ->isNotEmpty(),
                                                            'stock_consumptions' => $item->stockMovements
                                                                ->where('movement_type', 'out')
                                                                ->whereNull('cancelled_at')
                                                                ->whereNull('reversed_from_movement_id')
                                                                ->map(fn ($movement) => [
                                                                    'item' => $movement->stockItem?->name ?? 'Item de estoque',
                                                                    'quantity' => (float) $movement->quantity,
                                                                    'unit' => $movement->stockItem?->unit,
                                                                    'total_cost' => $canViewCosts
                                                                        ? (float) $movement->total_cost
                                                                        : null,
                                                                ])->values(),
                                                        ]),
                                                        action: @js(route('vehicles.maintenance.items.update', [$vehicle->id, $openMaintenance->id, $item->id]))
                                                    })"
                                                >
                                                    <i class="bi bi-pencil"></i>
                                                    Editar
                                                </button>

                                                @php($itemHasStock = $item->stockMovements->where('movement_type', 'out')->whereNull('cancelled_at')->whereNull('reversal_movement_id')->isNotEmpty())
                                                @if(! $itemHasStock || ($maintenancePermissions['consume_stock'] ?? false))
                                                    <a
                                                        class="maintenance-inline-edit-button maintenance-correct-action"
                                                        href="{{ route('vehicles.maintenance.items.create', [
                                                            $vehicle->id,
                                                            $openMaintenance->id,
                                                            'procedure_id' => $item->procedure_id,
                                                            'execution_type' => $item->maintenance_type,
                                                            'replace_item' => $item->id,
                                                        ]) }}"
                                                    >
                                                        <i class="bi bi-arrow-clockwise"></i>
                                                        Corrigir serviço
                                                    </a>
                                                    <form method="POST" action="{{ route('vehicles.maintenance.items.destroy', [$vehicle->id, $openMaintenance->id, $item->id]) }}" onsubmit="return confirm('Excluir serviço?\n\nO serviço será removido desta manutenção. Custos, consumos e movimentos de estoque vinculados a ele serão revertidos. Os demais serviços da OM não serão alterados.')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="maintenance-inline-edit-button maintenance-delete-action">
                                                            <i class="bi bi-trash3"></i>
                                                            Excluir serviço
                                                        </button>
                                                    </form>
                                                @endif
                                            @endif
                                        </div>
                                    </div>

                                    @if($item->values->count())
                                        <button
                                            type="button"
                                            class="maintenance-toggle-consumed"
                                            @click="open = !open"
                                        >
                                            <span x-text="open ? 'Ocultar detalhes' : 'Ver itens consumidos'"></span>
                                            <i class="bi bi-chevron-down"></i>
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

                    @if($canEditItems && $openMaintenance->cancelledItems->isNotEmpty())
                        <section class="maintenance-replaced-items" data-maintenance-panel="services">
                            <div class="maintenance-open-items-header">
                                <div>
                                    <span>Histórico preservado</span>
                                    <h3>Serviços substituídos/cancelados</h3>
                                </div>
                            </div>
                            @foreach($openMaintenance->cancelledItems as $cancelledItem)
                                <div class="maintenance-open-item-row is-cancelled">
                                    <div class="maintenance-open-item-main">
                                        <div>
                                            <strong>{{ $cancelledItem->procedure?->name ?? 'Procedimento não informado' }}</strong>
                                            <span>
                                                Substituído em {{ optional($cancelledItem->cancelled_at)->format('d/m/Y H:i') }}
                                                · {{ $cancelledItem->canceller?->name ?? 'Usuário não informado' }}
                                            </span>
                                            <small>{{ $cancelledItem->cancel_reason }}</small>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </section>
                    @endif
                </div>

                @includeWhen(
                    $openMaintenance->materialUsages->isNotEmpty() || ($maintenancePermissions['use_materials'] ?? false),
                    'vehicle.partials.maintenance-materials-summary',
                    [
                        'vehicle' => $vehicle,
                        'maintenance' => $openMaintenance,
                        'canUseMaterials' => (bool) ($maintenancePermissions['use_materials'] ?? false),
                        'canCancelMaterials' => (bool) ($maintenancePermissions['cancel_materials'] ?? false),
                        'canViewCosts' => $canViewCosts,
                    ]
                )

                @if(
                    $openMaintenance->extraCosts->isNotEmpty()
                    || ($maintenancePermissions['add_extra_costs'] ?? false)
                    || $canEditExtraCosts
                )
                    <section class="maintenance-services-card maintenance-extra-costs-card" data-maintenance-panel="costs" x-data="{}">
                        <div class="maintenance-open-items-header">
                            <div>
                                <span>Composição da ordem</span>
                                <h3>Custos avulsos lançados</h3>
                            </div>
                            <strong>{{ $openMaintenance->extraCosts->count() }} registro(s)</strong>
                        </div>

                        <div class="maintenance-scroll-body maintenance-extra-costs-list">
                        @forelse($openMaintenance->extraCosts as $extraCost)
                            <div class="maintenance-open-item-row">
                                <div class="maintenance-open-item-main">
                                    <div>
                                        <strong>{{ $extraCost->description }}</strong>
                                        <span>
                                            Data do custo: {{ optional($extraCost->effective_cost_date)->format('d/m/Y') }}
                                            · Lançado em: {{ optional($extraCost->created_at)->format('d/m/Y H:i') }}
                                            · {{ $extraCost->creator?->name ?? 'Responsável não informado' }}
                                        </span>
                                    </div>
                                    <div class="maintenance-open-item-cost">
                                        @if($canViewCosts)
                                            R$ {{ number_format($extraCost->amount, 2, ',', '.') }}
                                        @else
                                            Valor restrito
                                        @endif

                                        @if($canEditExtraCosts && $canViewCosts)
                                            <button
                                                type="button"
                                                class="maintenance-inline-edit-button"
                                                @click.stop="$dispatch('edit-maintenance-extra-cost', {
                                                    cost: @js([
                                                        'description' => $extraCost->description,
                                                        'amount' => (float) $extraCost->amount,
                                                        'cost_date' => optional($extraCost->effective_cost_date)->format('Y-m-d'),
                                                    ]),
                                                    action: @js(route('vehicles.maintenance.extra-costs.update', [$vehicle->id, $openMaintenance->id, $extraCost->id]))
                                                })"
                                            >
                                                <i class="bi bi-pencil"></i>
                                                Editar
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="maintenance-open-items-empty">Nenhum custo avulso registrado.</div>
                        @endforelse
                        </div>
                    </section>
                @endif

                @if($canEditItems || ($canEditExtraCosts && $canViewCosts))
                    @include('vehicle.partials.maintenance-edit-modals')
                @endif

        </section>
    @endif


@if(! $isMaintenanceDetail)
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
                @if($maintenanceRestrictionReason)
                    <div class="maintenance-open-restricted">
                        <button type="button" class="chm-page-button primary" disabled aria-disabled="true" title="Manutenção não permitida para veículos agregados nesta unidade.">
                            <i class="bi bi-lock"></i>
                            Abrir manutenção
                        </button>
                        <small><i class="bi bi-info-circle"></i> {{ $maintenanceRestrictionReason }}</small>
                    </div>
                @else
<a
                href="{{ route('vehicle.maintenance.create', $vehicle->id) }}"
                class="chm-page-button primary"
            >
                <i class="bi bi-wrench-adjustable"></i>
                Abrir manutenção
            </a>
                @endif
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
                            <i class="bi bi-wrench-adjustable"></i>
                        </div>
                        <div>
                            <h3>
                                {{ $procedure->name }}
                            </h3>
                            <div class="maintenance-procedure-rules">
                                @if($procedure->validity_km)
                                    <span>
                                        <i class="bi bi-speedometer2"></i>
                                        {{ number_format($procedure->interval_km, 0, ',', '.') }} km
                                    </span>
                                @endif

                                @if($procedure->validity_hours)
                                    <span>
                                        <i class="bi bi-clock"></i>
                                        {{ number_format($procedure->interval_hours, 0, ',', '.') }} h
                                    </span>
                                @endif

                                @if($procedure->validity_period)
                                    <span>
                                        <i class="bi bi-calendar-week"></i>
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
                                        <i class="bi bi-gear"></i>
                                        Manual
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>

                </div>

            @empty
                <div class="maintenance-empty-fields">
                    <i class="bi bi-info-circle"></i>
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

<div class="maintenance-workspace maintenance-previous-history" x-data="{ open: false }">
    <button type="button" class="maintenance-previous-toggle" x-on:click="open = !open" :aria-expanded="open">
        <span x-text="open ? 'Ocultar manutenções anteriores' : 'Ver manutenções anteriores'">Ver manutenções anteriores</span>
        <i class="bi bi-chevron-down" :class="{ 'is-rotated': open }"></i>
    </button>

<section class="maintenance-history-card" x-show="open" x-cloak>
    <div class="maintenance-section-title">
        <div>
            <span>Histórico</span>

            <h2>Manutenções anteriores</h2>

            <p>
                Consulte as últimas ordens encerradas deste veículo.
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
@endif


</div>



@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const tabs = Array.from(document.querySelectorAll('[data-maintenance-tab]'));
        const panels = Array.from(document.querySelectorAll('[data-maintenance-panel]'));

        if (!tabs.length || !panels.length) return;

        const activateTab = (name) => {
            tabs.forEach((tab) => {
                const active = tab.dataset.maintenanceTab === name;
                tab.classList.toggle('is-active', active);
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            panels.forEach((panel) => {
                panel.hidden = panel.dataset.maintenancePanel !== name;
            });
        };

        tabs.forEach((tab) => tab.addEventListener('click', () => activateTab(tab.dataset.maintenanceTab)));
        activateTab('general');
    });

    window.maintenanceMaterialsManager = function (config) {
        return {
            query: '', results: [], selected: null, quantity: '', notes: '',
            loading: false, submitting: false, message: '', messageType: 'success',
            count: config.count, totalQuantity: config.totalQuantity,
            materialsTotal: config.materialsTotal, maintenanceTotal: config.maintenanceTotal,
            money(value) { return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value || 0)); },
            selectItem(item) { this.selected = item; this.query = item.name; this.results = []; this.quantity = ''; },
            async search() {
                if (this.query.trim().length < 2) { this.results = []; return; }
                this.loading = true;
                try {
                    const response = await fetch(config.searchUrl + '?q=' + encodeURIComponent(this.query), { headers: { Accept: 'application/json' } });
                    this.results = response.ok ? await response.json() : [];
                } finally { this.loading = false; }
            },
            async request(form) {
                this.submitting = true; this.message = '';
                try {
                    const response = await fetch(form.action, { method: form.method || 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: new FormData(form) });
                    const payload = await response.json();
                    if (!response.ok) throw new Error(Object.values(payload.errors || {}).flat()[0] || payload.message || 'Não foi possível concluir a operação.');
                    this.count = payload.count; this.totalQuantity = payload.quantity_total; this.materialsTotal = payload.materials_total; this.maintenanceTotal = payload.maintenance_total;
                    this.$refs.materialsList.innerHTML = payload.list_html;
                    document.querySelectorAll('[data-maintenance-total]').forEach(element => element.textContent = this.money(payload.maintenance_total));
                    this.message = payload.message; this.messageType = 'success';
                    return true;
                } catch (error) { this.message = error.message; this.messageType = 'error'; return false; }
                finally { this.submitting = false; }
            },
            async addMaterial(event) {
                if (!this.selected || this.submitting) return;
                if (await this.request(event.target)) { this.selected = null; this.query = ''; this.quantity = ''; this.notes = ''; this.results = []; }
            },
            async submitAction(event) { if (!this.submitting) await this.request(event.target); }
        };
    };
</script>
@endpush
