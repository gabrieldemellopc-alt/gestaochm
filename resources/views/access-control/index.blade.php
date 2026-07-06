@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/access-control.css') }}?v=2">
@endpush

@section('content')
    @include('access-control.partials.panel')
@endsection
