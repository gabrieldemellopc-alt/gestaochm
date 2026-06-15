<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

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

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <link rel="stylesheet" href="{{ asset('css/pages/login.css') }}?v=3">
    </head>
    <body class="auth-guest-page font-sans antialiased">
        <div class="auth-guest-shell min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0">
            <div class="auth-guest-brand">
                <a href="/" aria-label="CHM">
                    <img src="{{ asset('images/logo-chm_.png') }}" alt="CHM">
                </a>
                <span>Plataforma Corporativa</span>
            </div>

            <div class="auth-guest-card w-full sm:max-w-md mt-6 px-6 py-4 overflow-hidden">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
