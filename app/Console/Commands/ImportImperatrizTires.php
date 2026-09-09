<?php

namespace App\Console\Commands;

use App\Models\{Location, User};
use App\Services\ImperatrizHistoricalTireImportService;
use Illuminate\Console\Command;

class ImportImperatrizTires extends Command
{
    protected $signature = 'tires:import-imperatriz {file} {--location=} {--dry-run} {--execute} {--user=}';
    protected $description = 'Importa historicamente os pneus de Imperatriz, sem alterar leituras de veículos.';

    public function handle(ImperatrizHistoricalTireImportService $service): int
    {
        if (! $this->option('dry-run') && ! $this->option('execute')) return $this->failure($this->error('Use --dry-run ou --execute.'));
        if ($this->option('dry-run') && $this->option('execute')) return $this->failure($this->error('Use apenas um modo por execução.'));
        $location=Location::find($this->option('location')); if (! $location) return $this->failure($this->error('Informe --location válido.'));
        try { $plan=$service->plan($this->argument('file'),$location); } catch (\Throwable $e) { return $this->failure($this->error($e->getMessage())); }
        $this->table(['linhas','criar','medir','instalar','apenas cadastrar','bloqueados','duplicidades','conflitos posição','veículos não encontrados'], [[ $plan['total'],$plan['create'],$plan['measurements'],$plan['installations'],$plan['registered_only'],$plan['blocked'],$plan['duplicates'],$plan['position_conflicts'],$plan['vehicles_not_found'] ]]);
        foreach ($plan['by_vehicle'] as $fleet=>$s) $this->line("{$fleet}: esperado {$s['expected']} / instalar {$s['install']} / apenas cadastrar {$s['registered_only']} / faltando {$s['missing']}");
        foreach ($plan['blocks'] as $row) $this->warn('Linha '.$row['line'].' '.$row['fleet'].' ferro '.($row['iron']??'—').': '.implode(', ',$row['issues']));
        if ($this->option('dry-run')) { $this->info('DRY-RUN: nenhuma alteração persistida.'); return self::SUCCESS; }
        $user=User::query()->where('tenant_id',$location->tenant_id)->when($this->option('user'),fn($q)=>$q->whereKey($this->option('user')))->first(); if (! $user) return $this->failure($this->error('Informe --user válido para auditoria.'));
        try { $service->execute($plan,$location,$user); } catch (\Throwable $e) { return $this->failure($this->error('Rollback executado: '.$e->getMessage())); }
        $this->info('IMPORTAÇÃO CONCLUÍDA.'); return self::SUCCESS;
    }
    private function failure(mixed $ignored): int { return self::FAILURE; }
}
