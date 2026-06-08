@php

    $activeDivision = null;

    if(
    
        session('active_division_id')
    

    ) {

        $activeDivision = \App\Models\Division::find(
            session('active_division_id')
        );

    }
@endphp

<header class="topbar">

    @if(($pageTitle ?? null) !== 'Portal Corporativo')

        {{-- MOBILE MENU --}}
        <button
            class="mobile-menu-button"
            onclick="
                document
                    .querySelector('.sidebar')
                    .classList
                    .add('mobile-open')
            "
        >
    
            <i data-lucide="menu"></i>
    
        </button>
    
    @endif

    {{-- CONTEXTO --}}
    <div class="topbar-context">

        <div class="
            topbar-brand
            {{
                ($pageTitle ?? null) !== 'Portal Corporativo' 
                    ? ($activeDivision->logo_theme ?? 'dark')
                    : ''
            }}
        ">

            <img
                src="{{
                    $activeDivision && $activeDivision->logo
                        ? asset('images/' . $activeDivision->logo)
                        : asset('images/logo-chm_.png')
                }}"
                alt="CHM"
            >

        </div>

        <div class="topbar-title-group">

            <h1>

                {{
                    $activeDivision->name
                    ?? ($pageTitle ?? 'CHM')
                }}

            </h1>

            <span>

                {{ $pageSubtitle ?? 'Sistema Corporativo' }}

            </span>

        </div>

    </div>

    {{-- USER --}}
    <div class="topbar-right">

        <div
            class="topbar-user-wrapper"
            x-data="{ open: false }"
        >

            <button
                class="topbar-user"
                @click="open = !open"
            >

                <div class="user-avatar">

                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}

                </div>

                <div class="user-info desktop-only">

                    <strong>
                        {{ auth()->user()->name }}
                    </strong>

                    <small>

                        {{
                            $activeDivision
                                ? $activeDivision->name
                                : 'Operador'
                        }}

                    </small>

                </div>

                <i
                    data-lucide="chevron-down"
                    class="user-chevron"
                ></i>

            </button>

            {{-- DROPDOWN --}}
            <div
                class="user-dropdown"
                x-show="open"
                @click.outside="open = false"
                x-transition
                x-cloak
            >

                {{-- MOBILE INFO --}}
                <div class="dropdown-user mobile-only">

                    <strong>
                        {{ auth()->user()->name }}
                    </strong>

                    <span>

                        {{
                            $activeDivision
                                ? $activeDivision->name
                                : 'Operador'
                        }}

                    </span>

                </div>

                <div class="dropdown-divider mobile-only"></div>

                {{-- TROCAR DIVISÃO --}}
                <a href="{{ route('division.leave') }}">
                    <i data-lucide="building-2"></i>

                    Trocar divisão

                </a>

                {{-- PERFIL --}}
                <a href="#">

                    <i data-lucide="user"></i>

                    Meu Perfil

                </a>

                {{-- SEGURANÇA --}}
                <a href="#">

                    <i data-lucide="shield"></i>

                    Segurança

                </a>

                {{-- CONFIG --}}
                <a href="#">

                    <i data-lucide="settings"></i>

                    Configurações

                </a>

                <div class="dropdown-divider"></div>

                {{-- LOGOUT --}}
                <form
                    method="POST"
                    action="{{ route('logout') }}"
                >

                    @csrf

                    <button type="submit">

                        <i data-lucide="log-out"></i>

                        Sair do sistema

                    </button>

                </form>

            </div>

        </div>

    </div>

</header>