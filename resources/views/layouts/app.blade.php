<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>CHM</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}?v={{ time() }}">
    <!--<link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ time() }}">-->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
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

    <script src="https://unpkg.com/lucide@latest"></script>

    <script>
        lucide.createIcons();
    </script>
    @stack('scripts')

</body>
</html>