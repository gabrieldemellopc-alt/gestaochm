<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class CleanupImperatrizTestMaintenance extends Command
{
    protected $signature = 'chm:cleanup-imperatriz-test-maintenance
        {--commit : Executa a exclusão após a auditoria segura}
        {--confirm-location= : Deve ser exatamente 3 para permitir exclusão}';

    protected $description = 'Audita e remove exclusivamente a OM de teste de materiais de Imperatriz';

    private const TENANT_ID = 1;
    private const DIVISION_ID = 1;
    private const LOCATION_ID = 3;
    private const ITEM_NAMES = ['Mangueira hidráulica', 'Capa para mangueira hidráulica'];

    public function handle(): int
    {
        $audit = $this->audit();
        $this->render($audit);

        if (! $audit['safe']) {
            $this->error('SAFE NO — nenhuma alteração foi realizada. '.$audit['reason']);
            return self::FAILURE;
        }

        if (! $this->option('commit')) {
            $this->info('SAFE YES — dry-run. Nenhuma alteração foi realizada. Use --commit --confirm-location=3 para excluir exclusivamente estes rastros.');
            return self::SUCCESS;
        }

        if ((string) $this->option('confirm-location') !== (string) self::LOCATION_ID) {
            $this->error('Confirmação inválida: use --confirm-location=3. Nenhuma alteração foi realizada.');
            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($audit): void {
                $this->deleteAuditRows($audit);
                DB::table('stock_items')->whereIn('id', $audit['item_ids'])->update(['quantity' => 0]);
                $after = $this->audit();
                if ($after['maintenance'] || $after['movements'] || array_filter($after['balances'])) {
                    throw new RuntimeException('Validação pós-limpeza falhou.');
                }
            });
        } catch (\Throwable $exception) {
            $this->error('CLEANUP FAILED — transação revertida: '.$exception->getMessage());
            return self::FAILURE;
        }

        $this->info('CLEANUP COMPLETE — OM de teste, movimentos e usos removidos; os dois saldos foram recalculados para 0.');
        return self::SUCCESS;
    }

    private function audit(): array
    {
        $items = DB::table('stock_items')->where('tenant_id', self::TENANT_ID)->where('location_id', self::LOCATION_ID)
            ->whereIn('name', self::ITEM_NAMES)->orderBy('name')->get(['id', 'name', 'quantity', 'unit', 'location_id']);
        $itemIds = $items->pluck('id')->map(fn ($id) => (int) $id)->all();
        $maintenanceIds = DB::table('maintenance_records as mr')->join('vehicles as v', 'v.id', '=', 'mr.vehicle_id')
            ->where('mr.tenant_id', self::TENANT_ID)->where('v.division_id', self::DIVISION_ID)->where('v.location_id', self::LOCATION_ID)
            ->whereNotNull('mr.cancelled_at')->whereExists(fn ($query) => $query->selectRaw('1')->from('stock_movements as sm')->whereColumn('sm.maintenance_record_id', 'mr.id')->whereIn('sm.stock_item_id', $itemIds))
            ->pluck('mr.id')->map(fn ($id) => (int) $id)->all();
        $maintenance = $maintenanceIds ? DB::table('maintenance_records')->whereIn('id', $maintenanceIds)->get(['id', 'workflow_status', 'vehicle_id', 'created_at', 'cancelled_at']) : collect();
        $movements = $maintenanceIds ? DB::table('stock_movements as sm')->join('stock_items as si', 'si.id', '=', 'sm.stock_item_id')
            ->whereIn('sm.maintenance_record_id', $maintenanceIds)->orderBy('sm.id')
            ->get(['sm.id', 'sm.stock_item_id', 'si.name as item', 'sm.movement_type', 'sm.quantity', 'sm.maintenance_record_id', 'sm.reversal_movement_id', 'sm.reversed_from_movement_id', 'sm.cancelled_at', 'sm.description', 'sm.created_at']) : collect();
        $movementIds = $movements->pluck('id')->map(fn ($id) => (int) $id)->all();
        $usages = $maintenanceIds ? DB::table('maintenance_material_usages')->whereIn('maintenance_record_id', $maintenanceIds)->get() : collect();
        $children = [];
        foreach (['maintenance_record_items', 'maintenance_record_values', 'maintenance_record_extra_costs', 'maintenance_record_status_logs', 'maintenance_photos', 'maintenance_photo_upload_tokens'] as $table) {
            $children[$table] = $maintenanceIds && Schema::hasTable($table) ? DB::table($table)->whereIn('maintenance_record_id', $maintenanceIds)->count() : 0;
        }
        $otherMovements = $itemIds ? DB::table('stock_movements')->whereIn('stock_item_id', $itemIds)->whereNotIn('id', $movementIds ?: [0])->count() : 0;
        $safe = $items->count() === 2 && count($maintenanceIds) === 1 && $movements->isNotEmpty() && $otherMovements === 0;
        $reason = $safe ? 'Apenas uma OM cancelada candidata e nenhum movimento externo nos dois itens.' : 'Foram encontrados itens/OMs/movimentos fora do conjunto exclusivo de teste.';

        return compact('items', 'itemIds', 'maintenance', 'maintenanceIds', 'movements', 'movementIds', 'usages', 'children', 'otherMovements', 'safe', 'reason') + ['balances' => $items->pluck('quantity', 'name')->all()];
    }

    private function render(array $audit): void
    {
        $this->line('TEST MAINTENANCE TO DELETE');
        $this->table(['ID', 'Status', 'Vehicle', 'Created', 'Cancelled'], $audit['maintenance']->map(fn ($row) => [$row->id, $row->workflow_status, $row->vehicle_id, $row->created_at, $row->cancelled_at])->all());
        $this->line('STOCK MOVEMENTS TO DELETE');
        $this->table(['ID', 'Item', 'Type', 'Qty', 'OM', 'Reversal', 'Cancelled', 'Description'], $audit['movements']->map(fn ($row) => [$row->id, $row->item, $row->movement_type, $row->quantity, $row->maintenance_record_id, $row->reversal_movement_id ?: $row->reversed_from_movement_id, $row->cancelled_at, $row->description])->all());
        $this->line('MATERIAL USAGES TO DELETE: '.$audit['usages']->count());
        $this->line('MAINTENANCE CHILDREN: '.json_encode($audit['children']));
        $this->table(['Item', 'Balance before', 'Balance after'], $audit['items']->map(fn ($item) => [$item->name, $item->quantity.' '.$item->unit, '0 '.$item->unit])->all());
        $this->line('OTHER MOVEMENTS FOR THESE ITEMS: '.$audit['otherMovements']);
    }

    private function deleteAuditRows(array $audit): void
    {
        $ids = $audit['maintenanceIds'];
        $itemIds = DB::table('maintenance_record_items')->whereIn('maintenance_record_id', $ids)->pluck('id')->all();
        if ($itemIds && Schema::hasTable('maintenance_record_item_values')) DB::table('maintenance_record_item_values')->whereIn('maintenance_record_item_id', $itemIds)->delete();
        DB::table('maintenance_material_usages')->whereIn('maintenance_record_id', $ids)->delete();
        foreach (array_keys($audit['children']) as $table) DB::table($table)->whereIn('maintenance_record_id', $ids)->delete();
        DB::table('maintenance_record_items')->whereIn('maintenance_record_id', $ids)->delete();
        DB::table('stock_movements')->whereIn('id', $audit['movementIds'])->delete();
        DB::table('maintenance_records')->whereIn('id', $ids)->delete();
    }
}
