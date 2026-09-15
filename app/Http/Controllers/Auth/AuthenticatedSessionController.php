<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\ActiveContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
    
        $user = Auth::user();
    
        if (
            $user &&
            ! (bool) $user->active
        ) {
            Auth::guard('web')->logout();
    
            $request->session()->invalidate();
    
            $request->session()->regenerateToken();
    
            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'Usuário desativado. Entre em contato com o administrador.',
                ])
                ->onlyInput('email');
        }
    
        $request->session()->regenerate();
    
        /*
         * Inicializa o contexto operacional antes de retornar para uma URL
         * acessada diretamente e preservada pelo Laravel como "intended".
         */
        if ($user) {
            $activeContext = app(ActiveContextService::class);

            $activeDivision = $activeContext->activeDivision($user);

            if (! $activeDivision) {
                $activeDivision = $activeContext
                    ->availableDivisions($user)
                    ->first();

                if ($activeDivision) {
                    $request->session()->put(
                        'active_division_id',
                        $activeDivision->id
                    );
                }
            }

            if ($activeDivision) {
                $activeContext->initializeActiveLocation(
                    $user,
                    $activeDivision->id
                );
            }
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }
    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
