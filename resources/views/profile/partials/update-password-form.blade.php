<section class="profile-form-section">
    <header class="profile-card-header">
        <span class="profile-card-kicker">Credenciais</span>
        <h2>Alterar senha</h2>
        <p>Use uma senha forte e confirme sua senha atual para autorizar a altera&ccedil;&atilde;o.</p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="profile-form">
        @csrf
        @method('put')

        <div class="profile-field">
            <x-input-label for="update_password_current_password" value="Senha atual" />
            <x-text-input id="update_password_current_password" name="current_password" type="password" autocomplete="current-password" />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="profile-error" />
        </div>

        <div class="profile-field">
            <x-input-label for="update_password_password" value="Nova senha" />
            <x-text-input id="update_password_password" name="password" type="password" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password')" class="profile-error" />
        </div>

        <div class="profile-field">
            <x-input-label for="update_password_password_confirmation" value="Confirmar nova senha" />
            <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="profile-error" />
        </div>

        <div class="profile-actions">
            <x-primary-button>Atualizar senha</x-primary-button>

            @if (session('status') === 'password-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2400)"
                    class="profile-success-message"
                >Senha atualizada.</p>
            @endif
        </div>
    </form>
</section>
