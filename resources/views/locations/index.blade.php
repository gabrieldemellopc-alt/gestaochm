@extends('layouts.app')

@php
    $pageTitle = 'Controle';
    $pageSubtitle = 'Cidades';
@endphp

@push('styles')
<link
    rel="stylesheet"
    href="{{ asset('css/pages/locations.css') }}?v=2"
>
@endpush

@section('content')

<div class="locations-page">

    @if($errors->any())
        <div class="locations-alert danger">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="locations-hero">

        <div>
            <span>
                Gestão operacional
            </span>

            <h1>
                Cidades
            </h1>

            <p>
                Gerencie as unidades/localidades vinculadas à divisão atualmente selecionada.
            </p>
        </div>

    </div>
    <div class="locations-summary-grid">
    
        <div class="locations-summary-card">
            <small>Cidades</small>
            <strong>{{ $summary['locations'] }}</strong>
        </div>
    
        <div class="locations-summary-card">
            <small>Veículos</small>
            <strong>{{ $summary['vehicles'] }}</strong>
        </div>
    
        <div class="locations-summary-card danger">
            <small>Críticos</small>
            <strong>{{ $summary['critical'] }}</strong>
        </div>
    
        <div class="locations-summary-card warning">
            <small>Atenção</small>
            <strong>{{ $summary['warning'] }}</strong>
        </div>
    
    </div>
    <div class="locations-grid">

        <section class="locations-card">

            <div class="locations-card-header">

                <div>
                    <h2>
                        Nova cidade
                    </h2>

                    <p>
                        Cadastre uma nova cidade para uso em veículos, permissões e operações.
                    </p>
                </div>

                <i data-lucide="map-pin-plus"></i>

            </div>

            <form
                method="POST"
                action="{{ route('locations.store') }}"
                class="locations-form"
            >
                @csrf

                <div class="form-group">
                    <label>
                        Nome da cidade
                    </label>

                    <input
                        type="text"
                        name="name"
                        value="{{ old('name') }}"
                        placeholder="Ex: Barreiras"
                        required
                    >
                </div>

                <button
                    type="submit"
                    class="locations-submit-btn"
                >
                    <i data-lucide="plus"></i>
                    Cadastrar cidade
                </button>

            </form>

        </section>

        <section class="locations-card">

            <div class="locations-card-header">

                <div>
                    <h2>
                        Cidades cadastradas
                    </h2>

                    <p>
                        Unidades disponíveis na divisão atual.
                    </p>
                </div>

                <i data-lucide="map"></i>

            </div>

            <div class="locations-list locations-dashboard-list">
            
                @forelse($locations as $location)
            
                    <div class="location-unit-card">
            
                        <div class="location-unit-header">
            
                            <div class="location-unit-title">
            
                                <div class="locations-item-icon">
                                    <i data-lucide="map-pin"></i>
                                </div>
            
                                <div>
                                    <strong>
                                        {{ $location->name }}
                                    </strong>
            
                                    <span>
                                        {{ $location->vehicles_count }} veículo(s) vinculados
                                    </span>
                                </div>
            
                            </div>
            
                            @php
                                $locationPayload = [
                                    'id' => $location->id,
                                    'name' => $location->name,
                                ];
                            @endphp
                            
                            <button
                                type="button"
                                class="locations-edit-btn"
                                onclick="openEditLocationModal({{ Illuminate\Support\Js::from($locationPayload) }})"
                            >
                                <i data-lucide="edit-3"></i>
                                Editar
                            </button>
            
                        </div>
            
                        <div class="location-unit-body">
            
                            <div
                                class="location-unit-section location-unit-section-clickable"
                                onclick="openLocationFleetModal(
                                    {{ Illuminate\Support\Js::from($location->name) }},
                                    {{ Illuminate\Support\Js::from($location->vehicles_payload) }}
                                )"
                            >         
                                <div class="location-unit-section-title">
                                    <i data-lucide="truck"></i>
                                    Frota
                                    <span>
                                        Ver veículos
                                    </span>
                                </div>
            
                                <div class="location-metrics-grid">
            
                                    <div class="location-metric">
                                        <small>Operacionais</small>
                                        <strong>{{ $location->operational_vehicles_count }}</strong>
                                    </div>
            
                                    <div class="location-metric warning">
                                        <small>Atenção</small>
                                        <strong>{{ $location->warning_vehicles_count }}</strong>
                                    </div>
            
                                    <div class="location-metric danger">
                                        <small>Críticos</small>
                                        <strong>{{ $location->critical_vehicles_count }}</strong>
                                    </div>
            
                                    <div class="location-metric maintenance">
                                        <small>Manutenção</small>
                                        <strong>{{ $location->maintenance_vehicles_count }}</strong>
                                    </div>
            
                                </div>
            
                            </div>
            
                            <div
                                class="location-unit-section location-unit-section-clickable"
                                onclick="openLocationTeamModal(
                                    {{ Illuminate\Support\Js::from($location->name) }},
                                    {{ Illuminate\Support\Js::from($location->team_payload) }}
                                )"
                            >         
                                <div class="location-unit-section-title">
                                    <i data-lucide="users"></i>
                                    Equipe
                                    <span>
                                        Ver equipe
                                    </span>
                                </div>
                                <div class="location-people-grid">
            
                                    <div>
                                        <small>Motoristas</small>
                                        <strong>{{ $location->drivers_count }}</strong>
                                    </div>
            
                                    <div>
                                        <small>Mecânicos</small>
                                        <strong>{{ $location->mechanics_count }}</strong>
                                    </div>
            
                                    <div>
                                        <small>Supervisores</small>
                                        <strong>{{ $location->supervisors_count }}</strong>
                                    </div>
            
                                    <div>
                                        <small>Gestores</small>
                                        <strong>{{ $location->managers_count }}</strong>
                                    </div>
            
                                </div>
            
                            </div>
            
                        </div>
            
                    </div>
            
                @empty
            
                    <div class="locations-empty">
                        Nenhuma cidade cadastrada para esta divisão.
                    </div>
            
                @endforelse
            
            </div>
        </section>

    </div>

</div>


{{-- MODAL EDITAR --}}
<div
    class="locations-modal-overlay"
    id="editLocationModal"
    style="display:none;"
>
    <div class="locations-modal">

        <div class="locations-modal-header">

            <div>
                <span>
                    Gestão operacional
                </span>

                <h2>
                    Editar cidade
                </h2>

                <p id="editLocationSubtitle">
                    —
                </p>
            </div>

            <button
                type="button"
                onclick="closeEditLocationModal()"
            >
                <i data-lucide="x"></i>
            </button>

        </div>

        <form
            method="POST"
            id="editLocationForm"
            class="locations-modal-form"
        >
            @csrf
            @method('PUT')

            <div class="form-group">
                <label>
                    Nome da cidade
                </label>

                <input
                    type="text"
                    name="name"
                    id="editLocationName"
                    required
                >
            </div>

            <div class="locations-modal-footer">

                <button
                    type="button"
                    class="locations-modal-cancel"
                    onclick="closeEditLocationModal()"
                >
                    Cancelar
                </button>

                <button
                    type="submit"
                    class="locations-modal-save"
                >
                    <i data-lucide="save"></i>
                    Salvar alterações
                </button>

            </div>

        </form>

    </div>
</div>
{{-- MODAL FROTA DA CIDADE --}}
<div
    class="locations-modal-overlay"
    id="locationFleetModal"
    style="display:none;"
>
    <div class="locations-large-modal">

        <div class="locations-modal-header">

            <div>
                <span>
                    Frota da cidade
                </span>

                <h2 id="locationFleetTitle">
                    Veículos
                </h2>

                <p id="locationFleetSubtitle">
                    —
                </p>
            </div>

            <button
                type="button"
                onclick="closeLocationFleetModal()"
            >
                <i data-lucide="x"></i>
            </button>

        </div>

        <div
            class="locations-large-modal-body"
            id="locationFleetList"
        ></div>

    </div>
</div>


{{-- MODAL EQUIPE DA CIDADE --}}
<div
    class="locations-modal-overlay"
    id="locationTeamModal"
    style="display:none;"
>
    <div class="locations-large-modal">

        <div class="locations-modal-header">

            <div>
                <span>
                    Equipe da cidade
                </span>

                <h2 id="locationTeamTitle">
                    Equipe
                </h2>

                <p id="locationTeamSubtitle">
                    —
                </p>
            </div>

            <button
                type="button"
                onclick="closeLocationTeamModal()"
            >
                <i data-lucide="x"></i>
            </button>

        </div>

        <div
            class="locations-large-modal-body"
            id="locationTeamList"
        ></div>

    </div>
</div>

<script>
function openEditLocationModal(location) {
    const modal =
        document.getElementById('editLocationModal');

    const form =
        document.getElementById('editLocationForm');

    form.action =
        "{{ url('/locations') }}/" + location.id;

    document.getElementById('editLocationName').value =
        location.name || '';

    document.getElementById('editLocationSubtitle').innerText =
        location.name || '—';

    modal.style.display =
        'flex';

    if (window.lucide) {
        lucide.createIcons();
    }
}

function closeEditLocationModal() {
    document.getElementById('editLocationModal').style.display =
        'none';
}

function statusLabel(status) {
    const labels = {
        operational: 'Operacional',
        maintenance: 'Manutenção',
        inactive: 'Inativo',
        ok: 'OK',
        warning: 'Atenção',
        danger: 'Crítico',
    };

    return labels[status] || status || '—';
}

function alertClass(status) {
    if (status === 'danger') {
        return 'danger';
    }

    if (status === 'warning') {
        return 'warning';
    }

    return 'ok';
}

function formatNumber(value) {
    if (value === null || value === undefined || value === '') {
        return '0';
    }

    return Number(value).toLocaleString('pt-BR');
}

function openLocationFleetModal(locationName, vehicles) {
    const modal =
        document.getElementById('locationFleetModal');

    const title =
        document.getElementById('locationFleetTitle');

    const subtitle =
        document.getElementById('locationFleetSubtitle');

    const list =
        document.getElementById('locationFleetList');

    title.innerText =
        locationName;

    subtitle.innerText =
        `${vehicles.length} veículo(s) vinculados`;

    if (! vehicles.length) {
        list.innerHTML =
            `<div class="locations-modal-empty">
                Nenhum veículo vinculado a esta cidade.
            </div>`;
    } else {
        list.innerHTML =
            vehicles.map(function (vehicle) {
                const mainAlert =
                    vehicle.main_alert
                        ? vehicle.main_alert.message
                        : 'Sem alerta preventivo';

                const status =
                    alertClass(vehicle.alert_status);

                return `
                    <div class="location-modal-vehicle-card ${status}">

                        <div class="location-modal-vehicle-main">

                            <div class="location-modal-vehicle-icon">
                                <i data-lucide="truck"></i>
                            </div>

                            <div>
                                <strong>
                                    ${vehicle.plate || 'Sem placa'} · ${vehicle.name || 'Veículo'}
                                </strong>

                                <span>
                                    ${vehicle.brand || 'Sem marca'} ${vehicle.model ? '· ' + vehicle.model : ''}
                                </span>
                            </div>

                        </div>

                        <div class="location-modal-vehicle-kpis">

                            <div>
                                <small>KM</small>
                                <strong>${formatNumber(vehicle.current_km)}</strong>
                            </div>

                            <div>
                                <small>Horas</small>
                                <strong>${formatNumber(vehicle.current_hours)}h</strong>
                            </div>

                            <div>
                                <small>Status</small>
                                <strong>${statusLabel(vehicle.operational_status)}</strong>
                            </div>

                        </div>

                        <div class="location-modal-alert ${status}">
                            <i data-lucide="triangle-alert"></i>
                            <span>${mainAlert}</span>
                        </div>
                        
                        <a
                            href="/vehicles/${vehicle.id}/details"
                            class="location-modal-go-vehicle"
                        >
                            <i data-lucide="external-link"></i>
                            Ir até o veículo
                        </a>

                    </div>
                `;
            }).join('');
    }

    modal.style.display =
        'flex';

    if (window.lucide) {
        lucide.createIcons();
    }
}

function closeLocationFleetModal() {
    document.getElementById('locationFleetModal').style.display =
        'none';
}

function openLocationTeamModal(locationName, team) {
    const modal =
        document.getElementById('locationTeamModal');

    const title =
        document.getElementById('locationTeamTitle');

    const subtitle =
        document.getElementById('locationTeamSubtitle');

    const list =
        document.getElementById('locationTeamList');

    title.innerText =
        locationName;

    subtitle.innerText =
        `${team.length} acesso(s) operacional(is) vinculados`;

    if (! team.length) {
        list.innerHTML =
            `<div class="locations-modal-empty">
                Nenhum acesso vinculado diretamente a esta cidade.
            </div>`;
    } else {
        list.innerHTML =
            team.map(function (member) {
                return `
                    <div class="location-modal-team-card">

                        <div class="location-modal-team-icon">
                            <i data-lucide="user-round"></i>
                        </div>

                        <div class="location-modal-team-main">
                            <strong>
                                ${member.name || 'Usuário'}
                            </strong>

                            <span>
                                ${member.email || 'Sem e-mail'}
                            </span>
                        </div>

                        <div class="location-modal-team-badges">

                            <span>
                                ${member.profile_label || member.profile || 'Perfil'}
                            </span>

                            <small>
                                ${member.module || 'Módulo'}
                            </small>

                        </div>

                    </div>
                `;
            }).join('');
    }

    modal.style.display =
        'flex';

    if (window.lucide) {
        lucide.createIcons();
    }
}

function closeLocationTeamModal() {
    document.getElementById('locationTeamModal').style.display =
        'none';
}
</script>
@endsection
