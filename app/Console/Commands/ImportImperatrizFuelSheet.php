<?php

namespace App\Console\Commands;

use App\Models\FuelImportBatch;
use App\Models\FuelImportRow;
use App\Models\FuelTank;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\FuelOperationContext;
use App\Services\FuelService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportImperatrizFuelSheet extends Command
{
    protected $signature = 'chm:import-imperatriz-fuel {file} {--tank-id=} {--user=} {--dry-run} {--commit} {--confirm-location=} {--allow-legacy-balance-anomalies}';

    protected $description = 'Importa eventos históricos de combustível com transação, auditoria e idempotência.';

    private const C = ['sequence', 'event_type', 'occurred_at', 'tank_id', 'vehicle_id', 'fuel_product_id', 'source', 'quantity_liters', 'unit_cost', 'total_cost', 'invoice_number', 'supplier_name', 'document_number', 'vehicle_km', 'vehicle_hours', 'notes', 'legacy_classification', 'external_reference'];

    public function handle(FuelService $fuel): int
    {
        if (! is_file($this->argument('file'))) {
            return $this->no('Arquivo não encontrado.');
        } $tank = FuelTank::find($this->option('tank-id'));
        if (! $tank) {
            return $this->no('Informe --tank-id válido.');
        }
        if (! $this->option('dry-run') && ! $this->option('commit')) {
            return $this->no('Use --dry-run ou --commit.');
        }if ($this->option('commit') && (int) $this->option('confirm-location') !== $tank->location_id) {
            return $this->no('Confirme a unidade com --confirm-location='.$tank->location_id);
        }
        $user = User::where('tenant_id', $tank->tenant_id)->find($this->option('user')) ?: User::where('tenant_id', $tank->tenant_id)->first();
        if (! $user) {
            return $this->no('Informe --user válido.');
        }
        try {
            $rows = $this->rows($this->argument('file'), $tank->id);
        } catch (\Throwable $e) {
            return $this->no($e->getMessage());
        }$hash = hash_file('sha256', $this->argument('file'));
        if ($this->option('commit') && FuelImportBatch::where('tenant_id', $tank->tenant_id)->where('source_hash', $hash)->exists()) {
            return $this->no('Lote já importado; nenhuma duplicação criada.');
        }
        $s = ['initial_balance' => 0, 'receipts' => 0, 'receipt_liters' => 0, 'receipt_value' => 0, 'internal_fillings' => 0, 'internal_liters' => 0, 'external_fillings' => 0, 'external_liters' => 0, 'above_capacity' => 0, 'below_zero' => 0, 'duplicates' => 0, 'km_hr_errors' => 0, 'errors' => []];
        $balances = [];
        try {
            DB::transaction(function () use ($fuel, $tank, $user, $rows, $hash, &$s, &$balances) {
                $batch = $this->option('commit') ? FuelImportBatch::create(['tenant_id' => $tank->tenant_id, 'division_id' => $tank->division_id, 'location_id' => $tank->location_id, 'fuel_tank_id' => $tank->id, 'responsible_user_id' => $user->id, 'source_file' => basename($this->argument('file')), 'source_hash' => $hash, 'status' => 'processing', 'is_historical_import' => true, 'allow_legacy_balance_anomalies' => (bool) $this->option('allow-legacy-balance-anomalies')]) : new FuelImportBatch(['id' => 0]);
                $service = $fuel->forOperationContext(new FuelOperationContext($user, $tank->tenant_id, $tank->division_id, $tank->location_id, true, $batch->id, (bool) $this->option('allow-legacy-balance-anomalies')));
                foreach ($rows as $r) {
                    $p = ['fuel_tank_id' => $tank->id, 'fuel_product_id' => $r['fuel_product_id'] ?: null, 'occurred_at' => $r['occurred_at'], 'received_at' => $r['occurred_at'], 'filled_at' => $r['occurred_at'], 'quantity_liters' => $r['quantity_liters'], 'unit_cost' => $r['unit_cost'] ?: null, 'total_cost' => $r['total_cost'] ?: null, 'invoice_number' => $r['invoice_number'] ?: null, 'supplier_name' => $r['supplier_name'] ?: null, 'document_number' => $r['document_number'] ?: null, 'vehicle_km' => $r['vehicle_km'] ?: null, 'vehicle_hours' => $r['vehicle_hours'] ?: null, 'vehicle_id' => $r['vehicle_id'] ?: null, 'source' => $r['source'] ?: null, 'notes' => trim(($r['notes'] ?? '').' | Importação histórica de combustível – Imperatriz – lote '.($batch->id ?: 'DRY-RUN'))];
                    try {
                        $e = match ($r['event_type']) {
                            'initial_balance' => $service->registerInitialBalance($p),'receipt' => $service->receiveFuel($p),'filling' => $service->registerFilling($p),'legacy_outflow' => $service->registerLegacyOutflow([...$p, 'external_reference' => $r['external_reference'], 'legacy_classification' => $r['legacy_classification'] ?? null]),default => throw new \RuntimeException('event_type inválido')
                        };
                        if ($this->option('commit')) {
                            FuelImportRow::create(['fuel_import_batch_id' => $batch->id, 'row_number' => $r['_line'], 'sequence' => $r['sequence'], 'external_reference' => $r['external_reference'], 'payload' => $r, 'status' => 'imported', 'entity_type' => $e::class, 'entity_id' => $e->id]);
                            app(AuditLogService::class)->record(['tenant_id' => $tank->tenant_id, 'division_id' => $tank->division_id, 'location_id' => $tank->location_id, 'user_id' => $user->id, 'auditable' => $e, 'module' => 'fuel', 'action' => 'imported', 'summary' => 'Linha importada de combustível.', 'metadata' => ['import_batch_id' => $batch->id, 'external_reference' => $r['external_reference'], 'legacy_classification' => $r['legacy_classification'] ?? null, 'historical_import' => true]]);
                        }$this->count($s, $r);
                    } catch (\Throwable $x) {
                        $s['errors'][] = 'Linha '.$r['_line'].': '.$x->getMessage();
                        throw $x;
                    }$t = $tank->fresh();
                    $balances[] = (float) $t->current_balance_liters;
                    if (end($balances) > (float) $t->capacity_liters) {
                        $s['above_capacity']++;
                    }if (end($balances) < 0) {
                        $s['below_zero']++;
                    }
                }
                $t = $tank->fresh();
                $s += ['final_balance' => $t->current_balance_liters, 'final_average_cost' => $t->average_unit_cost, 'final_stock_value' => $t->estimated_stock_value, 'minimum_balance' => $balances ? min($balances) : (float) $t->current_balance_liters, 'maximum_balance' => $balances ? max($balances) : (float) $t->current_balance_liters];
                if ($this->option('commit')) {
                    $batch->update(['status' => 'completed', 'summary' => $s]);
                } else {
                    throw new \RuntimeException('__rollback__');
                }
            });
        } catch (\Throwable $e) {
            if ($e->getMessage() !== '__rollback__') {
                $s['errors'][] = $e->getMessage();
            }
        }
        $this->table(array_keys($s), [array_map(fn ($v) => is_array($v) ? implode(' | ', $v) : $v, array_values($s))]);
        $ok = ! $s['errors'];
        $this->line('RESULTADO: '.($ok ? 'APTO PARA IMPORTAÇÃO' : 'BLOQUEADO'));

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function rows(string $file, int $tank): array
    {
        $a = IOFactory::load($file)->getActiveSheet()->toArray('', true, true, false);
        $h = array_map(fn ($v) => strtolower(trim((string) $v)), array_shift($a));
        foreach (self::C as $c) {
            if (! in_array($c, $h, true)) {
                throw new \RuntimeException('Coluna obrigatória ausente: '.$c);
            }
        }$o = [];
        $seen = [];
        foreach ($a as $i => $v) {
            $r = array_combine($h, array_pad($v, count($h), null));
            if (! array_filter($r, fn ($x) => $x !== null && $x !== '')) {
                continue;
            }$r['_line'] = $i + 2;
            $r['sequence'] = (int) $r['sequence'];
            $r['quantity_liters'] = (float) $r['quantity_liters'];
            $movesTank = $r['event_type'] !== 'filling' || ($r['source'] ?? null) === 'internal_tank';
            if ($movesTank && (int) $r['tank_id'] !== $tank) {
                throw new \RuntimeException('Linha '.$r['_line'].': tanque divergente.');
            }if (! $r['external_reference'] || isset($seen[$r['external_reference']])) {
                throw new \RuntimeException('Linha '.$r['_line'].': external_reference ausente ou duplicada.');
            }$seen[$r['external_reference']] = true;
            $o[] = $r;
        }usort($o, fn ($x, $y) => [$x['occurred_at'], $x['sequence']] <=> [$y['occurred_at'], $y['sequence']]);

        return $o;
    }

    private function count(array &$s, array $r): void
    {
        if ($r['event_type'] === 'initial_balance') {
            $s['initial_balance'] += (float) $r['quantity_liters'];
        }if ($r['event_type'] === 'receipt') {
            $s['receipts']++;
            $s['receipt_liters'] += (float) $r['quantity_liters'];
            $s['receipt_value'] += (float) $r['total_cost'];
        }if ($r['event_type'] === 'filling' && $r['source'] === 'internal_tank') {
            $s['internal_fillings']++;
            $s['internal_liters'] += (float) $r['quantity_liters'];
        }if ($r['event_type'] === 'filling' && $r['source'] === 'external_station') {
            $s['external_fillings']++;
            $s['external_liters'] += (float) $r['quantity_liters'];
        }
    }

    private function no(string $s): int
    {
        $this->error($s);

        return self::FAILURE;
    }
}
