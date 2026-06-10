<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Vehicle;
use App\Services\ActiveContextService;
use App\Services\MaintenanceService;

class MaintenanceController extends Controller
{
    public function store(Request $request, Vehicle $vehicle)
    {
        if ($redirect = $this->ensureVehicleInActiveContext($vehicle)) {
            return $redirect;
        }

        $data = $request->all();

        $result = MaintenanceService::create($data, $vehicle);

        return redirect()
            ->route('vehicle.maintenance.index', $vehicle->id)
            ->with('success', 'Manutenção registrada com sucesso.');
    }

    private function ensureVehicleInActiveContext(Vehicle $vehicle)
    {
        $activeLocation = app(ActiveContextService::class)
            ->activeLocation(auth()->user());

        if (! $activeLocation) {
            return redirect()
                ->route('portal')
                ->with(
                    'warning',
                    'Selecione uma unidade para continuar.'
                );
        }

        if (
            (int) $vehicle->tenant_id !== (int) auth()->user()->tenant_id
            || (int) $vehicle->division_id !== (int) session('active_division_id')
            || (int) $vehicle->location_id !== (int) $activeLocation->id
        ) {
            abort(403);
        }

        return null;
    }
}
