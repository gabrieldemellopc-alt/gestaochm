<?php

namespace App\Providers;

use App\Services\ActiveContextService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.topbar', function ($view) {
            $user = auth()->user();
            $activeDivision = null;
            $activeLocation = null;
            $availableLocations = collect();

            if ($user && session('active_division_id')) {
                $activeContext = app(ActiveContextService::class);
                $activeDivision = $activeContext->activeDivision($user);

                if ($activeDivision) {
                    $availableLocations = $activeContext->availableLocations(
                        $user,
                        $activeDivision->id
                    );
                    $activeLocation = $activeContext->initializeActiveLocation(
                        $user,
                        $activeDivision->id
                    );
                }
            }

            $view->with(compact(
                'activeDivision',
                'activeLocation',
                'availableLocations'
            ));
        });
    }
}
