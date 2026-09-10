<?php

namespace App\Http\Controllers;

use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\WorkshopExpense;
use App\Services\ActiveContextService;
use App\Services\AuditLogService;
use App\Services\WorkshopConsumptionService;
use App\Services\SupplierSnapshotService;
use App\Services\Permissions\ProfilePermissionService;
use Illuminate\Support\Facades\DB;
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
        $this->authorizeWorkshop('workshop.expenses.create', $user, $location);
        $data = $request->validate(['expense_date'=>['required','date'],'category'=>['required',Rule::in(WorkshopExpense::CATEGORIES)],'description'=>['required','string','max:255'],'supplier_name'=>['nullable','string','max:255'],'supplier_id'=>['nullable','integer'],'supplier_document'=>['nullable','string','max:20'],'invoice_number'=>['nullable','string','max:255'],'amount'=>['required','numeric','gt:0'],'notes'=>['nullable','string','max:2000']]);
        $expense = DB::transaction(function () use ($data, $user, $location) { $supplier=app(\App\Services\SupplierResolverService::class)->resolve($user->tenant_id,$data['supplier_id']??null,$data['supplier_name']??null,$data['supplier_document']??null); return WorkshopExpense::create(array_merge($data,app(SupplierSnapshotService::class)->fromResolvedSupplier($supplier,$data['supplier_name']??null),['tenant_id'=>$user->tenant_id,'division_id'=>$location->division_id,'location_id'=>$location->id,'created_by'=>$user->id])); });
        app(AuditLogService::class)->created($expense, ['tenant_id'=>$user->tenant_id,'location_id'=>$location->id,'module'=>'workshop','summary'=>'Despesa da oficina registrada.','after_data'=>$expense->toArray()]);
        if ($request->expectsJson()) return response()->json(['message'=>'Despesa da oficina registrada.','expense'=>['id'=>$expense->id,'category'=>$expense->categoryLabel(),'date'=>$expense->expense_date->format('d/m/Y'),'description'=>$expense->description,'amount'=>(float) $expense->amount]]);
        return back()->with('success', 'Despesa da oficina registrada.');
    }
    public function update(Request $request, WorkshopExpense $expense)
    {
        [$user, $location] = $this->context($request); $this->authorizeWorkshop('workshop.expenses.update', $user, $location); abort_unless($expense->tenant_id === $user->tenant_id && $expense->division_id === $location->division_id && $expense->location_id === $location->id, 404);
        $data = $request->validate(['expense_date'=>['required','date'],'category'=>['required',Rule::in(WorkshopExpense::CATEGORIES)],'description'=>['required','string','max:255'],'supplier_name'=>['nullable','string','max:255'],'supplier_id'=>['nullable','integer'],'supplier_document'=>['nullable','string','max:20'],'invoice_number'=>['nullable','string','max:255'],'amount'=>['required','numeric','gt:0'],'notes'=>['nullable','string','max:2000']]);
        DB::transaction(function () use ($data, $user, $location, $expense) { $before=$expense->toArray(); $supplier=app(\App\Services\SupplierResolverService::class)->resolve($user->tenant_id,$data['supplier_id']??null,$data['supplier_name']??null,$data['supplier_document']??null); $expense->update(array_merge($data,app(SupplierSnapshotService::class)->fromResolvedSupplier($supplier,$data['supplier_name']??null))); app(AuditLogService::class)->updated($expense, ['tenant_id'=>$user->tenant_id,'location_id'=>$location->id,'module'=>'workshop','summary'=>'Despesa atualizada.','before_data'=>$before,'after_data'=>$expense->fresh()->toArray()]); });
        return back()->with('success', 'Despesa atualizada.');
    }
    public function destroy(Request $request, WorkshopExpense $expense)
    {
        [$user, $location] = $this->context($request); $this->authorizeWorkshop('workshop.expenses.delete', $user, $location); abort_unless($expense->tenant_id === $user->tenant_id && $expense->division_id === $location->division_id && $expense->location_id === $location->id, 404);
        DB::transaction(function () use ($expense, $user, $location) { $before=$expense->toArray(); $expense->delete(); app(AuditLogService::class)->deleted($expense, ['tenant_id'=>$user->tenant_id,'location_id'=>$location->id,'module'=>'workshop','summary'=>'Despesa da oficina excluída.','before_data'=>$before]); });
        return back()->with('success', 'Despesa excluída.');
    }
    public function consume(Request $request, WorkshopConsumptionService $service)
    {
        [$user, $location] = $this->context($request); $this->authorizeWorkshop('workshop.consumptions.create', $user, $location); $data=$request->validate(['stock_item_id'=>['required','integer'],'quantity'=>['required','numeric','min:0.01'],'moved_at'=>['required','date'],'notes'=>['nullable','string','max:1000']]);
        $item=StockItem::whereKey($data['stock_item_id'])->where('tenant_id',$user->tenant_id)->where('location_id',$location->id)->where('is_workshop_consumable', true)->firstOrFail();
        $service->record($item, (float)$data['quantity'], $data['moved_at'], $data['notes']??null, $user);
        return back()->with('success', 'Consumo interno registrado.');
    }

    public function updateConsumption(Request $request, StockMovement $movement, WorkshopConsumptionService $service)
    {
        [$user, $location] = $this->context($request); $this->authorizeWorkshop('workshop.consumptions.update', $user, $location); $data=$request->validate(['stock_item_id'=>['required','integer'],'quantity'=>['required','numeric','min:0.01'],'moved_at'=>['required','date'],'notes'=>['nullable','string','max:1000']]);
        $item=StockItem::whereKey($data['stock_item_id'])->where('tenant_id',$user->tenant_id)->where('location_id',$location->id)->where('is_workshop_consumable', true)->firstOrFail();
        $new = $service->replace($movement, $item, (float) $data['quantity'], $data['moved_at'], $data['notes'] ?? null, $user);
        app(AuditLogService::class)->record(['tenant_id'=>$user->tenant_id,'location_id'=>$location->id,'module'=>'workshop','auditable'=>$new,'action'=>'updated','summary'=>'Consumo interno da oficina corrigido.','metadata'=>['replaced_stock_movement_id'=>$movement->id,'replacement_stock_movement_id'=>$new->id]]);
        return back()->with('success', 'Consumo interno corrigido com reversão do lançamento anterior.');
    }

    public function destroyConsumption(Request $request, StockMovement $movement, WorkshopConsumptionService $service)
    {
        [$user, $location] = $this->context($request); $this->authorizeWorkshop('workshop.consumptions.delete', $user, $location); $service->reverse($movement, $user);
        return back()->with('success', 'Consumo interno revertido e devolvido ao estoque.');
    }

    private function authorizeWorkshop(string $permission, $user, $location): void
    {
        abort_unless(app(ProfilePermissionService::class)->allows($user, $permission, ['tenant_id'=>$user->tenant_id,'division_id'=>$location->division_id,'location_id'=>$location->id,'module'=>'fleet']), 403);
    }
}
