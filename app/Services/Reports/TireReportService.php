<?php

namespace App\Services\Reports;

use App\Models\Tire;
use App\Models\TireEntry;
use App\Models\TireInstallation;
use App\Models\TireMeasurement;
use App\Models\TireRetread;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TireReportService
{
    private const NO_RECENT_MEASUREMENT_DAYS = 60;

    public function build(array $context, Request $request): array
    {
        $filters = $this->filters($context, $request);
        $vehicles = $this->vehicles($context);
        $inventory = $this->inventoryQuery($context)->get();
        $filteredTires = $this->filteredTires($context, $filters);
        $criticalTires = $inventory
            ->filter(fn (Tire $tire) => $this->isCritical($tire))
            ->sortBy(fn (Tire $tire) => (float) ($tire->current_tread_depth ?? 999))
            ->values();

        $noRecentMeasurements = $inventory
            ->filter(fn (Tire $tire) => $this->hasNoRecentMeasurement($tire))
            ->sortBy(fn (Tire $tire) => optional($tire->latestMeasurement?->measured_at)->timestamp ?? 0)
            ->values();

        return [
            'context' => $context,
            'filters' => $filters,
            'vehicles' => $vehicles,
            'summary' => $this->summary($inventory, $criticalTires, $noRecentMeasurements),
            'retreadSummary' => $this->retreadSummary($inventory),
            'tires' => $filteredTires,
            'criticalTires' => $criticalTires->take(20),
            'noRecentMeasurements' => $noRecentMeasurements->take(20),
            'tiresByVehicle' => $this->tiresByVehicle($context),
            'vehicleInstalledTires' => $filters['vehicle_id']
                ? $this->vehicleInstalledTires($context, (int) $filters['vehicle_id'])
                : collect(),
            'events' => $this->events($context, $filters),
            'cancelledRecords' => $this->cancelledRecords($context, $filters),
        ];
    }

    private function filters(array $context, Request $request): array
    {
        $startDate = $request->filled('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : Carbon::now()->subDays(30)->startOfDay();

        $endDate = $request->filled('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : Carbon::now()->endOfDay();

        $status = $request->input('status', 'all');
        if (! in_array($status, ['all', 'available', 'installed', 'maintenance', 'discarded'], true)) {
            $status = 'all';
        }

        $retreads = $request->input('retreads', 'all');
        if (! in_array($retreads, ['all', 'none', 'r1', 'r2', 'r3plus'], true)) {
            $retreads = 'all';
        }

        $vehicleId = $request->integer('vehicle_id') ?: null;
        if ($vehicleId && ! $this->vehicles($context)->contains('id', $vehicleId)) {
            $vehicleId = null;
        }

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'vehicle_id' => $vehicleId,
            'status' => $status,
            'only_installed' => $request->boolean('only_installed'),
            'only_critical' => $request->boolean('only_critical'),
            'retreads' => $retreads,
            'include_cancelled' => $context['can_view_cancelled'] && $request->boolean('include_cancelled'),
        ];
    }

    private function vehicles(array $context): Collection
    {
        return Vehicle::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division']->id)
            ->where('location_id', $context['location']->id)
            ->orderBy('name')
            ->get(['id', 'name', 'plate']);
    }

    private function inventoryQuery(array $context): Builder
    {
        return Tire::query()
            ->withCurrentTreadContext()
            ->with([
                'activeInstallation.vehicle',
                'latestMeasurement',
                'latestRetread',
            ])
            ->withCount('retreads')
            ->where('tenant_id', $context['tenant_id'])
            ->where('location_id', $context['location']->id)
            ->notCancelled();
    }

    private function filteredTires(array $context, array $filters): Collection
    {
        $query = $this->inventoryQuery($context)
            ->orderBy('code');

        if ($filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if ($filters['only_installed']) {
            $query->where('status', 'installed');
        }

        if ($filters['vehicle_id']) {
            $query->whereHas('installations', function (Builder $query) use ($filters) {
                $query->where('vehicle_id', $filters['vehicle_id']);
            });
        }

        return $query
            ->get()
            ->filter(fn (Tire $tire) => $this->matchesRetreadFilter($tire, $filters['retreads']))
            ->filter(fn (Tire $tire) => ! $filters['only_critical'] || $this->isCritical($tire))
            ->values();
    }

    private function summary(Collection $inventory, Collection $criticalTires, Collection $noRecentMeasurements): array
    {
        return [
            'total' => $inventory->count(),
            'installed' => $inventory->where('status', 'installed')->count(),
            'available' => $inventory->where('status', 'available')->count(),
            'maintenance' => $inventory->where('status', 'maintenance')->count(),
            'discarded' => $inventory->where('status', 'discarded')->count(),
            'critical' => $criticalTires->count(),
            'no_recent_measurement' => $noRecentMeasurements->count(),
        ];
    }

    private function retreadSummary(Collection $inventory): array
    {
        return [
            'none' => $inventory->filter(fn (Tire $tire) => (int) $tire->retreads_count === 0)->count(),
            'r1' => $inventory->filter(fn (Tire $tire) => (int) $tire->retreads_count === 1)->count(),
            'r2' => $inventory->filter(fn (Tire $tire) => (int) $tire->retreads_count === 2)->count(),
            'r3plus' => $inventory->filter(fn (Tire $tire) => (int) $tire->retreads_count >= 3)->count(),
        ];
    }

    private function tiresByVehicle(array $context): Collection
    {
        return TireInstallation::query()
            ->with(['vehicle', 'tire.latestMeasurement', 'tire.latestRetread'])
            ->where('tenant_id', $context['tenant_id'])
            ->where('active', true)
            ->whereHas('vehicle', function (Builder $query) use ($context) {
                $query
                    ->where('tenant_id', $context['tenant_id'])
                    ->where('division_id', $context['division']->id)
                    ->where('location_id', $context['location']->id);
            })
            ->whereHas('tire', function (Builder $query) use ($context) {
                $query
                    ->where('tenant_id', $context['tenant_id'])
                    ->where('location_id', $context['location']->id)
                    ->notCancelled();
            })
            ->orderBy('vehicle_id')
            ->orderBy('position_code')
            ->get()
            ->groupBy('vehicle_id')
            ->map(fn (Collection $installations) => [
                'vehicle' => $installations->first()->vehicle,
                'installations' => $installations,
                'critical_count' => $installations->filter(fn (TireInstallation $installation) => $this->isCritical($installation->tire))->count(),
            ])
            ->values();
    }

    private function vehicleInstalledTires(array $context, int $vehicleId): Collection
    {
        return TireInstallation::query()
            ->with(['vehicle', 'tire.latestMeasurement', 'tire.latestRetread'])
            ->where('tenant_id', $context['tenant_id'])
            ->where('vehicle_id', $vehicleId)
            ->where('active', true)
            ->whereHas('vehicle', function (Builder $query) use ($context) {
                $query
                    ->where('tenant_id', $context['tenant_id'])
                    ->where('division_id', $context['division']->id)
                    ->where('location_id', $context['location']->id);
            })
            ->whereHas('tire', function (Builder $query) use ($context) {
                $query
                    ->where('tenant_id', $context['tenant_id'])
                    ->where('location_id', $context['location']->id)
                    ->notCancelled();
            })
            ->orderBy('position_code')
            ->get();
    }

    private function events(array $context, array $filters): Collection
    {
        return collect()
            ->merge($this->entryEvents($context, $filters))
            ->merge($this->installationEvents($context, $filters))
            ->merge($this->measurementEvents($context, $filters))
            ->merge($this->retreadEvents($context, $filters))
            ->merge($this->discardEvents($context, $filters))
            ->sortByDesc('date')
            ->take(80)
            ->values();
    }

    private function entryEvents(array $context, array $filters): Collection
    {
        $query = TireEntry::query()
            ->withCount('tires')
            ->where('tenant_id', $context['tenant_id'])
            ->where('location_id', $context['location']->id)
            ->whereNull('cancelled_at')
            ->whereBetween('entry_date', [$filters['start_date'], $filters['end_date']]);

        return $query
            ->latest('entry_date')
            ->get()
            ->map(fn (TireEntry $entry) => [
                'date' => $entry->entry_date,
                'type' => 'Entrada',
                'title' => 'Entrada de pneus',
                'description' => trim(($entry->supplier_name ?: 'Fornecedor nao informado') . ' - ' . $entry->tires_count . ' pneu(s)'),
                'status' => $entry->cancelled_at ? 'cancelled' : 'normal',
            ]);
    }

    private function installationEvents(array $context, array $filters): Collection
    {
        $query = TireInstallation::query()
            ->with(['tire', 'vehicle'])
            ->where('tenant_id', $context['tenant_id'])
            ->where(function (Builder $query) use ($filters) {
                $query
                    ->whereBetween('installed_at', [$filters['start_date'], $filters['end_date']])
                    ->orWhereBetween('removed_at', [$filters['start_date'], $filters['end_date']]);
            })
            ->whereHas('vehicle', function (Builder $query) use ($context, $filters) {
                $query
                    ->where('tenant_id', $context['tenant_id'])
                    ->where('division_id', $context['division']->id)
                    ->where('location_id', $context['location']->id);

                if ($filters['vehicle_id']) {
                    $query->where('id', $filters['vehicle_id']);
                }
            })
            ->whereHas('tire', function (Builder $query) use ($context) {
                $query
                    ->where('tenant_id', $context['tenant_id'])
                    ->where('location_id', $context['location']->id)
                    ->notCancelled();
            });

        return $query
            ->get()
            ->flatMap(function (TireInstallation $installation) use ($filters) {
                $events = [];

                if ($this->dateInPeriod($installation->installed_at, $filters)) {
                    $events[] = [
                        'date' => $installation->installed_at,
                        'type' => 'Instalacao',
                        'title' => $installation->tire?->code . ' instalado',
                        'description' => ($installation->vehicle?->plate ?? '-') . ' - Posicao ' . $installation->position_code,
                        'status' => 'normal',
                    ];
                }

                if ($this->dateInPeriod($installation->removed_at, $filters)) {
                    $events[] = [
                        'date' => $installation->removed_at,
                        'type' => 'Retirada',
                        'title' => $installation->tire?->code . ' retirado',
                        'description' => ($installation->vehicle?->plate ?? '-') . ' - ' . ($installation->removal_reason ?: 'Sem motivo registrado'),
                        'status' => 'warning',
                    ];
                }

                return $events;
            });
    }

    private function measurementEvents(array $context, array $filters): Collection
    {
        $query = TireMeasurement::query()
            ->with(['tire', 'vehicle'])
            ->where('tenant_id', $context['tenant_id'])
            ->whereBetween('measured_at', [$filters['start_date'], $filters['end_date']])
            ->whereHas('tire', function (Builder $query) use ($context) {
                $query
                    ->where('tenant_id', $context['tenant_id'])
                    ->where('location_id', $context['location']->id)
                    ->notCancelled();
            })
            ->whereHas('vehicle', function (Builder $query) use ($context, $filters) {
                $query
                    ->where('tenant_id', $context['tenant_id'])
                    ->where('division_id', $context['division']->id)
                    ->where('location_id', $context['location']->id);

                if ($filters['vehicle_id']) {
                    $query->where('id', $filters['vehicle_id']);
                }
            });

        return $query
            ->whereNull('cancelled_at')
            ->latest('measured_at')
            ->get()
            ->map(fn (TireMeasurement $measurement) => [
                'date' => $measurement->measured_at,
                'type' => 'Medicao',
                'title' => ($measurement->tire?->code ?? '-') . ' - ' . number_format((float) $measurement->minimum_tread, 1, ',', '.') . ' mm',
                'description' => ($measurement->vehicle?->plate ?? '-') . ' - KM ' . ($measurement->vehicle_km ?? '-'),
                'status' => $measurement->cancelled_at ? 'cancelled' : 'normal',
            ]);
    }

    private function retreadEvents(array $context, array $filters): Collection
    {
        $query = TireRetread::query()
            ->with('tire')
            ->where('tenant_id', $context['tenant_id'])
            ->whereBetween('retreaded_at', [$filters['start_date'], $filters['end_date']])
            ->whereHas('tire', function (Builder $query) use ($context) {
                $query
                    ->where('tenant_id', $context['tenant_id'])
                    ->where('location_id', $context['location']->id)
                    ->notCancelled();
            })
            ->whereNull('cancelled_at');

        return $query
            ->latest('retreaded_at')
            ->get()
            ->map(fn (TireRetread $retread) => [
                'date' => $retread->retreaded_at,
                'type' => 'Recapagem',
                'title' => ($retread->tire?->code ?? '-') . ' - novo sulco ' . number_format((float) $retread->new_tread_depth, 1, ',', '.') . ' mm',
                'description' => $retread->provider_name ?: 'Fornecedor nao informado',
                'status' => $retread->cancelled_at ? 'cancelled' : 'normal',
            ]);
    }

    private function discardEvents(array $context, array $filters): Collection
    {
        return Tire::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('location_id', $context['location']->id)
            ->notCancelled()
            ->where('status', 'discarded')
            ->whereBetween('updated_at', [$filters['start_date'], $filters['end_date']])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Tire $tire) => [
                'date' => $tire->updated_at,
                'type' => 'Descarte',
                'title' => $tire->code . ' descartado',
                'description' => trim(($tire->brand ?: '-') . ' ' . ($tire->model ?: '')),
                'status' => 'critical',
            ]);
    }

    private function cancelledRecords(array $context, array $filters): Collection
    {
        if (! $filters['include_cancelled']) {
            return collect();
        }

        return collect()
            ->merge(
                TireMeasurement::query()
                    ->with(['tire', 'canceller'])
                    ->where('tenant_id', $context['tenant_id'])
                    ->whereNotNull('cancelled_at')
                    ->whereBetween('cancelled_at', [$filters['start_date'], $filters['end_date']])
                    ->whereHas('tire', fn (Builder $query) => $query->where('location_id', $context['location']->id))
                    ->get()
                    ->map(fn (TireMeasurement $measurement) => [
                        'date' => $measurement->cancelled_at,
                        'type' => 'Medicao cancelada',
                        'title' => $measurement->tire?->code ?? '-',
                        'reason' => $measurement->cancel_reason,
                        'user' => $measurement->canceller?->name,
                    ])
            )
            ->merge(
                TireRetread::query()
                    ->with(['tire', 'canceller'])
                    ->where('tenant_id', $context['tenant_id'])
                    ->whereNotNull('cancelled_at')
                    ->whereBetween('cancelled_at', [$filters['start_date'], $filters['end_date']])
                    ->whereHas('tire', fn (Builder $query) => $query->where('location_id', $context['location']->id))
                    ->get()
                    ->map(fn (TireRetread $retread) => [
                        'date' => $retread->cancelled_at,
                        'type' => 'Recapagem cancelada',
                        'title' => $retread->tire?->code ?? '-',
                        'reason' => $retread->cancel_reason,
                        'user' => $retread->canceller?->name,
                    ])
            )
            ->merge(
                TireEntry::query()
                    ->with('canceller')
                    ->where('tenant_id', $context['tenant_id'])
                    ->where('location_id', $context['location']->id)
                    ->whereNotNull('cancelled_at')
                    ->whereBetween('cancelled_at', [$filters['start_date'], $filters['end_date']])
                    ->get()
                    ->map(fn (TireEntry $entry) => [
                        'date' => $entry->cancelled_at,
                        'type' => 'Entrada cancelada',
                        'title' => 'Entrada #' . $entry->id,
                        'reason' => $entry->cancel_reason,
                        'user' => $entry->canceller?->name,
                    ])
            )
            ->merge(
                Tire::query()
                    ->with('canceller')
                    ->where('tenant_id', $context['tenant_id'])
                    ->where('location_id', $context['location']->id)
                    ->whereNotNull('cancelled_at')
                    ->whereBetween('cancelled_at', [$filters['start_date'], $filters['end_date']])
                    ->get()
                    ->map(fn (Tire $tire) => [
                        'date' => $tire->cancelled_at,
                        'type' => 'Pneu cancelado',
                        'title' => $tire->code,
                        'reason' => $tire->cancel_reason,
                        'user' => $tire->canceller?->name,
                    ])
            )
            ->sortByDesc('date')
            ->values();
    }

    private function matchesRetreadFilter(Tire $tire, string $filter): bool
    {
        $count = (int) $tire->retreads_count;

        return match ($filter) {
            'none' => $count === 0,
            'r1' => $count === 1,
            'r2' => $count === 2,
            'r3plus' => $count >= 3,
            default => true,
        };
    }

    private function isCritical(?Tire $tire): bool
    {
        if (! $tire || $tire->status === 'discarded') {
            return false;
        }

        $currentTread = $tire->current_tread_depth;

        if ($currentTread === null) {
            return false;
        }

        $criticalDepth = $tire->critical_tread_depth ?: 3;

        return (float) $currentTread <= (float) $criticalDepth;
    }

    private function hasNoRecentMeasurement(Tire $tire): bool
    {
        if ($tire->status === 'discarded') {
            return false;
        }

        $measurementDate = $tire->latestMeasurement?->measured_at;

        return ! $measurementDate
            || $measurementDate->lt(Carbon::now()->subDays(self::NO_RECENT_MEASUREMENT_DAYS));
    }

    private function dateInPeriod($date, array $filters): bool
    {
        if (! $date) {
            return false;
        }

        return Carbon::parse($date)->betweenIncluded($filters['start_date'], $filters['end_date']);
    }
}
