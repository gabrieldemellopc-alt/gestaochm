<?php
namespace App\Http\Controllers;
use App\Models\DataConsistencyAlert;
use App\Models\Vehicle;
use App\Services\ActiveContextService;
use App\Services\AuditLogService;
use App\Support\ConsistencyAlertPresenter;
use Illuminate\Http\Request;
class ConsistencyController extends Controller
{
    public function index(Request $request, ActiveContextService $context)
    {
        $user=$request->user(); $this->ensureAdministrator($user); $division=$context->activeDivision($user); if(!$division)return redirect()->route('portal')->with('warning','Selecione uma divisão para consultar a Central de Consistência.');
        $locations=$context->availableLocations($user,$division->id); $locationId=$request->filled('location_id')?$request->integer('location_id'):null; abort_if($locationId && !$locations->contains('id',$locationId),403);
        $query=DataConsistencyAlert::query()->with(['reviewer:id,name','location:id,name'])->where('tenant_id',$user->tenant_id)->where('division_id',$division->id)->when($locationId,fn($q)=>$q->where('location_id',$locationId),fn($q)=>$q->whereIn('location_id',$locations->pluck('id')));
        $status=$request->input('status'); if($status)$query->where('status',$status); else $query->whereIn('status',['new','reviewing']);
        foreach(['severity','module'] as $key) $query->when($request->filled($key),fn($q)=>$q->where($key,$request->input($key)));
        $query->when($request->filled('context_type'), fn($q) => $q->where('context_type', $request->input('context_type')));
        $query->when($request->filled('date_from'),fn($q)=>$q->whereDate('last_detected_at','>=',$request->date('date_from')))->when($request->filled('date_to'),fn($q)=>$q->whereDate('last_detected_at','<=',$request->date('date_to')))->when($request->filled('search'),function($q)use($request){$s='%'.trim($request->input('search')).'%';$q->where(fn($q)=>$q->where('title','like',$s)->orWhere('summary','like',$s)->orWhere('details','like',$s));});
        $base=DataConsistencyAlert::query()->where('tenant_id',$user->tenant_id)->where('division_id',$division->id)->whereIn('location_id',$locations->pluck('id'));
        $query->orderByRaw("case context_type when 'current' then 0 else 1 end")->orderByRaw("case severity when 'critical' then 0 when 'warning' then 1 when 'review' then 2 else 3 end");
        $alerts=$query->latest('last_detected_at')->paginate(20)->withQueryString();
        $vehicles=Vehicle::query()->whereIn('id',$alerts->getCollection()->where('entity_type',Vehicle::class)->pluck('entity_id'))->get(['id','name','plate','asset_code','renavam','serial_number'])->keyBy('id');
        $alerts->getCollection()->each(fn(DataConsistencyAlert $alert) => $alert->setRelation('consistencyVehicle',$vehicles->get($alert->entity_id)));
        return view('administration.consistency.index',['presenter'=>app(ConsistencyAlertPresenter::class),'alerts'=>$alerts,'locations'=>$locations,'filters'=>$request->all(),'kpis'=>['new'=>(clone $base)->whereIn('status',['new','reviewing'])->count(),'critical'=>(clone $base)->where('severity','critical')->where('context_type','current')->whereIn('status',['new','reviewing'])->count(),'reviewing'=>(clone $base)->where('status','reviewing')->count(),'historical'=>(clone $base)->where('context_type','historical')->whereIn('status',['new','reviewing'])->count(),'resolved'=>(clone $base)->where('status','resolved')->count(),'ignored'=>(clone $base)->where('status','ignored')->count()]]);
    }
    public function update(Request $request, DataConsistencyAlert $alert, AuditLogService $audit)
    {
        $this->ensureAdministrator($request->user()); $this->authorizeAlert($request,$alert); $data=$request->validate(['status'=>'required|in:reviewing,resolved,ignored,archived','resolution_note'=>'nullable|string|max:3000']); if($data['status']==='ignored' && blank($data['resolution_note']??null)) return back()->withErrors(['resolution_note'=>'Informe a justificativa para ignorar o alerta.'])->withInput();
        $before=$alert->status; $now=now(); $values=['status'=>$data['status'],'reviewed_by'=>$request->user()->id,'reviewed_at'=>$now,'resolution_note'=>$data['resolution_note']??$alert->resolution_note]; if($data['status']==='resolved')$values['resolved_at']=$now; if($data['status']==='ignored')$values['ignored_at']=$now; if($data['status']==='archived')$values['archived_at']=$now; $alert->update($values);
        $audit->record(['auditable'=>$alert,'tenant_id'=>$alert->tenant_id,'division_id'=>$alert->division_id,'location_id'=>$alert->location_id,'module'=>'consistency','action'=>'consistency_alert_'.$data['status'],'summary'=>'Status do alerta de consistência atualizado.','metadata'=>['alert_id'=>$alert->id,'rule_key'=>$alert->rule_key,'entity'=>[$alert->entity_type,$alert->entity_id],'before_status'=>$before,'after_status'=>$data['status'],'note'=>$values['resolution_note']]]);
        return back()->with('success','Alerta atualizado.');
    }
    private function authorizeAlert(Request $request, DataConsistencyAlert $alert):void { $user=$request->user(); abort_unless($alert->tenant_id===$user->tenant_id && $alert->division_id===session('active_division_id') && app(ActiveContextService::class)->availableLocations($user,$alert->division_id)->contains('id',$alert->location_id),403); }
    private function ensureAdministrator($user): void { abort_unless((int) $user->id === 1 || userHasProfile('admin'), 403); }
}
