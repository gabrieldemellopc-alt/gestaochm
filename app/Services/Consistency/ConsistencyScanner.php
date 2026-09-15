<?php
namespace App\Services\Consistency;

use App\Models\DataConsistencyAlert;
use App\Models\FuelFilling;
use App\Models\Vehicle;
use App\Models\VehicleUpdateLog;
use Illuminate\Support\Collection;

/** Read-only operational checks. Rules return normalized occurrences; persistence is centralized here. */
class ConsistencyScanner
{
    public function scan(array $scope = [], ?string $onlyRule = null, bool $dryRun = false): array
    {
        $startedAt = now();
        $rules = [
            'vehicle_reading_regression' => fn () => $this->readingRegression($scope),
            'vehicle_reading_jump' => fn () => $this->readingJumps($scope),
            'vehicle_repeated_reading' => fn () => $this->repeatedReadings($scope),
            'fuel_probable_duplicate' => fn () => $this->duplicateFillings($scope),
            'fuel_impossible_consumption' => fn () => $this->impossibleConsumption($scope),
            'vehicle_current_counter_divergence' => fn () => $this->counterDivergence($scope),
            'vehicle_possible_duplicate' => fn () => $this->duplicateVehicles($scope),
        ];
        if ($onlyRule && ! isset($rules[$onlyRule])) throw new \InvalidArgumentException("Regra desconhecida: {$onlyRule}");
        $summary = ['rules' => [], 'new' => 0, 'updated' => 0, 'existing' => 0, 'severity' => []];
        if (! $dryRun) DataConsistencyAlert::query()->where('tenant_id', $scope['tenant_id'])->when($scope['division_id'] ?? null, fn($q, $v) => $q->where('division_id', $v))->when($scope['location_id'] ?? null, fn($q, $v) => $q->where('location_id', $v))->update(['last_scanned_at' => $startedAt]);
        foreach ($rules as $key => $run) {
            if ($onlyRule && $onlyRule !== $key) continue;
            foreach ($run() as $occurrence) {
                $summary['rules'][$key] = ($summary['rules'][$key] ?? 0) + 1;
                $summary['severity'][$occurrence['severity']] = ($summary['severity'][$occurrence['severity']] ?? 0) + 1;
                if ($dryRun) continue;
                $result = $this->persist($occurrence, $startedAt); $summary[$result]++;
            }
        }
        return $summary;
    }

    private function base(array $scope): \Illuminate\Database\Eloquent\Builder { return Vehicle::query()->where('tenant_id', $scope['tenant_id'] ?? null)->when($scope['division_id'] ?? null, fn($q,$v)=>$q->where('division_id',$v))->when($scope['location_id'] ?? null, fn($q,$v)=>$q->where('location_id',$v)); }
    private function logs(array $scope): Collection
    {
        // A missing event time means the insertion order cannot safely be used as
        // operational chronology. Corrections are audit events, not new readings.
        return VehicleUpdateLog::query()
            ->with('vehicle:id,tenant_id,division_id,location_id,name,plate,asset_code,km_meter_status,hours_meter_status')
            ->whereHas('vehicle', fn($q) => $this->scopeVehicles($q, $scope))
            ->whereIn('type', ['km', 'hours'])->usableReading()->whereNotNull('new_value')->whereNotNull('read_at')
            ->whereNotIn('source', ['initial_registration', 'reading_correction', 'administrative_correction'])
            ->orderBy('vehicle_id')->orderBy('type')->orderBy('read_at')->orderBy('id')->get();
    }
    private function scopeVehicles($q, array $scope) { $q->where('tenant_id',$scope['tenant_id'] ?? null)->when($scope['division_id'] ?? null,fn($q,$v)=>$q->where('division_id',$v))->when($scope['location_id'] ?? null,fn($q,$v)=>$q->where('location_id',$v)); }
    private function occurrence(string $rule, string $severity, string $title, string $summary, Vehicle $vehicle, array $details, ?int $relatedId = null): array { $context=$this->contextFor($rule,$details); $fingerprintDetails=$details; unset($fingerprintDetails['magnitude'],$fingerprintDetails['difference']); $fingerprint=hash('sha256', implode('|',[$rule,Vehicle::class,$vehicle->id,$relatedId ?? '',json_encode($fingerprintDetails)])); return compact('rule','severity','title','summary','details','fingerprint','context') + ['context_type'=>$context,'module'=>'fleet','rule_key'=>$rule,'entity_type'=>Vehicle::class,'entity_id'=>$vehicle->id,'related_entity_type'=>$relatedId ? VehicleUpdateLog::class : null,'related_entity_id'=>$relatedId,'tenant_id'=>$vehicle->tenant_id,'division_id'=>$vehicle->division_id,'location_id'=>$vehicle->location_id]; }
    private function readingRegression(array $scope): array { $out=[]; foreach($this->logs($scope)->groupBy(fn($l)=>$l->vehicle_id.'-'.$l->type) as $items) { $previous=null; foreach($items as $log) { if($this->unreliable($log->vehicle,$log->type)){ $previous=null; continue; } if($previous && (float)$log->new_value < (float)$previous->new_value){$drop=(float)$previous->new_value-(float)$log->new_value;$severity=$drop>=50000?'critical':($drop>=1000?'warning':'review');$out[]=$this->occurrence('vehicle_reading_regression',$severity,'Regressão de '.($log->type==='km'?'KM':'horímetro'),'Leitura posterior inferior à leitura cronológica anterior.',$log->vehicle,['previous'=>['id'=>$previous->id,'at'=>$previous->read_at?->toDateTimeString(),'value'=>$previous->new_value,'source'=>$previous->source],'current'=>['id'=>$log->id,'at'=>$log->read_at?->toDateTimeString(),'value'=>$log->new_value,'source'=>$log->source],'magnitude'=>$drop,'type'=>$log->type],$log->id);} $previous=$log; } } return $out; }
    private function readingJumps(array $scope): array { $out=[]; foreach($this->logs($scope)->groupBy(fn($l)=>$l->vehicle_id.'-'.$l->type) as $items) { $p=null; foreach($items as $l) { if($this->unreliable($l->vehicle,$l->type)){ $p=null; continue; } if($p){$days=max(1,$p->read_at->diffInDays($l->read_at));$delta=(float)$l->new_value-(float)$p->new_value;$limit=$l->type==='km'?max(50000,10000*$days):max(2000,200*$days);if($delta>$limit)$out[]=$this->occurrence('vehicle_reading_jump','warning','Salto anormal de contador','Crescimento evidentemente incompatível com o intervalo operacional.',$l->vehicle,['previous'=>['id'=>$p->id,'value'=>$p->new_value,'at'=>$p->read_at->toDateTimeString()],'current'=>['id'=>$l->id,'value'=>$l->new_value,'at'=>$l->read_at->toDateTimeString()],'delta'=>$delta,'days'=>$days,'type'=>$l->type],$l->id);} $p=$l;} } return $out; }
    private function repeatedReadings(array $scope): array { $out=[]; foreach($this->logs($scope)->where('type','km')->groupBy('vehicle_id') as $items) { $run=collect(); foreach($items as $log) { if($this->unreliable($log->vehicle,'km')) break; if($run->isNotEmpty() && (float)$run->first()->new_value !== (float)$log->new_value){$out=array_merge($out,$this->repeatOccurrence($run));$run=collect();} $run->push($log); } $out=array_merge($out,$this->repeatOccurrence($run)); } return $out; }
    private function repeatOccurrence(Collection $run): array { if($run->count()<3 || $run->first()->read_at->toDateString()===$run->last()->read_at->toDateString())return []; $v=$run->first()->vehicle; return [$this->occurrence('vehicle_repeated_reading','review','Padrão de leituras repetidas','Três ou mais leituras iguais em dias diferentes impedem a análise segura de consumo.',$v,['value'=>$run->first()->new_value,'log_ids'=>$run->pluck('id')->all(),'from'=>$run->first()->read_at->toDateTimeString(),'to'=>$run->last()->read_at->toDateTimeString(),'events'=>$run->count()],$run->first()->id)]; }
    private function duplicateFillings(array $scope): array { $q=FuelFilling::query()->with(['vehicle:id,tenant_id,division_id,location_id,name,plate,asset_code','responsible:id,name'])->whereNull('cancelled_at')->whereHas('vehicle',fn($q)=>$this->scopeVehicles($q,$scope))->orderBy('vehicle_id')->orderBy('filled_at'); $out=[]; foreach($q->get()->groupBy(fn($f)=>$f->vehicle_id.'|'.$f->filled_at->toDateString()) as $items) foreach($items as $f) foreach($items as $other) if($f->id>$other->id && abs((float)$f->quantity_liters-(float)$other->quantity_liters)<=.01 && abs((float)$f->total_cost-(float)$other->total_cost)<=.02) $out[]=$this->occurrence('fuel_probable_duplicate','warning','Possível abastecimento duplicado','Mesmo veículo, dia, litros e valor em dois abastecimentos.',$f->vehicle,['fillings'=>[$this->fillingDetails($other),$this->fillingDetails($f)]],$f->id); return $out; }
    private function fillingDetails(FuelFilling $f): array{return ['id'=>$f->id,'at'=>$f->filled_at?->toDateTimeString(),'liters'=>$f->quantity_liters,'total'=>$f->total_cost,'km'=>$f->vehicle_km,'hours'=>$f->vehicle_hours,'responsible'=>$f->responsible?->name];}
    private function impossibleConsumption(array $scope): array { $out=[]; $f=FuelFilling::query()->with(['vehicle:id,tenant_id,division_id,location_id,name,plate,asset_code,km_meter_status','vehicleReadingLogs:id,fuel_filling_id,type,reading_status'])->whereNull('cancelled_at')->whereNotNull('vehicle_km')->where(fn($q)=>$q->whereNull('vehicle_km_status')->orWhere('vehicle_km_status','valid'))->whereHas('vehicle',fn($q)=>$this->scopeVehicles($q,$scope))->orderBy('vehicle_id')->orderBy('filled_at')->get(); foreach($f->groupBy('vehicle_id') as $items){$p=null;foreach($items as $x){$log=$x->vehicleReadingLogs->firstWhere('type','km');if($this->unreliable($x->vehicle,'km') || ($log && !$log->is_reading_usable) || (float)$x->vehicle_km<=1){$p=null;continue;} if($p){$distance=(float)$x->vehicle_km-(float)$p->vehicle_km;$average=$x->quantity_liters>0?$distance/(float)$x->quantity_liters:null;if($distance<0 || $average>20)$out[]=$this->occurrence('fuel_impossible_consumption','warning','Consumo impossível ou fora do limite','Intervalo válido com regressão de distância ou rendimento acima do limite operacional.',$x->vehicle,['previous_filling'=>$p->id,'filling'=>$x->id,'distance'=>$distance,'km_per_liter'=>$average],$x->id);} $p=$x;}} return $out; }
    private function counterDivergence(array $scope): array { $out=[]; foreach($this->base($scope)->get() as $v) foreach(['km'=>'current_km','hours'=>'current_hours'] as $type=>$field){if(($type==='km' && !$v->km_control_enabled) || ($type==='hours' && !$v->hours_control_enabled) || $this->unreliable($v,$type))continue;$last=VehicleUpdateLog::query()->where('vehicle_id',$v->id)->where('type',$type)->usableReading()->whereNotNull('read_at')->whereNotIn('source',['initial_registration','reading_correction','administrative_correction'])->orderByDesc('read_at')->orderByDesc('id')->first(); if($last && $v->{$field} !== null && (float)$v->{$field}<(float)$last->new_value){$gap=(float)$last->new_value-(float)$v->{$field};$out[]=$this->occurrence('vehicle_current_counter_divergence',$gap>=50000?'critical':($gap>=1000?'warning':'review'),'Contador atual divergente','O contador atual está abaixo da última leitura válida cronológica.',$v,['type'=>$type,'current'=>$v->{$field},'last_valid'=>$last->new_value,'difference'=>$gap,'log_id'=>$last->id,'source'=>$last->source],$last->id);}} return $out; }
    private function contextFor(string $rule, array $details): string
    {
        if ($rule === 'vehicle_reading_regression') {
            return (($details['previous']['source'] ?? null) === 'fuel_filling_import' && ($details['current']['source'] ?? null) === 'fuel_filling_import') ? 'historical' : 'current';
        }
        if ($rule === 'vehicle_repeated_reading') {
            if (! \Illuminate\Support\Facades\Schema::hasTable('vehicle_update_logs')) return 'current';
            $sources=VehicleUpdateLog::query()->whereIn('id',$details['log_ids'] ?? [])->pluck('source');
            return $sources->isNotEmpty() && $sources->every(fn($source) => $source === 'fuel_filling_import') ? 'historical' : 'current';
        }
        if ($rule === 'fuel_impossible_consumption') {
            if (! \Illuminate\Support\Facades\Schema::hasTable('vehicle_update_logs')) return 'current';
            $sources=VehicleUpdateLog::query()->whereIn('fuel_filling_id',array_filter([$details['previous_filling'] ?? null,$details['filling'] ?? null]))->where('type','km')->pluck('source');
            return $sources->count() >= 2 && $sources->every(fn($source) => $source === 'fuel_filling_import') ? 'historical' : 'current';
        }
        return (($details['source'] ?? null) === 'fuel_filling_import') ? 'historical' : 'current';
    }
    private function unreliable(Vehicle $vehicle, string $type): bool { return $type === 'km' ? $vehicle->km_meter_status === Vehicle::METER_STATUS_UNRELIABLE : $vehicle->hours_meter_status === Vehicle::METER_STATUS_UNRELIABLE; }
    private function duplicateVehicles(array $scope): array { $out=[]; foreach(['plate'=>'Placa','renavam'=>'RENAVAM','serial_number'=>'número de série','asset_code'=>'código patrimonial'] as $field=>$label) foreach($this->base($scope)->whereNotNull($field)->where($field,'!=','')->where('status','active')->get()->groupBy(fn($v)=>strtoupper(preg_replace('/[^A-Z0-9]/i','',(string)$v->{$field}))) as $value=>$vehicles) if($vehicles->count()>1) foreach($vehicles->skip(1) as $v)$out[]=$this->occurrence('vehicle_possible_duplicate','warning','Possível veículo duplicado','Identificador forte repetido: '.$label.'.',$v,['field'=>$field,'value'=>$value,'vehicle_ids'=>$vehicles->pluck('id')->values()->all()],$vehicles->first()->id); return $out; }
    private function persist(array $o, $startedAt): string { $alert=DataConsistencyAlert::query()->where('tenant_id',$o['tenant_id'])->where('fingerprint',$o['fingerprint'])->first(); if($alert){$alert->update(['last_detected_at'=>$startedAt,'last_scanned_at'=>$startedAt,'context_type'=>$o['context_type']]);return 'existing';} DataConsistencyAlert::create($o+['status'=>'new','first_detected_at'=>$startedAt,'last_detected_at'=>$startedAt,'last_scanned_at'=>$startedAt]);return 'new'; }
}
