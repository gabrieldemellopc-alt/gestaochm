@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/pages/portal.css') }}?v=2">
@endpush

@php

    $pageTitle = 'Portal Corporativo';

    $pageSubtitle = 'Selecione uma divisão operacional';

@endphp

@section('content')

<div class="portal-page">

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

    </div>

</div>

@endsection
