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
        $maintenance = $valid ? $this->maintenanceComposition($context, $start, $end)['maintenance_total'] : 0.0;
        $fuel = $valid ? (float) FuelFilling::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division']->id)
            ->where('location_id', $context['location']->id)
            ->whereNull('cancelled_at')
            ->whereBetween('filled_at', [$start, $end])
            ->selectRaw('COALESCE(SUM(COALESCE(source_total_cost, total_cost, 0)), 0) AS total')
            ->value('total') : 0.0;
        $expenses = $valid && Schema::hasTable('workshop_expenses') ? (float) WorkshopExpense::query()->where('tenant_id',$context['tenant_id'])->where('division_id',$context['division']->id)->where('location_id',$context['location']->id)->whereBetween('expense_date',[$start,$end])->sum('amount') : 0.0;
        $consumption = $valid ? (float) $this->reportContext->stockMovementQuery($context)->where('movement_type','out')->where('description','like',StockMovement::WORKSHOP_CONSUMPTION_PREFIX.'%')->whereNull('cancelled_at')->whereBetween('moved_at',[$start,$end])->sum('total_cost') : 0.0;
        $stockPurchases = $valid ? $this->stockPurchases($context, $start, $end) : ['total' => 0.0, 'count' => 0];
        return ['context'=>$context,'filters'=>['start_date'=>$start,'end_date'=>$end,'period_is_valid'=>$valid],'maintenance_total'=>round($maintenance,2),'fuel_total'=>round($fuel,2),'workshop_expenses_total'=>round($expenses,2),'workshop_consumption_total'=>round($consumption,2),'stock_purchases_total'=>round($stockPurchases['total'],2),'stock_purchase_entries_count'=>$stockPurchases['count'],'total'=>round($maintenance+$fuel+$expenses+$consumption,2)];
    }

    /** Shared event-based composition used by financial reports and workshop dashboards. */
    public function maintenanceComposition(array $context, Carbon $start, Carbon $end): array
    {
        $maintenanceIds = $this->maintenanceIds($context);
        $items = $this->maintenanceItems($maintenanceIds)->whereBetween('performed_at', [$start, $end]);
        $materials = StockMovement::query()->whereIn('maintenance_record_id', $maintenanceIds)->where('movement_type', 'out')->whereNull('cancelled_at')->whereNull('reversal_movement_id')->whereNull('reversed_from_movement_id')->whereBetween('moved_at', [$start, $end]);
        $serviceTotal = (float) (clone $items)->selectRaw("COALESCE(SUM(maintenance_record_items.total_cost - COALESCE((SELECT SUM(stock_movements.total_cost) FROM stock_movements WHERE stock_movements.maintenance_record_item_id = maintenance_record_items.id AND stock_movements.movement_type = 'out' AND stock_movements.cancelled_at IS NULL AND stock_movements.reversal_movement_id IS NULL AND stock_movements.reversed_from_movement_id IS NULL AND NOT EXISTS (SELECT 1 FROM maintenance_material_usages WHERE maintenance_material_usages.stock_movement_id = stock_movements.id AND maintenance_material_usages.cancelled_at IS NULL)), 0)), 0) AS total")->value('total');
        $materialsTotal = (float) (clone $materials)->where(function (Builder $query) { $query->whereNull('maintenance_record_item_id')->orWhereHas('maintenanceRecordItem', fn (Builder $item) => $item->whereNull('cancelled_at')); })->sum('total_cost');
        $extra = MaintenanceRecordExtraCost::query()->whereIn('maintenance_record_id', $maintenanceIds)->where(function (Builder $query) use ($start, $end) { $query->whereBetween('cost_date', [$start->toDateString(), $end->toDateString()])->orWhere(fn (Builder $legacy) => $legacy->whereNull('cost_date')->whereBetween('created_at', [$start, $end])); });
        $extraTotal = (float) (clone $extra)->sum('amount');
        $participants = collect()->merge((clone $items)->pluck('maintenance_record_id'))->merge((clone $materials)->pluck('maintenance_record_id'))->merge((clone $extra)->pluck('maintenance_record_id'))->filter()->unique()->values();
        return ['materials'=>round($materialsTotal,2),'services_other'=>round($serviceTotal,2),'extra_costs'=>round($extraTotal,2),'maintenance_total'=>round($materialsTotal+$serviceTotal+$extraTotal,2),'maintenance_ids'=>$participants->all(),'maintenance_count'=>$participants->count()];
    }

    public function workshopComposition(array $context, Carbon $start, Carbon $end): array
    {
        $maintenance = $this->maintenanceComposition($context, $start, $end);
        $expenses = (float) WorkshopExpense::query()->where('tenant_id', $context['tenant_id'])->where('division_id', $context['division']->id)->where('location_id', $context['location']->id)->whereBetween('expense_date', [$start, $end])->sum('amount');
        return $maintenance + ['workshop_expenses'=>round($expenses,2), 'workshop_total'=>round($maintenance['maintenance_total'] + $expenses,2), 'average_per_maintenance'=>$maintenance['maintenance_count'] ? round($maintenance['maintenance_total'] / $maintenance['maintenance_count'],2) : 0.0];
    }

    /**
     * Stock acquisitions are a cash-flow indicator, not an operational cost:
     * the same material becomes an operational cost only when it is consumed.
     */
    private function stockPurchases(array $context, Carbon $start, Carbon $end): array
    {
        $query = $this->reportContext->stockMovementQuery($context)
            ->where('movement_type', 'in')
            ->where('total_cost', '>', 0)
            ->whereNull('cancelled_at')
            ->whereNull('reversal_movement_id')
            ->whereNull('reversed_from_movement_id')
            ->whereNotExists(function ($usage) {
                $usage->selectRaw('1')
                    ->from('maintenance_material_usages')
                    ->whereColumn('maintenance_material_usages.purchase_entry_movement_id', 'stock_movements.id');
            })
            ->whereBetween('moved_at', [$start, $end]);

        // Initial balance is not a financial acquisition. Older installations
        // may not have this column, so retain compatibility with their schema.
        if (Schema::hasColumn('stock_movements', 'description')) {
            $query->where(function (Builder $query) {
                $query->whereNull('description')
                    ->orWhere('description', '!=', 'Estoque inicial');
            });
        }

        return [
            'total' => (float) (clone $query)->sum('total_cost'),
            'count' => (int) $query->count(),
        ];
    }

    private function maintenanceIds(array $context)
    {
        return $this->reportContext->maintenanceQuery($context)
            ->whereNull('deleted_at')
            ->whereNull('cancelled_at')
            ->select('id');
    }

    private function maintenanceItems($maintenanceIds): Builder
    {
        return MaintenanceRecordItem::query()
            ->whereIn('maintenance_record_id', $maintenanceIds)
            ->whereNull('cancelled_at');
    }
}
