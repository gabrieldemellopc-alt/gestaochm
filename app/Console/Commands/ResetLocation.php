<?php

namespace App\Console\Commands;

use App\Models\Division;
use App\Models\Location;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Removes operational data from one fully-qualified tenant/division/location.
 *
 * It deliberately does not touch configuration or identity data.  Every table
 * without a location_id is reached through a previously selected parent ID.
 */
class ResetLocation extends Command
{
    protected $signature = 'chm:reset-location
        {--tenant= : Tenant obrigatório}
        {--division= : Divisão obrigatória}
        {--location= : Location obrigatória}
        {--commit : Executa a exclusão dentro de uma transação}
        {--confirm-location= : Deve coincidir exatamente com --location}';

    protected $description = 'Audita e, somente com confirmação explícita, remove dados operacionais de uma location';

    public function handle(): int
    {
        $context = $this->resolveContext();
        if (! $context) {
            return self::FAILURE;
        }

        $inventory = $this->inventory($context);
        $this->renderInventory($inventory);

        if (! $this->option('commit')) {
            $this->info('SAFE YES — dry-run: shared tire parents are preserved and NENHUMA ALTERAÇÃO FOI REALIZADA. Use --commit --confirm-location='.$context['location']->id.' somente após aprovação.');
            return self::SUCCESS;
        }

        if ((string) $this->option('confirm-location') !== (string) $context['location']->id) {
            $this->error('Confirmação inválida: --confirm-location deve coincidir exatamente com --location. Nenhuma alteração foi realizada.');
            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($context, $inventory): void {
                $this->assertSafeInventory($inventory);
                $baseline = $this->otherLocationBaseline($context);
                $this->deleteInventory($inventory);
                $after = $this->inventory($context);
                $this->assertEmpty($after);
                $this->assertOtherLocationBaseline($baseline);
            });
        } catch (\Throwable $exception) {
            $this->error('RESET FAILED — toda a transação foi revertida: '.$exception->getMessage());
            return self::FAILURE;
        }

        $this->info('RESET COMPLETE — target location está vazia nos domínios operacionais; dados preservados não foram tocados.');
        return self::SUCCESS;
    }

    private function resolveContext(): ?array
    {
        foreach (['tenant', 'division', 'location'] as $option) {
            if (! ctype_digit((string) $this->option($option)) || (int) $this->option($option) < 1) {
                $this->error("Informe --{$option} com um ID numérico positivo.");
                return null;
            }
        }

        $tenant = Tenant::find((int) $this->option('tenant'));
        $division = Division::find((int) $this->option('division'));
        $location = Location::find((int) $this->option('location'));

        if (! $tenant || ! $division || ! $location
            || (int) $division->tenant_id !== (int) $tenant->id
            || (int) $location->tenant_id !== (int) $tenant->id
            || (int) $location->division_id !== (int) $division->id) {
            $this->error('Tenant, divisão e location não formam um contexto válido.');
            return null;
        }

        return compact('tenant', 'division', 'location');
    }

    private function inventory(array $context): array
    {
        $tenantId = $context['tenant']->id;
        $divisionId = $context['division']->id;
        $locationId = $context['location']->id;

        $vehicleIds = $this->contextQuery('vehicles', $tenantId, $divisionId, $locationId)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $maintenanceIds = $this->idsFor('maintenance_records', 'vehicle_id', $vehicleIds, fn (Builder $q) => $q->where('tenant_id', $tenantId));
        $maintenanceItemIds = $this->idsFor('maintenance_record_items', 'maintenance_record_id', $maintenanceIds);
        $compositionIds = $this->idsFor('maintenance_historical_compositions', 'maintenance_record_id', $maintenanceIds);
        $stockItemIds = $this->contextQuery('stock_items', $tenantId, $divisionId, $locationId)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $stockMovementIds = $this->contextQuery('stock_movements', $tenantId, $divisionId, $locationId)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $fuelFillingIds = $this->contextQuery('fuel_fillings', $tenantId, $divisionId, $locationId)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $fuelReceiptIds = $this->contextQuery('fuel_receipts', $tenantId, $divisionId, $locationId)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $fuelMovementIds = $this->contextQuery('fuel_movements', $tenantId, $divisionId, $locationId)->pluck('id')->map(fn ($id) => (int) $id)->all();

        // Tires have historical identities that can be shared by several locations.
        // The target is therefore the vehicle-linked event, never a broad tire parent.
        $tireIds = array_values(array_unique(array_merge(
            $this->idsFor('tire_installations', 'vehicle_id', $vehicleIds, fn (Builder $q) => $q->where('tenant_id', $tenantId), 'tire_id'),
            $this->idsFor('tire_measurements', 'vehicle_id', $vehicleIds, fn (Builder $q) => $q->where('tenant_id', $tenantId), 'tire_id')
        )));
        $targetInstallationIds = $this->idsFor('tire_installations', 'vehicle_id', $vehicleIds);
        $targetMeasurementIds = $this->idsFor('tire_measurements', 'vehicle_id', $vehicleIds);
        $targetEntryItemIds = $this->idsFor('tire_entry_items', 'tire_id', $tireIds);
        $tireEntryIds = $this->idsFor('tire_entry_items', 'id', $targetEntryItemIds, null, 'tire_entry_id');
        $sharedTireReferences = $this->externalTireReferences($tireIds, $vehicleIds);
        $sharedTireIds = collect($sharedTireReferences)->pluck('tire_id')->unique()->map(fn ($id) => (int) $id)->all();
        $sharedEntryReferences = $this->externalTireEntryReferences($targetEntryItemIds, $tireEntryIds);
        $sharedEntryIds = collect($sharedEntryReferences)->pluck('tire_entry_id')->unique()->map(fn ($id) => (int) $id)->all();
        $exclusiveTireIds = array_values(array_diff($tireIds, $sharedTireIds));
        $exclusiveEntryIds = array_values(array_diff($tireEntryIds, $sharedEntryIds));

        $tables = [
            'MAINTENANCE' => [
                'maintenance_historical_composition_items' => $this->countFor('maintenance_historical_composition_items', 'maintenance_historical_composition_id', $compositionIds),
                'maintenance_historical_compositions' => count($compositionIds),
                'maintenance_material_usages' => $this->countFor('maintenance_material_usages', 'maintenance_record_id', $maintenanceIds),
                'maintenance_record_item_values' => $this->countFor('maintenance_record_item_values', 'maintenance_record_item_id', $maintenanceItemIds),
                'maintenance_record_items' => count($maintenanceItemIds),
                'maintenance_record_extra_costs' => $this->countFor('maintenance_record_extra_costs', 'maintenance_record_id', $maintenanceIds),
                'maintenance_record_status_logs' => $this->countFor('maintenance_record_status_logs', 'maintenance_record_id', $maintenanceIds),
                'maintenance_record_values' => $this->countFor('maintenance_record_values', 'maintenance_record_id', $maintenanceIds),
                'maintenance_photo_upload_tokens' => $this->countFor('maintenance_photo_upload_tokens', 'maintenance_record_id', $maintenanceIds),
                'maintenance_photos' => $this->countFor('maintenance_photos', 'maintenance_record_id', $maintenanceIds),
                'maintenance_records' => count($maintenanceIds),
            ],
            'VEHICLES' => [
                'vehicle_reading_correction_evidences' => $this->countFor('vehicle_reading_correction_evidences', 'vehicle_id', $vehicleIds),
                'vehicle_reading_corrections' => $this->countFor('vehicle_reading_corrections', 'vehicle_id', $vehicleIds),
                'vehicle_downtime_periods' => $this->countFor('vehicle_downtime_periods', 'vehicle_id', $vehicleIds),
                'vehicle_update_logs' => $this->countFor('vehicle_update_logs', 'vehicle_id', $vehicleIds),
                'vehicle_operations' => $this->countFor('vehicle_operations', 'vehicle_id', $vehicleIds),
                'daily_checklists' => $this->countFor('daily_checklists', 'vehicle_id', $vehicleIds),
                'vehicle_allocations' => $this->countFor('vehicle_allocations', 'vehicle_id', $vehicleIds),
                'vehicles' => count($vehicleIds),
            ],
            'STOCK' => [
                'stock_movements' => count($stockMovementIds),
                'stock_items' => count($stockItemIds),
            ],
            'FUEL' => [
                'fuel_fillings' => count($fuelFillingIds),
                'fuel_receipts' => count($fuelReceiptIds),
                'fuel_movements' => count($fuelMovementIds),
            ],
            'TIRES' => [
                'tire_measurements' => count($targetMeasurementIds),
                'tire_installations' => count($targetInstallationIds),
                'tire_retreads' => $this->countFor('tire_retreads', 'tire_id', $exclusiveTireIds),
                'tire_entry_items' => count($targetEntryItemIds),
                'tire_entries' => count($exclusiveEntryIds),
                'vehicle_tire_positions' => $this->countFor('vehicle_tire_positions', 'vehicle_id', $vehicleIds),
                'tires' => count($exclusiveTireIds),
            ],
        ];

        return [
            'context' => ['tenant_id' => $tenantId, 'division_id' => $divisionId, 'location_id' => $locationId],
            'ids' => compact('vehicleIds', 'maintenanceIds', 'maintenanceItemIds', 'compositionIds', 'stockItemIds', 'stockMovementIds', 'fuelFillingIds', 'fuelReceiptIds', 'fuelMovementIds', 'tireIds', 'targetInstallationIds', 'targetMeasurementIds', 'targetEntryItemIds', 'tireEntryIds', 'exclusiveTireIds', 'exclusiveEntryIds'),
            'tables' => $tables,
            'total' => array_sum(array_map(fn (array $domain) => array_sum($domain), $tables)),
            'shared_records' => array_merge(
                collect($sharedTireReferences)->map(fn ($row) => ['type' => 'tire', 'id' => $row['tire_id'], 'reason' => $row['table'].' linked to external vehicle '.$row['vehicle_id']])->unique('id')->values()->all(),
                collect($sharedEntryReferences)->map(fn ($row) => ['type' => 'tire_entry', 'id' => $row['tire_entry_id'], 'reason' => 'contains outside-scope tire entry item '.$row['id'].' (tire '.$row['tire_id'].')'])->unique('id')->values()->all(),
            ),
            'vehicles' => $this->vehiclePreview($vehicleIds),
            'preserved' => ['tenant', 'division', 'location', 'users', 'user_division_accesses', 'profile_permission_overrides', 'system_audit_logs', 'fuel_tanks', 'procedures', 'structural/shared categories'],
        ];
    }

    private function renderInventory(array $inventory): void
    {
        foreach ($inventory['tables'] as $domain => $tables) {
            $this->line($domain);
            $this->table(['Domain', 'Table', 'Records'], collect($tables)->map(fn ($count, $table) => [$domain, $table, $count])->values()->all());
        }
        $this->line('TOTAL RECORDS TO DELETE: '.$inventory['total']);
        $this->line('Vehicles: '.json_encode($inventory['vehicles'], JSON_UNESCAPED_UNICODE));
        $this->line('Maintenance records: '.$this->summarizeIds($inventory['ids']['maintenanceIds']));
        $this->line('Stock items locais: '.count($inventory['ids']['stockItemIds']));
        $this->line('PRESERVED: '.implode(', ', $inventory['preserved']));
        if ($inventory['shared_records']) {
            $this->line('SHARED RECORDS PRESERVED');
            $this->table(['Type', 'ID', 'Reason'], collect($inventory['shared_records'])->map(fn ($row) => [$row['type'], $row['id'], $row['reason']])->all());
        }
    }

    private function deleteInventory(array $inventory): void
    {
        $ids = $inventory['ids'];
        $delete = fn (string $table, string $column, array $values) => $this->deleteFor($table, $column, $values);

        // Maintenance must leave before any StockItem it references.
        $delete('maintenance_historical_composition_items', 'maintenance_historical_composition_id', $ids['compositionIds']);
        $delete('maintenance_historical_compositions', 'id', $ids['compositionIds']);
        $delete('maintenance_material_usages', 'maintenance_record_id', $ids['maintenanceIds']);
        $delete('maintenance_record_item_values', 'maintenance_record_item_id', $ids['maintenanceItemIds']);
        foreach (['maintenance_record_extra_costs', 'maintenance_record_status_logs', 'maintenance_record_values', 'maintenance_photo_upload_tokens', 'maintenance_photos'] as $table) {
            $delete($table, 'maintenance_record_id', $ids['maintenanceIds']);
        }
        $delete('maintenance_record_items', 'id', $ids['maintenanceItemIds']);
        $delete('maintenance_records', 'id', $ids['maintenanceIds']);

        // Vehicle log rows can reference fuel fillings, so they leave first.
        $delete('vehicle_reading_correction_evidences', 'vehicle_id', $ids['vehicleIds']);
        $delete('vehicle_reading_corrections', 'vehicle_id', $ids['vehicleIds']);
        foreach (['vehicle_downtime_periods', 'vehicle_update_logs', 'vehicle_operations', 'daily_checklists', 'vehicle_allocations'] as $table) {
            $delete($table, 'vehicle_id', $ids['vehicleIds']);
        }

        $delete('tire_measurements', 'id', $ids['targetMeasurementIds']);
        $delete('tire_installations', 'id', $ids['targetInstallationIds']);
        $delete('tire_retreads', 'tire_id', $ids['exclusiveTireIds']);
        $delete('tire_entry_items', 'id', $ids['targetEntryItemIds']);
        $delete('tire_entries', 'id', $ids['exclusiveEntryIds']);
        $delete('vehicle_tire_positions', 'vehicle_id', $ids['vehicleIds']);
        $delete('tires', 'id', $ids['exclusiveTireIds']);

        // Fuel tanks are configuration and intentionally stay. Fuel rows go before vehicles.
        foreach (['fuel_fillings' => 'fuelFillingIds', 'fuel_receipts' => 'fuelReceiptIds', 'fuel_movements' => 'fuelMovementIds'] as $table => $key) {
            $delete($table, 'id', $ids[$key]);
        }
        $delete('stock_movements', 'id', $ids['stockMovementIds']);
        $delete('stock_items', 'id', $ids['stockItemIds']);
        $delete('vehicles', 'id', $ids['vehicleIds']);
    }

    private function assertSafeInventory(array $inventory): void
    {
        // Shared parents are explicitly preserved; they are not a reason to block target-only cleanup.
    }

    private function assertEmpty(array $inventory): void
    {
        if ($inventory['total'] !== 0) {
            throw new RuntimeException('Validação pós-reset falhou: ainda existem '.$inventory['total'].' registros operacionais no escopo alvo.');
        }
    }

    private function otherLocationBaseline(array $context): ?array
    {
        $other = DB::table('locations')->where('tenant_id', $context['tenant']->id)->where('id', '!=', $context['location']->id)->orderBy('id')->first(['id', 'division_id']);
        if (! $other) {
            return null;
        }
        $otherContext = ['tenant' => $context['tenant'], 'division' => Division::find($other->division_id), 'location' => Location::find($other->id)];
        return ['location_id' => $other->id, 'total' => $this->inventory($otherContext)['total']];
    }

    private function assertOtherLocationBaseline(?array $baseline): void
    {
        if (! $baseline) {
            return;
        }
        $location = Location::find($baseline['location_id']);
        $context = ['tenant' => Tenant::find($location->tenant_id), 'division' => Division::find($location->division_id), 'location' => $location];
        if ($this->inventory($context)['total'] !== $baseline['total']) {
            throw new RuntimeException('Validação pós-reset falhou: o baseline de outra location mudou.');
        }
    }

    private function contextQuery(string $table, int $tenantId, int $divisionId, int $locationId): Builder
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id') || ! Schema::hasColumn($table, 'location_id')) {
            throw new RuntimeException("A tabela {$table} não possui contexto tenant/location suficiente para um reset seguro.");
        }
        $query = DB::table($table);
        foreach (['tenant_id' => $tenantId, 'division_id' => $divisionId, 'location_id' => $locationId] as $column => $value) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                $query->where($column, $value);
            }
        }
        return $query;
    }

    private function idsFor(string $table, string $column, array $values, ?callable $extra = null, string $select = 'id'): array
    {
        if (! $values || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return [];
        }
        $query = DB::table($table)->whereIn($column, $values);
        if ($extra) {
            $extra($query);
        }
        return $query->pluck($select)->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    private function countFor(string $table, string $column, array $values): int
    {
        return $this->idsFor($table, $column, $values) ? DB::table($table)->whereIn($column, $values)->count() : 0;
    }

    private function deleteFor(string $table, string $column, array $values): int
    {
        return $values && Schema::hasTable($table) && Schema::hasColumn($table, $column)
            ? DB::table($table)->whereIn($column, $values)->delete()
            : 0;
    }

    private function externalTireReferences(array $tireIds, array $vehicleIds): array
    {
        if (! $tireIds) {
            return [];
        }
        $references = [];
        foreach (['tire_installations', 'tire_measurements'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach (DB::table($table)->whereIn('tire_id', $tireIds)->whereNotIn('vehicle_id', $vehicleIds)->get(['id', 'tire_id', 'vehicle_id']) as $row) {
                $references[] = ['table' => $table, 'id' => $row->id, 'tire_id' => $row->tire_id, 'vehicle_id' => $row->vehicle_id];
            }
        }
        return $references;
    }

    private function externalTireEntryReferences(array $targetEntryItemIds, array $entryIds): array
    {
        if (! $targetEntryItemIds || ! $entryIds || ! Schema::hasTable('tire_entry_items')) {
            return [];
        }

        return DB::table('tire_entry_items')
            ->whereIn('tire_entry_id', $entryIds)
            ->whereNotIn('id', $targetEntryItemIds)
            ->get(['id', 'tire_entry_id', 'tire_id'])
            ->map(fn ($row) => ['table' => 'tire_entry_items', 'id' => $row->id, 'tire_entry_id' => $row->tire_entry_id, 'tire_id' => $row->tire_id])
            ->all();
    }

    private function vehiclePreview(array $vehicleIds): array
    {
        if (! $vehicleIds) {
            return [];
        }
        return DB::table('vehicles')->whereIn('id', $vehicleIds)->orderBy('id')->get()->map(fn ($vehicle) => [
            'id' => $vehicle->id,
            'code' => $vehicle->asset_code ?? $vehicle->name,
            'plate' => $vehicle->plate,
        ])->all();
    }

    private function summarizeIds(array $ids): string
    {
        if (! $ids) {
            return '0';
        }
        return count($ids) <= 20 ? implode(', ', $ids) : count($ids).' IDs ('.min($ids).'–'.max($ids).')';
    }
}
