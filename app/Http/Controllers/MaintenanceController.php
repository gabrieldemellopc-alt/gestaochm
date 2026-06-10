<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Vehicle;
use App\Services\MaintenanceService;

class MaintenanceController extends Controller
{
    public function store(Request $request, Vehicle $vehicle)
    {
        $data = $request->all();

        $data['vehicle_id'] = $vehicle->id;

        $result = MaintenanceService::create($data);

        return redirect()
            ->route('vehicle.maintenance.index', $vehicle->id)
            ->with('success', 'Manutenção registrada com sucesso.');
    }
}