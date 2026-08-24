<?php

namespace App\Console\Commands;

use App\Models\Division;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SeedImperatrizVehiclesReview extends Command
{
    protected $signature = 'chm:seed-imperatriz-vehicles-review {--tenant=1} {--division=1} {--location=3} {--user=} {--commit} {--confirm-location=}';
    protected $description = 'Cadastra os seis veículos de Imperatriz sem leitura inicial';

    public function handle(): int
    {
        $context = $this->resolveReviewContext();
        if (! $context) return self::FAILURE;
        $rows = collect($this->rows());
        $conflicts = $this->conflicts($rows, $context['tenant']->id);
        $expectedExisting = Vehicle::query()->where('tenant_id', $context['tenant']->id)->where('division_id', $context['division']->id)->where('location_id', $context['location']->id)->count();
        $this->table(['Action', 'Asset Code', 'Plate', 'Tipo', 'Reading', 'Log', 'Notes'], $rows->map(fn ($r) => ['CREATE', $r['asset_code'], $r['plate'] ?? 'NULL', $r['type'], 'PENDING', 'NO', $r['notes']])->all());
        if ($conflicts) $this->table(['Asset Code', 'Conflito'], collect($conflicts)->map(fn ($message, $code) => [$code, $message])->all());

        if (! $this->option('commit')) {
            $this->info('SAFE '.(!$conflicts && $expectedExisting === 47 ? 'YES' : 'NO').' — dry-run: NENHUMA ALTERAÇÃO FOI REALIZADA.');
            if ($expectedExisting !== 47) $this->error("A location alvo possui {$expectedExisting} veículos; eram esperados 47 antes desta fase.");
            return !$conflicts && $expectedExisting === 47 ? self::SUCCESS : self::FAILURE;
        }
        if ($conflicts || $expectedExisting !== 47 || (string) $this->option('confirm-location') !== (string) $context['location']->id) {
            $this->error('Commit recusado: reveja o dry-run, conflitos e confirmação da location.'); return self::FAILURE;
        }
        $user = User::query()->where('tenant_id', $context['tenant']->id)->find($this->option('user'));
        if (! $user) { $this->error('Informe --user válido do tenant.'); return self::FAILURE; }

        try {
            DB::transaction(function () use ($rows, $context) {
                foreach ($rows as $row) {
                    Vehicle::create([
                        'tenant_id' => $context['tenant']->id, 'division_id' => $context['division']->id, 'location_id' => $context['location']->id,
                        'asset_code' => $row['asset_code'], 'name' => $row['asset_code'], 'plate' => $row['plate'], 'type' => $row['type'],
                        'current_km' => 0, 'current_hours' => 0, 'last_km_update_at' => null, 'last_hours_update_at' => null,
                        'status' => 'active', 'operational_status' => 'operational', 'notes' => $row['notes'],
                    ]);
                }
            });
        } catch (\Throwable $exception) {
            $this->error('IMPORT FAILED — transação revertida: '.$exception->getMessage()); return self::FAILURE;
        }
        $this->info('IMPORT COMPLETE — seis registros REVIEW criados sem leitura ou logs.');
        return self::SUCCESS;
    }

    private function resolveReviewContext(): ?array
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
        return [
            ['asset_code'=>'JJB8113','plate'=>'JJB-8113','type'=>'automovel','notes'=>'Leitura inicial pendente de confirmação. Valor original não validado: 2.'],
            ['asset_code'=>'MWB9265','plate'=>'MWB-9265','type'=>'automovel','notes'=>'Leitura inicial pendente de confirmação. Valor original não validado: 1.'],
            ['asset_code'=>'MWJ4945','plate'=>'MWJ-4945','type'=>'automovel','notes'=>'Leitura inicial pendente de confirmação. Valor original não validado: 2.'],
            ['asset_code'=>'NXP7I77','plate'=>'NXP-7I77','type'=>'automovel','notes'=>'Leitura inicial pendente de confirmação. Valor original não validado: 1.'],
            ['asset_code'=>'F350AKSA','plate'=>null,'type'=>'automovel','notes'=>'Leitura inicial pendente de confirmação. Identificador original: F.350 AKSA. Placa real pendente de confirmação.'],
            ['asset_code'=>'RET0089','plate'=>null,'type'=>'trator','notes'=>'Leitura inicial pendente de confirmação. Valor informado originalmente: 238088. Unidade/leitura pendente de confirmação.'],
        ];
    }
}
