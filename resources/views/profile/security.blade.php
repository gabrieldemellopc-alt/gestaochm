@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pages/profile.css') }}?v=4">
@endpush

@section('content')
<div class="profile-page profile-page--security">
    <div class="profile-page-shell">
        <header class="profile-hero">
            <div class="profile-hero-icon profile-hero-icon--secure" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z" />
                    <path d="m9 12 2 2 4-4" />
                </svg>
            </div>

            <div class="profile-hero-copy">
                <span class="profile-eyebrow">Prote&ccedil;&atilde;o da conta</span>
                <h1>Seguran&ccedil;a</h1>
                <p>Atualize sua senha para manter sua conta protegida.</p>
            </div>
        </header>

        <div class="profile-layout profile-layout--security">
            <section class="profile-card profile-card--main">
                @include('profile.partials.update-password-form')
            </section>

            <aside class="profile-card profile-guidance-card">
                <div class="profile-card-header">
                    <span class="profile-card-kicker">Boas pr&aacute;ticas</span>
                    <h2>Senha segura</h2>
                </div>

                <ul class="profile-check-list">
                    <li>Use uma senha longa, com letras, n&uacute;meros e caracteres especiais.</li>
                    <li>N&atilde;o reutilize a mesma senha de outros sistemas.</li>
                    <li>Confirme a senha atual para autorizar a altera&ccedil;&atilde;o.</li>
                </ul>
            </aside>
        </div>
    </div>
</div>
@endsection