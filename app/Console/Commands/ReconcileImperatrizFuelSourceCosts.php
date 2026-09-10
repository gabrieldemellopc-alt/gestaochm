<?php

namespace App\Console\Commands;

use App\Models\FuelFilling;
use App\Models\FuelTank;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ReconcileImperatrizFuelSourceCosts extends Command
{
    protected $signature = 'chm:reconcile-imperatriz-fuel-source-costs {file} {--dry-run} {--commit} {--confirm-location=}';
    protected $description = 'Reconcilia somente os custos de origem dos abastecimentos históricos de Imperatriz.';

    public function handle(): int
    {
        if (! is_file($this->argument('file')) || ((bool) $this->option('dry-run') === (bool) $this->option('commit'))) return $this->abortWithError('Use um arquivo existente e exatamente uma opção: --dry-run ou --commit.');
        if ((int) $this->option('confirm-location') !== 3) return $this->abortWithError('Confirme a unidade com --confirm-location=3.');
        $tank = FuelTank::query()->where('location_id', 3)->where('name', 'Diesel 01')->first();
        if (! $tank) return $this->abortWithError('Tanque histórico de Imperatriz não encontrado.');

        $sheet = IOFactory::load($this->argument('file'))->getSheetByName('GERAL');
        if (! $sheet) return $this->abortWithError('Aba GERAL não encontrada.');
        // The worksheet has formatting through Excel's last row. Read only the
        // historical data range, never the million formatted empty rows.
        $rows = $sheet->rangeToArray('A2:M800', '', true, true, false);
        $rawRows = $sheet->rangeToArray('A2:M800', '', true, false, false);
        $decimal = static function ($value): float {
            if (is_numeric($value)) return (float) $value;
            $normalized = preg_replace('/[^0-9,.-]/', '', (string) $value);
            if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
                return (float) (strrpos($normalized, '.') > strrpos($normalized, ',')
                    ? str_replace(',', '', $normalized)
                    : str_replace(',', '.', str_replace('.', '', $normalized)));
            }
            return (float) str_replace(',', '.', $normalized);
        };
        $source = collect($rows)->map(function (array $r, int $index) use ($decimal, $rawRows) {
            $raw = $rawRows[$index];
            $date = $r[0] instanceof \DateTimeInterface ? $r[0] : (is_numeric($r[0] ?? null) ? ExcelDate::excelToDateTimeObject((float) $r[0]) : (is_string($r[0] ?? null) && trim($r[0]) !== '' ? new \DateTime($r[0]) : null));
            return ['date' => $date, 'vehicle' => $r[3] ?: $r[1], 'liters' => round($decimal($r[7] ?? 0), 3), 'km' => round($decimal($r[5] ?? 0), 2), 'unit' => $decimal($raw[8] ?? $r[8] ?? 0), 'total' => $decimal($raw[9] ?? $r[9] ?? 0)];
        })->filter(fn ($r) => $r['date'] && $r['date'] >= new \DateTime('2026-07-01') && $r['date'] < new \DateTime('2026-09-03') && ! empty($r['vehicle']) && $r['liters'] > 0)->map(function ($r) { $r['date'] = $r['date']->format('Y-m-d'); unset($r['vehicle']); return $r; })->values();
        if ($source->count() !== 729 || round($source->sum('liters'), 3) !== 82095.800) return $this->abortWithError('A planilha não possui os 729 abastecimentos esperados (encontrados: '.$source->count().'; '.number_format($source->sum('liters'), 3, ',', '.').' L).');

        $fillings = FuelFilling::query()->where('tenant_id', $tank->tenant_id)->where('division_id', $tank->division_id)->where('location_id', 3)->where('fuel_tank_id', $tank->id)->whereBetween('filled_at', ['2026-07-01','2026-09-03'])->whereNull('cancelled_at')->get();
        if ($fillings->count() !== 729 || round($fillings->sum('quantity_liters'), 3) !== 82095.800) return $this->abortWithError('Os 729 abastecimentos esperados não foram encontrados no CHM.');
        $index = $fillings->groupBy(fn ($f) => $f->filled_at->format('Y-m-d').'|'.number_format($f->quantity_liters,3,'.','').'|'.number_format($f->vehicle_km ?? 0,2,'.',''));
        $fallback = $fillings->groupBy(fn ($f) => $f->filled_at->format('Y-m-d').'|'.number_format($f->quantity_liters,3,'.',''));
        $used=[]; $updates=[];
        foreach ($source as $row) {
            $key=$row['date'].'|'.number_format($row['liters'],3,'.','').'|'.number_format($row['km'],2,'.','');
            $f=collect($index->get($key, []))->first(fn ($x) => !isset($used[$x->id])) ?: collect($fallback->get($row['date'].'|'.number_format($row['liters'],3,'.',''), []))->first(fn ($x) => !isset($used[$x->id]));
            if (! $f) return $this->abortWithError('Não foi possível conciliar uma linha da planilha.');
            $used[$f->id]=true; $updates[]=['id'=>$f->id,'unit'=>$row['unit'],'total'=>$row['total']];
        }
        if (count($updates)!==729) return $this->abortWithError('Conciliação incompleta.');
        $summary=$source->groupBy(fn($r)=>substr($r['date'],0,7))->map(fn($r)=>['qtd'=>$r->count(),'litros'=>round($r->sum('liters'),3),'total'=>round($r->sum('total'),3)]);
        $this->table(['Período','Qtd','Litros','Custo origem'], $summary->map(fn($v,$k)=>[$k,$v['qtd'],number_format($v['litros'],3,',','.'),number_format($v['total'],3,',','.')])->values()->all());
        $this->info('Total: 729 abastecimentos; 82.095,800 L; saldo do tanque permanece '.number_format((float)$tank->current_balance_liters,3,',','.').' L.');
        if ($this->option('dry-run')) { $this->info('DRY-RUN: nenhuma alteração persistida.'); return self::SUCCESS; }
        DB::transaction(function () use ($updates) { foreach ($updates as $u) FuelFilling::query()->whereKey($u['id'])->update(['source_unit_cost'=>$u['unit'],'source_total_cost'=>$u['total']]); });
        $this->info('Conciliação concluída. Execuções posteriores são idempotentes.'); return self::SUCCESS;
    }
    private function abortWithError(string $message): int { $this->error($message); return self::FAILURE; }
}
