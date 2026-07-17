

<aside class="sidebar">





    {{-- MOBILE HEADER --}}

    <div class="sidebar-mobile-header">



        <div class="sidebar-mobile-brand">



            <img

                src="{{ asset('images/logo-chm_.png') }}"

                alt="CHM"

            >



        </div>



        <button

            class="sidebar-close-button"

            onclick="

                document

                    .querySelector('.sidebar')

                    .classList

                    .remove('mobile-open')

            "

        >



            <i data-lucide="x"></i>



        </button>



    </div>



    {{-- LOGO --}}

    <div class="sidebar-logo">



        <img

            src="{{ asset('images/logo-chm_.png') }}"

            alt="CHM"

        >



    </div>



    {{-- MENU --}}

    <nav class="sidebar-nav">



        <div class="sidebar-section-title">

            OPERACIONAL

        </div>



        {{-- DASHBOARD --}}

        <a

            href="{{ route('dashboard') }}"

            class="sidebar-link {{

                request()->routeIs('dashboard')

                ? 'active'

                : ''

            }}"

        >



            <span class="sidebar-icon">

                <i data-lucide="layout-dashboard"></i>

            </span>



            Dashboard



        </a>



        {{-- VEÍCULOS --}}

        <a

            href="{{ route('vehicles.index') }}"

            class="sidebar-link {{

                request()->routeIs('vehicles.*')

                ? 'active'

                : ''

            }}"

        >



            <span class="sidebar-icon">

                <i data-lucide="truck"></i>

            </span>



            Veículos



        </a>



        {{-- OEPRACIONAL --}}

        <a

            href="{{ route('operations.index') }}"

            class="sidebar-link {{

                request()->routeIs('operations.*')

                ? 'active'

                : ''

            }}"

            style="display:none;pointer-events: none"

        >

            <span class="sidebar-icon">

                <i data-lucide="radio-tower"></i>

            </span>



            Operações

        </a>




        @php
            $sidebarPermissionService = app(\App\Services\Permissions\ProfilePermissionService::class);
            $sidebarCanPermission = function (string $permissionKey) use ($sidebarPermissionService) {
                $sidebarCurrentUser = auth()->user();

                if (! $sidebarCurrentUser) {
                    return false;
                }

                if (userHasProfile('admin') || userHasProfile('manager')) {
                    return true;
                }

                if (! userHasProfile('supervisor')) {
                    return true;
                }

                return $sidebarPermissionService->allows($sidebarCurrentUser, $permissionKey);
            };
        @endphp
        @if((userHasProfile('supervisor') || userHasProfile('manager') || userHasProfile('admin')) && $sidebarCanPermission('navigation.fuel'))

        {{-- ABASTECIMENTOS --}}

        <a

            href="{{ route('fuel.tanks.index') }}"

            class="sidebar-link {{

                request()->routeIs('fuel.*')

                ? 'active'

                : ''

            }}"

        >

            <span class="sidebar-icon">

                <i data-lucide="fuel"></i>

            </span>

            Abastecimentos

        </a>

        @endif

        {{-- PROCEDIMENTOS --}}
        <a

            href="{{ route('procedures.index') }}"

            class="sidebar-link {{

                request()->routeIs('procedures.*')

                ? 'active'

                : ''

            }}"  style="display:none"

        >



            <span class="sidebar-icon">

                <i data-lucide="clipboard-list"></i>

            </span>



            Procedimentos



        </a>



        @php

        $workshopActive =

                request()->routeIs('workshop.*') ||

                request()->routeIs('stock.*') ||

                request()->routeIs('procedures.*');

        @endphp



        @if($sidebarCanPermission('navigation.workshop') || $sidebarCanPermission('navigation.tires') || $sidebarCanPermission('navigation.stock'))
        <div class="sidebar-group {{ $workshopActive ? 'open active' : '' }}" x-data="{ open: {{ $workshopActive ? 'true' : 'false' }} }">

            <button
                type="button"
                class="sidebar-link sidebar-link-dropdown {{ $workshopActive ? 'active' : '' }}"
                @click="open = !open"
            >
                <span class="sidebar-icon">
                    <i data-lucide="wrench"></i>
                </span>

                <span class="sidebar-link-text">
                    Oficina
                </span>

                <span class="sidebar-chevron" :class="{ 'rotate': open }">
                    <i data-lucide="chevron-down"></i>
                </span>
            </button>

            <div class="sidebar-submenu" x-show="open" x-collapse x-cloak>
                @if($sidebarCanPermission('navigation.workshop'))
                    <a
                        href="{{ route('workshop.index') }}"
                        class="sidebar-submenu-link {{ request()->routeIs('workshop.index') ? 'active' : '' }}"
                    >
                        <i data-lucide="layout-dashboard"></i>
                        Visão geral
                    </a>
                @endif

                @if($sidebarCanPermission('navigation.tires'))
                    <a
                        href="{{ route('workshop.tires.index') }}"
                        class="sidebar-submenu-link {{ request()->routeIs('workshop.tires.*') ? 'active' : '' }}"
                    >
                        <i data-lucide="circle-dot"></i>
                        Controle de pneus
                    </a>
                @endif

                @if($sidebarCanPermission('navigation.stock'))
                    <a
                        href="{{ route('stock.index') }}"
                        class="sidebar-submenu-link {{ request()->routeIs('stock.*') ? 'active' : '' }}"
                    >
                        <i data-lucide="boxes"></i>
                        Estoque
                    </a>
                @endif

                @if($sidebarCanPermission('navigation.workshop'))
                    <a
                        href="{{ route('procedures.index') }}"
                        class="sidebar-submenu-link {{ request()->routeIs('procedures.*') ? 'active' : '' }}"
                    >
                        <i data-lucide="clipboard-list"></i>
                        Procedimentos
                    </a>
                @endif
            </div>

        </div>
        @endif


        {{-- HISTÓRICO --}}

        <a

            href="#"

            class="sidebar-link {{

                request()->routeIs('maintenances.*')

                ? 'active'

                : ''

            }}"             style="display:none"



        >



            <span class="sidebar-icon">

                <i data-lucide="history"></i>

            </span>



            Histórico



        </a>



        {{-- ESTOQUE --}}

        <a

            href="{{ route('stock.index') }}"

            class="sidebar-link {{

                request()->routeIs('stock.*')

                ? 'active'

                : ''

            }}"  style="display:none"

        >



            <span class="sidebar-icon">

                <i data-lucide="boxes"></i>

            </span>



            Estoque



        </a>



        <div class="sidebar-section-title">

            GESTÃO

        </div>



        {{-- ALERTAS --}}

        <a

            href="#"

            class="sidebar-link {{

                request()->routeIs('alerts.*')

                ? 'active'

                : ''

            }}"

            style="display:none"

        >



            <span class="sidebar-icon">

                <i data-lucide="triangle-alert"></i>

            </span>



            Alertas



        </a>



        {{-- CIDADES --}}
        @php
            $sidebarUser = auth()->user();
            $sidebarDivisionId = session('active_division_id');

            $sidebarIsAdmin = false;
            $sidebarHasGlobalLocationAccess = false;
            $sidebarLocationCount = 0;

            if ($sidebarUser && $sidebarDivisionId) {
                $sidebarAccessQuery = $sidebarUser
                    ->divisionAccesses()
                    ->where('tenant_id', $sidebarUser->tenant_id)
                    ->where('division_id', $sidebarDivisionId)
                    ->where('module', 'fleet')
                    ->where('active', true);

                $sidebarIsAdmin = (clone $sidebarAccessQuery)
                    ->where('profile', 'admin')
                    ->exists();

                $sidebarHasGlobalLocationAccess = (clone $sidebarAccessQuery)
                    ->whereNull('location_id')
                    ->exists();

                $sidebarLocationCount = (clone $sidebarAccessQuery)
                    ->whereNotNull('location_id')
                    ->distinct()
                    ->count('location_id');
            }

            $canViewLocationsMenu =
                $sidebarIsAdmin
                || $sidebarHasGlobalLocationAccess
                || $sidebarLocationCount > 1;
        @endphp

        @if($canViewLocationsMenu)
            <a
                href="{{ route('locations.index') }}"
                class="sidebar-link {{
                    request()->routeIs('locations.*')
                        ? 'active'
                        : ''
                }}"
            >
                <span class="sidebar-icon">
                    <i data-lucide="map-pin"></i>
                </span>

                Cidades
            </a>
        @endif



        @if($sidebarCanPermission('navigation.reports'))
        {{-- RELATÓRIOS --}}

        <a

            href="{{ route('reports.index') }}"

            class="sidebar-link {{

                request()->routeIs('reports.*')

                ? 'active'

                : ''

            }}"

        >



            <span class="sidebar-icon">

                <i data-lucide="bar-chart-3"></i>

            </span>



            Relatórios



        </a>
        @endif


        @if(userHasProfile('manager') || userHasProfile('admin'))

            {{-- NOTAS FISCAIS --}}

            <a

                href="{{ route('fiscal-documents.index') }}"

                class="sidebar-link {{

                    request()->routeIs('fiscal-documents.*')

                    ? 'active'

                    : ''

                }}"

            >

                <span class="sidebar-icon">

                    <i data-lucide="receipt-text"></i>

                </span>

                Notas Fiscais

            </a>

        @endif




        @if(userHasProfile('manager') || userHasProfile('admin'))

            {{-- PERMISSÕES --}}

            <a
                href="{{ route('permissions.index') }}"
                class="sidebar-link {{ request()->routeIs('permissions.*') ? 'active' : '' }}"
            >
                <span class="sidebar-icon">
                    <i data-lucide="shield-check"></i>
                </span>
                Permissões
            </a>

        @endif
        @can('viewAuditLogs')

            {{-- AUDITORIA --}}

            <a

                href="{{ route('audit.index') }}"

                class="sidebar-link {{

                    request()->routeIs('audit.*')

                    ? 'active'

                    : ''

                }}"

            >

                <span class="sidebar-icon">

                    <i data-lucide="fingerprint"></i>

                </span>

                Auditoria

            </a>

        @endcan



        {{-- CHECKLISTs --}}

        <a

            href="{{ route('checklists.index') }}"

            class="sidebar-link {{

                request()->routeIs('checklists.*')

                ? 'active'

                : ''

            }}"

            style="display:none;pointer-events: none"
        >



            <span class="sidebar-icon">

                <i data-lucide="clipboard-check"></i>

            </span>



            Checklists



        </a>

        {{-- CONFIGURAÇÕES --}}

        <a

            href="#"

            class="sidebar-link {{

                request()->routeIs('settings.*')

                ? 'active'

                : ''

            }}"

            style="display:none"

        >



            <span class="sidebar-icon">

                <i data-lucide="settings"></i>

            </span>



            Configurações



        </a>



    </nav>



</aside>
