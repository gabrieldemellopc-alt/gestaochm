<?php
namespace App\Console\Commands;
use App\Models\Vehicle;
use Illuminate\Console\Command;

class NormalizeImperatrizVehicleTypes extends Command
{
    protected $signature = 'chm:normalize-imperatriz-vehicle-types';
    protected $description = 'Exibe em dry-run as correcoes de tipos originais da frota de Imperatriz.';
    private const TYPES = [
        'VVA001'=>'varredeira','VVA002'=>'varredeira','VOA001'=>'onibus','JJB8113'=>'onibus','MWB9265'=>'onibus','MWJ4945'=>'onibus','NXP7I77'=>'onibus','DUI5I26'=>'pipa','HXJ5A74'=>'carroceria_aberta','HYT3J97'=>'carroceria_aberta','MVM5I45'=>'carroceria_aberta','F350AKSA'=>'caminhonete','RET0001'=>'retroescavadeira','RET0002'=>'retroescavadeira','RET0003'=>'retroescavadeira','RET0032'=>'retroescavadeira','RET0060'=>'retroescavadeira','RET0089'=>'retroescavadeira',
    ];
    public function handle(): int
    {
        $vehicles = Vehicle::query()->where('tenant_id', 1)->where('division_id', 1)->where('location_id', 3)->whereIn('asset_code', array_keys(self::TYPES))->orderBy('asset_code')->get();
        $missing = collect(array_keys(self::TYPES))->diff($vehicles->pluck('asset_code'));
        if ($missing->isNotEmpty()) { $this->error('SAFE NO: veículos ausentes: '.$missing->implode(', ')); return self::FAILURE; }
        $this->info('IMPERATRIZ VEHICLE TYPE NORMALIZATION — DRY-RUN');
        $this->table(['ID','Asset Code','Tipo atual','Tipo novo','Ícone'], $vehicles->map(fn ($vehicle) => [$vehicle->id, $vehicle->asset_code, $vehicle->type, self::TYPES[$vehicle->asset_code], Vehicle::iconForType(self::TYPES[$vehicle->asset_code])])->all());
        $this->warn('Nenhuma alteração foi gravada.');
        return self::SUCCESS;
    }
}
