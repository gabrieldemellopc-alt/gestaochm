<?php

namespace Tests\Unit;

use App\Exports\MaintenanceReportExport;
use App\Http\Controllers\ReportController;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceRecordItem;
use App\Models\Procedure;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Collection;
use ReflectionMethod;
use Tests\TestCase;

class MaintenanceReportChangesTest extends TestCase
{
    public function test_changes_are_absent_without_permission(): void { $this->assertSame([], $this->changes(['can_view_changes'=>false,'can_view_costs'=>false])); }
    public function test_changes_are_structured_and_costs_are_hidden_without_cost_permission(): void { $changes=$this->changes(['can_view_changes'=>true,'can_view_costs'=>false]); $this->assertCount(1,$changes); $this->assertSame('Servico anterior',$changes[0]['old_procedure']); $this->assertSame('Servico novo',$changes[0]['replacement_procedure']); $this->assertNull($changes[0]['old_cost']); $this->assertNull($changes[0]['replacement_cost']); $this->assertNull($changes[0]['returned_stock'][0]['unit_cost']); $this->assertNull($changes[0]['new_stock'][0]['total_cost']); $this->assertFalse($changes[0]['considered_in_totals']); }
    public function test_change_costs_are_available_with_cost_permission(): void { $changes=$this->changes(['can_view_changes'=>true,'can_view_costs'=>true]); $this->assertSame(100.0,$changes[0]['old_cost']); $this->assertSame(150.0,$changes[0]['replacement_cost']); $this->assertSame(25.0,$changes[0]['returned_stock'][0]['total_cost']); $this->assertSame(40.0,$changes[0]['new_stock'][0]['total_cost']); }
    public function test_excel_adds_a_separate_changes_sheet_only_when_authorized(): void { $maintenance=['changes'=>[['old_procedure'=>'A']]]; $authorized=new MaintenanceReportExport(['canViewChanges'=>true,'maintenances'=>[$maintenance]]); $unauthorized=new MaintenanceReportExport(['canViewChanges'=>false,'maintenances'=>[$maintenance]]); $this->assertContains('Alterações',collect($authorized->sheets())->map->title()->all()); $this->assertNotContains('Alterações',collect($unauthorized->sheets())->map->title()->all()); }
    public function test_pdf_changes_partial_is_empty_without_permission(): void { $rows=[['id'=>1,'vehicle_name'=>'Veiculo','vehicle_plate'=>'ABC1D23','changes'=>[['old_procedure'=>'Anterior','replacement_procedure'=>'Novo','changed_by'=>'Gestor','changed_at'=>now(),'reason'=>'Correcao','returned_stock'=>[],'new_stock'=>[],'old_cost'=>null,'replacement_cost'=>null]]]]; $hidden=view('reports.pdf.partials.maintenance-changes',['canViewChanges'=>false,'canViewCosts'=>false,'maintenanceChangeRows'=>$rows])->render(); $visible=view('reports.pdf.partials.maintenance-changes',['canViewChanges'=>true,'canViewCosts'=>false,'maintenanceChangeRows'=>$rows])->render(); $this->assertSame('',trim($hidden)); $this->assertStringContainsString('Serviço anterior',$visible); $this->assertStringNotContainsString('Custo anterior:',$visible); }
    private function changes(array $context): array { $oldProcedure=new Procedure(['name'=>'Servico anterior']); $newProcedure=new Procedure(['name'=>'Servico novo']); $user=new User(['name'=>'Gestor']); $returned=new StockMovement(['movement_type'=>'out','quantity'=>1,'unit_cost'=>25,'total_cost'=>25,'reversal_movement_id'=>99]); $returned->setRelation('stockItem',new StockItem(['name'=>'Peca devolvida'])); $consumed=new StockMovement(['movement_type'=>'out','quantity'=>2,'unit_cost'=>20,'total_cost'=>40]); $consumed->setRelation('stockItem',new StockItem(['name'=>'Peca nova'])); $replacement=new MaintenanceRecordItem(['total_cost'=>150]); $replacement->id=20; $replacement->setRelation('procedure',$newProcedure); $replacement->setRelation('stockMovements',new Collection([$consumed])); $old=new MaintenanceRecordItem(['total_cost'=>100,'cancel_reason'=>'Correcao necessaria','cancellation_type'=>'replacement','cancelled_at'=>now()]); $old->id=10; $old->setRelation('procedure',$oldProcedure); $old->setRelation('canceller',$user); $old->setRelation('replacement',$replacement); $old->setRelation('stockMovements',new Collection([$returned])); $maintenance=new MaintenanceRecord(); $maintenance->setRelation('cancelledItems',new Collection([$old])); return (new ReflectionMethod(ReportController::class,'maintenanceChanges'))->invoke(app(ReportController::class),$maintenance,$context); }
}
