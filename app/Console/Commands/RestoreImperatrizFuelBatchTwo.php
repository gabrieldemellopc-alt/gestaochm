<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RestoreImperatrizFuelBatchTwo extends Command
{
    private const MANIFEST_SHA256 = 'b82acdc90ad7f76b72fca742e05a3f52e63448a5fee2b67644a1eef281280e1d';

    protected $signature = 'chm:restore-imperatriz-fuel-batch-two
        {--dry-run}
        {--commit}
        {--confirm-batch=}
        {--confirm-location=}
        {--manifest=storage/app/imports/imperatriz_batch_2_vehicle_restore_manifest.json}';
    protected $description = 'Rollback transacional do batch 2 e restauração comprovada dos contadores anteriores';

    public function handle(RollbackImperatrizFuelImport $rollback): int
    {
        if ((bool)$this->option('dry-run') === (bool)$this->option('commit')) return $this->refuse('Use exatamente --dry-run ou --commit.');
        if ((string)$this->option('confirm-batch') !== '2' || (string)$this->option('confirm-location') !== '3') return $this->refuse('Exige --confirm-batch=2 e --confirm-location=3.');
        try {
            $manifest=$this->manifest();
            if ($this->option('dry-run')) { $plan=$rollback->plan(2,false); $this->render($plan,$manifest); return $plan['problems']?self::FAILURE:self::SUCCESS; }
            DB::transaction(function()use($rollback,$manifest){$plan=$rollback->plan(2,true);$this->validate($plan,$manifest,true);if($plan['problems'])throw new RuntimeException(implode(' | ',$plan['problems']));$rollback->executePlan($plan);foreach($manifest['vehicles'] as $entry){DB::table('vehicles')->where('id',$entry['vehicle_id'])->update($entry['values']);}$this->assertRestored($manifest);});
        } catch (\Throwable $e) { return $this->refuse('RESTAURAÇÃO BLOQUEADA/REVERTIDA: '.$e->getMessage()); }
        $this->info('RESTAURAÇÃO CONCLUÍDA.'); return self::SUCCESS;
    }
    private function manifest():array{$path=base_path($this->option('manifest'));if(!is_file($path))throw new RuntimeException('Manifesto não encontrado.');$m=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);$hash=$m['payload_sha256']??null;unset($m['payload_sha256']);if(!$hash||$hash!==self::MANIFEST_SHA256||hash('sha256',json_encode($m,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))!==$hash)throw new RuntimeException('Hash do manifesto inválido ou não autorizado.');$m['payload_sha256']=$hash;if(($m['batch_id']??null)!==2||($m['location_id']??null)!==3||count($m['vehicles']??[])!==27)throw new RuntimeException('Manifesto não descreve exatamente os 27 veículos do batch 2.');return $m;}
    private function validate(array $plan,array $m,bool $lock):void{$ids=[];foreach($m['vehicles'] as $e){if(!isset($e['vehicle_id'],$e['values'],$e['evidence'])||count($e['values'])!==5)throw new RuntimeException('Entrada de manifesto incompleta.');$ids[]=$e['vehicle_id'];}$actual=DB::table('vehicles')->whereIn('id',collect($plan['fillings'])->pluck('vehicle_id')->unique()->all())->whereBetween('updated_at',[$plan['batch']->created_at,$plan['batch']->updated_at])->pluck('id')->sort()->values()->all();sort($ids);if($ids!==$actual)throw new RuntimeException('Veículos tocados pelo batch divergem do manifesto.');if($lock)DB::table('vehicles')->whereIn('id',$ids)->lockForUpdate()->get();}
    private function render(array $plan,array $m):void{$this->validate($plan,$m,false);$tires=$plan['tire_baseline'];$preserved=implode(PHP_EOL,array_map(fn($table)=>$table.': '.$tires[$table]['count'].' -> '.$tires[$table]['count'],['tires','tire_measurements','tire_installations']));$this->table(['Item','Valor'],[['Batch',2],['Linhas a remover',count($plan['rows'])],['Fillings',count($plan['fillings'])],['Leituras históricas',count($plan['logs'])],['Auditorias',count($plan['audits'])],['Veículos a restaurar',count($m['vehicles'])],['Tanque atual',$plan['tank']->current_balance_liters.' / '.$plan['tank']->estimated_stock_value.' / '.$plan['tank']->average_unit_cost],['Tanque após','0.000 / 0.00 / 0.0000'],['Pneus preservados',$preserved]]);foreach($m['vehicles'] as $e){$now=DB::table('vehicles')->find($e['vehicle_id']);$d=[];foreach($e['values'] as $k=>$v)if((string)$now->$k!==(string)$v)$d[]="$k: {$now->$k} -> $v";$this->line('Veículo '.$e['vehicle_id'].': '.implode('; ',$d));}$this->line('RESULTADO: '.($plan['problems']?'BLOQUEADO':'APTO PARA RESTAURAÇÃO'));}
    private function assertRestored(array $m):void{foreach($m['vehicles'] as $e){$now=(array)DB::table('vehicles')->find($e['vehicle_id']);foreach($e['values'] as $k=>$v)if((string)$now[$k]!== (string)$v)throw new RuntimeException("Veículo {$e['vehicle_id']} divergente em {$k}");}}
    private function refuse(string $s):int{$this->error($s);return self::FAILURE;}
}
