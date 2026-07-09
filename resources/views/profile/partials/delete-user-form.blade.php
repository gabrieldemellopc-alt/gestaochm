<section class="profile-form-section profile-danger-section">
    <header class="profile-card-header">
        <span class="profile-card-kicker profile-card-kicker--danger">A&ccedil;&atilde;o sens&iacute;vel</span>
        <h2>Excluir conta</h2>
        <p>Esta a&ccedil;&atilde;o remove sua conta e deve ser usada apenas quando houver certeza operacional.</p>
    </header>

    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >Excluir conta</x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="profile-delete-modal">
            @csrf
            @method('delete')

            <h2>Confirmar exclus&atilde;o da conta</h2>

            <p>
                Depois de exclu&iacute;da, a conta n&atilde;o poder&aacute; ser restaurada por esta tela. Informe sua senha para confirmar.
            </p>

            <div class="profile-field">
                <x-input-label for="password" value="Senha" class="sr-only" />

                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    placeholder="Senha"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" class="profile-error" />
            </div>

            <div class="profile-modal-actions">
                <x-secondary-button x-on:click="$dispatch('close')">
                    Cancelar
                </x-secondary-button>

                <x-danger-button>
                    Excluir conta
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
