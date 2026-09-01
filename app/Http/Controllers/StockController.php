<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Validation\Rule;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Services\ActiveContextService;
use App\Services\AuditLogService;
use App\Services\Permissions\ProfilePermissionService;
use App\Services\StockService;
use App\Services\StockItemDetailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\TenantFiscalSettingService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockController extends Controller
{
    public function index(Request $request)
    {
        $activeLocation = $this->activeLocation();

        if (! $activeLocation) {
            return $this->missingActiveLocationRedirect();
        }

        $this->authorizeStockPermission('stock.view');
        $stockPermissions = $this->stockPermissions();

        $tenantId = auth()->user()->tenant_id;

        $search = $this->normalizeStockSearch($request->query('search'));
        $tokens = $search === '' ? [] : preg_split('/\\s+/', $search);

        $categoriesQuery = StockCategory::query()
            ->where('tenant_id', $tenantId)
            ->withCount([
                'items as items_count' => function ($query) use ($tenantId, $activeLocation) {
                    $query
                        ->where('stock_items.tenant_id', $tenantId)
                        ->where('stock_items.location_id', $activeLocation->id);
                },
                'items as other_location_items_count' => function ($query) use ($tenantId, $activeLocation) {
                    $query
                        ->where('stock_items.tenant_id', $tenantId)
                        ->where('stock_items.location_id', '!=', $activeLocation->id);
                },
            ])
            ->with([
                'items' => function ($query) use ($tenantId, $activeLocation, $search, $tokens) {
                    $query
                        ->where('stock_items.tenant_id', $tenantId)
                        ->where('stock_items.location_id', $activeLocation->id);

                    if ($search !== '') {
                        $this->applyStockSearchToItems($query, $search, $tokens);
                    }
                },
            ])
            ->orderBy('name');

        if ($search !== '') {
            $categoriesQuery->where(function ($query) use ($tenantId, $activeLocation, $search, $tokens) {
                $categoryName = $this->stockSearchExpression('stock_categories.name');

                $query->whereRaw("{$categoryName} LIKE ?", ['%'.$search.'%'])
                    ->orWhereHas('items', function ($items) use ($tenantId, $activeLocation, $search, $tokens) {
                        $items->where('stock_items.tenant_id', $tenantId)
                            ->where('stock_items.location_id', $activeLocation->id);
                        $this->applyStockSearchToItems($items, $search, $tokens);
                    });
            });

            $rankedCategoryItems = StockItem::query()
                ->whereColumn('stock_items.stock_category_id', 'stock_categories.id')
                ->where('stock_items.tenant_id', $tenantId)
                ->where('stock_items.location_id', $activeLocation->id);
            $this->applyStockSearchToItems($rankedCategoryItems, $search, $tokens);
            $categoryRelevance = DB::query()
                ->fromSub($rankedCategoryItems, 'ranked_stock_items')
                ->selectRaw('MIN(search_relevance)');

            $categoriesQuery->select('stock_categories.*')
                ->selectSub($categoryRelevance, 'search_relevance')
                ->orderByRaw('search_relevance IS NULL')
                ->orderBy('search_relevance')
                ->orderBy('name');
        }

        $categories = $categoriesQuery->get();

        foreach ($categories as $category) {
            foreach ($category->items as $item) {
                $item->stock_status = StockService::getStatus($item);
            }
        }

        $stockEntryInvoiceRequired = app(TenantFiscalSettingService::class)->requires('stock_entry');
        return view('stock.index', compact('categories', 'search', 'stockPermissions', 'stockEntryInvoiceRequired'));
    }

    /** Normalize text without changing the values persisted in the database. */
    private function normalizeStockSearch(?string $value): string
    {
        $value = preg_replace('/\\s+/', ' ', trim((string) $value));

        return Str::of($value)->ascii()->lower()->value();
    }

    /**
     * SQL expression compatible with MySQL and SQLite tests. It makes the
     * comparison accent-insensitive even when a legacy database collation is not.
     */
    private function stockSearchExpression(string $column): string
    {
        $expression = "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$column}, 'Á', 'a'), 'É', 'e'), 'Í', 'i'), 'Ó', 'o'), 'Ú', 'u'))";
        // Portuguese characters cover the data entered in this application.
        // Keeping this compact also avoids oversized expressions on SQLite.
        foreach (['á' => 'a', 'ã' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c'] as $from => $to) {
            $expression = "REPLACE({$expression}, '{$from}', '{$to}')";
        }

        return $expression;
    }

    private function applyStockSearchToItems($query, string $search, array $tokens): void
    {
        $name = $this->stockSearchExpression('stock_items.name');
        $brand = $this->stockSearchExpression('stock_items.brand');
        $category = $this->stockSearchExpression('stock_categories.name');
        $phraseLike = '%'.$search.'%';
        $startsLike = $search.'%';
        $allNameTokens = implode(' AND ', array_fill(0, count($tokens), "{$name} LIKE ?"));
        $allNameTokenBindings = array_map(fn ($token) => '%'.$token.'%', $tokens);

        $query->leftJoin('stock_categories', 'stock_categories.id', '=', 'stock_items.stock_category_id')
            ->where(function ($matches) use ($tokens, $name, $brand, $category, $phraseLike) {
                $matches->whereRaw("{$name} LIKE ? OR {$brand} LIKE ? OR {$category} LIKE ?", [$phraseLike, $phraseLike, $phraseLike]);
                foreach ($tokens as $token) {
                    $like = '%'.$token.'%';
                    $matches->orWhereRaw("{$name} LIKE ? OR {$brand} LIKE ? OR {$category} LIKE ?", [$like, $like, $like]);
                }
            })
            ->select('stock_items.*')
            ->selectRaw(
                "CASE
                    WHEN {$name} = ? THEN 1
                    WHEN {$name} LIKE ? THEN 2
                    WHEN {$name} LIKE ? THEN 3
                    WHEN {$allNameTokens} THEN 4
                    WHEN {$brand} = ? THEN 5
                    WHEN {$brand} LIKE ? THEN 6
                    WHEN {$brand} LIKE ? THEN 7
                    WHEN {$category} = ? THEN 8
                    WHEN {$category} LIKE ? THEN 9
                    ELSE 10
                END AS search_relevance",
                [...[$search, $startsLike, $phraseLike], ...$allNameTokenBindings, ...[$search, $startsLike, $phraseLike, $search, $startsLike]]
            );

        // A token hit in the item name is always more relevant than an equally
        // matching brand or category; more name tokens win inside that tier.
        $tokenScore = implode(' + ', array_fill(0, count($tokens), "CASE WHEN {$name} LIKE ? THEN 1 ELSE 0 END"));
        $query->selectRaw("({$tokenScore}) AS search_token_matches", array_map(fn ($token) => '%'.$token.'%', $tokens))
            ->orderBy('search_relevance')
            ->orderByDesc('search_token_matches')
            ->orderBy('stock_items.name')
            ->orderBy('stock_items.brand');
    }

    public function dashboard(Request $request)
    {
        $activeLocation = $this->activeLocation();
        if (! $activeLocation) {
            abort(403, 'Selecione uma unidade para continuar.');
        }

        $this->authorizeStockPermission('stock.view');

        $period = $request->validate(['period' => ['nullable', 'in:30d,current_month,90d,current_year']])['period'] ?? '30d';
        $now = now();
        [$start, $groupBy] = match ($period) {
            'current_month' => [$now->copy()->startOfMonth(), 'day'],
            '90d' => [$now->copy()->subDays(89)->startOfDay(), 'week'],
            'current_year' => [$now->copy()->startOfYear(), 'month'],
            default => [$now->copy()->subDays(29)->startOfDay(), 'day'],
        };
        $tenantId = auth()->user()->tenant_id;
        $locationId = $activeLocation->id;
        $canViewCosts = $this->canStock('stock.view_costs');

        $items = StockItem::query()->where('tenant_id', $tenantId)->where('location_id', $locationId)
            ->where('active', true)->with('category:id,name')->get();
        $validMovements = StockMovement::query()->where('tenant_id', $tenantId)->where('location_id', $locationId)
            ->whereNull('cancelled_at')->whereNull('reversed_from_movement_id');
        $periodMovements = (clone $validMovements)->where(function ($query) use ($start, $now) {
            $query->whereBetween('moved_at', [$start, $now])
                ->orWhere(function ($query) use ($start, $now) {
                    $query->whereNull('moved_at')->whereBetween('created_at', [$start, $now]);
                });
        })
            ->with(['stockItem:id,name,unit,stock_category_id', 'stockItem.category:id,name', 'maintenanceRecord.procedure', 'maintenanceRecord.vehicle'])->get();

        $entries = $periodMovements->where('movement_type', 'in');
        $outputs = $periodMovements->where('movement_type', 'out');
        $belowMinimum = $items->filter(fn ($item) => (float) $item->quantity > 0 && (float) $item->quantity < (float) $item->minimum_quantity);
        $zeroStock = $items->filter(fn ($item) => (float) $item->quantity <= 0);
        $lastMovements = (clone $validMovements)->get(['stock_item_id', 'moved_at', 'created_at'])
            ->sortByDesc(fn ($movement) => $movement->moved_at ?? $movement->created_at)
            ->groupBy('stock_item_id')->map->first();
        $staleItems = $items->filter(function ($item) use ($lastMovements, $now) {
            $last = $lastMovements->get($item->id);
            return ! $last || Carbon::parse($last->moved_at ?? $last->created_at)->lt($now->copy()->subDays(90));
        });

        $series = $periodMovements->groupBy(function ($movement) use ($groupBy) {
            $date = Carbon::parse($movement->moved_at ?? $movement->created_at);
            return match ($groupBy) {
                'month' => $date->format('Y-m'),
                'week' => $date->startOfWeek()->format('Y-m-d'),
                default => $date->format('Y-m-d'),
            };
        })->map(function ($movements, $label) {
            return ['label' => $label, 'entries' => (float) $movements->where('movement_type', 'in')->sum('quantity'), 'outputs' => (float) $movements->where('movement_type', 'out')->sum('quantity')];
        })->sortKeys()->values();
        $financialMovements = $canViewCosts ? $periodMovements->groupBy(function ($movement) use ($groupBy) {
            $date = Carbon::parse($movement->moved_at ?? $movement->created_at);
            return match ($groupBy) {
                'month' => $date->format('Y-m'),
                'week' => $date->startOfWeek()->format('Y-m-d'),
                default => $date->format('Y-m-d'),
            };
        })->map(function ($movements, $label) {
            return ['label' => $label, 'entries_value' => round((float) $movements->where('movement_type', 'in')->sum('total_cost'), 2), 'outputs_value' => round((float) $movements->where('movement_type', 'out')->sum('total_cost'), 2)];
        })->sortKeys()->values() : [];
        $topConsumed = $outputs->groupBy('stock_item_id')->map(function ($movements) {
            $item = $movements->first()->stockItem;
            return ['item' => $item?->name ?? 'Item removido', 'category' => $item?->category?->name ?? 'Sem categoria', 'quantity' => (float) $movements->sum('quantity'), 'unit' => $item?->unit ?? '-'];
        })->sortByDesc('quantity')->take(8)->values();
        $topCategories = $outputs->groupBy(fn ($movement) => $movement->stockItem?->category?->name ?? 'Sem categoria')->map(function ($movements, $category) use ($canViewCosts) {
            $result = ['category' => $category, 'quantity' => (float) $movements->sum('quantity')];
            if ($canViewCosts) $result['value'] = round((float) $movements->sum('total_cost'), 2);
            return $result;
        })->sortByDesc('quantity')->take(8)->values();
        $formatItem = fn ($item) => ['item' => $item->name, 'category' => $item->category?->name ?? 'Sem categoria', 'quantity' => (float) $item->quantity, 'minimum_quantity' => (float) $item->minimum_quantity, 'difference' => max(0, (float) $item->minimum_quantity - (float) $item->quantity)];

        $maintenanceOutputs = $outputs->whereNotNull('maintenance_record_id')->take(10)->map(function ($movement) {
            $maintenance = $movement->maintenanceRecord;
            return ['item' => $movement->stockItem?->name ?? 'Item removido', 'quantity' => (float) $movement->quantity, 'unit' => $movement->stockItem?->unit ?? '-', 'procedure' => $maintenance?->procedure?->name ?? 'Manutenção #'.$movement->maintenance_record_id, 'vehicle' => $maintenance?->vehicle?->plate ?? $maintenance?->vehicle?->name];
        })->values();
        $stockValueItems = $canViewCosts ? $items->map(fn ($item) => ['item' => $item->name, 'quantity' => (float) $item->quantity, 'unit_cost' => (float) $item->unit_cost, 'value' => round((float) $item->quantity * (float) $item->unit_cost, 2)])->sortByDesc('value')->take(8)->values() : [];

        return response()->json(['summary' => ['total_items' => $items->count(), 'below_minimum' => $belowMinimum->count(), 'zero_stock' => $zeroStock->count(), 'entries' => (float) $entries->sum('quantity'), 'outputs' => (float) $outputs->sum('quantity'), 'maintenance_consumption' => (float) $outputs->whereNotNull('maintenance_record_id')->sum('quantity'), 'stock_value' => $canViewCosts ? round((float) $items->sum(fn ($item) => (float) $item->quantity * (float) $item->unit_cost), 2) : null, 'movement_value' => $canViewCosts ? round((float) $periodMovements->sum('total_cost'), 2) : null, 'can_view_costs' => $canViewCosts], 'entries_vs_outputs' => $series, 'financial_movements' => $financialMovements, 'top_consumed_items' => $topConsumed, 'top_categories' => $topCategories, 'below_minimum_items' => $belowMinimum->map($formatItem)->values(), 'zero_stock_items' => $zeroStock->map(function ($item) use ($lastMovements, $formatItem) { $row = $formatItem($item); $last = $lastMovements->get($item->id); $row['last_movement'] = $last?->moved_at?->format('Y-m-d') ?? $last?->created_at?->format('Y-m-d'); return $row; })->values(), 'stale_items' => $staleItems->map(function ($item) use ($lastMovements, $now) { $last = $lastMovements->get($item->id); $date = $last?->moved_at ?? $last?->created_at; return ['item' => $item->name, 'quantity' => (float) $item->quantity, 'last_movement' => $date?->format('Y-m-d'), 'days_without_movement' => $date ? (int) Carbon::parse($date)->diffInDays($now) : null]; })->values(), 'top_stock_value_items' => $stockValueItems, 'maintenance_outputs' => $maintenanceOutputs]);
    }

    public function showItem(StockItem $item, StockItemDetailService $detailService)
    {
        if ($redirect = $this->ensureItemInActiveContext($item)) {
            return $redirect;
        }

        $this->authorizeStockPermission('stock.view');
        $this->authorizeStockPermission('stock.view_item_details');

        $canViewCosts = $this->canStock('stock.view_costs');
        $canViewAudit = Gate::allows('viewAuditLogs');
        $stockPermissions = $this->stockPermissions();
        $details = $detailService->build(
            $item,
            auth()->user()->tenant_id,
            (int) $item->location_id,
            $canViewCosts,
            $canViewAudit
        );

        return view('stock.show', array_merge($details, compact(
            'stockPermissions',
            'canViewCosts',
            'canViewAudit'
        )));
    }

    public function itemData(StockItem $item)
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

    public function itemReportPdf(
        Request $request,
        StockItem $item,
        StockItemDetailService $detailService
    ) {
        if ($redirect = $this->ensureItemInActiveContext($item)) {
            return $redirect;
        }

        $this->authorizeStockPermission('stock.view');
        $this->authorizeStockPermission('stock.view_item_details');

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $canViewCosts = $this->canStock('stock.view_costs');
        $canViewAudit = Gate::allows('viewAuditLogs');
        $payload = $detailService->buildPdfReportPayload(
            $item,
            auth()->user()->tenant_id,
            (int) $item->location_id,
            $validated['start_date'],
            $validated['end_date'],
            $canViewCosts,
            $canViewAudit
        );

        return Pdf::loadView('stock.pdf.item-report', $payload)
            ->setPaper('a4', 'landscape')
            ->stream('relatorio-item-estoque-'.$item->id.'.pdf');
    }

    public function storeCategory(Request $request)
    {
        if (! $this->activeLocation()) {
            return $this->missingActiveLocationRedirect();
        }

        $this->authorizeStockPermission('stock.manage_categories');

        $validated = $request->validate([
            'name' => $this->categoryNameRules(),
        ]);

        $tenantId = auth()->user()->tenant_id;
        $category = DB::transaction(function () use ($validated, $tenantId) {
            $name = trim($validated['name']);
            $this->ensureCategoryNameIsAvailable($name, $tenantId);

            return StockCategory::create([
                'tenant_id' => $tenantId,
                'name' => $name,
            ]);
        });

        app(AuditLogService::class)->created($category, [
            'tenant_id' => $tenantId,
            'module' => 'stock',
            'summary' => 'Categoria de estoque criada.',
        ]);

        return redirect()->back()->with('success', 'Categoria criada com sucesso.');
    }

    public function updateCategory(Request $request, StockCategory $category)
    {
        $this->ensureCategoryInActiveContext($category);
        $this->authorizeStockPermission('stock.manage_categories');

        $validated = $request->validate(['name' => $this->categoryNameRules()]);
        $tenantId = auth()->user()->tenant_id;

        DB::transaction(function () use ($category, $validated, $tenantId) {
            $category = StockCategory::query()->where('tenant_id', $tenantId)->lockForUpdate()->findOrFail($category->id);
            $before = $category->toArray();
            $name = trim($validated['name']);
            $this->ensureCategoryNameIsAvailable($name, $tenantId, $category->id);
            $category->update(['name' => $name]);

            app(AuditLogService::class)->updated($category, [
                'tenant_id' => $tenantId,
                'module' => 'stock',
                'summary' => 'Categoria de estoque atualizada.',
                'before_data' => $before,
                'after_data' => $category->toArray(),
            ]);
        });

        return redirect()->back()->with('success', 'Categoria atualizada com sucesso.');
    }

    public function destroyCategory(StockCategory $category)
    {
        $this->ensureCategoryInActiveContext($category);
        $this->authorizeStockPermission('stock.manage_categories');
        $tenantId = auth()->user()->tenant_id;

        $result = DB::transaction(function () use ($category, $tenantId) {
            $category = StockCategory::query()->where('tenant_id', $tenantId)->lockForUpdate()->findOrFail($category->id);
            $activeLocation = $this->activeLocation();
            $localItemsCount = StockItem::query()
                ->where('tenant_id', $tenantId)
                ->where('stock_category_id', $category->id)
                ->where('location_id', $activeLocation->id)
                ->lockForUpdate()
                ->count();

            if ($localItemsCount > 0) {
                return ['local_items_count' => $localItemsCount, 'other_location_items_count' => 0];
            }

            // StockCategory has no location_id in the actual schema.  A category
            // with items elsewhere is therefore shared and cannot be deleted
            // without nulling those foreign keys.
            $otherLocationItemsCount = StockItem::query()
                ->where('tenant_id', $tenantId)
                ->where('stock_category_id', $category->id)
                ->where('location_id', '!=', $activeLocation->id)
                ->lockForUpdate()
                ->count();

            if ($otherLocationItemsCount > 0) {
                return ['local_items_count' => 0, 'other_location_items_count' => $otherLocationItemsCount];
            }

            $before = $category->toArray();
            app(AuditLogService::class)->deleted($category, [
                'tenant_id' => $tenantId,
                'module' => 'stock',
                'summary' => 'Categoria de estoque excluída.',
                'before_data' => $before,
            ]);
            $category->delete();

            return ['local_items_count' => 0, 'other_location_items_count' => 0];
        });

        if ($result['local_items_count'] > 0) {
            return redirect()->back()->with('error', "Esta categoria não pode ser excluída porque possui {$result['local_items_count']} itens vinculados nesta unidade.");
        }

        if ($result['other_location_items_count'] > 0) {
            return redirect()->back()->with('error', "Esta categoria é compartilhada e não pode ser excluída porque possui {$result['other_location_items_count']} itens vinculados em outra unidade.");
        }

        return redirect()->back()->with('success', 'Categoria excluída com sucesso.');
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
            'is_workshop_consumable' => ['nullable', 'boolean'],
        ]);

        $isWorkshopConsumable = (bool) ($validated['is_workshop_consumable'] ?? false);

        DB::transaction(function () use ($validated, $tenantId, $activeLocation, $isWorkshopConsumable) {
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
                'is_workshop_consumable' => $isWorkshopConsumable,
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
            'is_workshop_consumable' => ['nullable', 'boolean'],
        ]);

        $isWorkshopConsumable = (bool) ($validated['is_workshop_consumable'] ?? false);

        $item->update([
            ...$validated,
            'is_workshop_consumable' => $isWorkshopConsumable,
        ]);

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
                Rule::requiredIf(app(TenantFiscalSettingService::class)->requires('stock_entry') && $request->input('movement_type') === 'in'),
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
            'view_item_details' => $this->canStock('stock.view_item_details'),
            'manage_categories' => $this->canStock('stock.manage_categories'),
            'manage_items' => $this->canStock('stock.manage_items'),
            'create_entry' => $this->canStock('stock.entry'),
            'create_manual_output' => $this->canStock('stock.manual_output'),
            'import_invoice' => $this->canStock('stock.entry') && $this->canStock('fiscal_documents.import'),
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

    private function ensureCategoryInActiveContext(StockCategory $category): void
    {
        if (! $this->activeLocation()) {
            abort(403, 'Selecione uma unidade para continuar.');
        }

        if ((int) $category->tenant_id !== (int) auth()->user()->tenant_id) {
            abort(403);
        }
    }

    private function categoryNameRules(): array
    {
        return [
            'required',
            'string',
            'max:255',
            function (string $attribute, mixed $value, \Closure $fail) {
                if (trim((string) $value) === '') {
                    $fail('Informe o nome da categoria.');
                }
            },
        ];
    }

    private function ensureCategoryNameIsAvailable(string $name, int $tenantId, ?int $exceptCategoryId = null): void
    {
        $exists = StockCategory::query()
            ->where('tenant_id', $tenantId)
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->when($exceptCategoryId, fn ($query) => $query->whereKeyNot($exceptCategoryId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'Já existe uma categoria com este nome neste tenant.',
            ]);
        }
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
