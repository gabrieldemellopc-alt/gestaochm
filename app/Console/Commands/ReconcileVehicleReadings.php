<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Vehicle;
use App\Services\VehicleReadingReconciliationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileVehicleReadings extends Command
{
    protected $signature = 'chm:reconcile-vehicle-readings {--tenant=} {--division=} {--location=} {--vehicle=} {--dry-run} {--commit} {--user=}';
    protected $description = 'Reconcilia o KM consolidado pela última leitura válida efetiva.';
    public function handle(VehicleReadingReconciliationService $service): int
    {
        if (! $this->option('tenant') || ! $this->option('division') || ! $this->option('location')) { $this->error('Informe --tenant, --division e --location.'); return self::FAILURE; }
        $vehicles = Vehicle::query()->where('tenant_id',$this->option('tenant'))->where('division_id',$this->option('division'))->where('location_id',$this->option('location'))->when($this->option('vehicle'),fn($q,$id)=>$q->whereKey($id))->get();
        $plans=[]; foreach($vehicles as $v){ $r=$service->latestValid($v); $plans[]=['vehicle'=>$v,'reading'=>$r]; }
        $this->table(['ID','Frota','Atual','Calculado','Data calculada','Origem','Ref.','Ação'],collect($plans)->map(fn($p)=>[$p['vehicle']->id,$p['vehicle']->asset_code?:$p['vehicle']->name,$p['vehicle']->current_km,$p['reading']['km']??'-',$p['reading']['date']??'-',$p['reading']['source']??'-',$p['reading']['fuel_filling_id']??$p['reading']['log_id']??'-',!$p['reading']?'REVISAR':((float)$p['vehicle']->current_km===$p['reading']['km']?'MANTER':'ATUALIZAR')])->all());
        if ($this->option('dry-run') || ! $this->option('commit')) { $this->info('Modo de análise: nenhuma gravação foi realizada.'); return self::SUCCESS; }
        if (!User::find($this->option('user'))) { $this->error('Execução real exige --user.'); return self::FAILURE; }
        foreach($plans as $p) if($p['reading']) DB::transaction(fn()=>Vehicle::whereKey($p['vehicle']->id)->update(['current_km'=>$p['reading']['km'],'last_km_update_at'=>$p['reading']['date']]));
        return self::SUCCESS;
    }
}
