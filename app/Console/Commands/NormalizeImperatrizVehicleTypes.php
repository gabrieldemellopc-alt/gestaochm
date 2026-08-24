<?php
namespace App\Console\Commands;
use App\Models\Vehicle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeImperatrizVehicleTypes extends Command
{
    protected $signature = 'chm:normalize-imperatriz-vehicle-types {--commit : Grava a normalização} {--confirm-location= : Confirme a unidade informando 3}';
    protected $description = 'Normaliza com segurança os tipos originais da frota de Imperatriz; dry-run por padrão.';
    private const TYPES = [
        'VVA001'=>'varredeira','VVA002'=>'varredeira','VOA001'=>'onibus','JJB8113'=>'onibus','MWB9265'=>'onibus','MWJ4945'=>'onibus','NXP7I77'=>'onibus','DUI5I26'=>'pipa','HXJ5A74'=>'carroceria_aberta','HYT3J97'=>'carroceria_aberta','MVM5I45'=>'carroceria_aberta','F350AKSA'=>'caminhonete','RET0001'=>'retroescavadeira','RET0002'=>'retroescavadeira','RET0003'=>'retroescavadeira','RET0032'=>'retroescavadeira','RET0060'=>'retroescavadeira','RET0089'=>'retroescavadeira',
    ];
    public function handle(): int
    {
        $commit = (bool) $this->option('commit');
        if ($commit && (string) $this->option('confirm-location') !== '3') {
            $this->error('SAFE NO: use --commit --confirm-location=3.');
            return self::FAILURE;
        }
        $vehicles = Vehicle::query()->where('tenant_id', 1)->where('division_id', 1)->where('location_id', 3)->whereIn('asset_code', array_keys(self::TYPES))->orderBy('asset_code')->get();
        $missing = collect(array_keys(self::TYPES))->diff($vehicles->pluck('asset_code'));
        $duplicates = $vehicles->groupBy('asset_code')->filter(fn ($items) => $items->count() > 1);
        $invalidCurrent = $vehicles->filter(fn (Vehicle $vehicle) => ! in_array($vehicle->type, Vehicle::typeValues(), true));
        $invalidTarget = collect(self::TYPES)->filter(fn ($type) => ! in_array($type, Vehicle::typeValues(), true));
        if ($missing->isNotEmpty() || $duplicates->isNotEmpty() || $vehicles->count() !== count(self::TYPES) || $invalidCurrent->isNotEmpty() || $invalidTarget->isNotEmpty()) {
            $this->error('SAFE NO: validação da frota original falhou.');
            if ($missing->isNotEmpty()) $this->line('Ausentes: '.$missing->implode(', '));
            if ($duplicates->isNotEmpty()) $this->line('Duplicados: '.$duplicates->keys()->implode(', '));
            return self::FAILURE;
        }
        $updates = $vehicles->filter(fn (Vehicle $vehicle) => $vehicle->type !== self::TYPES[$vehicle->asset_code]);
        $this->info('IMPERATRIZ VEHICLE TYPE NORMALIZATION — '.($commit ? 'COMMIT' : 'DRY-RUN'));
        $this->table(['ID','Asset Code','Tipo atual','Tipo novo','Ícone'], $vehicles->map(fn ($vehicle) => [$vehicle->id, $vehicle->asset_code, $vehicle->type, self::TYPES[$vehicle->asset_code], Vehicle::iconForType(self::TYPES[$vehicle->asset_code])])->all());
        if (! $commit) { $this->warn('Nenhuma alteração foi gravada.'); return self::SUCCESS; }
        if ($updates->isEmpty()) { $this->info('Nothing to update.'); return self::SUCCESS; }
        DB::transaction(function () use ($updates) { foreach ($updates as $vehicle) $vehicle->update(['type' => self::TYPES[$vehicle->asset_code]]); });
        $this->info('NORMALIZATION COMPLETE');
        $this->line('Updated: '.$updates->count());
        return self::SUCCESS;
    }
}
