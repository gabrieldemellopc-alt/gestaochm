<x-app-layout>
    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/pages/profile.css') }}?v=2">
    @endpush

    <x-slot name="header">
        <h2 class="profile-page-title font-semibold text-xl leading-tight">
            Segurança da conta
        </h2>
    </x-slot>

    <div class="profile-page py-12">
        <div class="profile-page-shell max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="profile-section-card profile-password-card p-4 sm:p-8">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
