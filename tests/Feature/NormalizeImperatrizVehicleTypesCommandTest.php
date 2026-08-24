<?php
namespace Tests\Feature;
use App\Models\Division;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Vehicle;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
class NormalizeImperatrizVehicleTypesCommandTest extends TestCase
{
    protected function setUp(): void { parent::setUp(); Schema::create('tenants', fn(Blueprint $t)=>[$t->id(),$t->timestamps()]); Schema::create('divisions',fn(Blueprint $t)=>[$t->id(),$t->unsignedBigInteger('tenant_id'),$t->timestamps()]); Schema::create('locations',fn(Blueprint $t)=>[$t->id(),$t->unsignedBigInteger('tenant_id'),$t->unsignedBigInteger('division_id'),$t->string('name')->nullable(),$t->timestamps()]); Schema::create('vehicles',fn(Blueprint $t)=>[$t->id(),$t->unsignedBigInteger('tenant_id'),$t->unsignedBigInteger('division_id'),$t->unsignedBigInteger('location_id'),$t->string('name'),$t->string('asset_code'),$t->string('type')->default('automovel'),$t->string('operational_status')->default('operational'),$t->timestamps()]); }
    public function test_dry_run_commit_confirmation_and_idempotence(): void { $vehicles=$this->seedFixtures(); $this->artisan('chm:normalize-imperatriz-vehicle-types')->assertExitCode(0); $this->assertSame('automovel',$vehicles->first()->fresh()->type); $this->artisan('chm:normalize-imperatriz-vehicle-types',['--commit'=>true])->expectsOutputToContain('SAFE NO')->assertExitCode(1); $this->artisan('chm:normalize-imperatriz-vehicle-types',['--commit'=>true,'--confirm-location'=>3])->expectsOutputToContain('Updated: 18')->assertExitCode(0); $this->assertSame('pipa',Vehicle::where('asset_code','DUI5I26')->value('type')); $this->assertSame('onibus',Vehicle::where('asset_code','VOA001')->value('type')); $this->assertSame('Preservar', $vehicles->first()->fresh()->name); $this->artisan('chm:normalize-imperatriz-vehicle-types',['--commit'=>true,'--confirm-location'=>3])->expectsOutputToContain('Nothing to update.')->assertExitCode(0); }
    private function seedFixtures() { Tenant::forceCreate(['id'=>1]); Division::forceCreate(['id'=>1,'tenant_id'=>1]); Location::forceCreate(['id'=>3,'tenant_id'=>1,'division_id'=>1]); $map=['VVA001','VVA002','VOA001','JJB8113','MWB9265','MWJ4945','NXP7I77','DUI5I26','HXJ5A74','HYT3J97','MVM5I45','F350AKSA','RET0001','RET0002','RET0003','RET0032','RET0060','RET0089']; return collect($map)->map(fn($code)=>Vehicle::create(['tenant_id'=>1,'division_id'=>1,'location_id'=>3,'name'=>'Preservar','asset_code'=>$code,'type'=>str_starts_with($code,'RET')?'trator':'automovel'])); }
}
