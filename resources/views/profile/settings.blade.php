@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pages/profile.css') }}?v=4">
@endpush

@section('content')
<div class="profile-page profile-page--settings">
    <div class="profile-page-shell">
        <header class="profile-hero">
            <div class="profile-hero-icon profile-hero-icon--settings" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" />
                    <path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21a2 2 0 1 1-4 0v-.1A1.7 1.7 0 0 0 9 19.4a1.7 1.7 0 0 0-1.88.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1.1-.4H3a2 2 0 1 1 0-4h.1A1.7 1.7 0 0 0 4.6 9a1.7 1.7 0 0 0-.34-1.88l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1.1V3a2 2 0 1 1 4 0v.1A1.7 1.7 0 0 0 15 4.6a1.7 1.7 0 0 0 1.88-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.7 1.7 0 0 0 19.4 9a1.7 1.7 0 0 0 .6 1 1.7 1.7 0 0 0 1.1.4h.1a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.7.6Z" />
                </svg>
            </div>

            <div class="profile-hero-copy">
                <span class="profile-eyebrow">Prefer&ecirc;ncias</span>
                <h1>Configura&ccedil;&otilde;es pessoais</h1>
                <p>Prefer&ecirc;ncias de uso da sua conta.</p>
            </div>
        </header>

        <div class="profile-layout profile-layout--settings">
            <section class="profile-card profile-card--main">
                <div class="profile-card-header">
                    <span class="profile-card-kicker">Interface</span>
                    <h2>Tema visual</h2>
                    <p>O tema claro/escuro corporativo &eacute; controlado pelo bot&atilde;o da barra superior.</p>
                </div>

                <div class="profile-preference-list">
                    <article class="profile-preference-item">
                        <div>
                            <strong>Tema corporativo local</strong>
                            <p>A escolha fica salva neste navegador quando o recurso estiver dispon&iacute;vel na interface.</p>
                        </div>

                        <span class="profile-status-pill">Local</span>
                    </article>

                    <article class="profile-preference-item profile-preference-item--muted">
                        <div>
                            <strong>Prefer&ecirc;ncias persistidas</strong>
                            <p>Ainda n&atilde;o h&aacute; prefer&ecirc;ncias pessoais gravadas em banco para este perfil.</p>
                        </div>

                        <span class="profile-status-pill profile-status-pill--muted">Em planejamento</span>
                    </article>
                </div>
            </section>

            <aside class="profile-card profile-guidance-card">
                <div class="profile-card-header">
                    <span class="profile-card-kicker">Escopo</span>
                    <h2>Sem altera&ccedil;&atilde;o corporativa</h2>
                </div>

                <p>
                    Esta p&aacute;gina n&atilde;o altera tenant, divis&atilde;o, unidade, perfil ou permiss&otilde;es. Esses ajustes continuam restritos ao Controle de Acessos.
                </p>
            </aside>
        </div>
    </div>
</div>
@endsection