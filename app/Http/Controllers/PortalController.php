<?php

namespace App\Http\Controllers;
use App\Models\Division;
use App\Services\ActiveContextService;
use Illuminate\Http\Request;

class PortalController extends Controller
{
    public function index()
    {
        $divisions = auth()
            ->user()
            ->divisions()
            ->get();

        return view(
            'portal.index',
            compact('divisions')
        );
    }
    public function enterDivision(
        Division $division,
        ActiveContextService $activeContext
    )
    {
        session([
            'active_division_id' => $division->id
        ]);
    
        $activeContext->revalidateActiveLocation(
            auth()->user(),
            $division->id
        );

        return redirect()
            ->route('dashboard');
    }
    public function division(
        Division $division,
        ActiveContextService $activeContext
    )
    {
        
        session([
            'active_division_id' => $division->id
        ]);
        $activeContext->revalidateActiveLocation(
            auth()->user(),
            $division->id
        );

        return view(
            'portal.division',
            compact('division')
        );
    }
    public function leaveDivision(ActiveContextService $activeContext)
    {
        session()->forget(
    
            'active_division_id'
    
        );
    
        $activeContext->clearActiveLocation();

        return redirect()
    
            ->route('portal');
    }
}
