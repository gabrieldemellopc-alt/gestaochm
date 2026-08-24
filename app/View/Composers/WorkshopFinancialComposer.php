<?php

namespace App\View\Composers;

use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\WorkshopExpense;
use App\Services\ActiveContextService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class WorkshopFinancialComposer
{
    public function __construct(private readonly ActiveContextService $activeContext)
    {
    }

    public function compose(View $view): void
    {
        $user = auth()->user();
        $location = $user ? $this->activeContext->activeLocation($user) : null;

        $defaults = [
            'workshopExpenseMonthTotal' => 0.0,
            'workshopExpenseRecent' => collect(),
            'workshopConsumptionMonthTotal' => 0.0,
            'workshopConsumptionRecent' => collect(),
            'workshopOperationalCostMonth' => 0.0,
            'workshopConsumableStockItems' => collect(),
            'workshopExpenseCategories' => WorkshopExpense::LABELS,
        ];

        if (! $user || ! $location) {
            $view->with($defaults);

            return;
        }

        $month = Carbon::now();
        $stockItems = Schema::hasTable('stock_items')
            ? StockItem::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('location_id', $location->id)
                ->where('active', true)
                ->where('is_workshop_consumable', true)
                ->where('quantity', '>', 0)
                ->orderBy('name')
                ->get(['id', 'name', 'unit', 'quantity', 'unit_cost'])
            : collect();

        $movementQuery = Schema::hasTable('stock_movements')
            ? StockMovement::query()
                ->with(['stockItem'])
                ->where('tenant_id', $user->tenant_id)
                ->where('location_id', $location->id)
                ->where('movement_type', 'out')
                ->where('description', 'like', StockMovement::WORKSHOP_CONSUMPTION_PREFIX.'%')
                ->whereNull('cancelled_at')
            : null;

        $expenseQuery = Schema::hasTable('workshop_expenses')
            ? WorkshopExpense::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('division_id', $location->division_id)
                ->where('location_id', $location->id)
            : null;

        $expenseMonthTotal = $expenseQuery
            ? (float) (clone $expenseQuery)->whereBetween('expense_date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->sum('amount')
            : 0.0;
        $consumptionMonthTotal = $movementQuery
            ? (float) (clone $movementQuery)->whereBetween('moved_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->sum('total_cost')
            : 0.0;

        $view->with([
            ...$defaults,
            'workshopExpenseMonthTotal' => round($expenseMonthTotal, 2),
            'workshopExpenseRecent' => $expenseQuery ? (clone $expenseQuery)->latest('expense_date')->latest('id')->limit(5)->get() : collect(),
            'workshopConsumptionMonthTotal' => round($consumptionMonthTotal, 2),
            'workshopConsumptionRecent' => $movementQuery ? (clone $movementQuery)->latest('moved_at')->latest('id')->limit(5)->get() : collect(),
            'workshopOperationalCostMonth' => round($expenseMonthTotal + $consumptionMonthTotal, 2),
            'workshopConsumableStockItems' => $stockItems,
        ]);
    }
}
