<?php

namespace App\Console\Commands;

use App\Models\FuelFilling;
use App\Models\FuelImportBatch;
use App\Models\FuelImportRow;
use App\Models\FuelTank;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AuditLogService;
use App\Services\VehicleReadingService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ImportImperatrizRolloutFuel extends Command
{
    protected $signature = 'chm:import-imperatriz-rollout-fuel {file} {--dry-run} {--commit} {--confirm-location=} {--user=}';
    protected $description = 'Importa o rollout histórico de abastecimentos de Imperatriz com classificação por linha.';

    private const UNRELIABLE = ['KDR8I43', 'MFP0899', 'HXJ5A74', 'MWJ4945', 'MWB9265'];
    private const SUSPECT = ['BXF0B12', 'BXG9J79', 'JAC2E39'];
    private const PENDING = ['TMN6E70', 'PKL1H93'];
    private const MISSING = ['ESC0001', 'BOBCAT', 'DEP6303'];

    public function handle(VehicleReadingService $readings, AuditLogService $audit): int
    {
        if ((bool) $this->option('dry-run') === (bool) $this->option('commit')) return $this->blocked('Use exatamente um de --dry-run ou --commit.');
        if (! is_file($file = $this->argument('file'))) return $this->blocked('Arquivo não encontrado.');
        if ((int) $this->option('confirm-location') !== 3) return $this->blocked('Use --confirm-location=3.');
        $tank = FuelTank::query()->whereKey(3)->where('location_id', 3)->first();
        if (! $tank) return $this->blocked('Tanque 3/localidade 3 não encontrado.');
        $user = User::query()->where('tenant_id', $tank->tenant_id)->find($this->option('user')) ?? User::query()->where('tenant_id', $tank->tenant_id)->first();
        if (! $user) return $this->blocked('Usuário responsável não encontrado.');
        $rows = $this->rows($file);
        $hash = hash_file('sha256', $file);
        $summary = array_fill_keys(['total','existing','probable_duplicate','new','importable','normal','unreliable','suspect','pending','missing','errors'], 0);
        $summary['liters'] = $summary['value'] = 0;
        $actions = [];
        $run = function () use ($rows, $tank, $user, $hash, $readings, $audit, &$summary, &$actions) {
            $batch = $this->option('commit') ? FuelImportBatch::create(['tenant_id'=>$tank->tenant_id,'division_id'=>$tank->division_id,'location_id'=>3,'fuel_tank_id'=>3,'responsible_user_id'=>$user->id,'source_file'=>basename($this->argument('file')),'source_hash'=>$hash,'status'=>'processing','is_historical_import'=>true,'allow_legacy_balance_anomalies'=>true]) : null;
            foreach ($rows as $row) {
                $summary['total']++; $summary['liters'] += $row['liters']; $summary['value'] += $row['total'];
                [$status, $vehicle, $reason] = $this->classify($row, $tank);
                $summary[$status] = ($summary[$status] ?? 0) + 1;
                if (in_array($status, ['normal','unreliable','suspect'], true)) { $summary['new']++; $summary['importable']++; }
                $actions[] = [$row['line'], $row['date']->format('d/m/Y'), $row['plate'], $row['liters'], $row['reading'], $status, $reason];
                if (! in_array($status, ['normal','unreliable','suspect'], true)) { if ($batch) FuelImportRow::create(['fuel_import_batch_id'=>$batch->id,'row_number'=>$row['line'],'sequence'=>$row['line'],'external_reference'=>$row['reference'],'payload'=>$row + ['classification'=>$status,'reason'=>$reason],'status'=>$status,'entity_type'=>null,'entity_id'=>null]); continue; }
                if (! $this->option('commit')) continue;
                $filling = FuelFilling::create(['tenant_id'=>$tank->tenant_id,'division_id'=>$tank->division_id,'location_id'=>3,'fuel_tank_id'=>3,'fuel_product_id'=>$tank->fuel_product_id,'source'=>FuelFilling::SOURCE_INTERNAL_TANK,'vehicle_id'=>$vehicle->id,'filled_at'=>$row['date'],'quantity_liters'=>$row['liters'],'unit_cost'=>$row['unit'],'source_unit_cost'=>$row['unit'],'total_cost'=>$row['total'],'source_total_cost'=>$row['total'],'supplier_name'=>'Importação histórica Imperatriz','notes'=>'Importação rollout Imperatriz; sem movimento físico; ref '.$row['reference'].'; linha '.$row['line']]);
                $type = $vehicle->km_control_enabled ? 'km' : ($vehicle->hours_control_enabled ? 'hours' : null);
                if ($type) {
                    if ($status === 'suspect' || ($status === 'unreliable' && $vehicle->{$type.'_meter_status'} !== Vehicle::METER_STATUS_UNRELIABLE)) $readings->recordSuspectReading($vehicle, $type, $row['reading'], $user, 'fuel_filling_import', 'Importação histórica '.$row['reference'], $row['date'], $filling, $reason);
                    else $type === 'km' ? $readings->updateKm($vehicle, $row['reading'], $user, 'fuel_filling_import', 'Importação histórica '.$row['reference'], 'vehicle_km', true, $row['date'], $filling) : $readings->updateHours($vehicle, $row['reading'], $user, 'fuel_filling_import', 'Importação histórica '.$row['reference'], 'vehicle_hours', true, $row['date'], $filling);
                }
                FuelImportRow::create(['fuel_import_batch_id'=>$batch->id,'row_number'=>$row['line'],'sequence'=>$row['line'],'external_reference'=>$row['reference'],'payload'=>$row + ['classification'=>$status,'reason'=>$reason],'status'=>'imported','entity_type'=>FuelFilling::class,'entity_id'=>$filling->id]);
                $audit->record(['tenant_id'=>$tank->tenant_id,'division_id'=>$tank->division_id,'location_id'=>3,'user_id'=>$user->id,'auditable'=>$filling,'module'=>'fuel','action'=>'imported','summary'=>'Rollout Imperatriz importado.','metadata'=>['import_batch_id'=>$batch->id,'external_reference'=>$row['reference'],'classification'=>$status,'reason'=>$reason,'historical_import'=>true]]);
            }
            if ($batch) $batch->update(['status'=>'completed','summary'=>$summary]);
        };
        $this->option('commit') ? DB::transaction($run) : $run();
        $this->table(['Linha','Data','Veículo','Litros','Leitura','Status','Ação'], $actions);
        $this->table(array_keys($summary), [array_values($summary)]);
        $this->line('Efeito no saldo atual do tanque: nenhum (abastecimentos históricos do tanque interno, sem movimento físico).');
        return self::SUCCESS;
    }

    private function rows(string $file): array
    {
        $sheet = IOFactory::load($file)->getActiveSheet(); $rows = [];
        // Columns are intentionally positional: D=vehicle, F=observed reading,
        // H=liters, I=unit cost, J=total cost. J contains Excel formulas.
        for ($line = 2; $line <= $sheet->getHighestDataRow(); $line++) {
            $dateValue = $sheet->getCell('A'.$line)->getValue(); if ($dateValue === null || $dateValue === '') continue;
            $date = $dateValue instanceof \DateTimeInterface ? Carbon::instance($dateValue) : (is_numeric($dateValue) ? Carbon::instance(ExcelDate::excelToDateTimeObject((float) $dateValue)) : Carbon::parse($dateValue));
            $liters = (float) $sheet->getCell('H'.$line)->getCalculatedValue();
            $unit = (float) $sheet->getCell('I'.$line)->getCalculatedValue();
            $totalCell = $sheet->getCell('J'.$line); $totalValue = $totalCell->getCalculatedValue();
            // A workbook without a cached formula result still has deterministic
            // source columns, so derive only from H×I rather than coercing formula text.
            $total = is_numeric($totalValue) ? (float) $totalValue : $liters * $unit;
            $plate = $this->norm($sheet->getCell('D'.$line)->getValue() ?: $sheet->getCell('B'.$line)->getValue());
            $rows[] = ['line'=>$line,'date'=>$date,'plate'=>$plate,'reading'=>(float) $sheet->getCell('F'.$line)->getCalculatedValue(),'liters'=>$liters,'unit'=>$unit,'total'=>$total,'reference'=>'imperatriz-rollout-'.$date->format('Ymd').'-'.$plate.'-'.$line];
        } return $rows;
    }
    private function classify(array $r, FuelTank $tank): array
    {
        if (in_array($r['plate'], self::MISSING, true)) return ['missing', null, 'Veículo não encontrado'];
        if (in_array($r['plate'], self::PENDING, true)) return ['pending', null, 'Pendente de conferência humana'];
        $lookup = ['JAV7132' => 'JAV7I32'][$r['plate']] ?? $r['plate'];
        $vehicle = Vehicle::query()->where('tenant_id',$tank->tenant_id)->where('division_id',$tank->division_id)->where('location_id',3)->where(fn($q)=>$q->whereRaw("REPLACE(plate,'-','')=?",[$lookup])->orWhere('asset_code',$lookup)->orWhere('name',$lookup))->first();
        if (! $vehicle) return ['missing', null, 'Veículo não encontrado'];
        if (FuelImportRow::query()->where('external_reference',$r['reference'])->where('status','imported')->exists()) return ['existing',$vehicle,'Referência já importada'];
        $same = FuelFilling::query()->where('vehicle_id',$vehicle->id)->whereDate('filled_at',$r['date'])->whereBetween('quantity_liters',[$r['liters']-.01,$r['liters']+.01])->whereBetween('source_total_cost',[$r['total']-.02,$r['total']+.02])->exists();
        if ($same) return ['probable_duplicate',$vehicle,'Combinação veículo/data/litros/valor já existe'];
        if (in_array($r['plate'], self::SUSPECT, true)) return ['suspect',$vehicle,'Leitura conhecida como inconsistente'];
        if (in_array($r['plate'], self::UNRELIABLE, true)) return ['unreliable',$vehicle,'Leitura repetida/não confiável'];
        return ['normal',$vehicle,'Importar leitura histórica'];
    }
    private function norm($value): string { return strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $value)); }
    private function blocked(string $message): int { $this->error($message); return self::FAILURE; }
}
