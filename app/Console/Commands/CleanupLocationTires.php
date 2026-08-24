<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CleanupLocationTires extends Command
{
    protected $signature = 'chm:cleanup-location-tires {--tenant=} {--division=} {--location=} {--commit} {--confirm-location=}';
    protected $description = 'Audita e remove somente pneus exclusivos de uma location; dry-run por padrão';

    public function handle(): int
    {
        foreach (['tenant', 'division', 'location'] as $option) {
            if (! ctype_digit((string) $this->option($option))) {
                $this->error("Informe --{$option} com um ID numérico.");
                return self::FAILURE;
            }
        }

        $tenant = (int) $this->option('tenant');
        $division = (int) $this->option('division');
        $location = (int) $this->option('location');
        $valid = DB::table('locations')->where('id', $location)->where('tenant_id', $tenant)->where('division_id', $division)->exists();
        if (! $valid) {
            $this->error('Contexto tenant/division/location inválido.');
            return self::FAILURE;
        }

        $plan = $this->plan($tenant, $location);
        $this->render($plan);

        if (! $this->option('commit')) {
            $this->info('SAFE '.($plan['safe'] ? 'YES' : 'NO').' — dry-run: nenhuma alteração foi realizada.');
            return self::SUCCESS;
        }
        if (! $plan['safe'] || (string) $this->option('confirm-location') !== (string) $location) {
            $this->error('Commit bloqueado: plano inseguro ou confirmação inválida. Nenhuma alteração foi realizada.');
            return self::FAILURE;
        }

        DB::transaction(function () use ($plan): void {
            $this->delete('tire_retreads', 'tire_id', $plan['delete_tire_ids']);
            $this->delete('tire_entry_items', 'tire_id', $plan['delete_tire_ids']);
            $this->delete('tire_measurements', 'tire_id', $plan['delete_tire_ids']);
            $this->delete('tire_installations', 'tire_id', $plan['delete_tire_ids']);
            $this->delete('tires', 'id', $plan['delete_tire_ids']);
            $this->delete('tire_entries', 'id', $plan['delete_entry_ids']);
        });

        $after = $this->plan((int) $this->option('tenant'), (int) $this->option('location'));
        if ($after['delete_tire_ids'] !== []) {
            $this->error('Validação pós-commit falhou; transação deveria ter removido todos os pneus exclusivos.');
            return self::FAILURE;
        }
        $this->info('CLEANUP COMPLETE — somente pneus exclusivos foram removidos.');
        return self::SUCCESS;
    }

    private function plan(int $tenant, int $location): array
    {
        if (! Schema::hasColumn('tires', 'location_id')) {
            return ['delete_tire_ids' => [], 'shared' => [], 'review' => [['id' => null, 'reason' => 'tires.location_id não existe; não há origem segura para exclusão.']], 'entries' => [], 'delete_entry_ids' => [], 'safe' => false];
        }
        $tires = DB::table('tires')->where('tenant_id', $tenant)->where('location_id', $location)->orderBy('id')->get();
        $delete = []; $shared = []; $review = []; $entryIds = [];
        foreach ($tires as $tire) {
            $id = (int) $tire->id;
            $outsideVehicles = DB::table('tire_installations as ti')->join('vehicles as v', 'v.id', '=', 'ti.vehicle_id')->where('ti.tire_id', $id)->where('v.location_id', '!=', $location)->get(['ti.id as installation_id', 'v.id as vehicle_id', 'v.location_id']);
            $outsideMeasurements = DB::table('tire_measurements as tm')->join('vehicles as v', 'v.id', '=', 'tm.vehicle_id')->where('tm.tire_id', $id)->where('v.location_id', '!=', $location)->get(['tm.id as measurement_id', 'v.id as vehicle_id', 'v.location_id']);
            $entries = DB::table('tire_entry_items')->where('tire_id', $id)->pluck('tire_entry_id')->map(fn ($value) => (int) $value)->all();
            $entryIds = array_merge($entryIds, $entries);
            $outsideEntryItems = DB::table('tire_entry_items as tei')->join('tires as other', 'other.id', '=', 'tei.tire_id')->whereIn('tei.tire_entry_id', $entries ?: [0])->where('other.location_id', '!=', $location)->get(['tei.id', 'tei.tire_entry_id', 'tei.tire_id', 'other.location_id']);
            $row = ['id' => $id, 'identification' => $tire->code ?? $tire->serial_number ?? (string) $id, 'location_id' => $tire->location_id, 'entries' => $entries, 'outside_vehicles' => $outsideVehicles->all(), 'outside_measurements' => $outsideMeasurements->all(), 'outside_entry_items' => $outsideEntryItems->all()];
            if ($outsideVehicles->isNotEmpty() || $outsideMeasurements->isNotEmpty() || $outsideEntryItems->isNotEmpty()) $shared[] = $row + ['reason' => 'vínculo fora da location alvo'];
            else $delete[] = $row;
        }
        $entryIds = array_values(array_unique($entryIds));
        $deleteEntries = [];
        foreach ($entryIds as $entryId) {
            $remaining = DB::table('tire_entry_items')->where('tire_entry_id', $entryId)->whereNotIn('tire_id', collect($delete)->pluck('id')->all())->count();
            if ($remaining === 0) $deleteEntries[] = $entryId;
        }
        return ['delete_tire_ids' => collect($delete)->pluck('id')->all(), 'delete' => $delete, 'shared' => $shared, 'review' => $review, 'entries' => $entryIds, 'delete_entry_ids' => $deleteEntries, 'safe' => $review === []];
    }

    private function render(array $plan): void
    {
        $this->table(['TIRES TO DELETE', 'Identificação', 'Motivo'], collect($plan['delete'] ?? [])->map(fn ($tire) => [$tire['id'], $tire['identification'], 'exclusivo/orfão de Imperatriz'])->all());
        $this->table(['SHARED TIRES PRESERVED', 'Identificação', 'Motivo'], collect($plan['shared'])->map(fn ($tire) => [$tire['id'], $tire['identification'], $tire['reason']])->all());
        $this->table(['ENTRIES TO DELETE'], collect($plan['delete_entry_ids'])->map(fn ($id) => [$id])->all());
        $this->line('ENTRIES PRESERVED: '.implode(', ', array_values(array_diff($plan['entries'], $plan['delete_entry_ids']))));
        $this->line('TOTAL DELETE: '.(count($plan['delete_tire_ids']) + count($plan['delete_entry_ids'])));
    }

    private function delete(string $table, string $column, array $ids): void { if ($ids !== []) DB::table($table)->whereIn($column, $ids)->delete(); }
}
