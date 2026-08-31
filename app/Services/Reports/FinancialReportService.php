<?php

namespace App\Services\Reports;

use App\Models\FuelFilling;
use App\Models\MaintenanceRecordExtraCost;
use App\Models\MaintenanceRecordItem;
use App\Models\StockMovement;
use App\Models\WorkshopExpense;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class FinancialReportService
{
    public function __construct(private readonly ReportContextService $reportContext)
    {
    }

    public function build(array $filters = [], ?array $context = null): array
    {
        $context ??= $this->reportContext->resolve();
        if (! $context) return ['context' => null, 'error' => 'Contexto ativo de divisão/unidade não encontrado.'];
        $start = !empty($filters['start_date']) ? Carbon::parse($filters['start_date'])->startOfDay() : Carbon::now()->startOfMonth();
        $end = !empty($filters['end_date']) ? Carbon::parse($filters['end_date'])->endOfDay() : Carbon::now()->endOfDay();
        $valid = $start->lte($end);
        $maintenance = $valid ? $this->maintenanceTotal($context, $start, $end) : 0.0;
        $fuel = $valid ? (float) FuelFilling::query()->where('tenant_id',$context['tenant_id'])->where('division_id',$context['division']->id)->where('location_id',$context['location']->id)->whereNull('cancelled_at')->whereBetween('filled_at',[$start,$end])->sum('total_cost') : 0.0;
        $expenses = $valid && Schema::hasTable('workshop_expenses') ? (float) WorkshopExpense::query()->where('tenant_id',$context['tenant_id'])->where('division_id',$context['division']->id)->where('location_id',$context['location']->id)->whereBetween('expense_date',[$start,$end])->sum('amount') : 0.0;
        $consumption = $valid ? (float) $this->reportContext->stockMovementQuery($context)->where('movement_type','out')->where('description','like',StockMovement::WORKSHOP_CONSUMPTION_PREFIX.'%')->whereNull('cancelled_at')->whereBetween('moved_at',[$start,$end])->sum('total_cost') : 0.0;
        return ['context'=>$context,'filters'=>['start_date'=>$start,'end_date'=>$end,'period_is_valid'=>$valid],'maintenance_total'=>round($maintenance,2),'fuel_total'=>round($fuel,2),'workshop_expenses_total'=>round($expenses,2),'workshop_consumption_total'=>round($consumption,2),'total'=>round($maintenance+$fuel+$expenses+$consumption,2)];
    }

    /**
     * Maintenance is reported by its cost events, rather than by the date of
     * the order.  An order may remain open across several financial periods.
     */
    private function maintenanceTotal(array $context, Carbon $start, Carbon $end): float
    {
        $maintenanceIds = $this->maintenanceIds($context);

        // Item total_cost contains the cost of stock consumed while adding an
        // internal procedure.  Those materials are accounted for below using
        // stock_movements.moved_at, so only the non-material portion belongs
        // to the service-item event.
        $services = (float) $this->maintenanceItems($maintenanceIds)
            ->whereBetween('performed_at', [$start, $end])
            ->selectRaw("COALESCE(SUM(maintenance_record_items.total_cost - COALESCE((
                SELECT SUM(stock_movements.total_cost)
                FROM stock_movements
                WHERE stock_movements.maintenance_record_item_id = maintenance_record_items.id
                  AND stock_movements.movement_type = 'out'
                  AND stock_movements.cancelled_at IS NULL
                  AND stock_movements.reversal_movement_id IS NULL
                  AND stock_movements.reversed_from_movement_id IS NULL
                  AND NOT EXISTS (
                      SELECT 1 FROM maintenance_material_usages
                      WHERE maintenance_material_usages.stock_movement_id = stock_movements.id
                        AND maintenance_material_usages.cancelled_at IS NULL
                  )
            ), 0)), 0) AS total")
            ->value('total');

        // A stock movement is the single source for material consumption.  A
        // direct purchase creates both an entry and an out movement; counting
        // only the active out movement deliberately avoids counting it twice.
        $materials = (float) StockMovement::query()
            ->whereIn('maintenance_record_id', $maintenanceIds)
            ->where('movement_type', 'out')
            ->whereNull('cancelled_at')
            ->whereNull('reversal_movement_id')
            ->whereNull('reversed_from_movement_id')
            ->where(function (Builder $query) {
                $query->whereNull('maintenance_record_item_id')
                    ->orWhereHas('maintenanceRecordItem', fn (Builder $item) => $item->whereNull('cancelled_at'));
            })
            ->whereBetween('moved_at', [$start, $end])
            ->sum('total_cost');

        // cost_date is the explicit financial date.  Older rows did not have
        // it, therefore retain their creation timestamp as a compatibility
        // fallback without ever using the order's dates.
        $extraCosts = (float) MaintenanceRecordExtraCost::query()
            ->whereIn('maintenance_record_id', $maintenanceIds)
            ->where(function (Builder $query) use ($start, $end) {
                $query->whereBetween('cost_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhere(function (Builder $legacy) use ($start, $end) {
                        $legacy->whereNull('cost_date')->whereBetween('created_at', [$start, $end]);
                    });
            })
            ->sum('amount');

        return $services + $materials + $extraCosts;
    }

    private function maintenanceIds(array $context)
    {
        return $this->reportContext->maintenanceQuery($context)
            ->whereNull('deleted_at')
            ->select('id');
    }

    private function maintenanceItems($maintenanceIds): Builder
    {
        return MaintenanceRecordItem::query()
            ->whereIn('maintenance_record_id', $maintenanceIds)
            ->whereNull('cancelled_at');
    }
}
