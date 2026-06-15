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
        <small>1 disponível</small>
    </div>

    <div class="portal-grid">

        @foreach($divisions as $division)
            <div class="
                portal-card
                division-theme-{{ $division->logo_theme }}
            ">

                <div class="portal-card-top">

                    <div class="portal-icon {{ $division->logo_theme }}">

                        <img
                            src="{{ $division->logo ? asset('images/' . $division->logo) : asset('images/logo-chm.png') }}"
                            alt="{{ $division->name }}"
                        >

                    </div>

                    <div>

                        <h3>
                            {{ $division->name }}
                        </h3>

                        <span>
                            Divisão operacional
                        </span>

                    </div>

                </div>

                <a
                    href="{{ route('division.modules', $division) }}"
                    class="portal-button"
                >

                    Acessar grupo

                </a>

            </div>

        @endforeach

        @foreach([
            ['name' => 'BSM', 'description' => 'Divisão operacional em preparação'],
            ['name' => 'PGS', 'description' => 'Divisão operacional em preparação'],
            ['name' => 'MG', 'description' => 'Divisão operacional em preparação'],
        ] as $placeholderDivision)
            <div class="portal-card portal-card-disabled" aria-disabled="true">
                <span class="portal-coming-soon">Em breve</span>
                <div class="portal-card-top">
                    <div class="portal-icon portal-placeholder-icon">
                        <i data-lucide="building"></i>
                    </div>
                    <div>
                        <h3>{{ $placeholderDivision['name'] }}</h3>
                        <span>{{ $placeholderDivision['description'] }}</span>
                    </div>
                </div>
                <div class="portal-button portal-button-disabled">Indisponível</div>
            </div>
        @endforeach

    </div>

</div>

@endsection
