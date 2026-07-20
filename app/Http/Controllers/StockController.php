<?php

namespace App\Http\Controllers;

use Illuminate\Validation\Rule;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Services\ActiveContextService;
use App\Services\AuditLogService;
use App\Services\Permissions\ProfilePermissionService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class StockController extends Controller
{
    public function index()
    {
        $activeLocation = $this->activeLocation();

        if (! $activeLocation) {
            return $this->missingActiveLocationRedirect();
        }

        $this->authorizeStockPermission('stock.view');
        $stockPermissions = $this->stockPermissions();

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

        return view('stock.index', compact('categories', 'stockPermissions'));
    }

    public function showItem(StockItem $item)
    {
        if ($redirect = $this->ensureItemInActiveContext($item)) {
            return $redirect;
        }

        $this->authorizeStockPermission('stock.view');

        $tenantId = auth()->user()->tenant_id;
        $locationId = $item->location_id;

        $item->load([
            'category',
            'movements' => function ($query) use ($tenantId, $locationId) {
                    $query
                        ->where('tenant_id', $tenantId)
                        ->where('location_id', $locationId)
                        ->with(['reversalMovement', 'reversedFromMovement'])
                        ->latest()
                        ->limit(10);
            },
        ]);

        if (Gate::denies('viewAuditLogs')) {
            $item->movements->each->makeHidden([
                'cancel_reason',
                'cancelled_by',
            ]);
        }

        if (! $this->canStock('stock.view_costs')) {
            $this->stripStockCostsForResponse($item);
        }

        return response()->json($item);
    }

    public function storeCategory(Request $request)
    {
        if (! $this->activeLocation()) {
            return $this->missingActiveLocationRedirect();
        }

        $this->authorizeStockPermission('stock.manage_categories');

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

        $this->authorizeStockPermission('stock.manage_items');

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
            'minimum_quantity' => ['nullable', 'numeric', 'min:0'],
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
                'quantity' => 0,
                'minimum_quantity' => $validated['minimum_quantity'] ?? 0,
                'unit_cost' => 0,
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

        $this->authorizeStockPermission('stock.manage_items');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:50'],
            'minimum_quantity' => ['required', 'numeric', 'min:0'],
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
            'moved_at' => ['required', 'date'],
            // ENTRADA
            'unit_cost' => [
                Rule::requiredIf($request->input('movement_type') === 'in'),
                'nullable',
                'numeric',
                'min:0',
            ],
            'total_cost' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'invoice_number' => [
                'nullable',
                'string',
                'max:255',
            ],
            'supplier_name' => [
                'nullable',
                'string',
                'max:255',
            ],
    
            // SAÍDA
            'description' => [
                Rule::requiredIf($request->input('movement_type') === 'out'),
                'nullable',
                'string',
                'min:10',
                'max:1000',
            ],
        ]);

        $this->authorizeStockMovementPermission($validated['movement_type']);
    
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
    
            $quantity = round((float) $validated['quantity'], 2);
            $quantityBefore = round((float) $item->quantity, 2);
            $unitCostBefore = round((float) $item->unit_cost, 2);
    
            if (
                $validated['movement_type'] === 'out'
                && $quantity > $quantityBefore
            ) {
                return false;
            }
    
            if ($validated['movement_type'] === 'in') {
                $movementTotalCost = isset($validated['total_cost']) && $validated['total_cost'] !== null
                    ? round((float) $validated['total_cost'], 2)
                    : round($quantity * (float) ($validated['unit_cost'] ?? 0), 2);
    
                $movementUnitCost = $quantity > 0
                    ? round($movementTotalCost / $quantity, 2)
                    : 0;
    
                $quantityAfter = round($quantityBefore + $quantity, 2);
    
                $stockValueBefore = round($quantityBefore * $unitCostBefore, 2);
                $stockValueAfter = round($stockValueBefore + $movementTotalCost, 2);
    
                $newAverageUnitCost = $quantityAfter > 0
                    ? round($stockValueAfter / $quantityAfter, 2)
                    : 0;
            } else {
                $movementUnitCost = $unitCostBefore;
                $movementTotalCost = round($quantity * $movementUnitCost, 2);
                $quantityAfter = round($quantityBefore - $quantity, 2);
                $newAverageUnitCost = $unitCostBefore;
            }
    
            $movement = StockMovement::create([
                'tenant_id' => $tenantId,
                'location_id' => $activeLocation->id,
                'stock_item_id' => $item->id,
                'movement_type' => $validated['movement_type'],
                'quantity' => $quantity,
                'unit_cost' => $movementUnitCost,
                'total_cost' => $movementTotalCost,
                'invoice_number' => $validated['invoice_number'] ?? null,
                'supplier_name' => $validated['supplier_name'] ?? null,
                'description' => $validated['description'] ?? null,
                'moved_at' => $validated['moved_at'],
            ]);
    
            $item->quantity = $quantityAfter;
            $item->unit_cost = $newAverageUnitCost;
            $item->save();
    
            app(AuditLogService::class)->created($movement, [
                'tenant_id' => $tenantId,
                'location_id' => $activeLocation->id,
                'module' => 'stock',
                'summary' => 'Movimentacao manual de estoque registrada.',
                'after_data' => $movement->toArray(),
                'metadata' => [
                    'stock_item_id' => $item->id,
                    'movement_type' => $validated['movement_type'],
                    'quantity_before' => $quantityBefore,
                    'quantity_after' => $quantityAfter,
                    'unit_cost_before' => $unitCostBefore,
                    'unit_cost_after' => $newAverageUnitCost,
                    'movement_unit_cost' => $movementUnitCost,
                    'movement_total_cost' => $movementTotalCost,
                ],
            ]);
    
            return true;
        });
    
        if (! $stored) {
            return back()->with('error', 'Quantidade indisponível em estoque.');
        }
    
        return redirect()->back();
    }

    public function cancelMovement(Request $request, StockMovement $movement)
    {
        if (Gate::denies('cancelStockMovements') || ! $this->canStock('stock.cancel_movement')) {
            abort(403, 'Você não tem permissão para executar esta ação.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        try {
            $this->cancelManualMovement($movement, $validated['reason']);
        } catch (ValidationException $exception) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $exception->getMessage(),
                    'errors' => $exception->errors(),
                ], 422);
            }

            return back()->withErrors($exception->errors());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Movimentacao cancelada com sucesso.',
            ]);
        }

        return back()->with('success', 'Movimentacao cancelada com sucesso.');
    }

    private function authorizeStockPermission(string $permissionKey): void
    {
        if ($this->canStock($permissionKey)) {
            return;
        }

        abort(403, 'Você não tem permissão para executar esta ação.');
    }

    private function authorizeStockMovementPermission(string $movementType): void
    {
        $permissionKey = $movementType === 'in'
            ? 'stock.entry'
            : 'stock.manual_output';

        $this->authorizeStockPermission($permissionKey);
    }

    private function canStock(string $permissionKey): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return app(ProfilePermissionService::class)->allows($user, $permissionKey, [
            'tenant_id' => $user->tenant_id,
            'division_id' => session('active_division_id'),
            'location_id' => session('active_location_id'),
            'module' => 'fleet',
        ]);
    }

    private function stockPermissions(): array
    {
        return [
            'view' => $this->canStock('stock.view'),
            'manage_categories' => $this->canStock('stock.manage_categories'),
            'manage_items' => $this->canStock('stock.manage_items'),
            'create_entry' => $this->canStock('stock.entry'),
            'create_manual_output' => $this->canStock('stock.manual_output'),
            'cancel_movement' => Gate::allows('cancelStockMovements') && $this->canStock('stock.cancel_movement'),
            'view_costs' => $this->canStock('stock.view_costs'),
        ];
    }

    private function stripStockCostsForResponse(StockItem $item): void
    {
        $item->setAttribute('unit_cost', null);

        if ($item->relationLoaded('movements')) {
            $item->movements->each(function (StockMovement $movement) {
                $movement->setAttribute('unit_cost', null);
                $movement->setAttribute('total_cost', null);
            });
        }
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

    private function cancelManualMovement(
        StockMovement $movement,
        string $reason
    ): StockMovement {
        $activeLocation = $this->activeLocation();

        if (! $activeLocation) {
            throw ValidationException::withMessages([
                'movement' => 'Selecione uma unidade para continuar.',
            ]);
        }

        return DB::transaction(function () use ($movement, $reason, $activeLocation) {
            $movement = StockMovement::query()
                ->where('tenant_id', auth()->user()->tenant_id)
                ->where('location_id', $activeLocation->id)
                ->whereKey($movement->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($movement->cancelled_at || $movement->reversal_movement_id) {
                throw ValidationException::withMessages([
                    'movement' => 'Esta movimentacao ja foi cancelada.',
                ]);
            }

            if ($movement->reversed_from_movement_id) {
                throw ValidationException::withMessages([
                    'movement' => 'Um movimento reverso nao pode ser cancelado diretamente.',
                ]);
            }

            if ($movement->maintenance_record_id) {
                throw ValidationException::withMessages([
                    'movement' => 'Movimentos vinculados a manutencao devem ser revertidos pelo cancelamento da manutencao.',
                ]);
            }

            $item = StockItem::query()
                ->where('tenant_id', $movement->tenant_id)
                ->where('location_id', $movement->location_id)
                ->whereKey($movement->stock_item_id)
                ->lockForUpdate()
                ->first();

            if (! $item) {
                throw ValidationException::withMessages([
                    'movement' => 'Nao foi possivel localizar o item de estoque.',
                ]);
            }

            $beforeMovement = $movement->toArray();
            $beforeQuantity = (float) $item->quantity;
            $reverseType = $movement->movement_type === 'in' ? 'out' : 'in';
            $quantity = (float) $movement->quantity;

            if ($reverseType === 'out' && $quantity > $beforeQuantity) {
                throw ValidationException::withMessages([
                    'movement' => 'Saldo insuficiente para reverter esta entrada.',
                ]);
            }

            $reverseMovement = StockMovement::create([
                'tenant_id' => $movement->tenant_id,
                'location_id' => $movement->location_id,
                'stock_item_id' => $movement->stock_item_id,
                'movement_type' => $reverseType,
                'quantity' => $movement->quantity,
                'unit_cost' => $movement->unit_cost,
                'description' => 'Reversão do cancelamento da movimentação #'.$movement->id,
                'reversed_from_movement_id' => $movement->id,
            ]);

            if ($reverseType === 'in') {
                $item->quantity = $beforeQuantity + $quantity;
            } else {
                $item->quantity = $beforeQuantity - $quantity;
            }

            $item->save();

            $movement->update([
                'cancelled_at' => now(),
                'cancelled_by' => auth()->id(),
                'cancel_reason' => $reason,
                'reversal_movement_id' => $reverseMovement->id,
            ]);

            $movementAfter = $movement->fresh(['reversalMovement']);

            app(AuditLogService::class)->created($reverseMovement, [
                'tenant_id' => $movement->tenant_id,
                'location_id' => $movement->location_id,
                'module' => 'stock',
                'summary' => 'Movimento reverso criado para cancelamento de movimentacao manual.',
                'after_data' => $reverseMovement->toArray(),
                'metadata' => [
                    'stock_item_id' => $movement->stock_item_id,
                    'original_stock_movement_id' => $movement->id,
                    'quantity_before' => $beforeQuantity,
                    'quantity_after' => $item->quantity,
                ],
                'reason' => $reason,
            ]);

            app(AuditLogService::class)->cancelled($movementAfter, [
                'tenant_id' => $movement->tenant_id,
                'location_id' => $movement->location_id,
                'module' => 'stock',
                'summary' => 'Movimentacao manual de estoque cancelada.',
                'before_data' => $beforeMovement,
                'after_data' => $movementAfter->toArray(),
                'metadata' => [
                    'stock_item_id' => $movement->stock_item_id,
                    'reversal_movement_id' => $reverseMovement->id,
                    'quantity_before' => $beforeQuantity,
                    'quantity_after' => $item->quantity,
                ],
                'reason' => $reason,
            ]);

            return $movementAfter;
        });
    }

    private function missingActiveLocationRedirect()
    {
        return redirect()
            ->route('portal')
            ->with('warning', 'Selecione uma unidade para continuar.');
    }
}
