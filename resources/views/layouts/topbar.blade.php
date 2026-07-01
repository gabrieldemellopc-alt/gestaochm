{{-- Active context is shared by AppServiceProvider.


    $activeDivision = null;



    if(

    

        session('active_division_id')

    



    ) {



        $activeDivision = \App\Models\Division::find(

            session('active_division_id')

        );



    }

--}}

@php
    $topbarLogo = 'logo-chm_.png';

    if ($activeDivision && $activeDivision->logo) {
        $baseLogo = $activeDivision->logo;

        $logoForDarkBg = preg_replace(
            '/(\.[a-zA-Z0-9]+)$/',
            '_$1',
            $baseLogo
        );

        $shouldUseLightLogo = ($activeDivision->logo_theme ?? 'dark') === 'dark';

        $topbarLogo = $baseLogo;

        if (
            $shouldUseLightLogo
            && file_exists(public_path('images/' . $logoForDarkBg))
        ) {
            $topbarLogo = $logoForDarkBg;
        }
    }

    $userDivisionCount = auth()
        ->user()
        ->divisionAccesses()
        ->where('active', true)
        ->where('tenant_id', auth()->user()->tenant_id)
        ->distinct('division_id')
        ->count('division_id');

    $canSwitchDivision = $userDivisionCount > 1;
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
                src="{{ asset('images/' . $topbarLogo) }}"
                alt="{{ $activeDivision->name ?? 'Grupo CHM' }}"
            >



        </div>



        <div class="topbar-title-group">


            <h1 class="division-wordmark">
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

        @if($activeDivision)
            <form
                method="POST"
                action="{{ route('active-location.update') }}"
                class="topbar-location-selector"
            >
                @csrf

                <label for="topbarActiveLocation">
                    <i data-lucide="map-pin"></i>
                    <span>Unidade</span>
                </label>

                <select
                    id="topbarActiveLocation"
                    name="location_id"
                    aria-label="Unidade ativa"
                    @if($availableLocations->count() <= 1) disabled @endif
                    onchange="this.form.submit()"
                >
                    @forelse($availableLocations as $location)
                        <option
                            value="{{ $location->id }}"
                            @selected($activeLocation?->id === $location->id)
                        >
                            {{ $location->name }}
                        </option>
                    @empty
                        <option>Sem unidade disponível</option>
                    @endforelse
                </select>
            </form>
        @endif


        <button
            type="button"
            class="chm-theme-toggle"
            data-chm-theme-toggle
            aria-label="Ativar modo claro corporativo"
            aria-pressed="false"
            title="Ativar modo claro corporativo"
        >
            <span class="chm-theme-toggle-icon chm-theme-toggle-icon-dark" aria-hidden="true">
                <i data-lucide="moon"></i>
            </span>

            <span class="chm-theme-toggle-icon chm-theme-toggle-icon-light" aria-hidden="true">
                <i data-lucide="sun"></i>
            </span>

            <span class="chm-theme-toggle-label" data-chm-theme-label>
                Escuro
            </span>
        </button>

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
                @if($canSwitchDivision)
                    <a href="{{ route('division.leave') }}">
                        <i data-lucide="building-2"></i>
                        Trocar divisão
                    </a>
                @endif



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
