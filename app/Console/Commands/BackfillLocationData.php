<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class BackfillLocationData extends Command
{
    protected $signature = 'chm:backfill-location-data
                            {--commit : Apply the backfill inside a single database transaction}';

    protected $description = 'Backfill location_id for stock, procedures, tires, and tire entries';

    private const BARREIRAS = 1;
    private const LUIS_EDUARDO = 2;
    private const IMPERATRIZ = 3;

    private array $fallbacks = [];

    private array $problems = [];

    private array $stats = [];

    private Collection $locations;

    private Collection $allLocations;

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');

        $this->components->info($commit ? 'COMMIT mode' : 'DRY-RUN mode (no database writes)');

        try {
            $this->loadAndValidateLocations();
            $this->showSnapshot('Before');

            if ($commit) {
                DB::transaction(function (): void {
                    $this->runBackfill(true);
                    $this->validateFinalState(true);
                });
            } else {
                $this->runBackfill(false);
                $this->validateFinalState(false);
            }

            $this->showReport();

            if ($commit) {
                $this->showSnapshot('After');
                $this->components->info('Backfill committed successfully.');
            } else {
                $this->components->info('Dry-run completed. Run again with --commit to apply this plan.');
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function loadAndValidateLocations(): void
    {
        $this->allLocations = DB::table('locations')->get()->keyBy('id');
        $this->locations = $this->allLocations
            ->only([self::BARREIRAS, self::LUIS_EDUARDO, self::IMPERATRIZ]);

        foreach ([self::BARREIRAS, self::LUIS_EDUARDO, self::IMPERATRIZ] as $locationId) {
            if (! $this->locations->has($locationId)) {
                throw new RuntimeException("Required location {$locationId} does not exist.");
            }
        }

        $expectedNames = [
            self::BARREIRAS => 'barreiras',
            self::LUIS_EDUARDO => 'luís eduardo',
            self::IMPERATRIZ => 'imperatriz',
        ];

        foreach ($expectedNames as $locationId => $expectedName) {
            $actualName = mb_strtolower(trim((string) $this->locations[$locationId]->name));

            if ($actualName !== $expectedName) {
                throw new RuntimeException(
                    "Location {$locationId} must be {$expectedName}; found {$actualName}."
                );
            }
        }

        $tenantIds = $this->locations->pluck('tenant_id')->unique();
        $divisionIds = $this->locations->pluck('division_id')->unique();

        if ($tenantIds->count() !== 1 || $divisionIds->count() !== 1) {
            throw new RuntimeException('Approved locations must belong to the same tenant and division.');
        }

        $division = DB::table('divisions')->where('id', $divisionIds->first())->first();

        if (! $division || (int) $division->tenant_id !== (int) $tenantIds->first()) {
            throw new RuntimeException('Approved locations have an invalid tenant/division relationship.');
        }

        $missingProcedures = collect([7, 8, 9, 10, 11, 12, 13])
            ->diff(DB::table('procedures')->whereIn('id', [7, 8, 9, 10, 11, 12, 13])->pluck('id'));

        if ($missingProcedures->isNotEmpty()) {
            throw new RuntimeException(
                'Approved source procedures are missing: '.$missingProcedures->implode(', ').'.'
            );
        }
    }

    private function runBackfill(bool $write): void
    {
        $this->backfillStockItems($write);
        $this->backfillStockMovements($write);
        $this->backfillProcedures($write);
        $this->backfillTireEntries($write);
        $this->backfillTires($write);
    }

    private function backfillStockItems(bool $write): void
    {
        $approved = [
            1 => self::BARREIRAS,
            2 => self::BARREIRAS,
            8 => self::BARREIRAS,
            9 => self::BARREIRAS,
            10 => self::BARREIRAS,
            11 => self::LUIS_EDUARDO,
        ];

        foreach (DB::table('stock_items')->orderBy('id')->get() as $item) {
            if ($this->preserveValidLocation('stock_items', $item)) {
                continue;
            }

            $locationId = $approved[$item->id] ?? self::BARREIRAS;

            if (! isset($approved[$item->id])) {
                $this->fallback('stock_items', $item->id, 'Not listed in approved map; Barreiras fallback.');
            }

            $this->assertTenantMatchesLocation('stock_items', $item->id, $item->tenant_id, $locationId);
            $this->updateLocation('stock_items', $item->id, $item->location_id, $locationId, $write);
        }
    }

    private function backfillStockMovements(bool $write): void
    {
        $items = DB::table('stock_items')->get()->keyBy('id');

        foreach (DB::table('stock_movements')->orderBy('id')->get() as $movement) {
            if ($this->preserveValidLocation('stock_movements', $movement)) {
                continue;
            }

            $item = $items->get($movement->stock_item_id);

            if (! $item) {
                $this->problem('stock_movements', $movement->id, 'Stock item not found.');
                continue;
            }

            $locationId = $this->maintenanceLocationFromDescription($movement);

            if ($locationId === null) {
                $locationId = $this->plannedStockItemLocation($item);
                $this->fallback(
                    'stock_movements',
                    $movement->id,
                    'No recoverable maintenance; inherited stock item location.'
                );
            }

            $this->assertTenantMatchesLocation(
                'stock_movements',
                $movement->id,
                $movement->tenant_id ?? $item->tenant_id,
                $locationId
            );
            $this->updateLocation(
                'stock_movements',
                $movement->id,
                $movement->location_id,
                $locationId,
                $write
            );
        }
    }

    private function maintenanceLocationFromDescription(object $movement): ?int
    {
        if (! preg_match('/manuten(?:ç|c)[aã]o\s*#\s*(\d+)/iu', (string) $movement->description, $matches)) {
            return null;
        }

        $maintenance = DB::table('maintenance_records')
            ->join('vehicles', 'vehicles.id', '=', 'maintenance_records.vehicle_id')
            ->where('maintenance_records.id', (int) $matches[1])
            ->select([
                'maintenance_records.tenant_id as maintenance_tenant_id',
                'vehicles.tenant_id as vehicle_tenant_id',
                'vehicles.location_id',
            ])
            ->first();

        if (! $maintenance || ! $maintenance->location_id) {
            $this->problem(
                'stock_movements',
                $movement->id,
                "Maintenance #{$matches[1]} was not found or has no vehicle location."
            );

            return null;
        }

        $movementTenantId = $movement->tenant_id;

        if (
            $movementTenantId
            && (
                (int) $maintenance->maintenance_tenant_id !== (int) $movementTenantId
                || (int) $maintenance->vehicle_tenant_id !== (int) $movementTenantId
            )
        ) {
            $this->problem('stock_movements', $movement->id, 'Maintenance reference belongs to another tenant.');

            return null;
        }

        return (int) $maintenance->location_id;
    }

    private function plannedStockItemLocation(object $item): int
    {
        $existingLocationId = $this->validExistingLocationId($item);

        if ($existingLocationId !== null) {
            return $existingLocationId;
        }

        return match ((int) $item->id) {
            11 => self::LUIS_EDUARDO,
            default => self::BARREIRAS,
        };
    }

    private function backfillProcedures(bool $write): void
    {
        $approved = [
            7 => [self::BARREIRAS, self::LUIS_EDUARDO, self::IMPERATRIZ],
            8 => [self::BARREIRAS, self::IMPERATRIZ],
            9 => [self::BARREIRAS, self::LUIS_EDUARDO, self::IMPERATRIZ],
            10 => [self::BARREIRAS, self::LUIS_EDUARDO, self::IMPERATRIZ],
            11 => [self::BARREIRAS],
            12 => [self::IMPERATRIZ],
            13 => [self::BARREIRAS],
        ];

        $procedures = DB::table('procedures')->orderBy('id')->get();
        $approvedSources = $procedures
            ->whereIn('id', array_keys($approved))
            ->keyBy('id');
        $procedureMap = [];

        foreach ($procedures as $procedure) {
            $existingCloneSource = $approvedSources->first(function ($source, $sourceId) use ($approved, $procedure) {
                return (int) $sourceId !== (int) $procedure->id
                    && (int) $source->tenant_id === (int) $procedure->tenant_id
                    && $source->name === $procedure->name
                    && in_array((int) $procedure->location_id, $approved[$sourceId], true);
            });
            $hasValidLocation = $this->preserveValidLocation('procedures', $procedure);

            if ($existingCloneSource && $hasValidLocation) {
                $procedureMap[$existingCloneSource->id][(int) $procedure->location_id] = (int) $procedure->id;
                $this->stats['procedure_clones_preserved'] = ($this->stats['procedure_clones_preserved'] ?? 0) + 1;
                continue;
            }

            $locations = $approved[$procedure->id] ?? null;

            if ($hasValidLocation) {
                $currentLocationId = (int) $procedure->location_id;
                $procedureMap[$procedure->id][$currentLocationId] = (int) $procedure->id;

                foreach ($locations ?? [] as $locationId) {
                    if ($locationId === $currentLocationId) {
                        continue;
                    }

                    $this->assertTenantMatchesLocation('procedures', $procedure->id, $procedure->tenant_id, $locationId);
                    $procedureMap[$procedure->id][$locationId] = $this->findOrCloneProcedure(
                        $procedure,
                        $locationId,
                        $write
                    );
                }

                continue;
            }

            $locations ??= [self::BARREIRAS];

            if (! isset($approved[$procedure->id])) {
                $this->fallback('procedures', $procedure->id, 'Not listed in approved map; Barreiras fallback.');
            }

            foreach ($locations as $index => $locationId) {
                $this->assertTenantMatchesLocation('procedures', $procedure->id, $procedure->tenant_id, $locationId);

                if ($index === 0) {
                    $this->updateLocation(
                        'procedures',
                        $procedure->id,
                        $procedure->location_id,
                        $locationId,
                        $write
                    );
                    $procedureMap[$procedure->id][$locationId] = (int) $procedure->id;
                    continue;
                }

                $procedureMap[$procedure->id][$locationId] = $this->findOrCloneProcedure(
                    $procedure,
                    $locationId,
                    $write
                );
            }
        }

        $this->remapProcedureVehicles($procedureMap, $write);
    }

    private function findOrCloneProcedure(object $source, int $locationId, bool $write): int
    {
        $existing = DB::table('procedures')
            ->where('tenant_id', $source->tenant_id)
            ->where('location_id', $locationId)
            ->where('name', $source->name)
            ->orderBy('id')
            ->first();

        if ($existing) {
            $this->stats['procedure_clones_reused'] = ($this->stats['procedure_clones_reused'] ?? 0) + 1;

            return (int) $existing->id;
        }

        $this->stats['procedure_clones_planned'] = ($this->stats['procedure_clones_planned'] ?? 0) + 1;

        if (! $write) {
            return -(((int) $source->id * 10) + $locationId);
        }

        $procedureData = (array) $source;
        unset($procedureData['id']);
        $procedureData['location_id'] = $locationId;
        $procedureData['created_at'] = now();
        $procedureData['updated_at'] = now();

        $cloneId = (int) DB::table('procedures')->insertGetId($procedureData);

        foreach (DB::table('procedure_fields')->where('procedure_id', $source->id)->orderBy('id')->get() as $field) {
            $fieldData = (array) $field;
            unset($fieldData['id']);
            $fieldData['procedure_id'] = $cloneId;
            $fieldData['created_at'] = now();
            $fieldData['updated_at'] = now();
            DB::table('procedure_fields')->insert($fieldData);
        }

        $this->stats['procedure_clones_created'] = ($this->stats['procedure_clones_created'] ?? 0) + 1;

        return $cloneId;
    }

    private function remapProcedureVehicles(array $procedureMap, bool $write): void
    {
        $this->stats['procedure_vehicle_already_correct'] ??= 0;
        $this->stats['procedure_vehicle_remapped'] ??= 0;
        $this->stats['procedure_vehicle_missing_mapping'] ??= 0;

        $pivots = DB::table('procedure_vehicle')
            ->join('vehicles', 'vehicles.id', '=', 'procedure_vehicle.vehicle_id')
            ->join('procedures', 'procedures.id', '=', 'procedure_vehicle.procedure_id')
            ->select([
                'procedure_vehicle.id',
                'procedure_vehicle.procedure_id',
                'procedure_vehicle.vehicle_id',
                'vehicles.location_id',
                'procedures.location_id as procedure_location_id',
            ])
            ->orderBy('procedure_vehicle.id')
            ->get();

        foreach ($pivots as $pivot) {
            if (! $pivot->location_id) {
                $this->stats['procedure_vehicle_missing_mapping'] =
                    ($this->stats['procedure_vehicle_missing_mapping'] ?? 0) + 1;
                $this->problem('procedure_vehicle', $pivot->id, 'Vehicle has no location.');
                continue;
            }

            if ((int) $pivot->procedure_location_id === (int) $pivot->location_id) {
                $this->stats['procedure_vehicle_already_correct'] =
                    ($this->stats['procedure_vehicle_already_correct'] ?? 0) + 1;
                continue;
            }

            $targetId = $this->procedureTargetForLocation(
                $procedureMap,
                (int) $pivot->procedure_id,
                (int) $pivot->location_id
            );

            if (! $targetId) {
                $this->stats['procedure_vehicle_missing_mapping'] =
                    ($this->stats['procedure_vehicle_missing_mapping'] ?? 0) + 1;
                $this->problem('procedure_vehicle', $pivot->id, 'No approved procedure for vehicle location.');
                continue;
            }

            if ((int) $pivot->procedure_id === $targetId) {
                $this->stats['procedure_vehicle_already_correct'] =
                    ($this->stats['procedure_vehicle_already_correct'] ?? 0) + 1;
                continue;
            }

            $this->stats['procedure_vehicle_remapped'] = ($this->stats['procedure_vehicle_remapped'] ?? 0) + 1;

            if (! $write) {
                continue;
            }

            $duplicate = DB::table('procedure_vehicle')
                ->where('vehicle_id', $pivot->vehicle_id)
                ->where('procedure_id', $targetId)
                ->exists();

            if ($duplicate) {
                DB::table('procedure_vehicle')->where('id', $pivot->id)->delete();
            } else {
                DB::table('procedure_vehicle')->where('id', $pivot->id)->update([
                    'procedure_id' => $targetId,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function procedureTargetForLocation(array $procedureMap, int $procedureId, int $locationId): ?int
    {
        foreach ($procedureMap as $locationMap) {
            if (in_array($procedureId, $locationMap, true)) {
                return $locationMap[$locationId] ?? null;
            }
        }

        return $procedureMap[$procedureId][$locationId] ?? null;
    }

    private function backfillTireEntries(bool $write): void
    {
        $approved = [
            1 => self::BARREIRAS,
            2 => self::BARREIRAS,
            3 => self::IMPERATRIZ,
        ];

        foreach (DB::table('tire_entries')->orderBy('id')->get() as $entry) {
            if ($this->preserveValidLocation('tire_entries', $entry)) {
                continue;
            }

            $locationId = $approved[$entry->id] ?? self::BARREIRAS;

            if (! isset($approved[$entry->id])) {
                $this->fallback('tire_entries', $entry->id, 'Not listed in approved map; Barreiras fallback.');
            }

            $this->assertTenantMatchesLocation('tire_entries', $entry->id, $entry->tenant_id, $locationId);
            $this->updateLocation('tire_entries', $entry->id, $entry->location_id, $locationId, $write);
        }
    }

    private function backfillTires(bool $write): void
    {
        $entries = DB::table('tire_entries')->get()->keyBy('id');

        foreach (DB::table('tires')->orderBy('id')->get() as $tire) {
            if ($this->preserveValidLocation('tires', $tire)) {
                continue;
            }

            $locationId = $this->activeInstallationLocation($tire);
            $reason = 'active installation';

            if ($locationId === null) {
                $locationId = $this->latestInstallationLocation($tire);
                $reason = 'latest historical installation';
            }

            if ($locationId === null && $tire->entry_id && $entries->has($tire->entry_id)) {
                $entry = $entries[$tire->entry_id];
                $locationId = $this->plannedTireEntryLocation($entry);
                $reason = 'tire entry';
            }

            if ($locationId === null) {
                $entryId = DB::table('tire_entry_items')
                    ->where('tire_id', $tire->id)
                    ->value('tire_entry_id');

                if ($entryId && $entries->has($entryId)) {
                    $locationId = $this->plannedTireEntryLocation($entries[$entryId]);
                    $reason = 'tire entry item';
                }
            }

            if ($locationId === null) {
                $locationId = self::BARREIRAS;
                $reason = 'Barreiras fallback';
                $this->fallback('tires', $tire->id, 'No installation or tire entry location.');
            }

            $this->assertTenantMatchesLocation('tires', $tire->id, $tire->tenant_id, $locationId);
            $this->stats["tires_from_{$reason}"] = ($this->stats["tires_from_{$reason}"] ?? 0) + 1;
            $this->updateLocation('tires', $tire->id, $tire->location_id, $locationId, $write);
        }
    }

    private function activeInstallationLocation(object $tire): ?int
    {
        return $this->installationLocationQuery($tire)
            ->where('tire_installations.active', true)
            ->orderByDesc('tire_installations.installed_at')
            ->orderByDesc('tire_installations.id')
            ->value('vehicles.location_id');
    }

    private function latestInstallationLocation(object $tire): ?int
    {
        return $this->installationLocationQuery($tire)
            ->orderByRaw('COALESCE(tire_installations.removed_at, tire_installations.installed_at) DESC')
            ->orderByDesc('tire_installations.id')
            ->value('vehicles.location_id');
    }

    private function installationLocationQuery(object $tire)
    {
        return DB::table('tire_installations')
            ->join('vehicles', 'vehicles.id', '=', 'tire_installations.vehicle_id')
            ->where('tire_installations.tire_id', $tire->id)
            ->where('tire_installations.tenant_id', $tire->tenant_id)
            ->where('vehicles.tenant_id', $tire->tenant_id)
            ->whereNotNull('vehicles.location_id');
    }

    private function plannedTireEntryLocation(object $entry): int
    {
        return $this->validExistingLocationId($entry) ?? match ((int) $entry->id) {
            3 => self::IMPERATRIZ,
            default => self::BARREIRAS,
        };
    }

    private function preserveValidLocation(string $table, object $record): bool
    {
        if (! $record->location_id) {
            $this->stats["{$table}_null_fill_planned"] =
                ($this->stats["{$table}_null_fill_planned"] ?? 0) + 1;

            return false;
        }

        $location = $this->allLocations->get((int) $record->location_id);

        if (! $location) {
            $this->stats["{$table}_missing_location_repair_planned"] =
                ($this->stats["{$table}_missing_location_repair_planned"] ?? 0) + 1;

            return false;
        }

        if ((int) $location->tenant_id !== (int) $record->tenant_id) {
            $this->stats["{$table}_incompatible_location_repair_planned"] =
                ($this->stats["{$table}_incompatible_location_repair_planned"] ?? 0) + 1;

            return false;
        }

        $this->stats["{$table}_preserved"] = ($this->stats["{$table}_preserved"] ?? 0) + 1;

        return true;
    }

    private function validExistingLocationId(object $record): ?int
    {
        if (! $record->location_id) {
            return null;
        }

        $location = $this->allLocations->get((int) $record->location_id);

        if (! $location || (int) $location->tenant_id !== (int) $record->tenant_id) {
            return null;
        }

        return (int) $location->id;
    }

    private function updateLocation(
        string $table,
        int $id,
        mixed $currentLocationId,
        int $targetLocationId,
        bool $write
    ): void {
        if ((int) $currentLocationId === $targetLocationId) {
            $this->stats["{$table}_unchanged"] = ($this->stats["{$table}_unchanged"] ?? 0) + 1;
            return;
        }

        $this->stats["{$table}_updates"] = ($this->stats["{$table}_updates"] ?? 0) + 1;

        if ($write) {
            DB::table($table)->where('id', $id)->update(['location_id' => $targetLocationId]);
        }
    }

    private function assertTenantMatchesLocation(
        string $table,
        int $id,
        mixed $tenantId,
        int $locationId
    ): void {
        $location = $this->locations->get($locationId);

        if (! $tenantId || ! $location || (int) $tenantId !== (int) $location->tenant_id) {
            throw new RuntimeException("{$table} #{$id} is incompatible with location {$locationId}.");
        }
    }

    private function validateFinalState(bool $committed): void
    {
        if (! $committed) {
            $this->stats['validation_mode'] = 'planned validation; database remains unchanged';
            return;
        }

        foreach (['stock_items', 'stock_movements', 'procedures', 'tires', 'tire_entries'] as $table) {
            $nulls = DB::table($table)->whereNull('location_id')->count();

            if ($nulls > 0) {
                throw new RuntimeException("Validation failed: {$table} still has {$nulls} null location_id values.");
            }
        }

        $this->validateTenantLocationCompatibility();
        $this->validateProcedureClones();
        $this->validateProcedureVehicles();
        $this->validateInstalledTires();
        $this->validateNeverInstalledTires();
        $this->validateStockMovementsWithoutMaintenance();
    }

    private function validateTenantLocationCompatibility(): void
    {
        foreach (['stock_items', 'stock_movements', 'procedures', 'tires', 'tire_entries'] as $table) {
            $invalid = DB::table($table)
                ->join('locations', 'locations.id', '=', "{$table}.location_id")
                ->whereColumn("{$table}.tenant_id", '!=', 'locations.tenant_id')
                ->count();

            if ($invalid > 0) {
                throw new RuntimeException("Validation failed: {$table} has {$invalid} tenant/location mismatches.");
            }
        }
    }

    private function validateProcedureClones(): void
    {
        foreach ([7 => [1, 2, 3], 8 => [1, 3], 9 => [1, 2, 3], 10 => [1, 2, 3]] as $sourceId => $locations) {
            $source = DB::table('procedures')->where('id', $sourceId)->first();
            $sourceFields = DB::table('procedure_fields')->where('procedure_id', $sourceId)->count();

            foreach ($locations as $locationId) {
                $clone = DB::table('procedures')
                    ->where('tenant_id', $source->tenant_id)
                    ->where('name', $source->name)
                    ->where('location_id', $locationId)
                    ->first();

                if (! $clone) {
                    throw new RuntimeException("Validation failed: procedure {$sourceId} missing at location {$locationId}.");
                }

                $cloneFields = DB::table('procedure_fields')->where('procedure_id', $clone->id)->count();

                if ($cloneFields !== $sourceFields) {
                    throw new RuntimeException("Validation failed: procedure {$clone->id} field count differs from source.");
                }
            }
        }
    }

    private function validateProcedureVehicles(): void
    {
        $missingLocations = DB::table('procedure_vehicle')
            ->join('vehicles', 'vehicles.id', '=', 'procedure_vehicle.vehicle_id')
            ->join('procedures', 'procedures.id', '=', 'procedure_vehicle.procedure_id')
            ->where(function ($query): void {
                $query->whereNull('vehicles.location_id')
                    ->orWhereNull('procedures.location_id');
            })
            ->count();

        if ($missingLocations > 0) {
            throw new RuntimeException("Validation failed: {$missingLocations} procedure_vehicle links have no location.");
        }

        $duplicates = DB::table('procedure_vehicle')
            ->select('vehicle_id', 'procedure_id', DB::raw('COUNT(*) as total'))
            ->groupBy('vehicle_id', 'procedure_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($duplicates > 0) {
            throw new RuntimeException("Validation failed: {$duplicates} duplicated procedure_vehicle groups.");
        }

        $incompatible = DB::table('procedure_vehicle')
            ->join('vehicles', 'vehicles.id', '=', 'procedure_vehicle.vehicle_id')
            ->join('procedures', 'procedures.id', '=', 'procedure_vehicle.procedure_id')
            ->whereColumn('vehicles.location_id', '!=', 'procedures.location_id')
            ->count();

        if ($incompatible > 0) {
            throw new RuntimeException("Validation failed: {$incompatible} procedure_vehicle location mismatches.");
        }
    }

    private function validateInstalledTires(): void
    {
        $invalid = DB::table('tire_installations')
            ->join('tires', 'tires.id', '=', 'tire_installations.tire_id')
            ->join('vehicles', 'vehicles.id', '=', 'tire_installations.vehicle_id')
            ->where('tire_installations.active', true)
            ->whereColumn('tires.location_id', '!=', 'vehicles.location_id')
            ->count();

        if ($invalid > 0) {
            throw new RuntimeException("Validation failed: {$invalid} active installed tires have another location.");
        }
    }

    private function validateNeverInstalledTires(): void
    {
        $invalid = DB::table('tires')
            ->join('tire_entries', 'tire_entries.id', '=', 'tires.entry_id')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('tire_installations')
                    ->whereColumn('tire_installations.tire_id', 'tires.id');
            })
            ->whereColumn('tires.location_id', '!=', 'tire_entries.location_id')
            ->count();

        if ($invalid > 0) {
            throw new RuntimeException("Validation failed: {$invalid} never-installed tires differ from their entry.");
        }

        $invalidEntryItems = DB::table('tires')
            ->join('tire_entry_items', 'tire_entry_items.tire_id', '=', 'tires.id')
            ->join('tire_entries', 'tire_entries.id', '=', 'tire_entry_items.tire_entry_id')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('tire_installations')
                    ->whereColumn('tire_installations.tire_id', 'tires.id');
            })
            ->whereColumn('tires.location_id', '!=', 'tire_entries.location_id')
            ->count();

        if ($invalidEntryItems > 0) {
            throw new RuntimeException(
                "Validation failed: {$invalidEntryItems} never-installed tires differ from their entry item."
            );
        }
    }

    private function validateStockMovementsWithoutMaintenance(): void
    {
        $invalid = DB::table('stock_movements')
            ->join('stock_items', 'stock_items.id', '=', 'stock_movements.stock_item_id')
            ->select([
                'stock_movements.description',
                'stock_movements.location_id as movement_location_id',
                'stock_items.location_id as item_location_id',
            ])
            ->get()
            ->filter(function ($movement): bool {
                $hasMaintenanceReference = preg_match(
                    '/manuten(?:ç|c)[aã]o\s*#\s*(\d+)/iu',
                    (string) $movement->description
                );

                return ! $hasMaintenanceReference
                    && (int) $movement->movement_location_id !== (int) $movement->item_location_id;
            })
            ->count();

        if ($invalid > 0) {
            throw new RuntimeException("Validation failed: {$invalid} non-maintenance movements differ from their item.");
        }
    }

    private function showSnapshot(string $label): void
    {
        $rows = [];

        foreach (['stock_items', 'stock_movements', 'procedures', 'tires', 'tire_entries'] as $table) {
            $rows[] = [
                $table,
                DB::table($table)->count(),
                DB::table($table)->whereNull('location_id')->count(),
            ];
        }

        $this->newLine();
        $this->line("<info>{$label} snapshot</info>");
        $this->table(['Table', 'Rows', 'Null location_id'], $rows);
    }

    private function showReport(): void
    {
        $this->newLine();
        $this->line('<info>Backfill report</info>');

        $rows = collect($this->stats)
            ->sortKeys()
            ->map(fn ($value, $key) => [$key, $value])
            ->values()
            ->all();

        $this->table(['Metric', 'Value'], $rows ?: [['No changes planned', 0]]);

        $this->line('<comment>Fallbacks</comment>');
        $this->table(['Table', 'ID', 'Reason'], $this->fallbacks ?: [['-', '-', 'None']]);

        $this->line('<comment>Problems</comment>');
        $this->table(['Table', 'ID', 'Problem'], $this->problems ?: [['-', '-', 'None']]);
    }

    private function fallback(string $table, int $id, string $reason): void
    {
        $this->fallbacks[] = [$table, $id, $reason];
    }

    private function problem(string $table, int $id, string $reason): void
    {
        $this->problems[] = [$table, $id, $reason];
    }
}
