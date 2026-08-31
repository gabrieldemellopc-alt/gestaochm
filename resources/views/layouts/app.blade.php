<!DOCTYPE html>

<html lang="pt-BR" data-chm-theme="dark">


<head>



    <script>
        (function () {
            var allowedThemes = ['dark', 'corporate-light'];
            var theme = 'dark';

            try {
                var savedTheme = localStorage.getItem('chm-theme');

                if (allowedThemes.indexOf(savedTheme) !== -1) {
                    theme = savedTheme;
                }
            } catch (error) {
                theme = 'dark';
            }

            document.documentElement.setAttribute('data-chm-theme', theme);
        })();
    </script>
    <script>
        (function () {
            try {
                if (
                    window.innerWidth >= 1025
                    &&
                    localStorage.getItem('chm-sidebar-collapsed') === '1'
                ) {
                    document.documentElement.classList.add(
                        'sidebar-collapsed'
                    );
                }
            } catch (error) {
                console.warn(
                    'Não foi possível restaurar o estado da sidebar.',
                    error
                );
            }
        })();
    </script>
    <meta charset="UTF-8">


    <meta name="viewport" content="width=device-width, initial-scale=1.0">



    <title>CHM</title>



    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link rel="stylesheet" href="{{ asset('css/custom.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('css/chm-themes.css') }}?v=1">
    <!--<link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ time() }}">-->

    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('favicon.png') }}">    

    @stack('styles')

</head>



<body class="chm-body">



    @auth



        @if(

            request()->routeIs('portal')

            || request()->routeIs('division.*')

        )

            <div class="portal-layout">



                @include('layouts.topbar')



                <main class="portal-main">



                    <div class="portal-content">

                    

                        @include('layouts.flash-messages')

                    

                        @yield('content')

                    

                    </div>



                </main>



            </div>



        @else



            @include('layouts.sidebar')

            <div

                class="sidebar-overlay"

                onclick="

                    document

                        .querySelector('.sidebar')

                        .classList

                        .remove('mobile-open')

                "

            ></div>

            <main class="chm-main">



                @include('layouts.topbar')



                <div class="chm-content">

                

                    @include('layouts.flash-messages')

                

                    @yield('content')

                

                </div>



            </main>



        @endif



    @else



        <main class="guest-main">



            @yield('content')



        </main>



    @endauth

    <script src="{{ asset('js/chm-theme.js') }}?v=1"></script>

    @stack('scripts')

<script>
document.addEventListener('DOMContentLoaded', function () {
    const storageKey = 'chm-sidebar-collapsed';
    const root = document.documentElement;

    function isDesktop() {
        return window.matchMedia('(min-width: 1025px)').matches;
    }

    function isCollapsed() {
        return root.classList.contains('sidebar-collapsed');
    }

    function updateButtons() {
        const collapsed = isCollapsed();

        document
            .querySelectorAll('[data-sidebar-collapse]')
            .forEach(function (button) {
                const label = collapsed
                    ? 'Expandir menu lateral'
                    : 'Recolher menu lateral';

                button.setAttribute('aria-label', label);
                button.setAttribute('title', label);
                button.setAttribute(
                    'aria-pressed',
                    collapsed ? 'true' : 'false'
                );

                const icon = button.querySelector(
                    '[data-sidebar-collapse-icon]'
                );

                if (icon) {
                    icon.className = collapsed ? 'bi bi-layout-sidebar-inset' : 'bi bi-layout-sidebar-inset';
                }
            });
    }

    function setSidebarCollapsed(collapsed, persist = true) {
        if (!isDesktop()) {
            root.classList.remove('sidebar-collapsed');
            updateButtons();
            return;
        }

        root.classList.toggle(
            'sidebar-collapsed',
            collapsed
        );

        if (persist) {
            try {
                localStorage.setItem(
                    storageKey,
                    collapsed ? '1' : '0'
                );
            } catch (error) {
                console.warn(
                    'Não foi possível salvar o estado da sidebar.',
                    error
                );
            }
        }

        updateButtons();
    }

    document
        .querySelectorAll('[data-sidebar-collapse]')
        .forEach(function (button) {
            button.addEventListener('click', function () {
                setSidebarCollapsed(!isCollapsed());
            });
        });

    window.addEventListener('resize', function () {
        if (!isDesktop()) {
            root.classList.remove('sidebar-collapsed');
            updateButtons();
            return;
        }

        let savedCollapsed = false;

        try {
            savedCollapsed =
                localStorage.getItem(storageKey) === '1';
        } catch (error) {
            savedCollapsed = false;
        }

        setSidebarCollapsed(savedCollapsed, false);
    });

    updateButtons();
});
</script>
@if(request()->routeIs('vehicles.edit'))
    @include('vehicle.partials.transfer-ui')
@endif
</body>
</html>
