<?php

namespace App\Console\Commands;

use App\Models\FuelFilling;
use App\Models\User;
use App\Services\FuelReadingSynchronizationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncFuelReadings extends Command
{
    protected $signature = 'chm:sync-fuel-readings {--dry-run : Analisa sem gravar} {--commit : Confirma a gravação após a análise} {--tenant=} {--division=} {--location=} {--vehicle=} {--user=}';
    protected $description = 'Sincroniza leituras históricas de KM a partir de abastecimentos, com segurança e idempotência.';

    public function handle(FuelReadingSynchronizationService $sync): int
    {
        $scope = ['tenant_id' => $this->option('tenant') ? (int) $this->option('tenant') : null, 'division_id' => $this->option('division') ? (int) $this->option('division') : null, 'location_id' => $this->option('location') ? (int) $this->option('location') : null, 'vehicle_id' => $this->option('vehicle') ? (int) $this->option('vehicle') : null];
        if (! $scope['tenant_id'] || ! $scope['division_id'] || ! $scope['location_id']) { $this->error('Informe --tenant, --division e --location para limitar o escopo.'); return self::FAILURE; }

        $all = FuelFilling::query()->where('tenant_id', $scope['tenant_id'])->where('division_id', $scope['division_id'])->where('location_id', $scope['location_id'])->when($scope['vehicle_id'], fn ($q, $id) => $q->where('vehicle_id', $id))->get();
        $eligible = $sync->eligibleFillings($scope);
        $already = $all->filter(fn ($f) => $f->vehicle_id && $f->vehicle_km !== null && $f->vehicleReadingLogs()->where('type', 'km')->exists())->count();
        $issues = $sync->anomalies($eligible);
        $this->table(['Veículos analisados', 'Abastecimentos analisados', 'Com KM informado', 'Já sincronizados', 'A sincronizar', 'Ignorados/cancelados'], [[ $all->pluck('vehicle_id')->filter()->unique()->count(), $all->count(), $all->whereNotNull('vehicle_km')->count(), $already, $eligible->count(), $all->count() - $eligible->count() - $already ]]);
        foreach ($eligible->groupBy('vehicle_id') as $vehicleFillings) { $vehicle = $vehicleFillings->first()->vehicle; $this->line(($vehicle?->asset_code ?: $vehicle?->plate ?: "Veículo #{$vehicleFillings->first()->vehicle_id}").': '.$vehicleFillings->count().' leitura(s), '.$vehicleFillings->first()->filled_at->format('d/m/Y').' → '.$vehicleFillings->last()->filled_at->format('d/m/Y')); }
        if ($issues->isNotEmpty()) $this->table(['Tipo', 'Veículo', 'Abast.', 'Data', 'KM', 'Detalhe'], $issues->map(fn ($i) => [$i['type'], $i['vehicle_id'], $i['filling_id'], $i['filled_at']->format('d/m/Y H:i'), $i['vehicle_km'], $i['message']])->all());
        if ($this->option('dry-run') || ! $this->option('commit')) { $this->info('Modo de análise: nenhuma gravação foi realizada. Use --commit para executar após revisar este resumo.'); return self::SUCCESS; }
        $user = User::query()->whereKey($this->option('user'))->first();
        if (! $user) { $this->error('Execução real exige --user=<id> para registrar a autoria das leituras.'); return self::FAILURE; }
        $created = 0; $errors = 0;
        foreach ($eligible->groupBy('vehicle_id') as $vehicleFillings) {
            try { $created += DB::transaction(fn () => $sync->syncVehicle($vehicleFillings->first()->vehicle, $vehicleFillings, $user)); }
            catch (\Throwable $e) { $errors++; $this->error("Veículo #{$vehicleFillings->first()->vehicle_id}: {$e->getMessage()}"); }
        }
        $this->info("Sincronização concluída: {$created} leitura(s) criada(s), {$errors} veículo(s) com erro.");
        return $errors ? self::FAILURE : self::SUCCESS;
    }
}
