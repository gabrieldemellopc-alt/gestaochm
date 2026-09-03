<?php
namespace App\Http\Controllers;
use App\Models\{FiscalDocument,FiscalDocumentItem,StockCategory,StockItem,SupplierAlias};
use App\Services\{ActiveContextService,AuditLogService,StockEntryService};
use App\Services\FiscalDocuments\{DanfePdfParser,FiscalDocumentItemMatcher,NfeXmlParser,FiscalSupplierRecognitionService};
use App\Services\Permissions\ProfilePermissionService;
use Illuminate\Http\Request; use Illuminate\Support\Facades\{DB,Storage,Validator}; use Illuminate\Validation\ValidationException; use Illuminate\Support\Str;
class FiscalDocumentImportController extends Controller
{
    private function context(Request $r): array { $u=$r->user(); $l=app(ActiveContextService::class)->activeLocation($u); abort_unless($l,422,'Selecione uma unidade para continuar.'); return [$u,$l]; }
    private function authorizeImport(Request $r): void { foreach(['fiscal_documents.import','stock.entry'] as $p) abort_unless(app(ProfilePermissionService::class)->allows($r->user(),$p,['module'=>'fleet','division_id'=>session('active_division_id'),'location_id'=>session('active_location_id')]),403); }
    public function parse(Request $r, DanfePdfParser $pdfParser, NfeXmlParser $xmlParser, FiscalDocumentItemMatcher $matcher, FiscalSupplierRecognitionService $suppliers)
    {
        $this->authorizeImport($r); [$u,$location]=$this->context($r);
        $r->validate(['file'=>['required','file','mimes:xml,pdf','mimetypes:application/pdf,application/xml,text/xml,text/plain','max:10240']]);
        $upload=$r->file('file'); $extension=strtolower($upload->getClientOriginalExtension()); $source=$extension==='xml'?'xml':'pdf';
        $token=(string)Str::uuid(); $path=$upload->storeAs("fiscal-imports/{$u->id}","{$token}.{$extension}",'local');
        try {
            $data=$source==='xml' ? $xmlParser->parse(Storage::disk('local')->path($path)) : $pdfParser->parse(Storage::disk('local')->path($path));
        } catch(\Throwable $e) {
            if($source==='xml'){ Storage::disk('local')->delete($path); throw ValidationException::withMessages(['file'=>$e->getMessage()]); }
            $data=['import_source'=>'pdf','text_extracted'=>false,'warning'=>'Não foi possível ler automaticamente os dados deste PDF. Para importação automática completa, envie o XML da NF-e ou preencha os dados manualmente.','warnings'=>[],'items'=>[]];
        }
        $items=StockItem::with('category')->where('tenant_id',$u->tenant_id)->where('location_id',$location->id)->where('active',true)->orderBy('name')->get(['id','name','unit','stock_category_id']);
        $categories=StockCategory::where('tenant_id',$u->tenant_id)->orderBy('name')->get(['id','name']);
        $data['items']=$matcher->suggestForParsedItems($data['items'],$items,$categories);
        $data['supplier_recognition']=$suppliers->recognize($u->tenant_id, $data['supplier_name'] ?? null, $data['supplier_cnpj'] ?? null, $source);
        session()->put("fiscal_imports.{$token}",['path'=>$path,'extension'=>$extension,'import_source'=>$source,'tenant_id'=>$u->tenant_id,'location_id'=>$location->id,'created_at'=>now()->timestamp]);
        return response()->json(['token'=>$token,'note'=>$data,'stock_items'=>$items,'categories'=>$categories]);
    }
    public function confirm(Request $r, StockEntryService $entries, FiscalSupplierRecognitionService $suppliers)
    {
        $this->authorizeImport($r); [$u,$location]=$this->context($r);
        $validator=Validator::make($r->all(),['token'=>'required|uuid','number'=>'required|string|max:60','series'=>'nullable|string|max:30','access_key'=>'nullable|string|size:44','issued_at'=>'nullable|date','supplier_name'=>'required|string|max:255','supplier_cnpj'=>'nullable|string|max:18','supplier_id'=>'nullable|integer','create_supplier'=>'nullable|boolean','add_supplier_alias'=>'nullable|boolean','products_total'=>'nullable|numeric|min:0','total_amount'=>'nullable|numeric|min:0','items'=>'required|array|min:1','items.*.action'=>'required|in:existing,new,ignore','items.*.stock_item_id'=>'nullable|integer','items.*.stock_category_id'=>'nullable|integer','items.*.description'=>'required|string|max:255','items.*.unit'=>'required|string|max:20','items.*.quantity'=>'required_unless:items.*.action,ignore|numeric|min:0.0001','items.*.unit_value'=>'required_unless:items.*.action,ignore|numeric|min:0','items.*.total_value'=>'required_unless:items.*.action,ignore|numeric|min:0','items.*.product_code'=>'nullable|string|max:100','items.*.ncm'=>'nullable|string|max:20','items.*.cfop'=>'nullable|string|max:10','items.*.discount_value'=>'nullable|numeric|min:0','items.*.brand'=>'nullable|string|max:255','items.*.minimum_quantity'=>'nullable|numeric|min:0']);
        $validator->after(function ($validator) use ($u, $location, $r) { foreach ($r->input('items',[]) as $index=>$line) { if (($line['action']??null)==='ignore') continue; if (($line['action']??null)==='new') { $categoryId=$line['stock_category_id']??null; if (!$categoryId) $validator->errors()->add("items.$index.stock_category_id",'Selecione uma categoria para este item.'); elseif (!StockCategory::where('tenant_id',$u->tenant_id)->whereKey($categoryId)->exists()) $validator->errors()->add("items.$index.stock_category_id",'Categoria inválida ou não encontrada. Selecione novamente a categoria.'); } elseif (!StockItem::where('tenant_id',$u->tenant_id)->where('location_id',$location->id)->whereKey($line['stock_item_id']??null)->exists()) $validator->errors()->add("items.$index.stock_item_id",'Item de estoque inválido ou não encontrado. Selecione novamente o item.'); } });
        $v=$validator->validate();
        $draft=session()->get("fiscal_imports.{$v['token']}"); if(!$draft || $draft['tenant_id']!=$u->tenant_id || $draft['location_id']!=$location->id) throw ValidationException::withMessages(['token'=>'A validação expirou. Envie o arquivo novamente.']);
        $key=preg_replace('/\D/','',$v['access_key']??''); $dup=FiscalDocument::where('tenant_id',$u->tenant_id)->when($key,fn($q)=>$q->where('access_key',$key),fn($q)=>$q->where('supplier_cnpj',preg_replace('/\D/','',$v['supplier_cnpj']??''))->where('number',$v['number'])->where('series',$v['series']??null))->exists();
        if($dup) throw ValidationException::withMessages(['number'=>'Esta nota fiscal já foi importada.']);
        $document=DB::transaction(function() use($v,$draft,$u,$location,$key,$entries,$suppliers){
            $final="fiscal-documents/{$u->tenant_id}/".now()->format('Y').'/'.Str::uuid().'.'.$draft['extension']; Storage::disk('local')->move($draft['path'],$final);
            $supplier=($v['create_supplier']??false) ? $suppliers->createFromFiscal($u->tenant_id,$v['supplier_name'],$v['supplier_cnpj']??null) : $suppliers->resolve($u->tenant_id,$v['supplier_id']??null);
            if ($supplier && ! $supplier->active) throw ValidationException::withMessages(['supplier_id'=>'Fornecedor inativo não pode ser vinculado a uma nova nota fiscal.']);
            if ($supplier && ($v['add_supplier_alias']??false) && app(\App\Services\SupplierNormalizer::class)->normalizeName($v['supplier_name']) !== $supplier->normalized_name) SupplierAlias::firstOrCreate(['supplier_id'=>$supplier->id,'normalized_alias'=>app(\App\Services\SupplierNormalizer::class)->normalizeName($v['supplier_name'])],['alias'=>$v['supplier_name']]);
            $doc=FiscalDocument::create(['tenant_id'=>$u->tenant_id,'division_id'=>session('active_division_id'),'location_id'=>$location->id,'created_by'=>$u->id,'number'=>$v['number'],'series'=>$v['series']??null,'access_key'=>$key?:null,'issued_at'=>$v['issued_at']??null,'supplier_name'=>$v['supplier_name'],'supplier_cnpj'=>preg_replace('/\D/','',$v['supplier_cnpj']??''),'supplier_id'=>$supplier?->id,'products_total'=>$v['products_total']??null,'total_amount'=>$v['total_amount']??null,'pdf_path'=>$final]);
            foreach($v['items'] as $line){ if($line['action']==='ignore') continue; $created=false;
                if($line['action']==='new'){ abort_unless(app(ProfilePermissionService::class)->allows($u,'stock.manage_items',['module'=>'fleet','location_id'=>$location->id]),403); $category=StockCategory::where('tenant_id',$u->tenant_id)->findOrFail($line['stock_category_id']); $item=StockItem::create(['tenant_id'=>$u->tenant_id,'location_id'=>$location->id,'stock_category_id'=>$category->id,'name'=>$line['description'],'brand'=>$line['brand']??null,'unit'=>$line['unit'],'quantity'=>0,'minimum_quantity'=>$line['minimum_quantity']??0,'unit_cost'=>0,'active'=>true]); $created=true; }
                else $item=StockItem::where('tenant_id',$u->tenant_id)->where('location_id',$location->id)->findOrFail($line['stock_item_id']);
                $movement=$entries->record($item,['quantity'=>$line['quantity'],'total_cost'=>$line['total_value'],'invoice_number'=>trim($v['number'].' / '.$v['series'],' /'),'supplier_name'=>$v['supplier_name'],'supplier_id'=>$supplier?->id,'description'=>'Importação validada de NF-e','moved_at'=>$v['issued_at']??now(),'fiscal_document_id'=>$doc->id],['summary'=>'Movimentação criada por importação validada de NF-e.']);
                FiscalDocumentItem::create(['fiscal_document_id'=>$doc->id,'stock_item_id'=>$item->id,'stock_category_id'=>$item->stock_category_id,'stock_movement_id'=>$movement->id,'product_code'=>$line['product_code']??null,'description'=>$line['description'],'ncm'=>$line['ncm']??null,'cfop'=>$line['cfop']??null,'unit'=>$line['unit'],'quantity'=>$line['quantity'],'unit_value'=>$line['unit_value'],'discount_value'=>$line['discount_value']??0,'total_value'=>$line['total_value'],'created_stock_item'=>$created]);
            }
            app(AuditLogService::class)->created($doc,['tenant_id'=>$u->tenant_id,'location_id'=>$location->id,'module'=>'stock','summary'=>'Nota fiscal importada após validação humana.','after_data'=>$doc->toArray(),'metadata'=>['items'=>count($v['items'])]]); return $doc;
        }); session()->forget("fiscal_imports.{$v['token']}"); return response()->json(['message'=>'Nota fiscal importada com sucesso.','id'=>$document->id]);
    }
}
