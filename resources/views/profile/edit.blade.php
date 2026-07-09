@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pages/profile.css') }}?v=4">
@endpush

@section('content')
<div class="profile-page profile-page--account">
    <div class="profile-page-shell">
        <header class="profile-hero">
            <div class="profile-hero-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M20 21a8 8 0 0 0-16 0" />
                    <circle cx="12" cy="8" r="4" />
                </svg>
            </div>

            <div class="profile-hero-copy">
                <span class="profile-eyebrow">Conta pessoal</span>
                <h1>Meu perfil</h1>
                <p>Atualize suas informa&ccedil;&otilde;es pessoais e dados de acesso.</p>
            </div>
        </header>

        <div class="profile-layout profile-layout--profile">
            <section class="profile-card profile-card--main">
                @include('profile.partials.update-profile-information-form')
            </section>

            <aside class="profile-side-stack">
                <section class="profile-card profile-account-summary">
                    <div class="profile-card-header">
                        <span class="profile-card-kicker">Identidade</span>
                        <h2>Dados da conta</h2>
                    </div>

                    <dl class="profile-summary-list">
                        <div>
                            <dt>Nome</dt>
                            <dd>{{ $user->name }}</dd>
                        </div>

                        <div>
                            <dt>E-mail</dt>
                            <dd>{{ $user->email }}</dd>
                        </div>
                    </dl>

                    <p class="profile-note">
                        Perfil de acesso, tenant, divis&atilde;o, unidade e permiss&otilde;es continuam protegidos pelo Controle de Acessos.
                    </p>
                </section>

                <section class="profile-card profile-card--danger">
                    @include('profile.partials.delete-user-form')
                </section>
            </aside>
        </div>
    </div>
</div>
@endsection