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
    $activeAccessProfileLabel = 'Operador';

    if ($activeDivision) {
        $activeLocationId = $activeLocation?->id;
    
        $activeProfiles = auth()
            ->user()
            ->divisionAccesses()
            ->where('active', true)
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('division_id', $activeDivision->id)
            ->where('module', 'fleet')
            ->where(function ($query) use ($activeLocationId) {
                $query->whereNull('location_id');
    
                if ($activeLocationId) {
                    $query->orWhere(
                        'location_id',
                        $activeLocationId
                    );
                }
            })
            ->pluck('profile')
            ->filter()
            ->unique()
            ->map(function ($profile) {
                return match ($profile) {
                    'admin' => 'Administrador',
                    'manager' => 'Gestor',
                    'supervisor' => 'Supervisor',
                    'mechanic' => 'Mecânico',
                    'driver' => 'Motorista',
                    default => ucfirst($profile),
                };
            })
            ->values();
    
        if ($activeProfiles->isNotEmpty()) {
            $activeAccessProfileLabel =
                $activeProfiles->implode(' · ');
        }
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
                x-data="{
                    open: false,
                    selectedId: '{{ $activeLocation?->id }}',
                    selectedName: @js($activeLocation?->name ?? 'Selecionar unidade'),
                    selectLocation(id, name) {
                        this.selectedId = id;
                        this.selectedName = name;
                        this.open = false;
        
                        this.$nextTick(() => {
                            this.$refs.locationInput.value = id;
                            this.$refs.locationForm.submit();
                        });
                    }
                }"
                x-ref="locationForm"
                @click.outside="open = false"
                @keydown.escape.window="open = false"
            >
                @csrf
        
                <input
                    type="hidden"
                    name="location_id"
                    x-ref="locationInput"
                    value="{{ $activeLocation?->id }}"
                >
        
                <button
                    type="button"
                    class="topbar-location-trigger"
                    @click="open = !open"
                    :aria-expanded="open.toString()"
                    aria-haspopup="listbox"
                    @if($availableLocations->count() <= 1) disabled @endif
                >
                    <span class="topbar-location-trigger-label">
                        <i data-lucide="map-pin"></i>
        
                        <span>Unidade</span>
                    </span>
        
                    <strong x-text="selectedName"></strong>
        
                    @if($availableLocations->count() > 1)
                        <i
                            data-lucide="chevron-down"
                            class="topbar-location-chevron"
                            :class="{ 'is-open': open }"
                        ></i>
                    @endif
                </button>
        
                @if($availableLocations->count() > 1)
                    <div
                        class="topbar-location-dropdown"
                        x-show="open"
                        x-transition.origin.top
                        x-cloak
                        role="listbox"
                        aria-label="Selecionar unidade"
                    >
                        <div class="topbar-location-dropdown-header">
                            <span>Trocar unidade</span>
                            <small>{{ $availableLocations->count() }} disponíveis</small>
                        </div>
        
                        <div class="topbar-location-options">
                            @foreach($availableLocations as $location)
                                <button
                                    type="button"
                                    class="topbar-location-option"
                                    @class([
                                        'is-active' => $activeLocation?->id === $location->id,
                                    ])
                                    @click="selectLocation(
                                        '{{ $location->id }}',
                                        @js($location->name)
                                    )"
                                    role="option"
                                    :aria-selected="selectedId == '{{ $location->id }}'"
                                >
                                    <span class="topbar-location-option-marker">
                                        <i data-lucide="map-pin"></i>
                                    </span>
        
                                    <span class="topbar-location-option-copy">
                                        <strong>{{ $location->name }}</strong>
        
                                        @if($activeLocation?->id === $location->id)
                                            <small>Unidade ativa</small>
                                        @else
                                            <small>Clique para selecionar</small>
                                        @endif
                                    </span>
        
                                    <span
                                        class="topbar-location-option-check"
                                        x-show="selectedId == '{{ $location->id }}'"
                                    >
                                        <i data-lucide="check"></i>
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
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
            x-data="{
                open: false,
                toggle() {
                    this.open = ! this.open;

                    if (this.open) {
                        this.$nextTick(() => this.$refs.firstDropdownItem?.focus());
                    }
                }
            }"
            @keydown.escape.window="open = false"

        >



            <button

                type="button"

                class="topbar-user"

                @click="toggle()"

                :aria-expanded="open.toString()"

                aria-haspopup="menu"

            >



                <div class="user-avatar">



                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}



                </div>



                <div class="user-info desktop-only">



                    <strong>

                        {{ auth()->user()->name }}

                    </strong>



                    <small>
                        {{ $activeAccessProfileLabel }}
                    
                        @if($activeDivision)
                            <span aria-hidden="true">·</span>
                            {{ $activeDivision->name }}
                        @endif
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

                role="menu"

                x-transition

                x-cloak

            >



                {{-- MOBILE INFO --}}

                <div class="dropdown-user mobile-only">



                    <strong>

                        {{ auth()->user()->name }}

                    </strong>



                    <span>
                        {{ $activeAccessProfileLabel }}
                    
                        @if($activeDivision)
                            · {{ $activeDivision->name }}
                        @endif
                    </span>



                </div>



                <div class="dropdown-divider mobile-only"></div>



                {{-- TROCAR DIVISÃO --}}
                @if($canSwitchDivision)
                    <a
                        href="{{ route('division.leave') }}"
                        role="menuitem"
                        x-ref="firstDropdownItem"
                    >
                        <i data-lucide="building-2"></i>
                        Trocar divisão
                    </a>
                @endif



                {{-- PERFIL --}}

                <a
                    href="{{ route('profile.edit') }}"
                    role="menuitem"
                    @if(! $canSwitchDivision) x-ref="firstDropdownItem" @endif
                >



                    <i data-lucide="user"></i>



                    Meu Perfil



                </a>



                {{-- SEGURANÇA --}}

                <a
                    href="{{ route('profile.security') }}"
                    role="menuitem"
                >



                    <i data-lucide="shield"></i>



                    Segurança



                </a>



                {{-- CONFIG --}}

                <a
                    href="{{ route('profile.settings') }}"
                    role="menuitem"
                >



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



                    <button type="submit" role="menuitem">



                        <i data-lucide="log-out"></i>



                        Sair do sistema



                    </button>



                </form>



            </div>



        </div>



    </div>



</header>
