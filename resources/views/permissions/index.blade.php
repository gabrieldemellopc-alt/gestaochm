@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pages/permissions.css') }}?v=2">
@endpush

@section('content')
    @php
        $selectedDivision = $divisions->firstWhere('id', $scope['division_id']);
        $selectedLocation = $locations->firstWhere('id', $scope['location_id']);
        $selectedProfile = $profiles[$scope['profile']] ?? $scope['profile'];
        $selectedModule = $modules[$scope['module']] ?? $scope['module'];
        $copyLocations = $locations->filter(fn ($location) => (int) $location->id !== (int) ($scope['location_id'] ?? 0));
    @endphp

    <div class="permissions-page">
        <header class="permissions-header">
            <div>
                <span>Gestão administrativa</span>
                <h1>Permissões</h1>
                <p>Configure o que cada perfil pode acessar e executar.</p>
            </div>

            <div class="permissions-scope-badge">
                <span>Escopo ativo</span>
                <strong>{{ $selectedDivision?->name ?? 'Divisão selecionada' }}</strong>
                <small>{{ $selectedLocation?->name ?? 'Todas as unidades permitidas' }}</small>
            </div>
        </header>

        @if(session('success'))
            <div class="permissions-alert success">
                <i class="bi bi-check-circle"></i>
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="permissions-alert warning">
                <i class="bi bi-exclamation-triangle"></i>
                {{ $errors->first() }}
            </div>
        @endif

        <section class="permissions-context-panel">
            <div class="permissions-context-copy">
                <i class="bi bi-shield-check"></i>
                <div>
                    <span>Escopo da configuração</span>
                    <p>
                        As permissões abaixo são aplicadas ao perfil selecionado dentro da unidade escolhida.
                        Alterações afetam a navegação e as ações operacionais já protegidas no sistema.
                    </p>
                    <small>Para outra unidade, selecione a unidade desejada e configure separadamente.</small>
                </div>
            </div>

            <div class="permissions-context-grid">
                <div>
                    <span>Divisão</span>
                    <strong>{{ $selectedDivision?->name ?? 'Não informada' }}</strong>
                </div>
                <div>
                    <span>Unidade</span>
                    <strong>{{ $selectedLocation?->name ?? 'Todas permitidas' }}</strong>
                </div>
                <div>
                    <span>Perfil</span>
                    <strong>{{ $selectedProfile }}</strong>
                </div>
                <div>
                    <span>Módulo</span>
                    <strong>{{ $selectedModule }}</strong>
                </div>
            </div>
        </section>

        <form method="GET" action="{{ route('permissions.index') }}" class="permissions-filter-card">
            <label>
                <span>Divisão</span>
                <select name="division_id" onchange="this.form.submit()">
                    @foreach($divisions as $division)
                        <option value="{{ $division->id }}" @selected((int) $scope['division_id'] === (int) $division->id)>
                            {{ $division->name }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                <span>Unidade</span>
                <select name="location_id" onchange="this.form.submit()">
                    <option value="">Todas as unidades permitidas</option>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}" @selected((int) ($scope['location_id'] ?? 0) === (int) $location->id)>
                            {{ $location->name }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                <span>Perfil</span>
                <select name="profile" onchange="this.form.submit()">
                    @foreach($profiles as $value => $label)
                        <option value="{{ $value }}" @selected($scope['profile'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                <span>Módulo</span>
                <select name="module" onchange="this.form.submit()">
                    @foreach($modules as $value => $label)
                        <option value="{{ $value }}" @selected($scope['module'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </form>

                        <section class="permissions-management-panel">
            <div class="permission-management-header">
                <span>Ações de gestão</span>
                <h2>Aplicar ou restaurar configurações</h2>
                <p>Estas ações afetam apenas o perfil, módulo, divisão e unidade selecionados.</p>
            </div>

            <div class="permission-management-grid">
                <div class="permission-management-card">
                    <div class="permission-management-card-body">
                        <h3>Restaurar padrão</h3>
                        <p>Remove as permissões personalizadas desta unidade e restaura o padrão do sistema.</p>
                    </div>
                    <div class="permission-management-card-actions">
                        <form method="POST" action="{{ route('permissions.reset') }}" onsubmit="return confirm('Isso removerá as permissões personalizadas desta unidade e voltará ao padrão do sistema. Deseja continuar?')">
                            @csrf
                            <input type="hidden" name="division_id" value="{{ $scope['division_id'] }}">
                            <input type="hidden" name="location_id" value="{{ $scope['location_id'] }}">
                            <input type="hidden" name="profile" value="{{ $scope['profile'] }}">
                            <input type="hidden" name="module" value="{{ $scope['module'] }}">
                            <button type="submit" class="permissions-button secondary">Restaurar padrão</button>
                        </form>
                    </div>
                </div>

                <div class="permission-management-card">
                    <div class="permission-management-card-body">
                        <h3>Aplicar na divisão</h3>
                        <p>Aplica as permissões desta unidade para todas as outras unidades da mesma divisão.</p>
                    </div>
                    <div class="permission-management-card-actions">
                        <form method="POST" action="{{ route('permissions.apply-to-division') }}" onsubmit="return confirm('As permissões atuais serão aplicadas para todas as unidades desta divisão. Deseja continuar?')">
                            @csrf
                            <input type="hidden" name="division_id" value="{{ $scope['division_id'] }}">
                            <input type="hidden" name="location_id" value="{{ $scope['location_id'] }}">
                            <input type="hidden" name="profile" value="{{ $scope['profile'] }}">
                            <input type="hidden" name="module" value="{{ $scope['module'] }}">
                            <button type="submit" class="permissions-button secondary" @disabled(! $scope['location_id'])>
                                Aplicar para todas as unidades
                            </button>
                        </form>
                    </div>
                </div>

                <div class="permission-management-card">
                    <div class="permission-management-card-body">
                        <h3>Copiar de unidade</h3>
                        <p>Importa as permissões de outra unidade para a unidade atualmente selecionada.</p>
                    </div>
                    <div class="permission-management-card-actions">
                        @if($scope['location_id'] && $copyLocations->isNotEmpty())
                            <form method="POST" action="{{ route('permissions.copy-from-location') }}" class="permissions-copy-form" onsubmit="return confirm('As permissões da unidade escolhida serão copiadas para a unidade atual. Deseja continuar?')">
                                @csrf
                                <input type="hidden" name="division_id" value="{{ $scope['division_id'] }}">
                                <input type="hidden" name="location_id" value="{{ $scope['location_id'] }}">
                                <input type="hidden" name="profile" value="{{ $scope['profile'] }}">
                                <input type="hidden" name="module" value="{{ $scope['module'] }}">
                                <div class="permission-copy-group">
                                    <select name="source_location_id" required>
                                        <option value="">Copiar permissões de...</option>
                                        @foreach($copyLocations as $location)
                                            <option value="{{ $location->id }}">{{ $location->name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="permissions-button secondary">Copiar</button>
                                </div>
                            </form>
                        @else
                            <div class="permission-copy-group">
                                <select disabled>
                                    <option value="">Copiar permissões de...</option>
                                </select>
                                <button type="button" class="permissions-button secondary" disabled>Copiar</button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        <form method="POST" action="{{ route('permissions.update') }}" class="permissions-form">
            @csrf
            @method('PATCH')

            <input type="hidden" name="division_id" value="{{ $scope['division_id'] }}">
            <input type="hidden" name="location_id" value="{{ $scope['location_id'] }}">
            <input type="hidden" name="profile" value="{{ $scope['profile'] }}">
            <input type="hidden" name="module" value="{{ $scope['module'] }}">

            <div class="permissions-grid">
                @foreach($groups as $groupIndex => $group)
                    @php
                        $activeCount = collect($group['permissions'])->where('allowed', true)->count();
                        $totalCount = count($group['permissions']);
                        $hasOverrides = collect($group['permissions'])->contains(fn ($permission) => $permission['has_override']);
                        $startsOpen = $groupIndex === 0 || $hasOverrides;
                    @endphp

                    <section class="permission-group-card" x-data="{ open: {{ $startsOpen ? 'true' : 'false' }} }">
                        <button type="button" class="permission-group-header" @click="open = ! open" :aria-expanded="open.toString()">
                            <div>
                                <h2>{{ $group['label'] }}</h2>
                                @if($group['description'])
                                    <p>{{ $group['description'] }}</p>
                                @endif
                            </div>

                            <div class="permission-group-meta">
                                <span>{{ $activeCount }} / {{ $totalCount }} ativas</span>
                                <i class="bi bi-chevron-down" :class="{ 'is-open': open }"></i>
                            </div>
                        </button>

                        <div class="permission-list" x-show="open">
                            @foreach($group['permissions'] as $permission)
                                @php
                                    $badgeClass = $permission['has_override']
                                        ? 'custom'
                                        : ($permission['default'] ? 'allowed' : 'blocked');
                                    $badgeLabel = $permission['has_override']
                                        ? 'Personalizado'
                                        : ($permission['default'] ? 'Padrão permitido' : 'Padrão bloqueado');
                                @endphp

                                <label class="permission-row {{ $permission['has_override'] ? 'is-custom' : '' }}">
                                    <div>
                                        <strong>{{ $permission['label'] }}</strong>
                                        @if($permission['description'])
                                            <small>{{ $permission['description'] }}</small>
                                        @endif
                                        <span class="permission-state-badge {{ $badgeClass }}">{{ $badgeLabel }}</span>
                                    </div>

                                    <span class="permission-toggle">
                                        <input
                                            type="checkbox"
                                            name="permissions[{{ $permission['key'] }}]"
                                            value="1"
                                            @checked($permission['allowed'])
                                        >
                                        <span></span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>

            <footer class="permissions-actions">
                <a href="{{ route('permissions.index', $scope) }}" class="permissions-button secondary">Restaurar visualização</a>
                <button type="submit" class="permissions-button primary">
                    <i class="bi bi-floppy"></i>
                    Salvar permissões
                </button>
            </footer>
        </form>
    </div>
@endsection