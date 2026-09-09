<?php

namespace App\Services;

use App\Models\{Location, Tire, TireInstallation, TireMeasurement, User, Vehicle};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/** Historical tire import. It deliberately has no dependency on the fuel module. */
class ImperatrizHistoricalTireImportService
{
    private const MARKER = '[importacao-historica-pneus-imperatriz]';
    private const SUSPICIOUS_CODES = ['2605114', '265013'];

    public function plan(string $file, Location $location): array
    {
        $rows = $this->readRows($file);
        $codes = collect($rows)->pluck('iron')->filter(fn ($v) => $v && $v !== 'S/N')->countBy();
        $vehicles = Vehicle::query()->where('location_id', $location->id)->get()->keyBy('id');
        $byPlate = $vehicles->filter(fn ($v) => $v->plate)->keyBy(fn ($v) => strtoupper($v->plate));
        $byFleet = $vehicles->keyBy(fn ($v) => strtoupper($v->name));
        $existing = Tire::query()->where('tenant_id', $location->tenant_id)->pluck('id', 'code');

        $planned = [];
        foreach ($rows as $row) {
            $issues = [];
            $plateVehicle = $byPlate->get(strtoupper($row['plate']));
            $fleetVehicle = $byFleet->get(strtoupper($row['fleet']));
            $vehicle = $plateVehicle && $fleetVehicle && $plateVehicle->id === $fleetVehicle->id ? $plateVehicle : null;
            if (! $vehicle) $issues[] = 'veiculo_nao_encontrado_ou_divergente';
            if ($row['iron'] === 'S/N' || $row['iron'] === null || $row['iron'] === '') $issues[] = 'ferro_ausente';
            if (in_array($row['iron'], self::SUSPICIOUS_CODES, true)) $issues[] = 'ferro_suspeito';
            if ($row['iron'] && ($codes[$row['iron']] ?? 0) > 1) $issues[] = 'ferro_duplicado';
            if (! $row['position']) $issues[] = 'posicao_ausente';
            if ($vehicle && $row['position'] && ! in_array($row['position'], $this->positionsFor($vehicle->tire_layout), true)) $issues[] = 'posicao_invalida_layout';
            if ($row['iron'] && $existing->has($row['iron'])) {
                $issues[] = str_contains((string) Tire::find($existing[$row['iron']])?->notes, self::MARKER) ? 'ja_importado' : 'ferro_ja_cadastrado';
            }

            $identityBlocked = collect($issues)->contains(fn ($i) => in_array($i, ['ferro_ausente','ferro_suspeito','ferro_duplicado','ferro_ja_cadastrado'], true));
            $blocked = $identityBlocked || in_array('posicao_ausente', $issues, true) || in_array('posicao_invalida_layout', $issues, true);
            $canCreate = ! $identityBlocked && ! in_array('posicao_ausente', $issues, true) && ! in_array('posicao_invalida_layout', $issues, true) && ! in_array('ja_importado', $issues, true);
            $canMeasure = $canCreate && $vehicle && $row['position'] && ! in_array('posicao_invalida_layout', $issues, true);
            $canInstall = $canMeasure;
            $planned[] = $row + compact('vehicle', 'issues', 'blocked', 'canCreate', 'canMeasure', 'canInstall');
        }

        return $this->summary($planned);
    }

    public function execute(array $plan, Location $location, User $user): array
    {
        DB::transaction(function () use (&$plan, $location, $user) {
            foreach ($plan['rows'] as &$row) {
                if (! $row['canCreate']) continue;
                /** @var Vehicle $vehicle */
                $vehicle = $row['vehicle'];
                $notes = trim(self::MARKER.' Fonte: Pneus_Normalizados linha '.$row['line'].'. '.implode(' ', $row['notes']));
                $tire = Tire::create([
                    'tenant_id' => $location->tenant_id, 'location_id' => $location->id,
                    'code' => $row['iron'], 'brand' => $row['brand'], 'model' => $row['model'],
                    'initial_tread_depth' => $row['minimum_tread'], 'purchase_date' => $row['date'],
                    'status' => 'available', 'notes' => $notes,
                ]);
                if (! $row['canMeasure']) continue;
                // This intentionally records no vehicle reading and never calls VehicleReadingService.
                TireMeasurement::create([
                    'tenant_id' => $location->tenant_id, 'tire_id' => $tire->id, 'vehicle_id' => $vehicle->id,
                    'position_code' => $row['position'], 'measured_at' => $row['date'], 'vehicle_km' => $row['km'],
                    'outer_tread' => $row['treads'][0], 'center_outer_tread' => $row['treads'][1],
                    'center_inner_tread' => $row['treads'][2], 'inner_tread' => $row['treads'][3],
                    'average_tread' => $row['average_tread'], 'minimum_tread' => $row['minimum_tread'],
                    'notes' => $notes, 'user_id' => $user->id,
                ]);
                TireInstallation::create([
                    'tenant_id' => $location->tenant_id, 'tire_id' => $tire->id, 'vehicle_id' => $vehicle->id,
                    'position_code' => $row['position'], 'installed_at' => $row['date'], 'installed_km' => $row['km'],
                    'active' => true, 'created_by' => $user->id,
                ]);
                $tire->update(['status' => 'installed']);
                app(AuditLogService::class)->record(['tenant_id'=>$location->tenant_id,'division_id'=>$location->division_id,'location_id'=>$location->id,'user_id'=>$user->id,'auditable'=>$tire,'module'=>'tires','action'=>'imported','summary'=>'Pneu importado historicamente de Imperatriz.','metadata'=>['historical_import'=>true,'source'=>'controle_pneus_aksa_normalizado.xlsx','source_line'=>$row['line'],'iron'=>$row['iron'],'vehicle_id'=>$vehicle->id]]);
            }
        });
        return $plan;
    }

    private function readRows(string $file): array
    {
        if (! is_file($file)) throw new \RuntimeException('Arquivo não encontrado.');
        $sheet = IOFactory::load($file)->getSheetByName('Pneus_Normalizados');
        if (! $sheet) throw new \RuntimeException('Aba Pneus_Normalizados não encontrada.');
        $data = $sheet->toArray(null, true, true, false);
        $header = array_map(fn ($v) => trim((string) $v), array_shift($data));
        $map = array_flip($header);
        foreach (['Data conferência','Placa','Nº Frota','Nº Ferro','Posição','Sulco 1 (mm)','Sulco 2 (mm)','Sulco 3 (mm)','Menor sulco (mm)'] as $column) if (! array_key_exists($column, $map)) throw new \RuntimeException('Coluna ausente: '.$column);
        $out=[];
        foreach ($data as $index => $values) {
            if (! ($values[$map['Placa']] ?? null)) continue;
            $date = $values[$map['Data conferência']];
            $date = $date instanceof \DateTimeInterface
                ? Carbon::instance($date)
                : (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', (string) $date)
                    ? Carbon::createFromFormat('d/m/Y', (string) $date)
                    : Carbon::parse($date));
            $plate = strtoupper(trim((string) $values[$map['Placa']])); $fleet = strtoupper(trim((string) $values[$map['Nº Frota']]));
            $iron = trim((string) ($values[$map['Nº Ferro']] ?? '')) ?: null;
            $km = $values[$map['KM']] ?? null;
            $notes=[];
            $sourceNote = isset($map['Observação']) ? trim((string) ($values[$map['Observação']] ?? '')) : '';
            if ($sourceNote !== '') $notes[] = $sourceNote;
            if ($fleet === 'VCA001') { $date = Carbon::create(2026,8,25); $notes[]='Data corrigida historicamente de 25/08/2025 para 25/08/2026.'; }
            if (in_array($fleet, ['VCA004','VCA009','VCA012'], true) || $fleet === 'VCA010') { $km=null; if ($fleet === 'VCA010') $notes[]='KM da ficha não confiável; 2605100 preservado exclusivamente como ferro.'; }
            $treads=[]; foreach (['Sulco 1 (mm)','Sulco 2 (mm)','Sulco 3 (mm)','Sulco 4 (mm)'] as $column) $treads[] = isset($map[$column]) && $values[$map[$column]] !== null && $values[$map[$column]] !== '' ? (float)$values[$map[$column]] : null;
            $measuredTreads = array_values(array_filter($treads, fn ($value) => $value !== null));
            $out[]=['line'=>$index+2,'date'=>$date->toDateString(),'plate'=>$plate,'fleet'=>$fleet,'iron'=>$iron,'position'=>trim((string)($values[$map['Posição']]??'')) ?: null,'km'=>$km !== null && $km !== '' ? (int)$km : null,'brand'=>trim((string)($values[$map['Marca']]??'')) ?: null,'model'=>trim((string)($values[$map['Modelo']]??'')) ?: null,'treads'=>$treads,'average_tread'=>round(array_sum($measuredTreads) / count($measuredTreads), 2),'minimum_tread'=>(float)$values[$map['Menor sulco (mm)']],'notes'=>$notes];
        }
        return $out;
    }

    private function summary(array $rows): array
    {
        $created = collect($rows)->filter(fn($r)=>$r['canCreate']);
        $measured = $created->filter(fn($r)=>$r['canMeasure']);
        return ['rows'=>$rows,'total'=>count($rows),'create'=>$created->count(),'measurements'=>$measured->count(),'installations'=>$measured->count(),'registered_only'=>$created->reject(fn($r)=>$r['canMeasure'])->count(),'blocked'=>collect($rows)->where('blocked',true)->count(),'duplicates'=>collect($rows)->filter(fn($r)=>in_array('ferro_duplicado',$r['issues'],true))->count(),'position_conflicts'=>collect($rows)->filter(fn($r)=>in_array('posicao_ausente',$r['issues'],true)||in_array('posicao_invalida_layout',$r['issues'],true))->count(),'vehicles_not_found'=>collect($rows)->filter(fn($r)=>in_array('veiculo_nao_encontrado_ou_divergente',$r['issues'],true))->count(),'by_vehicle'=>collect($rows)->groupBy('fleet')->map(fn($g)=>['expected'=>$g->count(),'install'=>$g->where('canInstall',true)->count(),'registered_only'=>$g->filter(fn($r)=>$r['canCreate']&&!$r['canMeasure'])->count(),'missing'=>$g->where('blocked',true)->count()])->all(),'blocks'=>collect($rows)->filter(fn($r)=>$r['blocked'])->values()->all()];
    }

    private function positionsFor(?string $layout): array
    {
        return match ($layout) { 'truck_10_mixed' => ['1E','1D','2EI','2EE','2DI','2DE','3EI','3EE','3DI','3DE'], 'truck_6_mixed' => ['1E','1D','2EI','2EE','2DI','2DE'], default => [] };
    }
}
