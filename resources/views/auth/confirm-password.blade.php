<x-guest-layout>
    <div class="auth-helper-flow auth-helper-flow--confirm">
        <header class="auth-helper-header">
            <span class="auth-helper-kicker">Segurança</span>
            <h1>{{ __('Confirm Password') }}</h1>
            <p>
                {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
            </p>
        </header>

        <form method="POST" action="{{ route('password.confirm') }}">
            @csrf

            <!-- Password -->
            <div>
                <x-input-label for="password" :value="__('Password')" />

                <x-text-input id="password" class="block mt-1 w-full"
                                type="password"
                                name="password"
                                required autocomplete="current-password" />

                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="auth-helper-actions flex justify-end mt-4">
                <x-primary-button>
                    {{ __('Confirm') }}
                </x-primary-button>
            </div>
        </form>
    </div>
</x-guest-layout>
