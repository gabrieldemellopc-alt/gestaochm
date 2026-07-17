@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pages/permissions.css') }}?v=1">
@endpush

@section('content')
    <div class="permissions-page">
        <header class="permissions-header">
            <div>
                <span>Gestão administrativa</span>
                <h1>Permissões</h1>
                <p>Configure o que cada perfil pode acessar e executar.</p>
            </div>

            <div class="permissions-scope-badge">
                <span>Escopo seguro</span>
                <strong>{{ $divisions->firstWhere('id', $scope['division_id'])?->name ?? 'Divisão selecionada' }}</strong>
                <small>
                    {{ $locations->firstWhere('id', $scope['location_id'])?->name ?? 'Todas as unidades permitidas' }}
                </small>
            </div>
        </header>

        @if(session('success'))
            <div class="permissions-alert success">
                <i data-lucide="check-circle"></i>
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="permissions-alert warning">
                <i data-lucide="triangle-alert"></i>
                {{ $errors->first() }}
            </div>
        @endif

        <section class="permissions-info-card">
            <div>
                <i data-lucide="shield-check"></i>
            </div>
            <p>
                Este primeiro bloco cria a matriz configurável e aplica a visibilidade de navegação para o perfil Supervisor.
                As permissões operacionais de backend serão ativadas em blocos futuros por módulo, com validação rota a rota.
            </p>
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

        <form method="POST" action="{{ route('permissions.update') }}" class="permissions-form">
            @csrf
            @method('PATCH')

            <input type="hidden" name="division_id" value="{{ $scope['division_id'] }}">
            <input type="hidden" name="location_id" value="{{ $scope['location_id'] }}">
            <input type="hidden" name="profile" value="{{ $scope['profile'] }}">
            <input type="hidden" name="module" value="{{ $scope['module'] }}">

            <div class="permissions-grid">
                @foreach($groups as $group)
                    <section class="permission-group-card">
                        <div class="permission-group-header">
                            <div>
                                <h2>{{ $group['label'] }}</h2>
                                @if($group['description'])
                                    <p>{{ $group['description'] }}</p>
                                @endif
                            </div>
                        </div>

                        <div class="permission-list">
                            @foreach($group['permissions'] as $permission)
                                <label class="permission-row">
                                    <div>
                                        <strong>{{ $permission['label'] }}</strong>
                                        <small>
                                            {{ $permission['has_override'] ? 'Personalizada' : 'Padrão do sistema' }}
                                            · padrão {{ $permission['default'] ? 'permitido' : 'bloqueado' }}
                                        </small>
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
                <a href="{{ route('permissions.index') }}" class="permissions-button secondary">Restaurar visualização</a>
                <button type="submit" class="permissions-button primary">
                    <i data-lucide="save"></i>
                    Salvar permissões
                </button>
            </footer>
        </form>
    </div>
@endsection