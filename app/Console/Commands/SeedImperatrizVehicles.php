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
use RuntimeException;

class SeedImperatrizVehicles extends Command
{
    protected $signature = 'chm:seed-imperatriz-vehicles
        {--tenant=1}
        {--division=1}
        {--location=3}
        {--user= : Usuário responsável pela leitura inicial (obrigatório no commit)}
        {--commit : Grava a carga após aprovação}
        {--confirm-location= : Deve coincidir exatamente com --location}';

    protected $description = 'Prepara ou cadastra os 18 compactadores de Imperatriz com leitura inicial auditável';

    public function handle(VehicleReadingService $readings): int
    {
        $context = $this->resolveContext();
        if (! $context) return self::FAILURE;

        $rows = collect($this->rows())->map(fn (array $row) => [
            'code' => $row[0], 'plate' => $row[1], 'model' => $row[2], 'year' => $row[3],
            'chassis' => $row[4], 'renavam' => $row[5], 'initial_km' => $row[6],
        ]);
        $conflicts = $this->conflicts($rows, $context['tenant']->id);
        $this->table(['Code', 'Plate', 'Model', 'Year', 'Chassis', 'Renavam', 'Initial KM', 'Action'], $rows->map(fn ($row) => [$row['code'], $row['plate'], $row['model'], $row['year'], $row['chassis'], $row['renavam'], $row['initial_km'], isset($conflicts[$row['code']]) ? 'BLOCKED' : 'CREATE'])->all());

        if ($conflicts) {
            $this->error('Conflitos de código ou placa encontrados:');
            $this->table(['Code', 'Motivo'], collect($conflicts)->map(fn ($message, $code) => [$code, $message])->all());
        }

        if (! $this->option('commit')) {
            $this->info('SAFE '.($conflicts ? 'NO' : 'YES').' — dry-run: NENHUMA ALTERAÇÃO FOI REALIZADA.');
            return $conflicts ? self::FAILURE : self::SUCCESS;
        }

        if ($conflicts || (string) $this->option('confirm-location') !== (string) $context['location']->id) {
            $this->error('Commit recusado: use --confirm-location='.$context['location']->id.' após corrigir conflitos.');
            return self::FAILURE;
        }

        $user = User::query()->where('tenant_id', $context['tenant']->id)->find($this->option('user'));
        if (! $user) {
            $this->error('Informe --user com um usuário válido do tenant para registrar a leitura inicial.');
            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($rows, $context, $user, $readings) {
                foreach ($rows as $row) {
                    $vehicle = Vehicle::create([
                        'tenant_id' => $context['tenant']->id,
                        'division_id' => $context['division']->id,
                        'location_id' => $context['location']->id,
                        'asset_code' => $row['code'],
                        'name' => $row['code'],
                        'plate' => $row['plate'],
                        'brand' => 'VW',
                        'model' => $row['model'],
                        'year' => substr($row['year'], 0, 4),
                        'type' => 'lixo',
                        'tire_layout' => 'truck_6_mixed',
                        'current_km' => 0,
                        'current_hours' => 0,
                        'status' => 'active',
                        'operational_status' => 'operational',
                        'notes' => "Cadastro inicial de Imperatriz. Proprietário informado: AKSA. Ano/modelo: {$row['year']}. Chassi informado: {$row['chassis']}. RENAVAM informado: {$row['renavam']}.",
                    ]);
                    $readings->registerInitialKm($vehicle, $row['initial_km'], $user, now(), 'Cadastro inicial de Imperatriz.');
                }
            });
        } catch (\Throwable $exception) {
            $this->error('IMPORT FAILED — transação revertida: '.$exception->getMessage());
            return self::FAILURE;
        }

        $this->info('IMPORT COMPLETE — 18 compactadores e suas leituras iniciais foram criados.');
        return self::SUCCESS;
    }

    private function resolveContext(): ?array
    {
        $tenant = Tenant::find((int) $this->option('tenant'));
        $division = Division::find((int) $this->option('division'));
        $location = Location::find((int) $this->option('location'));
        if (! $tenant || ! $division || ! $location || (int) $division->tenant_id !== (int) $tenant->id || (int) $location->tenant_id !== (int) $tenant->id || (int) $location->division_id !== (int) $division->id) {
            $this->error('Tenant, divisão e location não formam um contexto válido.');
            return null;
        }
        return compact('tenant', 'division', 'location');
    }

    private function conflicts($rows, int $tenantId): array
    {
        $duplicateCodes = $rows->pluck('code')->duplicates();
        $duplicatePlates = $rows->pluck('plate')->duplicates();
        $existing = Vehicle::query()->where('tenant_id', $tenantId)->where(fn ($query) => $query->whereIn('asset_code', $rows->pluck('code'))->orWhereIn('plate', $rows->pluck('plate')))->get();
        $conflicts = [];
        foreach ($rows as $row) {
            if ($duplicateCodes->contains($row['code']) || $duplicatePlates->contains($row['plate'])) $conflicts[$row['code']] = 'Carga contém código ou placa duplicada.';
            $match = $existing->first(fn (Vehicle $vehicle) => $vehicle->asset_code === $row['code'] || $vehicle->plate === $row['plate']);
            if ($match) $conflicts[$row['code']] = "Já existe vehicle_id {$match->id} na location {$match->location_id}.";
        }
        return $conflicts;
    }

    private function rows(): array
    {
        return [
            ['VCA001','TMO-8H32','18.260','2025/2026','9536B8TD0TR026756','1481818586',4093], ['VCA002','TMS-4A23','18.260','2025/2026','9536B8TD1TR021498','1480952432',3376], ['VCA003','FZO-6F26','17.260','2019/2020','9536J8247LR029495','1237851715',151686], ['VCA004','JAC-2E39','17.190','2019/2020','9536E8246LR033851','1231739786',17083], ['VCA005','THG-4E23','18.260','2025/2026','9536B8TD0TR019788','1457197690',0], ['VCA006','JAC-2E37','17.190','2020/2021','9536E8231MR105659','1231739107',142239], ['VCA007','TMS-1J41','26.260','2025/2026','9536B8TD2TR028749','1479656698',5280], ['VCA008','TMW-6H93','18.260','2025/2026','9536B8TD6TR031556','1492209985',4688], ['VCA009','JAC-2E43','17.190','2020/2021','9536E8239MR105196','1231740318',150212], ['VCA010','THG-9B61','18.260','2025/2026','9536B8TD9TR020177','1457198255',17945], ['VCA011','TMW-7B90','18.260','2025/2026','9536B8TD8TR031719','1492207702',4530], ['VCA012','TMT-3G93','18.260','2025/2026','9536B8TD4TR026842','1486059004',2129], ['VCA013','TMN-6E70','18.260','2025/2026','9536B8TD7TR026818','1481812871',5486], ['VCA014','TMW-6B79','18.260','2025/2026','9536B8TD5TR019995','1492210932',2719], ['VCA015','TMS-3I82','18.260','2025/2026','9536B8TD0TR020472','1480907828',3330], ['VCA016','TMR-6E70','26.260','2025/2026','9536B8TD0TR028832','1479652358',5000], ['VCA017','TMN-8G17','18.260','2025/2026','9536B8TD7TR026303','1481816168',9499], ['VCA018','TMT-6B36','18.260','2025/2026','9536B8TD4TR026081','1486059101',9499],
        ];
    }
}
