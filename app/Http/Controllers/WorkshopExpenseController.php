<?php

namespace App\Http\Controllers;

use App\Models\StockItem;
use App\Models\WorkshopExpense;
use App\Services\ActiveContextService;
use App\Services\AuditLogService;
use App\Services\WorkshopConsumptionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkshopExpenseController extends Controller
{
    private function context(Request $request): array
    {
        $user = $request->user(); $location = app(ActiveContextService::class)->activeLocation($user);
        abort_unless($location, 422, 'Selecione uma unidade ativa.');
        return [$user, $location];
    }
    public function store(Request $request)
    {
        [$user, $location] = $this->context($request);
        $data = $request->validate(['expense_date'=>['required','date'],'category'=>['required',Rule::in(WorkshopExpense::CATEGORIES)],'description'=>['required','string','max:255'],'supplier_name'=>['nullable','string','max:255'],'invoice_number'=>['nullable','string','max:255'],'amount'=>['required','numeric','gt:0'],'notes'=>['nullable','string','max:2000']]);
        $expense = WorkshopExpense::create($data + ['tenant_id'=>$user->tenant_id,'division_id'=>$location->division_id,'location_id'=>$location->id,'created_by'=>$user->id]);
        app(AuditLogService::class)->created($expense, ['tenant_id'=>$user->tenant_id,'location_id'=>$location->id,'module'=>'workshop','summary'=>'Despesa da oficina registrada.','after_data'=>$expense->toArray()]);
        return back()->with('success', 'Despesa da oficina registrada.');
    }
    public function update(Request $request, WorkshopExpense $expense)
    {
        [$user, $location] = $this->context($request); abort_unless($expense->tenant_id === $user->tenant_id && $expense->location_id === $location->id, 404);
        $data = $request->validate(['expense_date'=>['required','date'],'category'=>['required',Rule::in(WorkshopExpense::CATEGORIES)],'description'=>['required','string','max:255'],'supplier_name'=>['nullable','string','max:255'],'invoice_number'=>['nullable','string','max:255'],'amount'=>['required','numeric','gt:0'],'notes'=>['nullable','string','max:2000']]);
        $expense->update($data); app(AuditLogService::class)->updated($expense, ['tenant_id'=>$user->tenant_id,'location_id'=>$location->id,'module'=>'workshop','summary'=>'Despesa da oficina atualizada.']);
        return back()->with('success', 'Despesa atualizada.');
    }
    public function destroy(Request $request, WorkshopExpense $expense)
    {
        [$user, $location] = $this->context($request); abort_unless($expense->tenant_id === $user->tenant_id && $expense->location_id === $location->id, 404);
        app(AuditLogService::class)->deleted($expense, ['tenant_id'=>$user->tenant_id,'location_id'=>$location->id,'module'=>'workshop','summary'=>'Despesa da oficina excluída.']); $expense->delete();
        return back()->with('success', 'Despesa excluída.');
    }
    public function consume(Request $request, WorkshopConsumptionService $service)
    {
        [$user, $location] = $this->context($request); $data=$request->validate(['stock_item_id'=>['required','integer'],'quantity'=>['required','numeric','min:0.01'],'moved_at'=>['required','date'],'notes'=>['nullable','string','max:1000']]);
        $item=StockItem::whereKey($data['stock_item_id'])->where('tenant_id',$user->tenant_id)->where('location_id',$location->id)->where('is_workshop_consumable', true)->firstOrFail();
        $service->record($item, (float)$data['quantity'], $data['moved_at'], $data['notes']??null, $user);
        return back()->with('success', 'Consumo interno registrado.');
    }
}
