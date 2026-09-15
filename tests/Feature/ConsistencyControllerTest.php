<?php
namespace Tests\Feature;

use App\Models\DataConsistencyAlert;
use App\Models\Division;
use App\Models\Location;
use App\Models\SystemAuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserDivisionAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsistencyControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_and_review_alert_with_audit(): void
    {
        [$user,$alert]=$this->context();
        $this->actingAs($user)->withSession(['active_division_id'=>$alert->division_id,'active_location_id'=>$alert->location_id])
            ->get(route('consistency.index'))->assertOk()->assertSee('Central de Consistência');
        $this->patch(route('consistency.update',$alert),['status'=>'reviewing'])->assertRedirect();
        $this->assertDatabaseHas('data_consistency_alerts',['id'=>$alert->id,'status'=>'reviewing','reviewed_by'=>$user->id]);
        $this->assertDatabaseHas('system_audit_logs',['action'=>'consistency_alert_reviewing','auditable_id'=>$alert->id]);
    }
    public function test_ignore_requires_note_and_resolve_archive_keep_alerts(): void
    {
        [$user,$alert]=$this->context(); $session=['active_division_id'=>$alert->division_id,'active_location_id'=>$alert->location_id];
        $this->actingAs($user)->withSession($session)->from(route('consistency.index'))->patch(route('consistency.update',$alert),['status'=>'ignored'])->assertSessionHasErrors('resolution_note');
        $this->actingAs($user)->withSession($session)->patch(route('consistency.update',$alert),['status'=>'ignored','resolution_note'=>'Confirmado'])->assertRedirect();
        $this->assertDatabaseHas('data_consistency_alerts',['id'=>$alert->id,'status'=>'ignored','resolution_note'=>'Confirmado']);
        $this->actingAs($user)->withSession($session)->patch(route('consistency.update',$alert),['status'=>'resolved','resolution_note'=>'Corrigido'])->assertRedirect();
        $this->actingAs($user)->withSession($session)->patch(route('consistency.update',$alert),['status'=>'archived'])->assertRedirect();
        $this->assertDatabaseHas('data_consistency_alerts',['id'=>$alert->id,'status'=>'archived']); $this->assertSame(3,SystemAuditLog::count());
    }
    public function test_other_tenant_cannot_view_or_patch_alert(): void
    {
        [$user,$alert]=$this->context(); [$otherUser,$otherAlert]=$this->context('Outro');
        $this->actingAs($user)->withSession(['active_division_id'=>$alert->division_id,'active_location_id'=>$alert->location_id])->get(route('consistency.index',['search'=>'Outro']))->assertDontSee('Alerta Outro');
        $this->patch(route('consistency.update',$otherAlert),['status'=>'reviewing'])->assertForbidden();
    }
    private function context(string $suffix=''): array
    {
        $tenant=Tenant::create(['name'=>'Tenant '.$suffix.uniqid()]); $division=Division::create(['tenant_id'=>$tenant->id,'name'=>'Divisão']); $location=Location::create(['tenant_id'=>$tenant->id,'division_id'=>$division->id,'name'=>'Local']);
        $user=User::factory()->create(['tenant_id'=>$tenant->id]); UserDivisionAccess::create(['tenant_id'=>$tenant->id,'user_id'=>$user->id,'division_id'=>$division->id,'location_id'=>null,'module'=>'fleet','profile'=>'admin','active'=>true]);
        $alert=DataConsistencyAlert::create(['tenant_id'=>$tenant->id,'division_id'=>$division->id,'location_id'=>$location->id,'module'=>'fleet','rule_key'=>'test_rule','severity'=>'warning','title'=>'Alerta '.$suffix,'summary'=>'Teste','fingerprint'=>sha1(uniqid('',true)),'status'=>'new','first_detected_at'=>now(),'last_detected_at'=>now(),'last_scanned_at'=>now()]); return [$user,$alert];
    }
}
