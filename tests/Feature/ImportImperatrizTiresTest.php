<?php

namespace Tests\Feature;

use App\Models\{Division, Location, Tenant, Tire, TireInstallation, TireMeasurement, User, Vehicle};
use App\Services\ImperatrizHistoricalTireImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportImperatrizTiresTest extends TestCase
{
    use RefreshDatabase;

    public function test_historical_import_is_safe_exact_and_idempotent(): void
    {
        [$location, $user] = $this->context();
        $file = $this->sheet();
        $service = app(ImperatrizHistoricalTireImportService::class);

        $this->artisan('tires:import-imperatriz', ['file'=>$file, '--location'=>3, '--dry-run'=>true])->assertExitCode(0);
        $this->assertDatabaseCount('tires', 0);
        $this->assertDatabaseCount('tire_measurements', 0);
        $this->assertDatabaseCount('tire_installations', 0);

        $plan = $service->plan($file, $location);
        $this->assertSame(11, $plan['total']);
        $this->assertSame(7, $plan['create']);
        $this->assertSame(7, $plan['measurements']);
        $this->assertSame(7, $plan['installations']);
        $this->assertSame(4, $plan['blocked']);
        $this->assertSame(2, $plan['duplicates']);
        $this->assertSame(1, $plan['by_vehicle']['VCA007']['install']);
        $this->assertSame(1, $plan['by_vehicle']['VCA016']['install']);

        $kmBefore = Vehicle::where('name', 'VCA001')->value('current_km');
        $service->execute($plan, $location, $user);
        $this->assertDatabaseCount('tires', 7);
        $this->assertDatabaseCount('tire_measurements', 7);
        $this->assertDatabaseCount('tire_installations', 7);
        $this->assertDatabaseHas('tires', ['code'=>'FERRO-001', 'status'=>'installed']);
        $this->assertSame($kmBefore, Vehicle::where('name', 'VCA001')->value('current_km'));
        $this->assertDatabaseHas('tire_measurements', ['vehicle_km'=>null]);
        $this->assertDatabaseHas('tire_installations', ['position_code'=>'3EE']);
        $this->assertDatabaseHas('tire_installations', ['position_code'=>'3DI']);
        $this->assertSame('2026-08-25', Tire::where('code', 'FERRO-001')->first()->purchase_date->toDateString());
        $this->assertSame('2026-08-25', TireMeasurement::whereHas('tire', fn ($q) => $q->where('code','FERRO-001'))->first()->measured_at->toDateString());

        $again = $service->plan($file, $location);
        $this->assertSame(0, $again['create']);
        $this->assertSame(7, TireInstallation::count());

        $bad = $plan; $bad['rows'][0]['iron'] = 'ROLLBACK-FERRO'; $bad['rows'][1]['iron'] = 'ROLLBACK-FERRO';
        try { $service->execute($bad, $location, $user); } catch (\Throwable) { /* expected rollback */ }
        $this->assertSame(7, Tire::count());
        $this->assertDatabaseMissing('tires', ['code'=>'ROLLBACK-FERRO']);
    }

    private function context(): array
    {
        $tenant = Tenant::create(['name'=>'Tenant']);
        $division = Division::create(['tenant_id'=>$tenant->id, 'name'=>'Divisão']);
        $location = Location::forceCreate(['id'=>3, 'tenant_id'=>$tenant->id, 'division_id'=>$division->id, 'name'=>'Imperatriz', 'active'=>true]);
        $user = User::forceCreate(['tenant_id'=>$tenant->id, 'name'=>'Importador', 'email'=>'importador@example.test', 'password'=>'x']);
        foreach ([['VCA001','TMO-8H32','truck_6_mixed'],['VCA004','JAC-2E39','truck_6_mixed'],['VCA007','TMS-1J41','truck_10_mixed'],['VCA009','JAC-2E43','truck_6_mixed'],['VCA010','THG-9B61','truck_6_mixed'],['VCA012','TMT-3G93','truck_6_mixed'],['VCA016','TMR-6E70','truck_10_mixed']] as [$name,$plate,$layout]) Vehicle::create(['tenant_id'=>$tenant->id,'division_id'=>$division->id,'location_id'=>$location->id,'name'=>$name,'plate'=>$plate,'type'=>'lixo','fleet_relation'=>'internal','tire_layout'=>$layout,'current_km'=>99999,'current_hours'=>77]);
        return [$location, $user];
    }

    private function sheet(): string
    {
        $book = new Spreadsheet(); $ws = $book->getActiveSheet(); $ws->setTitle('Pneus_Normalizados');
        $headers=['Data conferência','Placa','Nº Frota','KM','HR','Posição','Nº Ferro','Marca','Modelo','Vida','Sulco 1 (mm)','Sulco 2 (mm)','Sulco 3 (mm)','Sulco 4 (mm)','Menor sulco (mm)']; $ws->fromArray($headers, null, 'A1');
        $rows=[
            ['25/08/2025','TMO-8H32','VCA001',100,null,'1E','FERRO-001','M','X',null,10,9,8,7,7],
            ['25/08/2026','TMS-1J41','VCA007',200,null,'3EE','FERRO-007','M','X',null,10,9,8,7,7],
            ['25/08/2026','TMR-6E70','VCA016',200,null,'3DI','FERRO-016','M','X',null,10,9,8,7,7],
            ['25/08/2026','THG-9B61','VCA010',2605100,null,'1E','FERRO-010','M','X',null,10,9,8,7,7],
            ['25/08/2026','JAC-2E39','VCA004',null,null,'1E','FERRO-004','M','X',null,10,9,8,7,7],
            ['25/08/2026','JAC-2E43','VCA009',null,null,'1E','FERRO-009','M','X',null,10,9,8,7,7],
            ['25/08/2026','TMT-3G93','VCA012',12000,null,'1E','FERRO-012','M','X',null,10,9,8,7,7],
            ['25/08/2026','TMO-8H32','VCA001',100,null,'1D','DUP','M','X',null,10,9,8,7,7],
            ['25/08/2026','TMO-8H32','VCA001',100,null,'2EI','DUP','M','X',null,10,9,8,7,7],
            ['25/08/2026','TMO-8H32','VCA001',100,null,null,'S/N','M','X',null,10,9,8,7,7],
            ['25/08/2026','TMO-8H32','VCA001',100,null,null,'2605114','M','X',null,10,9,8,7,7],
        ]; $ws->fromArray($rows, null, 'A2'); $file=tempnam(sys_get_temp_dir(),'tires-').'.xlsx'; (new Xlsx($book))->save($file); return $file;
    }
}
