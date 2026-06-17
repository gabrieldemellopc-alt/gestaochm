<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceRecord;
use App\Models\UserDivisionAccess;
use App\Models\Vehicle;
use App\Services\ActiveContextService;
use App\Services\MaintenanceService;
use Illuminate\Http\Request;

class MaintenanceController extends Controller
{
    public function store(Request $request, Vehicle $vehicle)
    {
        if ($redirect = $this->ensureVehicleInActiveContext($vehicle)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Selecione uma unidade para continuar.',
                ], 422);
            }

            return $redirect;
        }

        $data = $request->all();

        $result = MaintenanceService::create($data, $vehicle);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Manutenção registrada com sucesso.',
                'maintenance' => $result['maintenance'],
            ], 201);
        }

        return redirect()
            ->route('vehicle.maintenance.index', $vehicle->id)
            ->with('success', 'Manutenção registrada com sucesso.');
    }

    public function cancel(
        Request $request,
        Vehicle $vehicle,
        MaintenanceRecord $maintenance
    ) {
        if ($redirect = $this->ensureVehicleInActiveContext($vehicle)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Selecione uma unidade para continuar.',
                ], 422);
            }

            return $redirect;
        }

        if ((int) $maintenance->vehicle_id !== (int) $vehicle->id) {
            abort(404);
        }

        if ((int) $maintenance->tenant_id !== (int) auth()->user()->tenant_id) {
            abort(403);
        }

        if (! $this->userCanCancelMaintenance($vehicle)) {
            abort(403);
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $cancelled = MaintenanceService::cancel(
            $maintenance,
            $data['reason'],
            auth()->user()
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Manutencao cancelada com sucesso.',
                'maintenance' => $cancelled,
            ]);
        }

        return back()->with('success', 'Manutencao cancelada com sucesso.');
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

    private function userCanCancelMaintenance(Vehicle $vehicle): bool
    {
        return UserDivisionAccess::query()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('user_id', auth()->id())
            ->where('division_id', $vehicle->division_id)
            ->where('module', 'fleet')
            ->where('profile', 'manager')
            ->where('active', true)
            ->where(function ($query) use ($vehicle) {
                $query
                    ->where('location_id', $vehicle->location_id)
                    ->orWhereNull('location_id');
            })
            ->exists();
    }
}
