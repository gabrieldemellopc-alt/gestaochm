<?php

namespace App\Http\Controllers;

use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Services\ActiveContextService;
use App\Services\AuditLogService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    public function index()
    {
        $activeLocation = $this->activeLocation();

        if (! $activeLocation) {
            return $this->missingActiveLocationRedirect();
        }

        $tenantId = auth()->user()->tenant_id;

        $categories = StockCategory::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'items' => function ($query) use ($tenantId, $activeLocation) {
                    $query
                        ->where('tenant_id', $tenantId)
                        ->where('location_id', $activeLocation->id);
                },
            ])
            ->orderBy('name')
            ->get();

        foreach ($categories as $category) {
            foreach ($category->items as $item) {
                $item->stock_status = StockService::getStatus($item);
            }
        }

        return view('stock.index', compact('categories'));
    }

    public function showItem(StockItem $item)
    {
        if ($redirect = $this->ensureItemInActiveContext($item)) {
            return $redirect;
        }

        $tenantId = auth()->user()->tenant_id;
        $locationId = $item->location_id;

        $item->load([
            'category',
            'movements' => function ($query) use ($tenantId, $locationId) {
                $query
                    ->where('tenant_id', $tenantId)
                    ->where('location_id', $locationId)
                    ->latest()
                    ->limit(3);
            },
        ]);

        return response()->json($item);
    }

    public function storeCategory(Request $request)
    {
        if (! $this->activeLocation()) {
            return $this->missingActiveLocationRedirect();
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        StockCategory::create([
            'tenant_id' => auth()->user()->tenant_id,
            'name' => $validated['name'],
        ]);

        return redirect()->back();
    }

    public function storeItem(Request $request)
    {
        $activeLocation = $this->activeLocation();

        if (! $activeLocation) {
            return $this->missingActiveLocationRedirect();
        }

        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'stock_category_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($tenantId) {
                    if (! StockCategory::where('tenant_id', $tenantId)->whereKey($value)->exists()) {
                        $fail('A categoria selecionada não pertence ao tenant atual.');
                    }
                },
            ],
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:50'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'minimum_quantity' => ['nullable', 'numeric', 'min:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'observation' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($validated, $tenantId, $activeLocation) {
            $item = StockItem::create([
                'tenant_id' => $tenantId,
                'location_id' => $activeLocation->id,
                'stock_category_id' => $validated['stock_category_id'],
                'name' => $validated['name'],
                'brand' => $validated['brand'] ?? null,
                'unit' => $validated['unit'],
                'quantity' => $validated['quantity'] ?? 0,
                'minimum_quantity' => $validated['minimum_quantity'] ?? 0,
                'unit_cost' => $validated['unit_cost'] ?? 0,
                'observation' => $validated['observation'] ?? null,
                'active' => true,
            ]);

            if ($item->quantity > 0) {
                StockMovement::create([
                    'tenant_id' => $tenantId,
                    'location_id' => $activeLocation->id,
                    'stock_item_id' => $item->id,
                    'movement_type' => 'in',
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_cost,
                    'description' => 'Estoque inicial',
                ]);
            }
        });

        return redirect()->back();
    }

    public function updateItem(Request $request, StockItem $item)
    {
        if ($redirect = $this->ensureItemInActiveContext($item)) {
            return $redirect;
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:50'],
            'minimum_quantity' => ['required', 'numeric', 'min:0'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'observation' => ['nullable', 'string'],
        ]);

        $item->update($validated);

        return redirect()->back();
    }

    public function storeMovement(Request $request)
    {
        $activeLocation = $this->activeLocation();

        if (! $activeLocation) {
            return $this->missingActiveLocationRedirect();
        }

        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'stock_item_id' => ['required', 'integer'],
            'movement_type' => ['required', 'in:in,out'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);

        $requestedItem = StockItem::findOrFail($validated['stock_item_id']);

        if ($redirect = $this->ensureItemInActiveContext($requestedItem)) {
            return $redirect;
        }

        $stored = DB::transaction(function () use ($validated, $tenantId, $activeLocation) {
            $item = StockItem::query()
                ->where('tenant_id', $tenantId)
                ->where('location_id', $activeLocation->id)
                ->lockForUpdate()
                ->findOrFail($validated['stock_item_id']);

            if (
                $validated['movement_type'] === 'out'
                && $validated['quantity'] > $item->quantity
            ) {
                return false;
            }

            $movement = StockMovement::create([
                'tenant_id' => $tenantId,
                'location_id' => $activeLocation->id,
                'stock_item_id' => $item->id,
                'movement_type' => $validated['movement_type'],
                'quantity' => $validated['quantity'],
                'unit_cost' => $validated['unit_cost'] ?? 0,
                'description' => $validated['description'] ?? null,
            ]);

            app(AuditLogService::class)->created($movement, [
                'tenant_id' => $tenantId,
                'location_id' => $activeLocation->id,
                'module' => 'stock',
                'summary' => 'Movimentacao manual de estoque registrada.',
                'after_data' => $movement->toArray(),
                'metadata' => [
                    'stock_item_id' => $item->id,
                    'movement_type' => $validated['movement_type'],
                    'quantity_before' => $item->quantity,
                    'quantity_after' => $validated['movement_type'] === 'in'
                        ? (float) $item->quantity + (float) $validated['quantity']
                        : (float) $item->quantity - (float) $validated['quantity'],
                ],
            ]);

            if ($validated['movement_type'] === 'in') {
                $item->quantity += $validated['quantity'];
            } else {
                $item->quantity -= $validated['quantity'];
            }

            $item->save();

            return true;
        });

        if (! $stored) {
            return back()->with('error', 'Quantidade indisponível em estoque.');
        }

        return redirect()->back();
    }

    private function activeLocation()
    {
        return app(ActiveContextService::class)
            ->activeLocation(auth()->user());
    }

    private function ensureItemInActiveContext(StockItem $item)
    {
        $activeLocation = $this->activeLocation();

        if (! $activeLocation) {
            return $this->missingActiveLocationRedirect();
        }

        if (
            (int) $item->tenant_id !== (int) auth()->user()->tenant_id
            || (int) $item->location_id !== (int) $activeLocation->id
        ) {
            abort(403);
        }

        return null;
    }

    private function missingActiveLocationRedirect()
    {
        return redirect()
            ->route('portal')
            ->with('warning', 'Selecione uma unidade para continuar.');
    }
}
