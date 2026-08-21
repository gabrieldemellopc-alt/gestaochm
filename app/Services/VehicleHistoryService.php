<?php

namespace App\Services;

use App\Models\FuelFilling;
use App\Models\MaintenanceMaterialUsage;
use App\Models\MaintenancePhoto;
use App\Models\MaintenanceRecord;
use App\Models\TireInstallation;
use App\Models\TireMeasurement;
use App\Models\Vehicle;
use App\Models\VehicleOperation;
use App\Models\VehicleReadingCorrection;
use App\Models\SystemAuditLog;
use Illuminate\Support\Collection;

class VehicleHistoryService
{
    /** Build a read-only, scoped timeline. Cost fields are omitted at the source when restricted. */
    public function build(Vehicle $vehicle, array $permissions): array
    {
        $scope = fn ($query) => $query->where('tenant_id', $vehicle->tenant_id)
            ->where('division_id', $vehicle->division_id)
            ->where('location_id', $vehicle->location_id);

        $vehicle->loadMissing(['division', 'location', 'currentAllocation.location']);
        $events = collect();

        // created_at continua sendo auditoria; a posição no histórico de leituras usa a data efetiva.
        $logs = $vehicle->updateLogs()->with('user')->orderByRaw('COALESCE(read_at, created_at) DESC')->get();
        $locationNames = \App\Models\Location::whereIn('id', $logs->where('type', 'location')
            ->flatMap(fn ($log) => [$log->old_value, $log->new_value])->filter()->unique())->pluck('name', 'id');
        foreach ($logs as $log) {
            $isLocation = $log->type === 'location';
            $isHours = $log->type === 'hours';
            $isStatus = in_array($log->type, ['status', 'operational_status'], true);
            $label = $isLocation ? 'Localização' : ($isStatus ? 'Operacional' : 'Leitura');
            $unit = $isHours ? ' h' : ($log->type === 'km' ? ' km' : '');
            $before = $isLocation ? $locationNames->get($log->old_value, 'Não informado') : ($log->old_value ?? 'Não informado') . $unit;
            $after = $isLocation ? $locationNames->get($log->new_value, 'Não informado') : ($log->new_value ?? 'Não informado') . $unit;
            $events->push($this->event($isLocation ? 'location' : ($isStatus ? 'operational' : 'reading'), $label,
                $isLocation ? 'Localização atualizada' : ($isStatus ? 'Status do veículo alterado' : ($isHours ? 'Horímetro atualizado' : 'Hodômetro atualizado')),
                $log->read_at ?? $log->created_at, $before . ' → ' . $after, array_filter(['Origem' => $log->source, 'Responsável' => $log->user?->name, 'Status da leitura' => $log->reading_status]), null, null, false));
        }

        VehicleReadingCorrection::query()->where('vehicle_id', $vehicle->id)->with(['evidence', 'user'])->get()
            ->each(function (VehicleReadingCorrection $correction) use ($events, $vehicle) {
                $before = $correction->originalLog?->new_value;
                $description = $correction->new_km !== null
                    ? number_format((float) ($before ?? 0), 0, ',', '.').' km → '.number_format((float) $correction->new_km, 0, ',', '.').' km'
                    : 'Correção administrativa de leitura.';
                $details = array_filter(['Data efetiva' => optional($correction->effective_at)->format('d/m/Y'), 'Responsável' => $correction->user?->name, 'Motivo' => $correction->reason]);
                $event = $this->event('reading', 'Correção administrativa', 'Correção administrativa de hodômetro', $correction->effective_at ?: $correction->created_at, $description, $details);
                if ($correction->evidence?->status === 'ready' && $correction->evidence->path) {
                    $event['url'] = route('vehicles.reading-correction.evidence.download', [$vehicle, $correction->evidence]);
                    $event['url_label'] = 'Visualizar evidência';
                } elseif ($correction->evidence) {
                    $event['details']['Evidência'] = 'Evidência indisponível';
                }
                $events->push($event);
            });

        $fillings = $scope(FuelFilling::query())->where('vehicle_id', $vehicle->id)->with(['product', 'tank'])->latest('filled_at')->get();
        foreach ($fillings as $filling) {
            $import = $this->importedFuelContext($filling->notes);
            $details = array_filter([
                'Produto' => $filling->product?->name,
                'Volume' => number_format((float) $filling->quantity_liters, 3, ',', '.') . ' L',
                'Local' => $import['is_imported'] ? 'Importação histórica' : $filling->location_label,
                'Documento' => $filling->document_number,
                'Km registrado' => $filling->vehicle_km ? number_format((float) $filling->vehicle_km, 0, ',', '.') . ' km' : null,
                'Média' => $import['efficiency'],
            ]);
            if ($permissions['fuel_costs']) $details['Custo'] = 'R$ ' . number_format((float) $filling->total_cost, 2, ',', '.');
            $events->push($this->event('fuel', 'Abastecimento', 'Abastecimento registrado', $filling->filled_at, $import['description'], $details, null, null, $filling->cancelled_at !== null));
        }

        $maintenances = MaintenanceRecord::where('tenant_id', $vehicle->tenant_id)->where('vehicle_id', $vehicle->id)->whereNull('deleted_at')->with('procedure')->get();
        foreach ($maintenances as $maintenance) {
            $details = array_filter(['OM' => '#' . $maintenance->id, 'Status' => $maintenance->workflow_status ?: $maintenance->service_status, 'Tipo' => $maintenance->maintenance_type, 'Procedimento' => $maintenance->procedure?->name, 'Dias parado' => $this->downtime($maintenance)]);
            if ($permissions['maintenance_costs']) $details['Custo consolidado'] = 'R$ ' . number_format((float) $maintenance->total_cost, 2, ',', '.');
            $events->push($this->event('maintenance', 'Manutenção', 'OM aberta', $maintenance->started_at ?: $maintenance->created_at, $maintenance->reason ?: $maintenance->notes, $details, route('vehicles.maintenance.show', [$vehicle, $maintenance]), null, $maintenance->cancelled_at !== null));
            if ($maintenance->finished_at) $events->push($this->event('maintenance', 'Manutenção', 'OM encerrada', $maintenance->finished_at, $maintenance->closure_notes, $details, route('vehicles.maintenance.show', [$vehicle, $maintenance]), null, $maintenance->cancelled_at !== null));
        }

        $usages = MaintenanceMaterialUsage::where('tenant_id', $vehicle->tenant_id)->whereHas('maintenanceRecord', fn ($q) => $q->where('vehicle_id', $vehicle->id)->whereNull('deleted_at'))->with(['stockItem', 'maintenanceRecord'])->get();
        foreach ($usages as $usage) {
            $details = ['Material' => $usage->stockItem?->name ?? 'Item de estoque', 'Quantidade' => number_format((float) $usage->quantity, 2, ',', '.'), 'OM' => '#' . $usage->maintenance_record_id];
            if ($permissions['maintenance_costs']) $details['Custo'] = 'R$ ' . number_format((float) $usage->total_cost, 2, ',', '.');
            $events->push($this->event('maintenance', 'Manutenção', 'Material utilizado na manutenção', $usage->created_at, $usage->notes, $details, route('vehicles.maintenance.show', [$vehicle, $usage->maintenanceRecord]), null, $usage->cancelled_at !== null));
        }

        $photos = MaintenancePhoto::where('tenant_id', $vehicle->tenant_id)->whereHas('maintenanceRecord', fn ($q) => $q->where('vehicle_id', $vehicle->id)->whereNull('deleted_at'))->with('maintenanceRecord')->get();
        foreach ($photos as $photo) $events->push($this->event('maintenance', 'Manutenção', 'Foto adicionada à manutenção #' . $photo->maintenance_record_id, $photo->created_at, $photo->caption ?: $photo->original_name, ['OM' => '#' . $photo->maintenance_record_id], route('vehicles.maintenance.show', [$vehicle, $photo->maintenanceRecord])));

        $installations = TireInstallation::where('tenant_id', $vehicle->tenant_id)->where('vehicle_id', $vehicle->id)->with('tire')->get();
        foreach ($installations as $installation) {
            $details = ['Pneu' => $installation->tire?->code ?? 'Não identificado', 'Posição' => $installation->position_code, 'Km' => $installation->installed_km ? number_format((float) $installation->installed_km, 0, ',', '.') . ' km' : null];
            $events->push($this->event('tire', 'Pneus', 'Pneu instalado', $installation->installed_at ?: $installation->created_at, null, array_filter($details)));
            if ($installation->removed_at) $events->push($this->event('tire', 'Pneus', 'Pneu removido', $installation->removed_at, $installation->removal_reason, array_filter($details)));
        }
        foreach (TireMeasurement::where('tenant_id', $vehicle->tenant_id)->where('vehicle_id', $vehicle->id)->whereNull('cancelled_at')->with('tire')->get() as $measurement) {
            $events->push($this->event('tire', 'Pneus', 'Medição de sulco registrada', $measurement->measured_at ?: $measurement->created_at, $measurement->notes, ['Pneu' => $measurement->tire?->code ?? 'Não identificado', 'Posição' => $measurement->position_code, 'Sulco mínimo' => number_format((float) $measurement->minimum_tread, 2, ',', '.') . ' mm']));
        }

        foreach (VehicleOperation::where('tenant_id', $vehicle->tenant_id)->where('vehicle_id', $vehicle->id)->get() as $operation) {
            $events->push($this->event('operational', 'Operacional', 'Movimentação iniciada', $operation->start_datetime_reported ?: $operation->created_at, $operation->start_observation, ['Km' => $operation->start_vehicle_km ? number_format((float) $operation->start_vehicle_km, 0, ',', '.') . ' km' : null]));
            if ($operation->end_datetime_reported) $events->push($this->event('operational', 'Operacional', 'Movimentação encerrada', $operation->end_datetime_reported, $operation->end_observation, ['Km' => $operation->end_vehicle_km ? number_format((float) $operation->end_vehicle_km, 0, ',', '.') . ' km' : null]));
        }

        SystemAuditLog::query()->where('auditable_type', Vehicle::class)->where('auditable_id', $vehicle->id)->where('action', 'vehicle_transferred')->with('user')->get()
            ->each(function (SystemAuditLog $audit) use ($events) {
                $from = ($audit->before_data['location_name'] ?? 'Origem não informada');
                $to = ($audit->after_data['location_name'] ?? 'Destino não informado');
                $events->push($this->event('location', 'Transferência de localidade', 'Veículo transferido', $audit->created_at, $from.' → '.$to, array_filter(['Responsável' => $audit->user?->name, 'Motivo' => $audit->reason])));
            });

        $events = $events->filter(fn ($event) => $event['occurred_at'])->sortByDesc('occurred_at')->values();
        return ['events' => $events, 'summary' => ['fillings' => $fillings->count(), 'maintenances' => $maintenances->count(), 'tires' => $installations->where('active', true)->count(), 'last_update' => $events->first()['occurred_at'] ?? null, 'total_cost' => ($permissions['fuel_costs'] && $permissions['maintenance_costs']) ? $fillings->whereNull('cancelled_at')->sum('total_cost') + $maintenances->whereNull('cancelled_at')->sum('total_cost') : null]];
    }

    private function event(string $type, string $label, string $title, $occurredAt, ?string $description = null, array $details = [], ?string $url = null, ?string $image = null, bool $cancelled = false): array { return compact('type', 'label', 'title', 'occurredAt', 'description', 'details', 'url', 'image', 'cancelled') + ['occurred_at' => $occurredAt]; }
    private function downtime(MaintenanceRecord $maintenance): ?string { if (! $maintenance->started_at) return null; return $maintenance->started_at->diffInDays($maintenance->finished_at ?: now()) . ' dia(s)'; }

    /** Remove import metadata and retain only a validated efficiency value. */
    private function importedFuelContext(?string $notes): array
    {
        $notes = trim((string) $notes);
        $isImported = preg_match('/IMP-IMPERATRIZ-FUEL:|Importa[çc][ãa]o hist[óo]rica Imperatriz/i', $notes) === 1;

        if ($isImported) {
            $efficiency = null;
            if (preg_match('/m[ée]dia\s*=\s*([0-9]+(?:[,.][0-9]+)?)/iu', $notes, $match)) {
                $value = (float) str_replace(',', '.', $match[1]);
                if ($value > 0 && $value < 100) {
                    $efficiency = number_format($value, 2, ',', '.') . ' km/L';
                }
            }

            return [
                'is_imported' => true,
                'description' => 'Abastecimento importado da planilha histórica de Imperatriz.',
                'efficiency' => $efficiency,
            ];
        }

        // Never surface an import token or its semicolon-separated implementation metadata.
        $description = preg_replace('/(?:^|;)\s*(?:IMP-[A-Z0-9_-]+:[a-f0-9]+|frota\s*=.*|km anterior\s*=.*|percorrido\s*=.*|m[ée]dia\s*=.*)\s*(?=;|$)/iu', '', $notes);
        $description = trim((string) preg_replace('/\s*;\s*;\s*/', '; ', $description), " ;");

        return ['is_imported' => false, 'description' => $description ?: null, 'efficiency' => null];
    }
}
