
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
        >
            <span class="sidebar-icon">
                <i data-lucide="radio-tower"></i>
            </span>
        
            Operações
        </a>

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
        
            <div class="sidebar-submenu" x-show="open" x-collapse>
                <a
                    href="{{ route('workshop.index') }}"
                    class="sidebar-submenu-link {{ request()->routeIs('workshop.index') ? 'active' : '' }}"
                >
                    <i data-lucide="layout-dashboard"></i>
                    Visão geral
                </a>
        
                <a
                    href="{{ route('workshop.tires.index') }}"
                    class="sidebar-submenu-link {{ request()->routeIs('workshop.tires.*') ? 'active' : '' }}"
                >
                    <i data-lucide="circle-dot"></i>
                    Controle de pneus
                </a>
        
                <a
                    href="{{ route('stock.index') }}"
                    class="sidebar-submenu-link {{ request()->routeIs('stock.*') ? 'active' : '' }}"
                >
                    <i data-lucide="boxes"></i>
                    Estoque
                </a>
        
                <a
                    href="{{ route('procedures.index') }}"
                    class="sidebar-submenu-link {{ request()->routeIs('procedures.*') ? 'active' : '' }}"
                >
                    <i data-lucide="clipboard-list"></i>
                    Procedimentos
                </a>
            </div>
        
        </div>

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
        
        {{-- CHECKLISTs --}}
        <a
            href="{{ route('checklists.index') }}"
            class="sidebar-link {{
                request()->routeIs('checklists.*')
                ? 'active'
                : ''
            }}"
        >
        
            <span class="sidebar-icon">
                <i data-lucide="clipboard-check"></i>
            </span>
        
            Checklists
        
        </a>
        
        {{-- ACESSOS --}}
        <a
            href="{{ route('access-control.index') }}"
            class="sidebar-link
            {{
                request()->routeIs('access-control.*')
                ? 'active'
                : ''
            }}"
        >
        
            <i data-lucide="shield"></i>
        
            <span>
                Controle de acessos
            </span>
        
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
