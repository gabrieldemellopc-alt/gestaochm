<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillVehicleOperationalControls extends Command
{
    protected $signature = 'chm:backfill-vehicle-operational-controls {--dry-run : Apenas exibe a política proposta} {--commit : Aplica a política explicitamente}';
    protected $description = 'Aplica de forma idempotente os controles operacionais por veículo; dry-run por padrão.';
    private const HOUR_TYPES = ['trator', 'retroescavadeira', 'varredeira'];

    public function handle(): int
    {
        if ($this->option('dry-run') && $this->option('commit')) {
            $this->error('Use apenas uma opção: --dry-run ou --commit.');
            return self::FAILURE;
        }
        $plans = Vehicle::query()->orderBy('id')->get()->map(function (Vehicle $vehicle): array {
            $hours = in_array($vehicle->type, self::HOUR_TYPES, true);
            return ['vehicle' => $vehicle, 'km' => ! $hours, 'hours' => $hours, 'tires' => true];
        });
        $this->info('CONTROLES OPERACIONAIS — '.($this->option('commit') ? 'COMMIT' : 'DRY-RUN'));
        $this->table(['vehicle_id', 'frota', 'placa', 'tipo', 'km_atual', 'hr_atual', 'km_flag', 'hr_flag', 'pneus_flag'], $plans->map(fn (array $p) => [$p['vehicle']->id, $p['vehicle']->asset_code ?: $p['vehicle']->name, $p['vehicle']->plate ?: '-', $p['vehicle']->type, $p['vehicle']->current_km, $p['vehicle']->current_hours, $p['km'] ? 'true' : 'false', $p['hours'] ? 'true' : 'false', 'true'])->all());
        $changes = $plans->filter(fn (array $p) => $p['vehicle']->km_control_enabled !== $p['km'] || $p['vehicle']->hours_control_enabled !== $p['hours'] || $p['vehicle']->tire_control_enabled !== $p['tires']);
        $this->line("Veículos: {$plans->count()} | alterações necessárias: {$changes->count()}");
        if (! $this->option('commit')) { $this->warn('Nenhuma alteração foi gravada. Execute com --commit somente após revisar esta lista.'); return self::SUCCESS; }
        DB::transaction(function () use ($changes): void { foreach ($changes as $p) Vehicle::whereKey($p['vehicle']->id)->update(['km_control_enabled' => $p['km'], 'hours_control_enabled' => $p['hours'], 'tire_control_enabled' => $p['tires']]); });
        $this->info('BACKFILL CONCLUÍDO.');
        return self::SUCCESS;
    }
}
