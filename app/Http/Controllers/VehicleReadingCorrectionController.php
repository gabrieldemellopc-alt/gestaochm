<?php

namespace App\Http\Controllers;

use App\Models\FuelFilling;
use App\Models\MaintenanceRecord;
use App\Models\TireInstallation;
use App\Models\TireMeasurement;
use App\Models\Vehicle;
use App\Models\VehicleOperation;
use App\Models\VehicleUpdateLog;
use App\Services\ActiveContextService;
use App\Services\AuditLogService;
use App\Services\Permissions\ProfilePermissionService;
use App\Services\VehicleReadingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VehicleReadingCorrectionController extends Controller
{
    public function preview(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorizeCorrection($vehicle);
        $data = $this->validated($request, false);

        return response()->json([
            'impacts' => $this->impacts($vehicle, $data)->values(),
        ]);
    }

    public function store(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $this->authorizeCorrection($vehicle);
        $data = $this->validated($request, true);
        $impacts = $this->impacts($vehicle, $data);

        DB::transaction(function () use ($vehicle, $data, $impacts) {
            $lockedVehicle = Vehicle::query()->whereKey($vehicle->id)->lockForUpdate()->firstOrFail();
            $before = $lockedVehicle->only(['current_km', 'current_hours']);
            $service = app(VehicleReadingService::class);
            $changed = false;

            if (array_key_exists('new_km', $data) && $data['new_km'] !== null) {
                $changed = $service->correctKm($lockedVehicle, $data['new_km'], auth()->user(), $data['reason']) || $changed;
            }

            if (array_key_exists('new_hours', $data) && $data['new_hours'] !== null) {
                $changed = $service->correctHours($lockedVehicle, $data['new_hours'], auth()->user(), $data['reason']) || $changed;
            }

            if (! $changed) {
                throw ValidationException::withMessages([
                    'reading_correction' => 'Informe ao menos uma leitura diferente da atual.',
                ]);
            }

            app(AuditLogService::class)->updated($lockedVehicle, [
                'tenant_id' => $lockedVehicle->tenant_id,
                'division_id' => $lockedVehicle->division_id,
                'location_id' => $lockedVehicle->location_id,
                'module' => 'fleet',
                'summary' => 'Leitura do veículo corrigida administrativamente.',
                'before_data' => $before,
                'after_data' => $lockedVehicle->fresh()->only(['current_km', 'current_hours']),
                'metadata' => [
                    'impact_count' => $impacts->count(),
                    'impact_references' => $impacts->pluck('reference')->values()->all(),
                ],
                'reason' => $data['reason'],
            ]);
        });

        return back()->with('success', 'Leitura corrigida com sucesso.');
    }

    private function validated(Request $request, bool $requireConfirmation): array
    {
        $rules = [
            'new_km' => ['nullable', 'numeric', 'min:0', 'required_without:new_hours'],
            'new_hours' => ['nullable', 'numeric', 'min:0', 'required_without:new_km'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];

        if ($requireConfirmation) {
            $rules['impact_confirmed'] = ['accepted'];
        }

        return $request->validate($rules, [
            'new_km.required_without' => 'Informe o novo KM ou o novo horímetro.',
            'new_hours.required_without' => 'Informe o novo KM ou o novo horímetro.',
            'reason.required' => 'Informe o motivo da correção.',
            'reason.min' => 'O motivo da correção deve ter pelo menos 10 caracteres.',
            'impact_confirmed.accepted' => 'Você precisa confirmar que entende os impactos da correção.',
        ]);
    }

    private function authorizeCorrection(Vehicle $vehicle): void
    {
        $location = app(ActiveContextService::class)->activeLocation(auth()->user());

        abort_unless(
            $location
            && (int) $vehicle->tenant_id === (int) auth()->user()->tenant_id
            && (int) $vehicle->division_id === (int) session('active_division_id')
            && (int) $vehicle->location_id === (int) $location->id,
            403
        );

        abort_unless(
            app(ProfilePermissionService::class)->allows(auth()->user(), 'vehicles.correct_readings'),
            403,
            'Você não tem permissão para corrigir leituras do veículo.'
        );
    }

    private function impacts(Vehicle $vehicle, array $data): Collection
    {
        $items = collect();
        $km = $data['new_km'] ?? null;
        $hours = $data['new_hours'] ?? null;

        if ($km !== null) {
            VehicleUpdateLog::query()->where('vehicle_id', $vehicle->id)->where('type', 'km')->where('new_value', '>', $km)->get()
                ->each(fn ($row) => $items->push($this->impact('Histórico de leitura', $row->created_at, $row->new_value, 'KM', 'VehicleUpdateLog #'.$row->id)));
            FuelFilling::query()->where('vehicle_id', $vehicle->id)->where('vehicle_km', '>', $km)->get()
                ->each(fn ($row) => $items->push($this->impact('Abastecimento', $row->filled_at, $row->vehicle_km, 'KM', 'FuelFilling #'.$row->id)));
            MaintenanceRecord::query()->where('vehicle_id', $vehicle->id)->where('performed_km', '>', $km)->get()
                ->each(fn ($row) => $items->push($this->impact('Manutenção', $row->performed_at ?? $row->created_at, $row->performed_km, 'KM', 'MaintenanceRecord #'.$row->id)));
            VehicleOperation::query()->where('vehicle_id', $vehicle->id)->where(fn ($q) => $q->where('start_vehicle_km', '>', $km)->orWhere('end_vehicle_km', '>', $km))->get()
                ->each(fn ($row) => $items->push($this->impact('Operação', $row->end_datetime_reported ?? $row->start_datetime_reported, max((float) $row->start_vehicle_km, (float) $row->end_vehicle_km), 'KM', 'VehicleOperation #'.$row->id)));
            TireInstallation::query()->where('vehicle_id', $vehicle->id)->where(fn ($q) => $q->where('installed_km', '>', $km)->orWhere('removed_km', '>', $km))->get()
                ->each(fn ($row) => $items->push($this->impact('Instalação/remoção de pneu', $row->removed_at ?? $row->installed_at, max((float) $row->installed_km, (float) $row->removed_km), 'KM', 'TireInstallation #'.$row->id)));
            TireMeasurement::query()->where('vehicle_id', $vehicle->id)->where('vehicle_km', '>', $km)->get()
                ->each(fn ($row) => $items->push($this->impact('Medição de pneu', $row->measured_at, $row->vehicle_km, 'KM', 'TireMeasurement #'.$row->id)));
        }

        if ($hours !== null) {
            VehicleUpdateLog::query()->where('vehicle_id', $vehicle->id)->where('type', 'hours')->where('new_value', '>', $hours)->get()
                ->each(fn ($row) => $items->push($this->impact('Histórico de leitura', $row->created_at, $row->new_value, 'h', 'VehicleUpdateLog #'.$row->id)));
            FuelFilling::query()->where('vehicle_id', $vehicle->id)->where('vehicle_hours', '>', $hours)->get()
                ->each(fn ($row) => $items->push($this->impact('Abastecimento', $row->filled_at, $row->vehicle_hours, 'h', 'FuelFilling #'.$row->id)));
            MaintenanceRecord::query()->where('vehicle_id', $vehicle->id)->where('performed_hours', '>', $hours)->get()
                ->each(fn ($row) => $items->push($this->impact('Manutenção', $row->performed_at ?? $row->created_at, $row->performed_hours, 'h', 'MaintenanceRecord #'.$row->id)));
            VehicleOperation::query()->where('vehicle_id', $vehicle->id)->where(fn ($q) => $q->where('start_vehicle_hours', '>', $hours)->orWhere('end_vehicle_hours', '>', $hours))->get()
                ->each(fn ($row) => $items->push($this->impact('Operação', $row->end_datetime_reported ?? $row->start_datetime_reported, max((float) $row->start_vehicle_hours, (float) $row->end_vehicle_hours), 'h', 'VehicleOperation #'.$row->id)));
        }

        return $items->sortByDesc('date')->values();
    }

    private function impact(string $type, mixed $date, mixed $value, string $unit, string $reference): array
    {
        return [
            'type' => $type,
            'date' => $date ? \Carbon\Carbon::parse($date)->format('d/m/Y H:i') : '-',
            'value' => number_format((float) $value, $unit === 'KM' ? 0 : 1, ',', '.').' '.$unit,
            'reference' => $reference,
        ];
    }
}
