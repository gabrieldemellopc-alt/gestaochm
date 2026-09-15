<?php
namespace Tests\Feature;

use App\Models\Division;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Vehicle;
use App\Models\VehicleUpdateLog;
use App\Services\Consistency\ConsistencyScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsistencyScannerIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_regression_is_detected_but_suspect_correction_and_missing_event_time_are_excluded(): void
    {
        [$v,$scope]=$this->vehicle();
        $this->log($v,100,'2026-09-01'); $this->log($v,90,'2026-09-02');
        $this->log($v,1,'2026-09-03','suspect'); $this->log($v,1,null,null,'administrative_correction');
        $this->assertSame(1,$this->scan($scope,'vehicle_reading_regression'));
    }
    public function test_jump_uses_event_time_and_requires_absurd_growth(): void
    {
        [$v,$scope]=$this->vehicle(); $this->log($v,100,'2026-09-01'); $this->log($v,60000,'2026-09-02');
        $this->assertSame(1,$this->scan($scope,'vehicle_reading_jump'));
        [$normal,$scope]=$this->vehicle(); $this->log($normal,100,'2026-09-01'); $this->log($normal,9000,'2026-09-02');
        $this->assertSame(1,$this->scan($scope,'vehicle_reading_jump'));
    }
    public function test_repetitions_are_aggregated_and_unreliable_is_excluded(): void
    {
        [$v,$scope]=$this->vehicle(); foreach(['01','02','03','04'] as $d)$this->log($v,500,"2026-09-$d");
        $this->assertSame(1,$this->scan($scope,'vehicle_repeated_reading'));
        [$bad,$scope]=$this->vehicle(['km_meter_status'=>'unreliable']); foreach(['01','02','03'] as $d)$this->log($bad,500,"2026-09-$d");
        $this->assertSame(1,$this->scan($scope,'vehicle_repeated_reading'));
    }
    public function test_counter_divergence_respects_enabled_counter_and_unreliable_meter(): void
    {
        [$v,$scope]=$this->vehicle(['current_km'=>100]); $this->log($v,200,'2026-09-02');
        $this->assertSame(1,$this->scan($scope,'vehicle_current_counter_divergence'));
        [$disabled,$scope]=$this->vehicle(['current_km'=>0,'km_control_enabled'=>false]); $this->log($disabled,200,'2026-09-02');
        [$unreliable,$scope]=$this->vehicle(['current_km'=>0,'km_meter_status'=>'unreliable']); $this->log($unreliable,200,'2026-09-02');
        $this->assertSame(1,$this->scan($scope,'vehicle_current_counter_divergence'));
    }
    public function test_strong_normalized_vehicle_identifiers_are_detected_but_names_are_not(): void
    {
        [$v,$scope]=$this->vehicle(['plate'=>'ABC-1234']); $this->vehicle(['plate'=>'abc1234']);
        $this->vehicle(['name'=>'Mesmo nome']); $this->vehicle(['name'=>'Mesmo nome']);
        $this->assertSame(1,$this->scan($scope,'vehicle_possible_duplicate'));
    }
    public function test_persistent_scan_is_idempotent_and_updates_scan_timestamp(): void
    {
        [$v,$scope]=$this->vehicle(); $this->log($v,100,'2026-09-01'); $this->log($v,90,'2026-09-02'); $scanner=app(ConsistencyScanner::class);
        $this->assertSame(1,$scanner->scan($scope,'vehicle_reading_regression',false)['new']);
        $first=\App\Models\DataConsistencyAlert::first(); $first->update(['status'=>'ignored']);
        $this->assertSame(1,$scanner->scan($scope,'vehicle_reading_regression',false)['existing']); $this->assertDatabaseCount('data_consistency_alerts',1); $this->assertSame('ignored',$first->fresh()->status); $this->assertNotNull($first->fresh()->last_scanned_at);
    }
    private function scan(array $scope,string $rule): int { return app(ConsistencyScanner::class)->scan($scope,$rule,true)['rules'][$rule]??0; }
    private function vehicle(array $extra=[]): array { $t=Tenant::firstOrCreate(['name'=>'T']); $d=Division::firstOrCreate(['tenant_id'=>$t->id,'name'=>'D']); $l=Location::firstOrCreate(['tenant_id'=>$t->id,'division_id'=>$d->id,'name'=>'L']); $v=Vehicle::create(array_merge(['tenant_id'=>$t->id,'division_id'=>$d->id,'location_id'=>$l->id,'name'=>'V'.uniqid(),'plate'=>'P'.uniqid(),'type'=>'automovel','status'=>'active','current_km'=>0,'km_control_enabled'=>true,'hours_control_enabled'=>false,'km_meter_status'=>'normal','hours_meter_status'=>'normal'],$extra)); return [$v,['tenant_id'=>$t->id,'division_id'=>$d->id,'location_id'=>$l->id]]; }
    private function log(Vehicle $v,$value,?string $at,?string $status=null,?string $source='fuel_filling_import'): void { VehicleUpdateLog::create(['vehicle_id'=>$v->id,'division_id'=>$v->division_id,'location_id'=>$v->location_id,'type'=>'km','new_value'=>$value,'read_at'=>$at,'reading_status'=>$status,'source'=>$source]); }
}
