<?php

namespace App\Console\Commands;

use App\Models\FuelImportBatch;
use App\Models\FuelTank;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/** Deliberately narrow rollback for one already-audited historical fuel batch. */
class RollbackImperatrizFuelImport extends Command
{
    protected $signature = 'chm:rollback-imperatriz-fuel-import
        {--batch= : ID exato do lote histórico}
        {--confirm-batch= : Deve repetir exatamente --batch}
        {--confirm-location= : Deve ser 3 (Imperatriz)}
        {--dry-run : Somente leitura}
        {--commit : Executa o rollback transacional}';

    protected $description = 'Remove com segurança apenas uma importação histórica de combustível de Imperatriz';

    private const LOCATION_ID = 3;
    private const BATCH_SOURCE = 'App\\Models\\FuelImportBatch';
    private const FILLING_SOURCE = 'App\\Models\\FuelFilling';
    private const RECEIPT_SOURCE = 'App\\Models\\FuelReceipt';
    private const MOVEMENT = 'App\\Models\\FuelMovement';
    private const FILLING = 'App\\Models\\FuelFilling';
    private const RECEIPT = 'App\\Models\\FuelReceipt';

    public function handle(): int
    {
        if (! ctype_digit((string) $this->option('batch')) || (int) $this->option('batch') < 1) return $this->refuse('Informe --batch com ID positivo.');
        if ((string) $this->option('confirm-batch') !== (string) $this->option('batch')) return $this->refuse('Confirmação inválida: --confirm-batch deve repetir --batch.');
        if ((string) $this->option('confirm-location') !== (string) self::LOCATION_ID) return $this->refuse('Confirmação inválida: --confirm-location deve ser 3.');
        if ((bool) $this->option('dry-run') === (bool) $this->option('commit')) return $this->refuse('Use exatamente um: --dry-run ou --commit.');

        try {
            if ($this->option('dry-run')) {
                $plan = $this->plan((int) $this->option('batch'), false);
                $this->render($plan);
                return $plan['problems'] ? self::FAILURE : self::SUCCESS;
            }

            DB::transaction(function (): void {
                $plan = $this->plan((int) $this->option('batch'), true);
                $this->render($plan);
                if ($plan['problems']) throw new RuntimeException(implode(' | ', $plan['problems']));
                $this->executePlan($plan);
            });
        } catch (\Throwable $e) {
            return $this->refuse('ROLLBACK BLOQUEADO/REVERTIDO: '.$e->getMessage());
        }
        $this->info('ROLLBACK CONCLUÍDO: somente entidades comprovadamente vinculadas ao batch foram removidas.');
        return self::SUCCESS;
    }

    public function plan(int $batchId, bool $lock): array
    {
        $batchQuery = FuelImportBatch::query()->whereKey($batchId); if ($lock) $batchQuery->lockForUpdate();
        $batch = $batchQuery->first(); if (! $batch) throw new RuntimeException('Batch inexistente ou já removido.');
        if ((int) $batch->location_id !== self::LOCATION_ID || (int) $batch->fuel_tank_id !== 3) throw new RuntimeException('O batch não pertence ao tanque 3 de Imperatriz.');
        $tankQuery = FuelTank::query()->whereKey(3); if ($lock) $tankQuery->lockForUpdate(); $tank = $tankQuery->firstOrFail();
        if ((int) $tank->location_id !== self::LOCATION_ID || (int) $tank->id !== (int) $batch->fuel_tank_id) throw new RuntimeException('Tanque/contexto divergente.');

        $rowsQ = DB::table('fuel_import_rows')->where('fuel_import_batch_id', $batch->id); if ($lock) $rowsQ->lockForUpdate(); $rows = $rowsQ->get();
        $ids = [self::FILLING => [], self::RECEIPT => [], self::MOVEMENT => []]; $problems = [];
        foreach ($rows as $row) {
            if (! isset($ids[$row->entity_type]) || ! $row->entity_id) $problems[] = "linha {$row->id} possui entidade inválida";
            else $ids[$row->entity_type][] = (int) $row->entity_id;
        }
        foreach ($ids as $type => $values) if (count($values) !== count(array_unique($values))) $problems[] = "entidades duplicadas em {$type}";
        $fillings = $this->selected('fuel_fillings', $ids[self::FILLING], $lock);
        $receipts = $this->selected('fuel_receipts', $ids[self::RECEIPT], $lock);
        $rowMovements = $this->selected('fuel_movements', $ids[self::MOVEMENT], $lock);
        if (count($fillings) !== count($ids[self::FILLING]) || count($receipts) !== count($ids[self::RECEIPT]) || count($rowMovements) !== count($ids[self::MOVEMENT])) $problems[] = 'uma entidade apontada por fuel_import_rows não existe';
        foreach ($fillings as $f) if ((int) $f->location_id !== 3 || (int) $f->tenant_id !== (int) $batch->tenant_id || ((string) $f->source !== 'external_station' && (int) $f->fuel_tank_id !== 3)) $problems[] = "filling {$f->id} fora do contexto";
        foreach ($receipts as $r) if ((int) $r->location_id !== 3 || (int) $r->fuel_tank_id !== 3) $problems[] = "receipt {$r->id} fora do contexto";

        $fillingIds = $ids[self::FILLING]; $receiptIds = $ids[self::RECEIPT];
        $movementQ = DB::table('fuel_movements')->where('location_id', 3); if ($lock) $movementQ->lockForUpdate(); $allMovements = $movementQ->get();
        $movementIds = [];
        foreach ($allMovements as $m) {
            $isDirect = $m->source_type === self::BATCH_SOURCE && (int) $m->source_id === (int) $batch->id;
            $isFill = $m->source_type === self::FILLING_SOURCE && in_array((int) $m->source_id, $fillingIds, true);
            $isReceipt = $m->source_type === self::RECEIPT_SOURCE && in_array((int) $m->source_id, $receiptIds, true);
            if ($isDirect || $isFill || $isReceipt) $movementIds[] = (int) $m->id; else $problems[] = "movimento de combustível externo/posterior {$m->id}";
        }
        foreach ($rowMovements as $m) if (! in_array((int) $m->id, $movementIds, true) || $m->source_type !== self::BATCH_SOURCE || (int) $m->source_id !== (int) $batch->id) $problems[] = "movimento direto {$m->id} inválido";
        foreach ($fillings as $f) {
            $n = collect($allMovements)->filter(fn ($m) => $m->source_type === self::FILLING_SOURCE && (int) $m->source_id === (int) $f->id)->count();
            if (($f->source === 'external_station' && $n !== 0) || ($f->source !== 'external_station' && $n !== 1)) $problems[] = "vínculo de movimento do filling {$f->id} inválido";
        }
        foreach ($receipts as $r) if (collect($allMovements)->filter(fn ($m) => $m->source_type === self::RECEIPT_SOURCE && (int) $m->source_id === (int) $r->id)->count() !== 1) $problems[] = "vínculo de movimento do receipt {$r->id} inválido";

        foreach (['fuel_fillings' => $fillingIds, 'fuel_receipts' => $receiptIds] as $table => $selected) {
            $q = DB::table($table)->where('location_id', 3); if ($lock) $q->lockForUpdate();
            foreach ($q->pluck('id')->map(fn ($id) => (int) $id)->all() as $id) if (! in_array($id, $selected, true)) $problems[] = "registro externo/posterior em {$table}: {$id}";
        }
        $logsQ = DB::table('vehicle_update_logs')->whereIn('fuel_filling_id', $fillingIds ?: [0]); if ($lock) $logsQ->lockForUpdate(); $logs = $logsQ->get(); $logIds = $logs->pluck('id')->map(fn ($id) => (int) $id)->all();
        $corrections = Schema::hasTable('vehicle_reading_corrections') ? DB::table('vehicle_reading_corrections')->whereIn('original_log_id', $logIds ?: [0])->orWhereIn('original_fuel_filling_id', $fillingIds ?: [0])->count() : 0;
        if ($corrections) $problems[] = "dependência inesperada: {$corrections} correções de leitura";
        foreach ($this->unexpectedForeignDependencies(['fuel_import_batches'=>[$batch->id], 'fuel_import_rows'=>$rows->pluck('id')->all(), 'fuel_fillings'=>$fillingIds, 'fuel_receipts'=>$receiptIds, 'fuel_movements'=>$movementIds, 'vehicle_update_logs'=>$logIds]) as $p) $problems[] = $p;

        $auditsQ = DB::table('system_audit_logs');
        if (DB::getDriverName() === 'sqlite') $auditsQ->where('metadata', 'like', '%"import_batch_id":'.$batch->id.'%');
        else $auditsQ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.import_batch_id')) = ?", [(string) $batch->id]);
        if ($lock) $auditsQ->lockForUpdate(); $audits = $auditsQ->get();
        $auditIds = $audits->pluck('id')->map(fn ($id) => (int) $id)->all();
        $relatedAudits = DB::table('system_audit_logs')->where(function ($q) use ($fillingIds, $receiptIds, $movementIds) { $q->where(fn ($x) => $x->where('auditable_type', self::FILLING)->whereIn('auditable_id', $fillingIds ?: [0]))->orWhere(fn ($x) => $x->where('auditable_type', self::RECEIPT)->whereIn('auditable_id', $receiptIds ?: [0]))->orWhere(fn ($x) => $x->where('auditable_type', self::MOVEMENT)->whereIn('auditable_id', $movementIds ?: [0])); })->pluck('id')->map(fn ($id) => (int) $id)->all();
        foreach ($relatedAudits as $id) if (! in_array($id, $auditIds, true)) $problems[] = "auditoria relacionada sem identidade do batch: {$id}";
        return compact('batch','tank','rows','ids','fillings','receipts','rowMovements','movementIds','logs','logIds','audits','auditIds','problems') + ['vehicle_baseline'=>$this->vehicleBaseline($fillings), 'tire_baseline'=>$this->tireBaseline((int) $batch->location_id)];
    }

    private function selected(string $table, array $ids, bool $lock) { $q = DB::table($table)->whereIn('id', $ids ?: [0]); if ($lock) $q->lockForUpdate(); return $q->get(); }
    public function executePlan(array $plan): void { $vehicleBaseline=$plan['vehicle_baseline'];$tireBaseline=$plan['tire_baseline'];foreach($this->deleteOrder($plan) as $table)$this->deleteSelected($table,$plan);FuelTank::query()->whereKey($plan['tank']->id)->lockForUpdate()->update(['current_balance_liters'=>0,'estimated_stock_value'=>0,'average_unit_cost'=>0]);$this->assertRemoved($plan);$this->assertBaselines($vehicleBaseline,$tireBaseline); }
    private function vehicleBaseline($fillings): array { $ids = collect($fillings)->pluck('vehicle_id')->filter()->unique()->all(); return DB::table('vehicles')->whereIn('id', $ids ?: [0])->get(['id','current_km','current_hours'])->mapWithKeys(fn ($v) => [$v->id => [$v->current_km,$v->current_hours]])->all(); }
    private function tireBaseline(int $locationId): array
    {
        // The reduced SQLite schema used by the command tests has no tire location
        // relationship. Production always takes the location-scoped branch below.
        if (! Schema::hasColumn('tires', 'location_id')
            || ! Schema::hasColumn('tire_measurements', 'tire_id')
            || ! Schema::hasColumn('tire_installations', 'tire_id')) {
            return $this->tireBaselineFromQueries($locationId, [
                'tires' => DB::table('tires')->orderBy('id'),
                'tire_measurements' => DB::table('tire_measurements')->orderBy('id'),
                'tire_installations' => DB::table('tire_installations')->orderBy('id'),
            ]);
        }

        return $this->tireBaselineFromQueries($locationId, [
            'tires' => DB::table('tires')->where('location_id', $locationId)->orderBy('tires.id'),
            'tire_measurements' => DB::table('tire_measurements as measurement')->join('tires as tire', 'tire.id', '=', 'measurement.tire_id')->where('tire.location_id', $locationId)->select('measurement.*')->orderBy('measurement.id'),
            'tire_installations' => DB::table('tire_installations as installation')->join('tires as tire', 'tire.id', '=', 'installation.tire_id')->where('tire.location_id', $locationId)->select('installation.*')->orderBy('installation.id'),
        ]);
    }

    private function tireBaselineFromQueries(int $locationId, array $queries): array
    {
        $baseline = ['location_id' => $locationId];
        foreach ($queries as $table => $query) {
            $records = $query->get()->map(fn ($record) => (array) $record)->all();
            $baseline[$table] = [
                'count' => count($records),
                'sha256' => hash('sha256', json_encode($records, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            ];
        }

        return $baseline;
    }
    private function assertBaselines(array $vehicles, array $tires): void { foreach ($vehicles as $id => $v) { $now=DB::table('vehicles')->where('id',$id)->first(['current_km','current_hours']); if (! $now || [$now->current_km,$now->current_hours] !== $v) throw new RuntimeException("KM/HR do veículo {$id} foi alterado"); } if ($this->tireBaseline((int) $tires['location_id']) !== $tires) throw new RuntimeException('Dados de pneus foram alterados.'); }
    private function unexpectedForeignDependencies(array $targets): array { $bad=[]; $allowed=['fuel_import_rows.fuel_import_batch_id','vehicle_update_logs.fuel_filling_id']; foreach ($this->foreignKeys() as $fk) { $key=$fk['table'].'.'.$fk['column']; if (isset($targets[$fk['ref_table']]) && $targets[$fk['ref_table']] && ! in_array($key,$allowed,true)) { $n=DB::table($fk['table'])->whereIn($fk['column'],$targets[$fk['ref_table']])->count(); if ($n) $bad[]="dependência inesperada: {$key} ({$n})"; } } return $bad; }
    private function foreignKeys(): array { if (DB::getDriverName()==='sqlite') return []; return collect(DB::select('SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL'))->map(fn($r)=>['table'=>$r->TABLE_NAME,'column'=>$r->COLUMN_NAME,'ref_table'=>$r->REFERENCED_TABLE_NAME])->all(); }
    private function deleteOrder(array $p): array
    {
        $nodes = ['system_audit_logs','vehicle_update_logs','fuel_movements','fuel_fillings','fuel_receipts','fuel_import_rows','fuel_import_batches'];
        $edges = array_fill_keys($nodes, []); // child => parents: every child must leave first.
        foreach ($this->foreignKeys() as $fk) if (isset($edges[$fk['table']], $edges[$fk['ref_table']])) $edges[$fk['table']][] = $fk['ref_table'];
        // These two links are polymorphic, hence not represented by an SQL FK.
        $edges['fuel_movements'] = array_merge($edges['fuel_movements'], ['fuel_fillings', 'fuel_receipts']);
        $edges['vehicle_update_logs'][] = 'fuel_fillings';
        $edges['fuel_import_rows'][] = 'fuel_import_batches';
        $in = array_fill_keys($nodes, 0); foreach ($edges as $parents) foreach (array_unique($parents) as $parent) $in[$parent]++;
        $order = []; while ($ready = array_keys(array_filter($in, fn ($n) => $n === 0))) { sort($ready); foreach ($ready as $child) { if (! isset($in[$child])) continue; unset($in[$child]); $order[] = $child; foreach (array_unique($edges[$child]) as $parent) if (isset($in[$parent])) $in[$parent]--; } }
        if ($in) throw new RuntimeException('Ciclo de dependências detectado; exclusão não é segura.');
        return $order;
    }
    private function deleteSelected(string $table, array $p): void { $map=['system_audit_logs'=>$p['auditIds'],'vehicle_update_logs'=>$p['logIds'],'fuel_movements'=>$p['movementIds'],'fuel_fillings'=>$p['ids'][self::FILLING],'fuel_receipts'=>$p['ids'][self::RECEIPT],'fuel_import_rows'=>$p['rows']->pluck('id')->all(),'fuel_import_batches'=>[$p['batch']->id]]; DB::table($table)->whereIn('id',$map[$table] ?: [0])->delete(); }
    private function assertRemoved(array $p): void { foreach (['fuel_import_rows'=>$p['rows']->pluck('id')->all(),'fuel_movements'=>$p['movementIds'],'fuel_fillings'=>$p['ids'][self::FILLING],'fuel_receipts'=>$p['ids'][self::RECEIPT],'vehicle_update_logs'=>$p['logIds'],'system_audit_logs'=>$p['auditIds']] as $t=>$ids) if (DB::table($t)->whereIn('id',$ids ?: [0])->exists()) throw new RuntimeException("resíduo em {$t}"); if (FuelImportBatch::find($p['batch']->id)) throw new RuntimeException('batch não removido'); }
    private function render(array $p): void { $internal=collect($p['fillings'])->where('source','!=','external_station')->count(); $external=count($p['fillings'])-$internal; $legacy=collect($p['rowMovements'])->where('movement_type','legacy_outflow')->count(); $this->table(['Item','Valor'],[['Batch',$p['batch']->id],['Arquivo',$p['batch']->source_file],['Tanque/location',$p['tank']->id.'/'.$p['tank']->location_id],['Linhas',count($p['rows'])],['Recebimentos',count($p['receipts'])],['Abastecimentos internos',$internal],['Abastecimentos externos',$external],['Legacy outflows',$legacy],['Movimentos',count($p['movementIds'])],['Leituras históricas',count($p['logs'])],['Auditorias',count($p['audits'])],['Dependências inesperadas',count($p['problems'])],['Saldo/valor/custo atual',$p['tank']->current_balance_liters.' / '.$p['tank']->estimated_stock_value.' / '.$p['tank']->average_unit_cost],['Saldo/valor/custo após rollback','0.000 / 0.00 / 0.0000']]); if($p['problems']) foreach(array_unique($p['problems']) as $x)$this->error($x); $this->line('RESULTADO: '.($p['problems']?'BLOQUEADO':'APTO PARA ROLLBACK')); }
    private function refuse(string $message): int { $this->error($message); return self::FAILURE; }
}
