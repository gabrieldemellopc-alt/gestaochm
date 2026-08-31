@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pages/settings.css') }}?v=1">
@endpush

@section('content')
    @php($canConfigurePermissions = app(\App\Services\Permissions\ProfilePermissionService::class)->allows(auth()->user(), 'admin.permissions.configure'))
    <main class="settings-page">
        <header class="settings-header">
            <span>Gestão administrativa</span>
            <h1>Configurações</h1>
            <p>Centralize as preferências administrativas do CHM.</p>
        </header>

        <nav class="settings-tabs" aria-label="Abas de configurações">
            @foreach(['general' => 'Geral', 'aggregated-vehicles' => 'Veículos agregados', 'fiscal-documents' => 'Documentos fiscais', 'permissions' => 'Permissões', 'system' => 'Sistema'] as $key => $label)
                @continue($key === 'permissions' && ! $canConfigurePermissions)
                <a href="{{ route('settings.index', ['tab' => $key]) }}" class="{{ $tab === $key ? 'is-active' : '' }}">{{ $label }}</a>
            @endforeach
        </nav>

        @if($tab === 'general')
            <section class="settings-card"><i class="bi bi-gear"></i><div><h2>Configurações gerais</h2><p>Preferências administrativas do sistema serão reunidas aqui.</p></div></section>
        @elseif($tab === 'aggregated-vehicles')
            <section class="settings-card settings-fiscal-card"><i class="bi bi-truck"></i><div class="settings-fiscal-content"><h2>Veículos agregados</h2><p>Estas permissões controlam ações operacionais dos veículos agregados da unidade ativa.</p><form method="POST" action="{{ route('settings.aggregated-vehicles.update') }}" class="settings-fiscal-form">@csrf @method('PATCH')<label class="settings-fiscal-routine"><span class="settings-fiscal-routine-copy"><strong>Permitir abastecimentos</strong><small>Permite lançar abastecimentos para agregados.</small></span><input type="hidden" name="allow_aggregated_fuel" value="0"><input type="checkbox" name="allow_aggregated_fuel" value="1" @checked($location?->allow_aggregated_fuel)></label><label class="settings-fiscal-routine"><span class="settings-fiscal-routine-copy"><strong>Permitir abertura de OM/manutenção</strong><small>Permite abrir manutenção para agregados.</small></span><input type="hidden" name="allow_aggregated_maintenance" value="0"><input type="checkbox" name="allow_aggregated_maintenance" value="1" @checked($location?->allow_aggregated_maintenance)></label><button class="settings-action" type="submit">Salvar configurações</button></form></div></section>
        @elseif($tab === 'fiscal-documents')
            <section class="settings-card settings-fiscal-card">
    <i class="bi bi-receipt"></i>
    <div class="settings-fiscal-content">
        <h2>Obrigatoriedade de notas fiscais</h2>
        <p>Defina em quais rotinas o número/documento fiscal será obrigatório ou opcional.</p>

        <form method="POST" action="{{ route('settings.fiscal-documents.update') }}" class="settings-fiscal-form">
            @csrf
            @method('PATCH')

            @foreach([
                'stock_entry' => ['title' => 'Entrada de item no estoque', 'description' => 'Exige número da nota/documento ao lançar entrada manual de item no estoque.'],
                'external_fuel_filling' => ['title' => 'Abastecimento em posto externo', 'description' => 'Exige documento fiscal ao lançar abastecimento externo.'],
                'fuel_receipt' => ['title' => 'Recebimento de combustível no tanque', 'description' => 'Exige nota fiscal ao registrar entrada no tanque interno.'],
                'maintenance_external_service' => ['title' => 'Serviços terceirizados em manutenção', 'description' => 'Exigir documento fiscal ao registrar serviço executado por prestador externo.'],
            ] as $key => $routine)
                <label class="settings-fiscal-routine">
                    <span class="settings-fiscal-routine-copy">
                        <strong>{{ $routine['title'] }}</strong>
                        <small>{{ $routine['description'] }}</small>
                    </span>

                    <select name="{{ $key }}" aria-label="Obrigatoriedade: {{ $routine['title'] }}">
                        <option value="0" @selected(!($fiscalRequirements[$key] ?? false))>Opcional</option>
                        <option value="1" @selected($fiscalRequirements[$key] ?? false)>Obrigatório</option>
                    </select>
                </label>
            @endforeach

            <button class="settings-action" type="submit">Salvar configurações</button>
        </form>
    </div>
</section>
        @elseif($tab === 'permissions' && $canConfigurePermissions)
            <section class="settings-card"><i class="bi bi-shield-check"></i><div><h2>Permissões</h2><p>As permissões continuam disponíveis na tela atual.</p><a class="settings-action" href="{{ route('permissions.index') }}">Abrir permissões <i class="bi bi-arrow-up-right"></i></a></div></section>
        @else
            <section class="settings-card"><i class="bi bi-server"></i><div><h2>Sistema</h2><p>Informações e preferências técnicas poderão ser exibidas aqui futuramente.</p></div></section>
        @endif
    </main>
@endsection
