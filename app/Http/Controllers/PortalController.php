<?php

namespace App\Http\Controllers;
use App\Models\Division;
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
    public function enterDivision(Division $division)
    {
        session([
            'active_division_id' => $division->id
        ]);
    
        return redirect()
            ->route('dashboard');
    }
    public function division(Division $division)
    {
        
        session([
            'active_division_id' => $division->id
        ]);
        return view(
            'portal.division',
            compact('division')
        );
    }
    public function leaveDivision()
    {
        session()->forget(
    
            'active_division_id'
    
        );
    
        return redirect()
    
            ->route('portal');
    }
}