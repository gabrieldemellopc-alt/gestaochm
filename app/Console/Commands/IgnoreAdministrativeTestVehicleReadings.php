<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\VehicleUpdateLog;
use Illuminate\Console\Command;

class IgnoreAdministrativeTestVehicleReadings extends Command
{
    private const LOG_IDS = [111, 113, 115, 117, 119, 121, 123, 128];

    protected $signature = 'chm:ignore-administrative-test-readings {--dry-run} {--commit} {--user=}';
    protected $description = 'Marca exclusivamente as leituras administrativas de teste previamente identificadas.';

    public function handle(): int
    {
        $logs = VehicleUpdateLog::query()->whereIn('id', self::LOG_IDS)->orderBy('id')->get();

        if ($logs->count() !== count(self::LOG_IDS)) {
            $this->error('Os oito logs de teste esperados não foram localizados; nenhuma alteração foi realizada.');
            return self::FAILURE;
        }

        $this->table(['Log', 'Veículo', 'Origem', 'Valor novo', 'Status atual'], $logs->map(fn (VehicleUpdateLog $log) => [$log->id, $log->vehicle_id, $log->source ?? '-', $log->new_value, $log->reading_status ?? 'NULL'])->all());

        if ($this->option('dry-run') || ! $this->option('commit')) {
            $this->info('Modo de análise: nenhuma gravação foi realizada.');
            return self::SUCCESS;
        }

        if (! $this->option('user') || ! User::find($this->option('user'))) {
            $this->error('Execução real exige um --user existente.');
            return self::FAILURE;
        }

        VehicleUpdateLog::query()->whereIn('id', self::LOG_IDS)->update(['reading_status' => VehicleUpdateLog::READING_STATUS_IGNORED, 'reading_issue' => 'Leitura de teste administrativo.', 'reviewed_by' => $this->option('user'), 'reviewed_at' => now()]);
        $this->info('Os oito logs de teste foram marcados como ignored.');
        return self::SUCCESS;
    }
}
