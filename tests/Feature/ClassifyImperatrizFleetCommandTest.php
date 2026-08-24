<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Vehicle;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClassifyImperatrizFleetCommandTest extends TestCase
{
    protected function setUp(): void { parent::setUp(); Schema::create('tenants', fn (Blueprint $t) => [$t->id(), $t->timestamps()]); Schema::create('divisions', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('tenant_id'), $t->timestamps()]); Schema::create('locations', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('tenant_id'), $t->unsignedBigInteger('division_id'), $t->string('name')->nullable(), $t->boolean('allow_aggregated_fuel')->default(false), $t->boolean('allow_aggregated_maintenance')->default(false), $t->timestamps()]); Schema::create('vehicles', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('tenant_id'), $t->unsignedBigInteger('division_id'), $t->unsignedBigInteger('location_id'), $t->string('name'), $t->string('asset_code'), $t->string('plate')->nullable(), $t->string('operational_status')->default('operational'), $t->string('fleet_relation')->default('internal'), $t->timestamps()]); }
    public function test_dry_run_does_not_write_and_commit_is_idempotent(): void { [$location] = $this->seedFleet(); $this->artisan('chm:classify-imperatriz-fleet')->expectsOutputToContain('SAFE YES')->assertExitCode(0); $this->assertSame(0, Vehicle::where('fleet_relation', 'aggregated')->count()); $this->artisan('chm:classify-imperatriz-fleet', ['--commit' => true, '--confirm-location' => 3])->assertExitCode(0); $this->assertSame(29, Vehicle::where('fleet_relation', 'aggregated')->count()); $this->assertTrue((bool) $location->fresh()->allow_aggregated_fuel); $this->artisan('chm:classify-imperatriz-fleet', ['--commit' => true, '--confirm-location' => 3])->assertExitCode(0); $this->assertSame(53, Vehicle::count()); }
    public function test_missing_vehicle_is_safe_no_and_other_location_is_untouched(): void { $this->seedFleet(52); $other = Vehicle::create(['tenant_id'=>1,'division_id'=>1,'location_id'=>4,'name'=>'Outro','asset_code'=>'VCA001','fleet_relation'=>'internal']); $this->artisan('chm:classify-imperatriz-fleet')->expectsOutputToContain('SAFE NO')->assertExitCode(1); $this->assertSame('internal', $other->fresh()->fleet_relation); }
    private function seedFleet(int $limit = 53): array { Tenant::forceCreate(['id'=>1]); Division::forceCreate(['id'=>1, 'tenant_id'=>1]); $location = Location::forceCreate(['id'=>3, 'tenant_id'=>1,'division_id'=>1,'name'=>'Imperatriz']); Location::forceCreate(['id'=>4, 'tenant_id'=>1,'division_id'=>1,'name'=>'Outra']); $codes = array_merge(['VCA001','VCA002','VCA003','VCA004','VCA005','VCA006','VCA007','VCA008','VCA009','VCA010','VCA011','VCA012','VCA013','VCA014','VCA015','VCA016','VCA017','VCA018','VBA001','VOA001','VVA001','VVA002','F350AKSA'], ['BXF0B12','BXG9J79','DUI5I26','HPB4781','HXJ5A74','HYT3J97','JAV7I09','JHM6104','JJB8113','JMG5E85','JVY5H96','KDE5579','KDR8I43','MFP0899','MVM5I45','MVZ0628','MWB9265','MWJ4945','NXH5J69','NXP7I77','PSF0060','RET0001','RET0002','RET0003','TRA0004','NHR4951','RET0032','RET0060','RET0089'], ['OQJ6J01']); foreach (array_slice($codes, 0, $limit) as $code) Vehicle::create(['tenant_id'=>1,'division_id'=>1,'location_id'=>3,'name'=>$code,'asset_code'=>$code,'fleet_relation'=>'internal']); return [$location]; }
}
