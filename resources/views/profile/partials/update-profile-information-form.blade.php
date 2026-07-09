<section class="profile-form-section">
    <header class="profile-card-header">
        <span class="profile-card-kicker">Dados b&aacute;sicos</span>
        <h2>Informa&ccedil;&otilde;es pessoais</h2>
        <p>Atualize seu nome e e-mail de acesso. Permiss&otilde;es e v&iacute;nculos corporativos n&atilde;o s&atilde;o editados aqui.</p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="profile-form">
        @csrf
        @method('patch')

        <div class="profile-field">
            <x-input-label for="name" value="Nome" />
            <x-text-input id="name" name="name" type="text" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="profile-error" :messages="$errors->get('name')" />
        </div>

        <div class="profile-field">
            <x-input-label for="email" value="E-mail" />
            <x-text-input id="email" name="email" type="email" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="profile-error" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="profile-inline-alert">
                    <p>
                        Seu e-mail ainda n&atilde;o foi verificado.
                        <button form="send-verification" class="profile-text-button">
                            Reenviar verifica&ccedil;&atilde;o
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="profile-success-message">
                            Um novo link de verifica&ccedil;&atilde;o foi enviado para seu e-mail.
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="profile-actions">
            <x-primary-button>Salvar altera&ccedil;&otilde;es</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2400)"
                    class="profile-success-message"
                >Perfil atualizado.</p>
            @endif
        </div>
    </form>
</section>
