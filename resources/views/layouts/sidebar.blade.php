

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



            <i class="bi bi-x-lg"></i>



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
        @if(app(\App\Services\Permissions\ProfilePermissionService::class)->allows(auth()->user(), 'navigation.dashboard'))
        <a
            href="{{ route('dashboard') }}"
            title="Dashboard"
            class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
        >
            <span class="sidebar-icon">
                <i class="bi bi-grid-1x2"></i>
            </span>

            <span class="sidebar-link-text">
                Dashboard
            </span>
        </a>
        @endif



        {{-- VEÍCULOS --}}
        @php
            $sidebarPermissionService = app(\App\Services\Permissions\ProfilePermissionService::class);
            $sidebarCurrentUser = auth()->user();
            $sidebarCanPermission = fn (string $permissionKey) => $sidebarCurrentUser
                && $sidebarPermissionService->allows($sidebarCurrentUser, $permissionKey);
            $sidebarCanAccessVehicles = $sidebarCanPermission('navigation.vehicles')
                && $sidebarCanPermission('vehicles.view');
        @endphp
        @if($sidebarCanAccessVehicles)
        <a

            href="{{ route('vehicles.index') }}"
            title="Veículos"

            class="sidebar-link {{

                request()->routeIs('vehicles.*')

                ? 'active'

                : ''

            }}"

        >



            <span class="sidebar-icon">

                <i class="bi bi-truck"></i>

            </span>



            <span class="sidebar-link-text">Veículos</span>




        </a>
        @endif







        @if((userHasProfile('supervisor') || userHasProfile('manager') || userHasProfile('admin')) && $sidebarCanPermission('navigation.fuel'))

        {{-- ABASTECIMENTOS --}}

        <a

            href="{{ route('fuel.tanks.index') }}"
            title="Abastecimentos"

            class="sidebar-link {{

                request()->routeIs('fuel.*')

                ? 'active'

                : ''

            }}"

        >

            <span class="sidebar-icon">

                <i class="bi bi-fuel-pump"></i>

            </span>

            <span class="sidebar-link-text">Abastecimentos</span>

        </a>

        @endif

        



        @php

        $workshopActive =

                request()->routeIs('workshop.*') ||

                request()->routeIs('stock.*') ||

                request()->routeIs('procedures.*');

        @endphp



@php
    $sidebarWorkshopItems = collect([
        ['permission' => 'navigation.workshop', 'route' => 'workshop.index', 'active' => 'workshop.index', 'label' => 'Oficina', 'dropdown_label' => 'Visão geral', 'icon' => 'wrench'],

        ['permission' => 'navigation.tires', 'route' => 'workshop.tires.index', 'active' => 'workshop.tires.*', 'label' => 'Pneus', 'dropdown_label' => 'Pneus', 'icon' => 'record-circle-fill'],

        ['permission' => 'navigation.stock', 'route' => 'stock.index', 'active' => 'stock.*', 'label' => 'Estoque', 'dropdown_label' => 'Estoque', 'icon' => 'boxes'],

        ['permission' => 'navigation.workshop', 'route' => 'procedures.index', 'active' => 'procedures.*', 'label' => 'Procedimentos', 'dropdown_label' => 'Procedimentos', 'icon' => 'clipboard-check'],
])->filter(fn (array $item) => $sidebarCanPermission($item['permission']))->values();
    $sidebarWorkshopIsFlat = $sidebarWorkshopItems->count() <= 5;
@endphp

@if($sidebarWorkshopItems->isNotEmpty())
@if($sidebarWorkshopIsFlat)
    @foreach($sidebarWorkshopItems as $workshopItem)
        <a
            href="{{ route($workshopItem['route']) }}"
            title="{{ $workshopItem['label'] }}"
            class="sidebar-link {{ request()->routeIs($workshopItem['active']) ? 'active' : '' }}"
        >
            <span class="sidebar-icon"><i class="{{ chm_icon($workshopItem['icon']) }}"></i></span>
            <span class="sidebar-link-text">{{ $workshopItem['label'] }}</span>
        </a>
    @endforeach
@else
    <div class="sidebar-group sidebar-workshop-group">

        <button
            type="button"
            id="sidebarWorkshopButton"
            title="Oficina"
            class="sidebar-link sidebar-link-dropdown {{
                $workshopActive ? 'active' : ''
            }}"
            aria-haspopup="true"
            aria-expanded="false"
        >
            <span class="sidebar-icon">
                <i class="bi bi-wrench-adjustable"></i>
            </span>

            <span class="sidebar-link-text">
                Oficina
            </span>

            <span class="sidebar-chevron">
                <i class="bi bi-chevron-down"></i>
            </span>
        </button>

        <div
            id="sidebarWorkshopMenu"
            class="sidebar-workshop-menu"
            hidden
        >
            <div class="sidebar-workshop-menu-title">
                Oficina
            </div>

            @foreach($sidebarWorkshopItems as $workshopItem)
                <a
                    href="{{ route($workshopItem['route']) }}"
                    class="sidebar-workshop-menu-link {{ request()->routeIs($workshopItem['active']) ? 'active' : '' }}"
                >
                    <i class="{{ chm_icon($workshopItem['icon']) }}"></i>
                    <span>{{ $workshopItem['dropdown_label'] }}</span>
                </a>
            @endforeach
        </div>

    </div>
@endif
@endif




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
        

        @if(
            $canViewLocationsMenu
            || $sidebarCanPermission('navigation.reports')
            || $sidebarCanPermission('reports.view')
            || (
                (
                    userHasProfile('manager')
                    || userHasProfile('admin')
                    || userHasProfile('supervisor')
                )
                && (
                    $sidebarCanPermission('navigation.fiscal_documents')
                    || $sidebarCanPermission('fiscal_documents.view')
                )
            )
            || userHasProfile('manager')
            || userHasProfile('admin')
            || auth()->user()?->can('viewAuditLogs')
        )
            <div class="sidebar-section-title">
                GESTÃO
            </div>
        @endif


        @if($canViewLocationsMenu)
            <a
                title="Cidades"

                href="{{ route('locations.index') }}"
                class="sidebar-link {{
                    request()->routeIs('locations.*')
                        ? 'active'
                        : ''
                }}"
            >
                <span class="sidebar-icon">
                    <i class="bi bi-geo-alt"></i>
                </span>

                <span class="sidebar-link-text">Cidades</span>

            </a>
        @endif



        @if($sidebarCanPermission('navigation.reports') || $sidebarCanPermission('reports.view'))
        {{-- RELATÓRIOS --}}

        <a

            href="{{ route('reports.index') }}"
    title="Relatórios"

            class="sidebar-link {{

                request()->routeIs('reports.*')

                ? 'active'

                : ''

            }}"

        >



            <span class="sidebar-icon">

                <i class="bi bi-bar-chart"></i>

            </span>



            <span class="sidebar-link-text">Relatórios</span>




        </a>
        @endif


        @if((userHasProfile('manager') || userHasProfile('admin') || userHasProfile('supervisor')) && ($sidebarCanPermission('navigation.fiscal_documents') || $sidebarCanPermission('fiscal_documents.view')))

            {{-- NOTAS FISCAIS --}}

            <a

                href="{{ route('fiscal-documents.index') }}"
    title="Notas Fiscais"

                class="sidebar-link {{

                    request()->routeIs('fiscal-documents.*')

                    ? 'active'

                    : ''

                }}"

            >

                <span class="sidebar-icon">

                    <i class="bi bi-receipt"></i>

                </span>

                <span class="sidebar-link-text">Notas Fiscais</span>


            </a>

        @endif




        @if(userHasProfile('manager') || userHasProfile('admin'))
            {{-- CONFIGURAÇÕES --}}
            <a title="Configurações"
                href="{{ route('settings.index') }}"
                class="sidebar-link {{ request()->routeIs('settings.*') ? 'active' : '' }}"
            >
                <span class="sidebar-icon"><i class="bi bi-gear"></i></span>
                <span class="sidebar-link-text">Configurações</span>
            </a>
@endif
        @can('viewAuditLogs')
            @if($sidebarCanPermission('navigation.audit') || $sidebarCanPermission('audit.view'))

            {{-- AUDITORIA --}}

            <a
    title="Auditoria"

                href="{{ route('audit.index') }}"

                class="sidebar-link {{

                    request()->routeIs('audit.*')

                    ? 'active'

                    : ''

                }}"

            >

                <span class="sidebar-icon">

                    <i class="bi bi-fingerprint"></i>

                </span>

                <span class="sidebar-link-text">Auditoria</span>


            </a>

                    @endif
        @endcan





    </nav>



</aside>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const button = document.getElementById('sidebarWorkshopButton');
        const menu = document.getElementById('sidebarWorkshopMenu');

        if (!button || !menu) {
            return;
        }

        /*
         * Move o menu para o body.
         * Isso impede que overflow da sidebar/nav corte o painel.
         */
        document.body.appendChild(menu);

        function sidebarIsCollapsed() {
            return document.documentElement.classList.contains(
                'sidebar-collapsed'
            );
        }

        function positionWorkshopMenu() {
            const rect = button.getBoundingClientRect();

            if (sidebarIsCollapsed()) {
                menu.classList.add('is-flyout');
                menu.classList.remove('is-dropdown');

                menu.style.top = `${rect.top}px`;
                menu.style.left = `${rect.right + 12}px`;
                menu.style.width = '230px';
            } else {
                menu.classList.remove('is-flyout');
                menu.classList.add('is-dropdown');

                const sidebar = document.querySelector('.sidebar');
                const sidebarRect = sidebar
                    ? sidebar.getBoundingClientRect()
                    : rect;

                menu.style.top = `${rect.bottom + 6}px`;
                menu.style.left = `${sidebarRect.left + 12}px`;
                menu.style.width = `${Math.max(
                    sidebarRect.width - 24,
                    220
                )}px`;
            }
        }

        function openWorkshopMenu() {
            positionWorkshopMenu();

            menu.hidden = false;
            menu.classList.add('is-open');
            button.classList.add('menu-open');

            button.setAttribute('aria-expanded', 'true');
        }

        function closeWorkshopMenu() {
            menu.hidden = true;
            menu.classList.remove('is-open');
            button.classList.remove('menu-open');

            button.setAttribute('aria-expanded', 'false');
        }

        function toggleWorkshopMenu(event) {
            event.preventDefault();
            event.stopPropagation();

            if (menu.hidden) {
                openWorkshopMenu();
            } else {
                closeWorkshopMenu();
            }
        }

        button.addEventListener('click', toggleWorkshopMenu);

        menu.addEventListener('click', function (event) {
            event.stopPropagation();
        });

        document.addEventListener('click', function () {
            closeWorkshopMenu();
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeWorkshopMenu();
            }
        });

        window.addEventListener('resize', function () {
            if (!menu.hidden) {
                positionWorkshopMenu();
            }
        });

        window.addEventListener(
            'scroll',
            function () {
                if (!menu.hidden) {
                    positionWorkshopMenu();
                }
            },
            true
        );

        /*
         * Observa a classe sidebar-collapsed.
         * Reposiciona o painel ao retrair ou expandir a sidebar.
         */
        const observer = new MutationObserver(function () {
            closeWorkshopMenu();
        });

        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class']
        });
    });
</script>
