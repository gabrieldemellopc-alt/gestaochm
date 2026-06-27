<?php

namespace App\Services\Reports;

use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class VehicleDossierReportService
{
    public function __construct(
        private readonly ReportContextService $reportContext
    ) {
    }

    public function build(array $filters = []): array
    {
        $context = $this->reportContext->resolve();

        if (! $context) {
            return $this->emptyResult(
                null,
                $this->emptyFilters($filters),
                ['Contexto ativo de divisao/unidade nao encontrado.']
            );
        }

        $appliedFilters = $this->filters($filters, $context);
        $validationErrors = $this->validationErrors($appliedFilters);
        $vehicle = null;

        if ($appliedFilters['vehicle_id']) {
            $vehicle = $this->vehicle($context, $appliedFilters['vehicle_id']);

            if (! $vehicle) {
                $validationErrors[] = 'Veiculo nao encontrado na tenant, divisao e unidade ativas.';
            }
        }

        if ($validationErrors !== []) {
            return $this->emptyResult($context, $appliedFilters, $validationErrors);
        }

        return [
            'context' => $this->contextPayload($context),
            'applied_filters' => $appliedFilters,
            'validation' => [
                'is_valid' => true,
                'errors' => [],
            ],
            'vehicle' => $this->vehiclePayload($vehicle),
            'executive_summary' => $this->emptyExecutiveSummary(),
            'cost_policy' => $this->costPolicy(),
            'maintenances' => collect(),
            'stock_consumption' => collect(),
            'fuel_fillings' => collect(),
            'tires_current' => collect(),
            'tire_events' => collect(),
            'operations' => collect(),
            'daily_checklists' => collect(),
            'km_hr_logs' => collect(),
            'alerts' => collect(),
            'cancelled_records' => collect(),
            'audit_records' => collect(),
        ];
    }

    private function filters(array $filters, array $context): array
    {
        $startDate = $this->dateFromFilter($filters['start_date'] ?? null, true);
        $endDate = $this->dateFromFilter($filters['end_date'] ?? null, false);
        $periodIsValid = $startDate && $endDate ? $startDate->lte($endDate) : false;

        return [
            'vehicle_id' => $this->positiveInteger($filters['vehicle_id'] ?? null),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'period_is_valid' => $periodIsValid,
            'period_error' => $startDate && $endDate && ! $periodIsValid
                ? 'A data inicial nao pode ser maior que a data final.'
                : null,
            'include_cancelled' => $context['can_view_cancelled']
                && filter_var($filters['include_cancelled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'include_audit' => $context['can_view_cancelled']
                && filter_var($filters['include_audit'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'include_drafts' => filter_var($filters['include_drafts'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'include_events_without_cost' => filter_var($filters['include_events_without_cost'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'include_fillings_without_km_hr' => filter_var($filters['include_fillings_without_km_hr'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'sections' => $this->sections($filters['sections'] ?? null),
        ];
    }

    private function emptyFilters(array $filters): array
    {
        return [
            'vehicle_id' => $this->positiveInteger($filters['vehicle_id'] ?? null),
            'start_date' => $this->dateFromFilter($filters['start_date'] ?? null, true),
            'end_date' => $this->dateFromFilter($filters['end_date'] ?? null, false),
            'period_is_valid' => false,
            'period_error' => null,
            'include_cancelled' => false,
            'include_audit' => false,
            'include_drafts' => filter_var($filters['include_drafts'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'include_events_without_cost' => filter_var($filters['include_events_without_cost'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'include_fillings_without_km_hr' => filter_var($filters['include_fillings_without_km_hr'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'sections' => $this->sections($filters['sections'] ?? null),
        ];
    }

    private function validationErrors(array $filters): array
    {
        $errors = [];

        if (! $filters['vehicle_id']) {
            $errors[] = 'Selecione um veiculo para gerar o dossie.';
        }

        if (! $filters['start_date']) {
            $errors[] = 'Informe a data inicial do periodo.';
        }

        if (! $filters['end_date']) {
            $errors[] = 'Informe a data final do periodo.';
        }

        if ($filters['period_error']) {
            $errors[] = $filters['period_error'];
        }

        return $errors;
    }

    private function vehicle(array $context, int $vehicleId): ?Vehicle
    {
        return $this->reportContext
            ->vehicleQuery($context)
            ->with(['division', 'location'])
            ->whereKey($vehicleId)
            ->first();
    }

    private function emptyResult(?array $context, array $filters, array $errors): array
    {
        return [
            'context' => $context ? $this->contextPayload($context) : null,
            'applied_filters' => $filters,
            'validation' => [
                'is_valid' => false,
                'errors' => $errors,
            ],
            'vehicle' => null,
            'executive_summary' => $this->emptyExecutiveSummary(),
            'cost_policy' => $this->costPolicy(),
            'maintenances' => collect(),
            'stock_consumption' => collect(),
            'fuel_fillings' => collect(),
            'tires_current' => collect(),
            'tire_events' => collect(),
            'operations' => collect(),
            'daily_checklists' => collect(),
            'km_hr_logs' => collect(),
            'alerts' => collect(),
            'cancelled_records' => collect(),
            'audit_records' => collect(),
        ];
    }

    private function contextPayload(array $context): array
    {
        return [
            'tenant_id' => $context['tenant_id'],
            'division' => $context['division'],
            'location' => $context['location'],
            'location_ids' => $context['location_ids'],
            'can_view_cancelled' => $context['can_view_cancelled'],
            'can_view_audit' => $context['can_view_cancelled'],
        ];
    }

    private function vehiclePayload(Vehicle $vehicle): array
    {
        return [
            'model' => $vehicle,
            'id' => $vehicle->id,
            'name' => $vehicle->name,
            'plate' => $vehicle->plate,
            'asset_code' => $vehicle->asset_code,
            'brand' => $vehicle->brand,
            'vehicle_model' => $vehicle->model,
            'year' => $vehicle->year,
            'type' => $vehicle->type,
            'status' => $vehicle->status,
            'operational_status' => $vehicle->operational_status,
            'current_km' => $vehicle->current_km,
            'current_hours' => $vehicle->current_hours,
            'last_km_update_at' => $vehicle->last_km_update_at,
            'last_hours_update_at' => $vehicle->last_hours_update_at,
            'division' => $vehicle->division,
            'location' => $vehicle->location,
            'notes' => $vehicle->notes,
        ];
    }

    private function emptyExecutiveSummary(): array
    {
        return [
            'maintenance_count' => 0,
            'maintenance_cost' => 0.0,
            'stock_consumed_cost' => 0.0,
            'fuel_liters' => 0.0,
            'fuel_cost' => 0.0,
            'installed_tires_count' => 0,
            'tire_measurements_count' => 0,
            'operations_count' => 0,
            'checklists_completed_count' => 0,
            'alerts_count' => 0,
            'operational_total_cost' => null,
            'cost_flags' => [
                'operational_total_is_final' => false,
                'contains_estimated_stock_cost' => false,
                'contains_uncalculated_fuel_consumption' => false,
            ],
            'notes' => [
                'Resumo executivo preparado para os proximos blocos; totais operacionais ainda nao calculados nesta etapa.',
            ],
        ];
    }

    private function costPolicy(): array
    {
        return [
            'maintenance_total_includes_stock' => 'desconhecido/investigar',
            'maintenance_cost_source' => 'maintenance_records.total_cost',
            'stock_cost_source' => 'stock_movements vinculados a maintenance_record_id',
            'operational_total_rule' => 'nao_calcular_nesta_etapa',
            'warnings' => [
                'Nao somar MaintenanceRecord.total_cost com StockMovement sem confirmar se o total da manutencao ja incorpora pecas do estoque.',
                'Custos de pneus nao serao tratados como custo operacional do periodo sem politica contabil explicita.',
                'Consumo km/l ou l/h nao sera calculado sem leituras confiaveis e crescentes.',
            ],
        ];
    }

    private function dateFromFilter(mixed $value, bool $startOfDay): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $date = Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }

        return $startOfDay ? $date->startOfDay() : $date->endOfDay();
    }

    private function sections(mixed $sections): array
    {
        if (is_string($sections)) {
            $sections = array_filter(array_map('trim', explode(',', $sections)));
        }

        if (! is_array($sections)) {
            return [];
        }

        return collect($sections)
            ->filter(fn ($section) => is_string($section) && trim($section) !== '')
            ->map(fn ($section) => trim($section))
            ->unique()
            ->values()
            ->all();
    }

    private function positiveInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $value ?: null;
    }
}
