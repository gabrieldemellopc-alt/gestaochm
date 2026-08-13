<?php

namespace App\Services;

use App\Models\StockItem;
use App\Models\StockMovement;
use Carbon\Carbon;

class StockItemDetailService
{
    public function buildPdfReportPayload(
        StockItem $item,
        int $tenantId,
        int $locationId,
        string $startDate,
        string $endDate,
        bool $canViewCosts,
        bool $canViewAudit
    ): array {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();
        $item->load('category');

        $query = StockMovement::query()
            ->where('tenant_id', $tenantId)
            ->where('location_id', $locationId)
            ->where('stock_item_id', $item->id)
            ->whereBetween('moved_at', [$start, $end]);

        $movements = (clone $query)
            ->with(['reversalMovement', 'reversedFromMovement', 'maintenanceRecord.vehicle', 'maintenanceRecordItem.procedure'])
            ->orderBy('moved_at')
            ->orderBy('id')
            ->get();

        $active = $movements->filter(fn (StockMovement $movement) =>
            ! $movement->cancelled_at
            && ! $movement->reversal_movement_id
            && ! $movement->reversed_from_movement_id
        );

        $summary = [
            'entries' => (float) $active->where('movement_type', 'in')->sum('quantity'),
            'outputs' => (float) $active->where('movement_type', 'out')->sum('quantity'),
            'balance' => (float) $active->where('movement_type', 'in')->sum('quantity')
                - (float) $active->where('movement_type', 'out')->sum('quantity'),
            'last_movement_at' => $movements->max('moved_at'),
        ];

        $priceHistory = $canViewCosts
            ? $active->where('movement_type', 'in')->whereNotNull('unit_cost')->values()
            : collect();
        $maintenanceOutputs = $active
            ->where('movement_type', 'out')
            ->whereNotNull('maintenance_record_id')
            ->values();

        if (! $canViewCosts) {
            $item->setAttribute('unit_cost', null);
            $movements->each(function (StockMovement $movement) {
                $movement->setAttribute('unit_cost', null);
                $movement->setAttribute('total_cost', null);
            });
        }

        if (! $canViewAudit) {
            $movements->each->makeHidden(['cancel_reason', 'cancelled_by']);
        }

        return compact(
            'item', 'movements', 'summary', 'priceHistory', 'maintenanceOutputs',
            'start', 'end', 'canViewCosts', 'canViewAudit'
        );
    }

    public function build(
        StockItem $item,
        int $tenantId,
        int $locationId,
        bool $canViewCosts,
        bool $canViewAudit
    ): array {
        $item->load('category');
        $item->stock_status = StockService::getStatus($item);

        $base = StockMovement::query()
            ->where('tenant_id', $tenantId)
            ->where('location_id', $locationId)
            ->where('stock_item_id', $item->id);

        $active = (clone $base)
            ->whereNull('cancelled_at')
            ->whereNull('reversal_movement_id')
            ->whereNull('reversed_from_movement_id');

        $movements = (clone $base)
            ->with([
                'reversalMovement',
                'reversedFromMovement',
                'maintenanceRecord.vehicle',
                'maintenanceRecordItem.procedure',
            ])
            ->orderByDesc('moved_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $summary = [
            'entries' => (float) (clone $active)->where('movement_type', 'in')->sum('quantity'),
            'outputs' => (float) (clone $active)->where('movement_type', 'out')->sum('quantity'),
            'last_movement_at' => (clone $base)->max('moved_at'),
        ];

        $priceHistory = collect();
        if ($canViewCosts) {
            $priceHistory = (clone $active)
                ->where('movement_type', 'in')
                ->whereNotNull('unit_cost')
                ->orderBy('moved_at')
                ->orderBy('id')
                ->get(['id', 'moved_at', 'unit_cost', 'total_cost', 'supplier_name', 'invoice_number']);
        }

        $maintenanceOutputs = (clone $active)
            ->where('movement_type', 'out')
            ->whereNotNull('maintenance_record_id')
            ->with(['maintenanceRecord.vehicle', 'maintenanceRecordItem.procedure'])
            ->orderByDesc('moved_at')
            ->limit(50)
            ->get();

        if (! $canViewCosts) {
            $item->setAttribute('unit_cost', null);
            $movements->getCollection()->each(function (StockMovement $movement) {
                $movement->setAttribute('unit_cost', null);
                $movement->setAttribute('total_cost', null);
            });
        }

        if (! $canViewAudit) {
            $movements->getCollection()->each->makeHidden([
                'cancel_reason',
                'cancelled_by',
            ]);
        }

        return compact('item', 'movements', 'summary', 'priceHistory', 'maintenanceOutputs');
    }
}
