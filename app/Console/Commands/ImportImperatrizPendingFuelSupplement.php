<?php

namespace App\Console\Commands;

use App\Models\FuelImportBatch;
use App\Models\FuelImportRow;
use App\Models\FuelTank;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AuditLogService;
use App\Services\FuelOperationContext;
use App\Services\FuelService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportImperatrizPendingFuelSupplement extends Command
{
    protected $signature = 'chm:import-imperatriz-pending-fuel-supplement {--dry-run} {--commit} {--confirm-location=}';
    protected $description = 'Importa, de forma idempotente, as duas pendências confirmadas de combustível de Imperatriz.';

    private const SOURCE = 'imperatriz_combustivel_suplementar_pendencias_2026-08-14_2026-09-01.csv';
    private const ROWS = [
        ['sequence'=>5300, 'occurred_at'=>'2026-08-14 00:00:00', 'vehicle'=>'VVA002', 'liters'=>56.800, 'km'=>null, 'hours'=>15.00, 'reference'=>'IMP-20260814-FILL-COM-0871'],
        ['sequence'=>7230, 'occurred_at'=>'2026-09-01 00:00:00', 'vehicle'=>'JAV7I32', 'liters'=>120.300, 'km'=>79337.00, 'hours'=>null, 'reference'=>'IMP-20260901-FILL-COM-1063'],
    ];

    public function handle(FuelService $fuel): int
    {
        if ((bool) $this->option('dry-run') === (bool) $this->option('commit')) {
            return $this->blocked('Use exatamente uma opção: --dry-run ou --commit.');
        }
        if ($this->option('commit') && (string) $this->option('confirm-location') !== '3') {
            return $this->blocked('Confirme a unidade com --confirm-location=3.');
        }

        $tank = FuelTank::query()->whereKey(3)->where('location_id', 3)->first();
        $user = $tank ? User::query()->where('tenant_id', $tank->tenant_id)->orderBy('id')->first() : null;
        if (! $tank || ! $user) return $this->blocked('Tanque 3 ou usuário responsável não encontrado.');
        $this->assertBaseline($tank);
        $source = storage_path('app/imports/'.self::SOURCE);
        if (! is_file($source)) return $this->blocked('Fonte suplementar não encontrada.');
        $hash = hash_file('sha256', $source);
        if (FuelImportBatch::query()->where('tenant_id', $tank->tenant_id)->where('source_hash', $hash)->exists()) return $this->blocked('Fonte suplementar já foi importada.');
        if (FuelImportRow::query()->whereIn('external_reference', collect(self::ROWS)->pluck('reference'))->exists()) return $this->blocked('Há referência externa já registrada em outro lote.');

        $jav = Vehicle::query()->where('location_id', 3)->where('plate', 'JAV-7I32')->first();
        $jav21 = Vehicle::query()->where('location_id', 3)->where('plate', 'JAV-7I21')->first();
        $this->table(['evento','veículo','litros','KM histórico','ação'], [
            ['IMP-20260814-FILL-COM-0871','VVA002 / id 79','56.800','HR 15,00','abastecimento interno histórico'],
            ['IMP-20260901-FILL-COM-1063',$jav ? 'JAV7I32 / id '.$jav->id : 'JAV7I32 (criar)','120.300','79.337','abastecimento interno histórico'],
            ['cadastro','JAV7I21'.($jav21 ? ' / id '.$jav21->id : ' (criar)'),'—','—','sem abastecimento'],
        ]);
        $this->line('Saldo antes: '.number_format((float) $tank->current_balance_liters, 3, ',', '.').' L');
        $this->line('Saídas suplementares: 177,100 L');
        $this->line('Saldo final previsto: 0,000 L');
        if (! $this->option('commit')) { $this->info('RESULTADO: APTO PARA IMPORTAÇÃO SUPLEMENTAR (DRY-RUN; nenhuma escrita).'); return self::SUCCESS; }

        DB::transaction(function () use ($tank, $user, $fuel, $hash, &$jav): void {
            $tank = FuelTank::query()->lockForUpdate()->findOrFail(3);
            $this->assertBaseline($tank);
            $jav = $this->findOrCreateVehicle('JAV7I32', 'JAV-7I32', $tank);
            $this->findOrCreateVehicle('JAV7I21', 'JAV-7I21', $tank);
            $batch = FuelImportBatch::create(['tenant_id'=>$tank->tenant_id,'division_id'=>$tank->division_id,'location_id'=>3,'fuel_tank_id'=>3,'responsible_user_id'=>$user->id,'source_file'=>self::SOURCE,'source_hash'=>$hash,'status'=>'processing','is_historical_import'=>true]);
            $service = $fuel->forOperationContext(new FuelOperationContext($user, $tank->tenant_id, $tank->division_id, 3, true, $batch->id));
            foreach (self::ROWS as $row) {
                $vehicle = $row['vehicle'] === 'VVA002' ? Vehicle::query()->findOrFail(79) : $jav;
                $before = $this->vehicleCounterSnapshot($vehicle);
                $filling = $service->registerFilling(['fuel_tank_id'=>3,'fuel_product_id'=>1,'vehicle_id'=>$vehicle->id,'source'=>'internal_tank','filled_at'=>$row['occurred_at'],'occurred_at'=>$row['occurred_at'],'quantity_liters'=>$row['liters'],'unit_cost'=>6.4700,'total_cost'=>round($row['liters'] * 6.47, 2),'vehicle_km'=>$row['km'],'vehicle_hours'=>$row['hours'],'notes'=>'Importação histórica suplementar confirmada; referência '.$row['reference']]);
                $after = $this->vehicleCounterSnapshot($vehicle->fresh());
                $diff = [];

                foreach ($before as $key => $value) {
                    if (($after[$key] ?? null) !== $value) {
                        $diff[$key] = [
                            'before' => $value,
                            'after' => $after[$key] ?? null,
                        ];
                    }
                }

                if ($diff !== []) {
                    throw new RuntimeException(
                        'Contador/timestamp de veículo foi alterado: '
                        . json_encode($diff, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    );
                }
                FuelImportRow::create(['fuel_import_batch_id'=>$batch->id,'row_number'=>count($batch->rows)+2,'sequence'=>$row['sequence'],'external_reference'=>$row['reference'],'payload'=>$row,'status'=>'imported','entity_type'=>$filling::class,'entity_id'=>$filling->id]);
                app(AuditLogService::class)->record(['tenant_id'=>$tank->tenant_id,'division_id'=>$tank->division_id,'location_id'=>3,'user_id'=>$user->id,'auditable'=>$filling,'module'=>'fuel','action'=>'imported','summary'=>'Pendência histórica suplementar importada.','metadata'=>['import_batch_id'=>$batch->id,'external_reference'=>$row['reference'],'historical_import'=>true]]);
            }
            $tank->refresh();
            if ((float) $tank->current_balance_liters !== 0.0) throw new RuntimeException('Saldo final divergente.');
            $batch->update(['status'=>'completed','summary'=>['internal_fillings'=>2,'internal_liters'=>177.1,'final_balance'=>'0.000']]);
        });
        $this->info('IMPORTAÇÃO SUPLEMENTAR CONCLUÍDA.');
        return self::SUCCESS;
    }

    private function findOrCreateVehicle(string $asset, string $plate, FuelTank $tank): Vehicle
    {
        $existing = Vehicle::query()->where('location_id', 3)->where('plate', $plate)->first();

        if ($existing) {
            return $existing;
        }

        // Reload database defaults (notably current_km/current_hours = 0) before
        // taking the immutable snapshot around the historical filling.
        return Vehicle::create(['tenant_id'=>$tank->tenant_id,'division_id'=>$tank->division_id,'location_id'=>3,'asset_code'=>$asset,'name'=>$asset,'plate'=>$plate,'type'=>'cacamba','fleet_relation'=>'aggregated','operation_started_at'=>'2026-08-01'])->fresh();
    }

    private function assertBaseline(FuelTank $tank): void
    {
        if (round((float) $tank->current_balance_liters, 3) !== 177.100) throw new RuntimeException('Saldo atual do tanque não é o residual esperado de 177,100 L.');
    }
    private function vehicleCounterSnapshot(Vehicle $vehicle): array
    {
        return [
            'current_km' => (string) $vehicle->current_km,
            'current_hours' => (string) $vehicle->current_hours,
            'last_km_update_at' => $this->normalizeDateTimeForSnapshot($vehicle->last_km_update_at),
            'last_hours_update_at' => $this->normalizeDateTimeForSnapshot($vehicle->last_hours_update_at),
            'updated_at' => $this->normalizeDateTimeForSnapshot($vehicle->updated_at),
        ];
    }
    private function normalizeDateTimeForSnapshot(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        if ($value instanceof \DateTimeInterface) return $value->format('Y-m-d H:i:s.u');
        return \Carbon\Carbon::parse($value, config('app.timezone'))->format('Y-m-d H:i:s.u');
    }
    private function blocked(string $message): int { $this->error('BLOQUEADO: '.$message); return self::FAILURE; }
}
