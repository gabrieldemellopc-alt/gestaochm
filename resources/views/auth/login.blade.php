@extends('layouts.app')

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

        <div class="login-brand">

            <img
                src="{{ asset('images/logo-chm_.png') }}"
                alt="CHM"
                class="login-logo"
            >

            <h2>
            Plataforma Corporativa de Gestão Operacional
            </h2>

            <div class="login-module-badge" style="display:none">
                Módulo ativo: Gestão de Frota
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

                <h1>
                    Entrar
                </h1>

                <span>
                    Acesse o painel operacional
                </span>

            </div>
            @if ($errors->any())
            
                <div class="login-alert-error">
            
                    <strong>
                        Não foi possível entrar
                    </strong>
            
                    <span>
                        {{ $errors->first() }}
                    </span>
            
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

        </form>

    </div>

</div>

@endsection