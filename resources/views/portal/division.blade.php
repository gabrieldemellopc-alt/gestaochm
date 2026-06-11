@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/pages/portal.css') }}?v=2">
@endpush

@php

    $pageTitle = $division->name;
    $pageSubtitle = 'Módulos operacionais';
@endphp

@section('content')

<div class="portal-page">

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

    </div>

</div>

@endsection
