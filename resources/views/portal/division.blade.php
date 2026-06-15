@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/pages/portal.css') }}?v=3">
@endpush

@php

    $pageTitle = $division->name;
    $pageSubtitle = 'Módulos operacionais';
@endphp

@section('content')

<div class="portal-page">
    <header class="portal-header portal-division-header">
        <div>
            <a href="{{ route('portal') }}" class="portal-back-link">
                <i data-lucide="arrow-left"></i>
                Voltar ao portal
            </a>
            <span class="portal-eyebrow">Divisão operacional</span>
            <h1>{{ $division->name }}</h1>
            <p>Escolha um módulo para acessar as operações desta divisão.</p>
        </div>
        <div class="portal-header-mark division-mark" aria-hidden="true">
            <i data-lucide="layers-3"></i>
        </div>
    </header>

    <div class="portal-section-heading">
        <div>
            <span>Módulos</span>
            <h2>Soluções disponíveis</h2>
        </div>
        <small>1 disponível</small>
    </div>

    <div class="portal-grid">

        {{-- FROTA --}}
        <div class="portal-card">

            <div class="portal-card-top">

                <div class="portal-icon">

                    <i data-lucide="truck"></i>

                </div>

                <div>

                    <h3>
                        Gestão de Frota
                    </h3>

                    <span>
                        Veículos, preventivas e oficina
                    </span>

                </div>

            </div>

            <a
                href="{{ route('division.enter', $division) }}"
                class="portal-button"
            >

                Acessar módulo

            </a>

        </div>

        @foreach([
            ['name' => 'Almoxarifado', 'description' => 'Gestão de materiais e suprimentos', 'icon' => 'package-open'],
            ['name' => 'Módulo-sem-nome', 'description' => 'Nova solução corporativa em definição', 'icon' => 'panels-top-left'],
            ['name' => 'Módulo-sem-nome', 'description' => 'Nova solução corporativa em definição', 'icon' => 'panels-top-left'],
        ] as $placeholderModule)
            <div class="portal-card portal-card-disabled" aria-disabled="true">
                <span class="portal-coming-soon">Em breve</span>
                <div class="portal-card-top">
                    <div class="portal-icon portal-placeholder-icon">
                        <i data-lucide="{{ $placeholderModule['icon'] }}"></i>
                    </div>
                    <div>
                        <h3>{{ $placeholderModule['name'] }}</h3>
                        <span>{{ $placeholderModule['description'] }}</span>
                    </div>
                </div>
                <div class="portal-button portal-button-disabled">Indisponível</div>
            </div>
        @endforeach

    </div>

</div>

@endsection
