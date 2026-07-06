@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/pages/portal.css') }}?v=4">
@if($canManageAccessControl ?? false)
    <link rel="stylesheet" href="{{ asset('css/access-control.css') }}?v=2">
@endif
@endpush

@php
    $pageTitle = 'Portal Corporativo';
    $pageSubtitle = 'Ambiente administrativo e operacional CHM';
    $activePortalTab = $activePortalTab ?? 'divisions';
@endphp

@section('content')

<div class="portal-page portal-admin-tabs-page">
    <header class="portal-header portal-compact-header">
        <div>
            <span class="portal-eyebrow">Ambiente corporativo CHM</span>
            <h1>Portal Corporativo</h1>
            <p>Escolha uma divis&atilde;o operacional ou acesse ferramentas administrativas do ambiente.</p>
        </div>
        <div class="portal-header-mark" aria-hidden="true">
            <i data-lucide="building-2"></i>
        </div>
    </header>

    <nav class="portal-tabs" aria-label="Se&ccedil;&otilde;es do Portal Corporativo">
        <a
            href="{{ route('portal') }}"
            class="portal-tab {{ $activePortalTab === 'divisions' ? 'active' : '' }}"
            aria-current="{{ $activePortalTab === 'divisions' ? 'page' : 'false' }}"
        >
            <i data-lucide="layout-grid"></i>
            <span>Divis&otilde;es</span>
        </a>

        @if($canManageAccessControl ?? false)
            <a
                href="{{ route('portal', ['tab' => 'access-control']) }}"
                class="portal-tab {{ $activePortalTab === 'access-control' ? 'active' : '' }}"
                aria-current="{{ $activePortalTab === 'access-control' ? 'page' : 'false' }}"
            >
                <i data-lucide="shield-check"></i>
                <span>Controle de acessos</span>
            </a>
        @endif
    </nav>

    @if($activePortalTab === 'access-control' && ($canManageAccessControl ?? false))
        <section class="portal-access-panel" aria-labelledby="portalAccessTitle">
            <div class="portal-section-heading portal-access-heading">
                <div>
                    <span>Administra&ccedil;&atilde;o do ambiente</span>
                    <h2 id="portalAccessTitle">Gest&atilde;o de acessos</h2>
                    <p>Gerencie usu&aacute;rios, perfis e permiss&otilde;es do ambiente corporativo sem entrar em uma divis&atilde;o operacional.</p>
                </div>

                <button
                    type="button"
                    class="chm-page-add-btn portal-access-add-btn"
                    onclick="openNewUserModal()"
                >
                    <i data-lucide="plus"></i>
                    <span>Novo usu&aacute;rio</span>
                </button>
            </div>

            @include('access-control.partials.panel', array_merge($accessControlPanelData ?? [], [
                'portalEmbedded' => true,
            ]))
        </section>
    @else
        <section class="portal-divisions-panel" aria-labelledby="portalDivisionsTitle">
            <div class="portal-section-heading">
                <div>
                    <span>Divis&otilde;es</span>
                    <h2 id="portalDivisionsTitle">Selecione uma divis&atilde;o</h2>
                    <p>Acesse a estrutura operacional dispon&iacute;vel para o seu perfil.</p>
                </div>
                <small>
                    {{ count($availableDivisionIds ?? []) }} dispon&iacute;vel{{ count($availableDivisionIds ?? []) === 1 ? '' : 's' }}
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
                            <span class="portal-coming-soon">Indispon&iacute;vel</span>
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
                                    @if($isAvailable)
                                        Divis&atilde;o operacional
                                    @else
                                        Sem acesso para este perfil
                                    @endif
                                </span>
                            </div>
                        </div>

                        @if($isAvailable)
                            <a href="{{ route('division.modules', $division) }}" class="portal-button">
                                Acessar grupo
                            </a>
                        @else
                            <div class="portal-button portal-button-disabled">
                                Indispon&iacute;vel
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>

@endsection
