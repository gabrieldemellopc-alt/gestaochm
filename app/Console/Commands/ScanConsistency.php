<?php
namespace App\Console\Commands;
use App\Models\Tenant;
use App\Services\Consistency\ConsistencyScanner;
use Illuminate\Console\Command;
class ScanConsistency extends Command
{
    protected $signature = 'chm:scan-consistency {--tenant=} {--location=} {--rule=} {--dry-run}';
    protected $description = 'Detecta inconsistências operacionais sem alterar dados de origem';
    public function handle(ConsistencyScanner $scanner): int
    {
        $tenants=Tenant::query()->when($this->option('tenant'),fn($q,$v)=>$q->whereKey($v))->get();
        if($tenants->isEmpty()){ $this->error('Tenant não encontrado.'); return self::FAILURE; }
        $all=['new'=>0,'updated'=>0,'existing'=>0,'severity'=>[],'rules'=>[]];
        foreach($tenants as $tenant){$r=$scanner->scan(['tenant_id'=>$tenant->id,'location_id'=>$this->option('location') ?: null],$this->option('rule') ?: null,(bool)$this->option('dry-run'));foreach(['new','updated','existing'] as $k)$all[$k]+=$r[$k];foreach($r['rules'] as $k=>$v)$all['rules'][$k]=($all['rules'][$k]??0)+$v;foreach($r['severity'] as $k=>$v)$all['severity'][$k]=($all['severity'][$k]??0)+$v;}
        $this->table(['Regra','Ocorrências'],collect($all['rules'])->map(fn($v,$k)=>[$k,$v])->all());
        $this->line('Novos: '.$all['new'].' | Atualizados: '.$all['updated'].' | Já existentes: '.$all['existing']);
        $this->line('Por severidade: '.collect($all['severity'])->map(fn($v,$k)=>"{$k}: {$v}")->implode(', '));
        return self::SUCCESS;
    }
}
