@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/pages/login.css') }}?v=4">
@endpush

@section('content')
<style>
    .login-alert-error {
        margin-bottom: 18px;
        padding: 14px 16px;
        border-radius: 14px;
        background: rgba(239, 68, 68, .12);
        border: 1px solid rgba(239, 68, 68, .26);
        color: #fecaca;
    }

    .login-alert-error strong {
        display: block;
        font-size: 13px;
        font-weight: 850;
        color: #fca5a5;
    }

    .login-alert-error span {
        display: block;
        margin-top: 4px;
        font-size: 13px;
        line-height: 1.4;
        color: #fecaca;
    }

    .login-error {
        display: block;
        margin-top: 7px;
        color: #fca5a5;
        font-size: 12px;
        font-weight: 700;
    }

    .login-card span {
        margin-bottom: 0px !important;
    }
</style>

<div class="login-page">

    <div class="login-side">

        <div class="login-side-grid" aria-hidden="true"></div>

        <div class="login-brand">

            <img
                src="{{ asset('images/logo-chm_.png') }}"
                alt="Grupo CHM"
                class="login-logo"
            >

            <h2>
                Plataforma Corporativa de Gestão Operacional
            </h2>

            <p class="login-brand-copy">
                Gestão centralizada das operações, frota, manutenção e ativos das empresas do Grupo CHM.
            </p>

            <div class="login-group-block">
              

                <div class="login-subgroup-logos" aria-label="Empresas do Grupo CHM">
                    <div class="login-subgroup-card">
                        <img
                            src="{{ asset('images/logo-aksa_.png') }}"
                            alt="AKSA"
                            class="login-subgroup-logo logo-aksa"
                        >
                    </div>
                
                    <div class="login-subgroup-card">
                        <img
                            src="{{ asset('images/logo-bsm_.png') }}"
                            alt="BSM Locações"
                            class="login-subgroup-logo logo-bsm"
                        >
                    </div>
                </div>
            </div>


        </div>

    </div>

    <div class="login-panel">

        <form
            method="POST"
            action="{{ route('login') }}"
            class="login-card"
        >

            @csrf

            <div class="login-header">
                <h1>Entrar</h1>
                <span>Acesse o painel operacional</span>
            </div>

            <p class="login-header-copy">
                Utilize suas credenciais corporativas para continuar.
            </p>

            @if ($errors->any())
                <div class="login-alert-error">
                    <strong>Não foi possível entrar</strong>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <div class="form-group">
                <label>Email</label>

                <input
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                >
            </div>

            <div class="form-group">
                <label>Senha</label>

                <input
                    type="password"
                    name="password"
                    required
                >

                @error('password')
                    <span class="login-error">
                        {{ $message }}
                    </span>
                @enderror
            </div>

            <button
                type="submit"
                class="login-button"
            >
                Entrar no Sistema
            </button>

            <div class="login-support-note">
                Problemas para acessar? Procure o administrador responsável pela sua unidade.
            </div>

        </form>

    </div>

</div>
@endsection