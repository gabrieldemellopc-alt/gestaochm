<x-app-layout>
    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/pages/profile.css') }}?v=2">
    @endpush

    <x-slot name="header">
        <h2 class="profile-page-title font-semibold text-xl leading-tight">
            Configurações pessoais
        </h2>
    </x-slot>

    <div class="profile-page py-12">
        <div class="profile-page-shell max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="profile-section-card p-4 sm:p-8">
                <div class="max-w-xl">
                    <section>
                        <header>
                            <h2 class="text-lg font-medium text-gray-900">
                                Preferências disponíveis
                            </h2>

                            <p class="mt-1 text-sm text-gray-600">
                                Ajustes pessoais que já funcionam sem alterar permissões, divisões ou dados corporativos.
                            </p>
                        </header>

                        <div class="mt-6 space-y-4">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900">
                                    Tema visual
                                </h3>

                                <p class="mt-1 text-sm text-gray-600">
                                    O tema claro/escuro corporativo é controlado pelo botão da barra superior e salvo neste navegador.
                                </p>
                            </div>

                            <div>
                                <h3 class="text-base font-semibold text-gray-900">
                                    Acessos e permissões
                                </h3>

                                <p class="mt-1 text-sm text-gray-600">
                                    Alterações de perfil, divisão, unidade ou permissões continuam restritas ao Controle de Acessos.
                                </p>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
