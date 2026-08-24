<?php

namespace App\Console\Commands;

use App\Models\Division;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\VehicleReadingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedImperatrizVehiclesPhaseTwo extends Command
{
    protected $signature = 'chm:seed-imperatriz-vehicles-phase-two {--tenant=1} {--division=1} {--location=3} {--user=} {--commit} {--confirm-location=}';
    protected $description = 'Prepara a segunda carga de Imperatriz: 21 veículos KM e 8 equipamentos HR';

    public function handle(VehicleReadingService $readings): int
    {
        $context = $this->resolveSeedContext();
        if (! $context) return self::FAILURE;
        $rows = collect($this->rows());
        $conflicts = $this->conflicts($rows, $context['tenant']->id);
        $this->table(['Action', 'Asset Code', 'Plate', 'Tipo original', 'Tipo CHM', 'Reading', 'Initial', 'Tire layout'], $rows->map(fn ($r) => [$r['action'], $r['asset_code'], $r['plate'] ?? 'NULL', $r['original_type'], $r['type'], $r['reading_type'], $r['reading'], $r['tire_layout'] ?? 'NULL'])->all());
        $this->table(['Action', 'Identificador', 'Tipo', 'Leitura', 'Motivo'], $this->reviews());
        if ($conflicts) $this->table(['Asset Code', 'Conflito'], collect($conflicts)->map(fn ($message, $code) => [$code, $message])->all());

        if (! $this->option('commit')) {
            $this->info('CREATE_KM: '.$rows->where('action', 'CREATE_KM')->count().' | CREATE_HR: '.$rows->where('action', 'CREATE_HR')->count().' | REVIEW: '.count($this->reviews()));
            $this->info('SAFE '.($conflicts ? 'NO' : 'YES').' — dry-run: NENHUMA ALTERAÇÃO FOI REALIZADA.');
            return $conflicts ? self::FAILURE : self::SUCCESS;
        }
        if ($conflicts || (string) $this->option('confirm-location') !== (string) $context['location']->id) {
            $this->error('Commit recusado: confirme a location e resolva conflitos.'); return self::FAILURE;
        }
        $user = User::query()->where('tenant_id', $context['tenant']->id)->find($this->option('user'));
        if (! $user) { $this->error('Informe --user válido do tenant para as leituras iniciais.'); return self::FAILURE; }

        DB::transaction(function () use ($rows, $context, $user, $readings) {
            foreach ($rows as $row) {
                $vehicle = Vehicle::create([
                    'tenant_id' => $context['tenant']->id, 'division_id' => $context['division']->id, 'location_id' => $context['location']->id,
                    'asset_code' => $row['asset_code'], 'name' => $row['asset_code'], 'plate' => $row['plate'], 'type' => $row['type'],
                    'tire_layout' => $row['tire_layout'], 'current_km' => 0, 'current_hours' => 0, 'status' => 'active', 'operational_status' => 'operational',
                    'notes' => 'Cadastro inicial de Imperatriz. Tipo informado: '.$row['original_type'].'.',
                ]);
                $row['reading_type'] === 'km'
                    ? $readings->registerInitialKm($vehicle, $row['reading'], $user, now(), 'Cadastro inicial de Imperatriz.')
                    : $readings->registerInitialHours($vehicle, $row['reading'], $user, now(), 'Cadastro inicial de Imperatriz.');
            }
        });
        $this->info('IMPORT COMPLETE — segunda carga de Imperatriz criada.');
        return self::SUCCESS;
    }

    private function resolveSeedContext(): ?array
    {
        $tenant=Tenant::find((int)$this->option('tenant')); $division=Division::find((int)$this->option('division')); $location=Location::find((int)$this->option('location'));
        if (!$tenant || !$division || !$location || (int)$division->tenant_id !== (int)$tenant->id || (int)$location->tenant_id !== (int)$tenant->id || (int)$location->division_id !== (int)$division->id) { $this->error('Contexto tenant/divisão/location inválido.'); return null; }
        return compact('tenant','division','location');
    }

    private function conflicts($rows, int $tenantId): array
    {
        $existing=Vehicle::query()->where('tenant_id',$tenantId)->where(fn($q)=>$q->whereIn('asset_code',$rows->pluck('asset_code'))->orWhereIn('plate',$rows->pluck('plate')->filter()))->get(); $conflicts=[];
        foreach($rows as $row) { if($rows->where('asset_code',$row['asset_code'])->count()>1) $conflicts[$row['asset_code']]='Código repetido na carga.'; if($row['plate'] && $rows->where('plate',$row['plate'])->count()>1) $conflicts[$row['asset_code']]='Placa repetida na carga.'; $match=$existing->first(fn($v)=>$v->asset_code===$row['asset_code'] || ($row['plate'] && $v->plate===$row['plate'])); if($match) $conflicts[$row['asset_code']]="Já existe vehicle_id {$match->id} na location {$match->location_id}."; }
        return $conflicts;
    }

    private function rows(): array
    {
        $km = [
            ['VBA001','PKL-1H93','Camionete Baú',1223,'bau'], ['VOA001','EZL-9J73','Ônibus',583217,'automovel'], ['BXF0B12','BXF-0B12','Caçamba Toco',596201,'cacamba'], ['BXG9J79','BXG-9J79','Caçamba Toco',179700,'cacamba'], ['DUI5I26','DUI-5I26','Caminhão Pipa',352284,'automovel'], ['HPB4781','HPB-4781','Caçamba Truck',4646,'cacamba'], ['HXJ5A74','HXJ-5A74','Caminhão Carroceria Aberta',383754,'automovel'], ['HYT3J97','HYT-3J97','Caminhão Carroceria Aberta',897084,'automovel'], ['JAV7I09','JAV-7I09','Caçamba Truck',90827,'cacamba'], ['JHM6104','JHM-6104','Caçamba Truck',398155,'cacamba'], ['JMG5E85','JMG-5E85','Caminhão Baú',36619,'bau'], ['JVY5H96','JVY-5H96','Caçamba Truck',68937,'cacamba'], ['KDE5579','KDE-5579','Caminhão Baú',0,'bau'], ['KDR8I43','KDR-8I43','Caçamba Truck',831574,'cacamba'], ['MFP0899','MFP-0899','Caçamba Truck',603876,'cacamba'], ['MVM5I45','MVM-5I45','Caminhão Carroceria Aberta',65317,'automovel'], ['MVZ0628','MVZ-0628','Caçamba Truck',831574,'cacamba'], ['NXH5J69','NXH-5J69','Caçamba Truck',448224,'cacamba'], ['PSF0060','PSF-0060','Caçamba Truck',238088,'cacamba'], ['NHR4951','NHR-4951','Caçamba Truck',128217,'cacamba'], ['OQJ6J01','OQJ-6J01','Guincho 3/4',494834,'automovel'],
        ];
        $hr = [['VVA001','Varredeira',32,'automovel'],['VVA002','Varredeira',15,'automovel'],['RET0001','Retroescavadeira',14,'trator'],['RET0002','Retroescavadeira',4795,'trator'],['RET0003','Retroescavadeira',3683,'trator'],['TRA0004','Trator-Praia',51,'trator'],['RET0032','Retroescavadeira',1895,'trator'],['RET0060','Retroescavadeira',6686,'trator']];
        return array_merge(array_map(fn($r)=>['action'=>'CREATE_KM','asset_code'=>$r[0],'plate'=>$r[1],'original_type'=>$r[2],'type'=>$r[4],'reading_type'=>'km','reading'=>$r[3],'tire_layout'=>null],$km),array_map(fn($r)=>['action'=>'CREATE_HR','asset_code'=>$r[0],'plate'=>null,'original_type'=>$r[1],'type'=>$r[3],'reading_type'=>'hours','reading'=>$r[2],'tire_layout'=>null],$hr));
    }

    private function reviews(): array
    {
        return [['REVIEW','JJB-8113','Ônibus','2','Leitura muito baixa; confirmar KM.'],['REVIEW','MWB-9265','Ônibus','1','Leitura muito baixa; confirmar KM.'],['REVIEW','MWJ-4945','Ônibus','2','Leitura muito baixa; confirmar KM.'],['REVIEW','NXP-7I77','Ônibus','1','Leitura muito baixa; confirmar KM.'],['REVIEW','F.350 AKSA','Caminhonete','161198','Confirmar identificador e placa.'],['REVIEW','RET0089','Retroescavadeira','238088','Confirmar se leitura é HR.']];
    }
}
