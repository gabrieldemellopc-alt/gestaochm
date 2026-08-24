<?php

namespace App\Console\Commands;

use App\Models\Location;
use App\Models\Vehicle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClassifyImperatrizFleet extends Command
{
    protected $signature = 'chm:classify-imperatriz-fleet {--commit : Grava a classificacao} {--confirm-location= : Confirme a unidade informando 3}';
    protected $description = 'Classifica com seguranca a frota original de Imperatriz; dry-run por padrao.';

    private const SCOPE = ['tenant_id' => 1, 'division_id' => 1, 'location_id' => 3];
    private const RELATIONS = [
        'internal' => ['VCA001','VCA002','VCA003','VCA004','VCA005','VCA006','VCA007','VCA008','VCA009','VCA010','VCA011','VCA012','VCA013','VCA014','VCA015','VCA016','VCA017','VCA018','VBA001','VOA001','VVA001','VVA002','F350AKSA'],
        'aggregated' => ['BXF0B12','BXG9J79','DUI5I26','HPB4781','HXJ5A74','HYT3J97','JAV7I09','JHM6104','JJB8113','JMG5E85','JVY5H96','KDE5579','KDR8I43','MFP0899','MVM5I45','MVZ0628','MWB9265','MWJ4945','NXH5J69','NXP7I77','PSF0060','RET0001','RET0002','RET0003','TRA0004','NHR4951','RET0032','RET0060','RET0089'],
        'rented' => ['OQJ6J01'],
    ];

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');
        if ($commit && (string) $this->option('confirm-location') !== '3') { $this->error('SAFE NO: use --commit --confirm-location=3.'); return self::FAILURE; }
        $expected = collect(self::RELATIONS)->flatten();
        if ($expected->count() !== 53 || $expected->unique()->count() !== 53) { $this->error('SAFE NO: lista original invalida ou duplicada.'); return self::FAILURE; }
        $location = Location::query()->where('tenant_id', 1)->where('division_id', 1)->whereKey(3)->first();
        if (! $location) { $this->error('SAFE NO: location 3 fora do escopo esperado.'); return self::FAILURE; }
        $vehicles = Vehicle::query()->where(self::SCOPE)->whereIn('asset_code', $expected)->orderBy('asset_code')->get();
        $found = $vehicles->pluck('asset_code'); $missing = $expected->diff($found); $duplicates = $vehicles->groupBy('asset_code')->filter(fn ($items) => $items->count() > 1);
        $extra = Vehicle::query()->where(self::SCOPE)->whereNotIn('asset_code', $expected)->count();
        if ($missing->isNotEmpty() || $duplicates->isNotEmpty() || $vehicles->count() !== 53 || $extra !== 0) {
            $this->error('SAFE NO: a frota cadastrada nao corresponde exatamente a lista original.');
            if ($missing->isNotEmpty()) $this->line('Ausentes: '.$missing->implode(', '));
            if ($duplicates->isNotEmpty()) $this->line('Duplicados: '.$duplicates->keys()->implode(', '));
            if ($extra) $this->line("Veiculos extras no escopo: {$extra}");
            return self::FAILURE;
        }
        $lookup = collect(self::RELATIONS)->flatMap(fn ($codes, $relation) => collect($codes)->mapWithKeys(fn ($code) => [$code => $relation]));
        $rows = $vehicles->map(fn (Vehicle $vehicle) => [$vehicle->id, $vehicle->asset_code, $vehicle->plate, $vehicle->fleet_relation ?? 'internal', $lookup[$vehicle->asset_code], ($vehicle->fleet_relation ?? 'internal') === $lookup[$vehicle->asset_code] ? 'NONE' : 'UPDATE'])->all();
        $this->info('FLEET RELATION UPDATES'); $this->table(['ID','Asset Code','Plate','Atual','Novo','Action'], $rows);
        $this->newLine(); $this->line('Resumo: internal: 23 | aggregated: 29 | rented: 1 | total: 53');
        $this->newLine(); $this->info('LOCATION SETTINGS');
        $this->line('allow_aggregated_fuel: '.($location->allow_aggregated_fuel ? 'true' : 'false').' -> true');
        $this->line('allow_aggregated_maintenance: '.($location->allow_aggregated_maintenance ? 'true' : 'false').' -> false');
        if (! $commit) { $this->warn('DRY-RUN: SAFE YES. Nenhuma alteracao foi gravada.'); return self::SUCCESS; }
        DB::transaction(function () use ($vehicles, $lookup, $location) { foreach ($vehicles as $vehicle) { $relation = $lookup[$vehicle->asset_code]; if ($vehicle->fleet_relation !== $relation) $vehicle->update(['fleet_relation' => $relation]); } $location->update(['allow_aggregated_fuel' => true, 'allow_aggregated_maintenance' => false]); });
        $this->info('SAFE YES: classificacao gravada sem alterar leituras, abastecimentos, manutencoes ou dados cadastrais.');
        return self::SUCCESS;
    }
}
