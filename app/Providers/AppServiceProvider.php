<?php

namespace App\Providers;

use App\Models\User;
use App\Models\UserDivisionAccess;
use App\Services\ActiveContextService;
use Illuminate\Support\Facades\Gate;
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
        Gate::define('viewAuditLogs', function (User $user) {
            $divisionId = session('active_division_id');

            if ($divisionId) {
                return UserDivisionAccess::query()
                    ->where('tenant_id', $user->tenant_id)
                    ->where('user_id', $user->id)
                    ->where('division_id', $divisionId)
                    ->where('module', 'fleet')
                    ->whereIn('profile', ['manager', 'admin'])
                    ->where('active', true)
                    ->exists();
            }

            return in_array(
                strtolower($user->profile ?? $user->role ?? $user->type ?? ''),
                ['manager', 'admin'],
                true
            );
        });

        Gate::define('cancelMaintenanceRecords', function (User $user) {
            $divisionId = session('active_division_id');
            $locationId = session('active_location_id');

            if ($divisionId) {
                return UserDivisionAccess::query()
                    ->where('tenant_id', $user->tenant_id)
                    ->where('user_id', $user->id)
                    ->where('division_id', $divisionId)
                    ->where('module', 'fleet')
                    ->whereIn('profile', ['supervisor', 'manager', 'admin'])
                    ->where('active', true)
                    ->where(function ($query) use ($locationId) {
                        if ($locationId) {
                            $query
                                ->where('location_id', $locationId)
                                ->orWhereNull('location_id');

                            return;
                        }

                        $query->whereNull('location_id');
                    })
                    ->exists();
            }

            return in_array(
                strtolower($user->profile ?? $user->role ?? $user->type ?? ''),
                ['supervisor', 'manager', 'admin'],
                true
            );
        });

        Gate::define('cancelStockMovements', function (User $user) {
            $divisionId = session('active_division_id');
            $locationId = session('active_location_id');

            if ($divisionId) {
                return UserDivisionAccess::query()
                    ->where('tenant_id', $user->tenant_id)
                    ->where('user_id', $user->id)
                    ->where('division_id', $divisionId)
                    ->where('module', 'fleet')
                    ->whereIn('profile', ['supervisor', 'manager', 'admin'])
                    ->where('active', true)
                    ->where(function ($query) use ($locationId) {
                        if ($locationId) {
                            $query
                                ->where('location_id', $locationId)
                                ->orWhereNull('location_id');

                            return;
                        }

                        $query->whereNull('location_id');
                    })
                    ->exists();
            }

            return in_array(
                strtolower($user->profile ?? $user->role ?? $user->type ?? ''),
                ['supervisor', 'manager', 'admin'],
                true
            );
        });

        Gate::define('cancelTireRecords', function (User $user) {
            $divisionId = session('active_division_id');
            $locationId = session('active_location_id');

            if ($divisionId) {
                return UserDivisionAccess::query()
                    ->where('tenant_id', $user->tenant_id)
                    ->where('user_id', $user->id)
                    ->where('division_id', $divisionId)
                    ->where('module', 'fleet')
                    ->whereIn('profile', ['supervisor', 'manager', 'admin'])
                    ->where('active', true)
                    ->where(function ($query) use ($locationId) {
                        if ($locationId) {
                            $query
                                ->where('location_id', $locationId)
                                ->orWhereNull('location_id');

                            return;
                        }

                        $query->whereNull('location_id');
                    })
                    ->exists();
            }

            return in_array(
                strtolower($user->profile ?? $user->role ?? $user->type ?? ''),
                ['supervisor', 'manager', 'admin'],
                true
            );
        });

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
