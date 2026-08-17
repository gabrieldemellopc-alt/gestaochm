<?php
namespace App\Services;
use App\Models\StockItem;
use App\Models\StockMovement;
class StockEntryService
{
    public function record(StockItem $item, array $data, array $audit = []): StockMovement
    {
        $item = StockItem::query()->where('tenant_id', $item->tenant_id)->where('location_id', $item->location_id)->lockForUpdate()->findOrFail($item->id);
        $quantity = round((float) $data['quantity'], 2); $beforeQty = round((float) $item->quantity, 2); $beforeCost = round((float) $item->unit_cost, 2);
        $total = round((float) ($data['total_cost'] ?? ($quantity * (float) ($data['unit_cost'] ?? 0))), 2);
        $unit = $quantity > 0 ? round($total / $quantity, 2) : 0; $afterQty = round($beforeQty + $quantity, 2);
        $average = $afterQty > 0 ? round((($beforeQty * $beforeCost) + $total) / $afterQty, 2) : 0;
        $movement = StockMovement::create([
            'tenant_id' => $item->tenant_id, 'location_id' => $item->location_id, 'stock_item_id' => $item->id,
            'movement_type' => 'in', 'quantity' => $quantity, 'unit_cost' => $unit, 'total_cost' => $total,
            'invoice_number' => $data['invoice_number'] ?? null, 'supplier_name' => $data['supplier_name'] ?? null,
            'description' => $data['description'] ?? null, 'moved_at' => $data['moved_at'], 'fiscal_document_id' => $data['fiscal_document_id'] ?? null,
        ]);
        $item->update(['quantity' => $afterQty, 'unit_cost' => $average]);
        app(AuditLogService::class)->created($movement, array_replace_recursive([
            'tenant_id' => $item->tenant_id, 'location_id' => $item->location_id, 'module' => 'stock', 'summary' => 'Entrada de estoque registrada.',
            'after_data' => $movement->toArray(), 'metadata' => compact('beforeQty', 'afterQty', 'beforeCost', 'average'),
        ], $audit));
        return $movement;
    }
}
