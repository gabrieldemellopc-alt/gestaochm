<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Services\ActiveContextService;
use App\Services\PortalAccessResolver;
use Illuminate\Support\Facades\Auth;

class PortalController extends Controller
{
    public function index(
        PortalAccessResolver $resolver,
        ActiveContextService $activeContext
    ) {
        $user = Auth::user();

        $divisions = $resolver->availableDivisions($user);

        $isAdmin = auth()
            ->user()
            ->divisionAccesses()
            ->where('active', true)
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('profile', 'admin')
            ->exists();
        
        if (! $isAdmin && $divisions->count() === 1) {
            return $this->autoEnterDivision(
                $divisions->first(),
                $resolver,
                $activeContext
            );
        }

        $availableDivisionIds = $divisions
            ->pluck('id')
            ->toArray();

        return view('portal.index', compact(
            'divisions',
            'availableDivisionIds'
        ));
    }

    public function enterDivision(
        Division $division,
        ActiveContextService $activeContext,
        PortalAccessResolver $resolver
    ) {
        $this->authorizeDivisionAccess($division, $resolver);

        return $this->autoEnterDivision(
            $division,
            $resolver,
            $activeContext
        );
    }

    public function division(
        Division $division,
        ActiveContextService $activeContext,
        PortalAccessResolver $resolver
    ) {
        $this->authorizeDivisionAccess($division, $resolver);

        session([
            'active_division_id' => $division->id,
        ]);

        $activeContext->revalidateActiveLocation(
            Auth::user(),
            $division->id
        );

        $moduleRedirect = $this->redirectIfSingleModule(
            $division,
            $resolver
        );

        if ($moduleRedirect) {
            return $moduleRedirect;
        }

        return view(
            'portal.division',
            compact('division')
        );
    }

    public function leaveDivision(ActiveContextService $activeContext)
    {
        session()->forget('active_division_id');

        $activeContext->clearActiveLocation();

        return redirect()->route('portal');
    }

    private function autoEnterDivision(
        Division $division,
        PortalAccessResolver $resolver,
        ActiveContextService $activeContext
    ) {
        session([
            'active_division_id' => $division->id,
        ]);

        $activeContext->revalidateActiveLocation(
            Auth::user(),
            $division->id
        );

        $moduleRedirect = $this->redirectIfSingleModule(
            $division,
            $resolver
        );

        if ($moduleRedirect) {
            return $moduleRedirect;
        }

        return view(
            'portal.division',
            compact('division')
        );
    }

    private function redirectIfSingleModule(
        Division $division,
        PortalAccessResolver $resolver
    ) {
        $modules = $resolver->modulesForDivision(
            Auth::user(),
            $division
        );

        if ($modules->count() !== 1) {
            return null;
        }

        $routeName = $resolver->routeForModule(
            $modules->first()
        );

        if (! $routeName) {
            return null;
        }

        return redirect()->route($routeName);
    }

    private function authorizeDivisionAccess(
        Division $division,
        PortalAccessResolver $resolver
    ): void {
        abort_unless(
            $resolver->userHasDivisionAccess(
                Auth::user(),
                $division
            ),
            403,
            'Você não possui acesso a esta divisão.'
        );
    }
}