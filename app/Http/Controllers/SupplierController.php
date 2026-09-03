<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Services\Permissions\ProfilePermissionService;
use App\Services\SupplierNormalizer;
use App\Services\SupplierSearchService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SupplierController extends Controller
{
    private function authorizeSupplierManagement(Request $request): void
    {
        abort_unless(app(ProfilePermissionService::class)->allows($request->user(), 'admin.access.manage', ['module'=>'fleet', 'division_id'=>session('active_division_id'), 'location_id'=>session('active_location_id')]), 403);
    }

    public function index(Request $request)
    {
        $this->authorizeSupplierManagement($request);
        $normalizer = app(SupplierNormalizer::class);
        $term = $normalizer->normalizeName((string) $request->q);
        $document = $normalizer->normalizeDocument((string) $request->q);
        $items = Supplier::forTenant($request->user()->tenant_id)->with('aliases')
            ->when($request->filled('q'), function ($query) use ($term, $document) {
                $query->where(function ($supplier) use ($term, $document) {
                    $supplier->where('normalized_name', 'like', "%{$term}%")
                        ->orWhereHas('aliases', fn ($aliases) => $aliases->where('normalized_alias', 'like', "%{$term}%"));
                    if ($document !== '') $supplier->orWhere('document', 'like', "%{$document}%");
                });
            })->orderBy('trade_name')->paginate(20)->withQueryString();
        return view('suppliers.index', compact('items'));
    }

    public function search(Request $request, SupplierSearchService $search)
    {
        $this->authorizeSupplierManagement($request);
        return response()->json($search->search($request->user()->tenant_id, (string) $request->q));
    }

    public function store(Request $request, SupplierNormalizer $normalizer)
    {
        $this->authorizeSupplierManagement($request);
        $data = $this->data($request, $normalizer); $data['tenant_id'] = $request->user()->tenant_id;
        $supplier = Supplier::create($data); $this->aliases($supplier, $request, $normalizer);
        return redirect()->route('suppliers.index')->with('success', 'Fornecedor cadastrado.');
    }

    public function update(Request $request, Supplier $supplier, SupplierNormalizer $normalizer)
    {
        $this->authorizeSupplierManagement($request); abort_unless($supplier->tenant_id === $request->user()->tenant_id, 404);
        $supplier->update($this->data($request, $normalizer, $supplier)); $this->aliases($supplier, $request, $normalizer);
        return back()->with('success', 'Fornecedor atualizado.');
    }

    private function data(Request $request, SupplierNormalizer $normalizer, ?Supplier $supplier = null): array
    {
        $data = $request->validate(['trade_name'=>['nullable','string','max:255','required_without:legal_name'], 'legal_name'=>['nullable','string','max:255'], 'document'=>['nullable','string','max:20'], 'active'=>['nullable','boolean'], 'aliases'=>['nullable','array'], 'aliases.*'=>['string','max:255']]);
        $document = $normalizer->normalizeDocument($data['document'] ?? null);
        if ($document && ! $normalizer->validDocument($document)) {
            throw ValidationException::withMessages(['document' => 'Informe um CPF ou CNPJ válido.']);
        }
        if ($document && Supplier::forTenant($request->user()->tenant_id)->where('document', $document)->when($supplier, fn ($query) => $query->whereKeyNot($supplier->id))->exists()) {
            throw ValidationException::withMessages(['document' => 'Este CPF/CNPJ já está cadastrado neste tenant.']);
        }
        $data['document'] = $document; $data['document_type'] = $normalizer->detectDocumentType($document); $data['normalized_name'] = $normalizer->normalizeName($data['trade_name'] ?: $data['legal_name']); $data['active'] = $request->boolean('active', true); unset($data['aliases']);
        return $data;
    }

    private function aliases(Supplier $supplier, Request $request, SupplierNormalizer $normalizer): void
    {
        foreach ($request->input('aliases', []) as $alias) { $alias = trim($alias); if ($alias !== '') $supplier->aliases()->firstOrCreate(['normalized_alias'=>$normalizer->normalizeName($alias)], ['alias'=>$alias]); }
    }
}
