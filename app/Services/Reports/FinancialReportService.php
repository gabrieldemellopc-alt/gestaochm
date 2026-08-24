<?php

namespace App\Services\Reports;

use App\Models\FuelFilling;
use App\Models\StockMovement;
use App\Models\WorkshopExpense;
use Carbon\Carbon;
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
        $maintenance = $valid ? (float) $this->reportContext->maintenanceQuery($context)->whereBetween('performed_at', [$start, $end])->sum('total_cost') : 0.0;
        $fuel = $valid ? (float) FuelFilling::query()->where('tenant_id',$context['tenant_id'])->where('division_id',$context['division']->id)->where('location_id',$context['location']->id)->whereNull('cancelled_at')->whereBetween('filled_at',[$start,$end])->sum('total_cost') : 0.0;
        $expenses = $valid && Schema::hasTable('workshop_expenses') ? (float) WorkshopExpense::query()->where('tenant_id',$context['tenant_id'])->where('division_id',$context['division']->id)->where('location_id',$context['location']->id)->whereBetween('expense_date',[$start,$end])->sum('amount') : 0.0;
        $consumption = $valid ? (float) $this->reportContext->stockMovementQuery($context)->where('movement_type','out')->where('description','like',StockMovement::WORKSHOP_CONSUMPTION_PREFIX.'%')->whereNull('cancelled_at')->whereBetween('moved_at',[$start,$end])->sum('total_cost') : 0.0;
        return ['context'=>$context,'filters'=>['start_date'=>$start,'end_date'=>$end,'period_is_valid'=>$valid],'maintenance_total'=>round($maintenance,2),'fuel_total'=>round($fuel,2),'workshop_expenses_total'=>round($expenses,2),'workshop_consumption_total'=>round($consumption,2),'total'=>round($maintenance+$fuel+$expenses+$consumption,2)];
    }
}
