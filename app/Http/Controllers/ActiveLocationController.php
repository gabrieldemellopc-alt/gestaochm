<?php

namespace App\Http\Controllers;

use App\Services\ActiveContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ActiveLocationController extends Controller
{
    public function update(
        Request $request,
        ActiveContextService $activeContext
    ): RedirectResponse {
        $data = $request->validate([
            'location_id' => ['required', 'integer'],
        ]);

        $activeContext->setActiveLocation(
            $request->user(),
            (int) $data['location_id']
        );

        return back();
    }
}
