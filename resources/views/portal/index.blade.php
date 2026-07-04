@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/pages/portal.css') }}?v=3">
@endpush

@php


    $pageTitle = 'Portal Corporativo';



    $pageSubtitle = 'Selecione uma divisão operacional';



@endphp



@section('content')



<div class="portal-page">
    <header class="portal-header">
        <div>
            <span class="portal-eyebrow">Ambiente corporativo CHM</span>
            <h1>Selecione a divisão</h1>
            <p>Acesse a estrutura operacional disponível para o seu perfil.</p>
        </div>
        <div class="portal-header-mark" aria-hidden="true">
            <i data-lucide="building-2"></i>
        </div>
    </header>

    <div class="portal-section-heading">
        <div>
            <span>Divisões</span>
            <h2>Grupos operacionais</h2>
        </div>
        <small>
            {{ count($availableDivisionIds ?? []) }} disponível{{ count($availableDivisionIds ?? []) === 1 ? '' : 's' }}
        </small>    
    </div>


    <div class="portal-grid">
    
        @foreach($divisions as $division)
    
            @php
                $baseLogo = $division->logo ?: 'logo-chm.png';
            
                $logoForDarkBg = preg_replace('/(\.[a-zA-Z0-9]+)$/', '_$1', $baseLogo);
            
                $shouldUseLightLogo = ($division->logo_theme ?? 'dark') === 'dark';
            
                $resolvedLogo = $baseLogo;
            
                if ($shouldUseLightLogo && file_exists(public_path('images/' . $logoForDarkBg))) {
                    $resolvedLogo = $logoForDarkBg;
                }

                $isAvailable = in_array($division->id, $availableDivisionIds ?? []);
                
            @endphp
    
            <div class="
                portal-card
                division-theme-{{ $division->logo_theme }}
                {{ ! $isAvailable ? 'portal-card-disabled' : '' }}
            ">
    
                @unless($isAvailable)
                    <span class="portal-coming-soon">Indisponível</span>
                @endunless
    
                <div class="portal-card-top">
    
                    <div class="portal-icon {{ $division->logo_theme }}">
                        <img
                            src="{{ asset('images/' . $resolvedLogo) }}"
                            alt="{{ $division->name }}"
                        >
                    </div>
    
                    <div>
                        <h3>{{ $division->name }}</h3>
    
                        <span>
                            {{ $isAvailable ? 'Divisão operacional' : 'Sem acesso para este perfil' }}
                        </span>
                    </div>
    
                </div>
    
                @if($isAvailable)
                    <a
                        href="{{ route('division.modules', $division) }}"
                        class="portal-button"
                    >
                        Acessar grupo
                    </a>
                @else
                    <div class="portal-button portal-button-disabled">
                        Indisponível
                    </div>
                @endif
    
            </div>
    
        @endforeach
    
    </div>

    @if($canManageAccessControl ?? false)
        <section class="portal-admin-section" aria-labelledby="portalAdminTitle">
            <div class="portal-section-heading">
                <div>
                    <span>AdministraÃ§Ã£o</span>
                    <h2 id="portalAdminTitle">AdministraÃ§Ã£o do ambiente</h2>
                </div>
            </div>

            <div class="portal-admin-grid">
                <a
                    href="{{ route('access-control.index') }}"
                    class="portal-card portal-admin-card"
                >
                    <div class="portal-card-top">
                        <div class="portal-icon">
                            <i data-lucide="shield-check"></i>
                        </div>

                        <div>
                            <h3>Controle de acessos</h3>
                            <span>Gerencie usuÃ¡rios, perfis e acessos Ã s divisÃµes.</span>
                        </div>
                    </div>

                    <div class="portal-button">
                        Abrir administraÃ§Ã£o
                    </div>
                </a>
            </div>
        </section>
    @endif


</div>



@endsection
