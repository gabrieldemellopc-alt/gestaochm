<?php

namespace App\Http\Controllers;

use App\Models\FuelFilling;
use App\Models\FuelProduct;
use App\Models\FuelReceipt;
use App\Models\FuelTank;
use App\Models\UserDivisionAccess;
use App\Models\Vehicle;
use App\Services\ActiveContextService;
use App\Services\FuelService;
use App\Services\TenantFiscalSettingService;
use App\Services\Permissions\ProfilePermissionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FuelTankController extends Controller
{
    public function index(Request $request)
    {
        $context = $this->activeContext();

        if (! $context) {
            return $this->missingActiveLocationRedirect();
        }

        $this->authorizeFuelManagement($context);
        $this->authorizeFuelPermission('fuel.view', $context);
        $fuelPermissions = $this->fuelPermissions($context);

        $products = FuelProduct::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $tanks = FuelTank::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division_id'])
            ->where('location_id', $context['location_id'])
            ->with('product')
            ->orderByDesc('active')
            ->orderBy('name')
            ->get()
            ->map(function (FuelTank $tank) {
                $tank->balance_status = $this->balanceStatus($tank);
                $tank->balance_percentage = $this->balancePercentage($tank);

                return $tank;
            });

        $fuelBalanceByProduct = $tanks
            ->where('active', true)
            ->groupBy('fuel_product_id')
            ->map(function ($productTanks) {
                $firstTank = $productTanks->first();

                return [
                    'product_id' => $firstTank->fuel_product_id,
                    'product_name' => $firstTank->product?->name ?? 'Produto',
                    'product_slug' => $firstTank->product?->slug ?? null,
                    'available_liters' => (float) $productTanks->sum(
                        fn (FuelTank $tank) => (float) $tank->current_balance_liters
                    ),
                    'capacity_liters' => (float) $productTanks->sum(
                        fn (FuelTank $tank) => (float) $tank->capacity_liters
                    ),
                    'tanks_count' => $productTanks->count(),
                ];
            })
            ->sortBy('product_name')
            ->values();

        $last30DaysStart = now()->subDays(30)->startOfDay();

        $fillingsLast30DaysQuery = FuelFilling::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division_id'])
            ->where('location_id', $context['location_id'])
            ->whereNull('cancelled_at')
            ->whereBetween('filled_at', [
                $last30DaysStart,
                now()->endOfDay(),
            ]);

        $fuelLast30Days = [
            'liters' => (float) (clone $fillingsLast30DaysQuery)
                ->sum('quantity_liters'),

            'total_cost' => (float) (clone $fillingsLast30DaysQuery)
                ->sum('total_cost'),

            'fillings_count' => (clone $fillingsLast30DaysQuery)
                ->count(),

            'start_date' => $last30DaysStart,
            'end_date' => now(),
        ];

        $canViewFuelReport = app(ProfilePermissionService::class)
            ->allows(
                $request->user(),
                'reports.fuel',
                [
                    'module' => 'fleet',
                ]
            );

        return view('fuel.tanks.index', [
            'activeDivision' => $context['division'],
            'activeLocation' => $context['location'],
            'products' => $products,
            'tanks' => $tanks,

            'fuelBalanceByProduct' => $fuelBalanceByProduct,
            'fuelLast30Days' => $fuelLast30Days,

            'vehicles' => $this->vehiclesForContext($context),
            'drivers' => $this->driversForContext($context),
            'latestReceipts' => $this->latestReceipts($context),
            'latestFillings' => $this->latestFillings($context),
            'openFuelModal' => request('fuel_modal') ?: session('fuel_modal'),
            'selectedFuelVehicleId' => request('fuel_vehicle_id') ?: old('vehicle_id'),
            'fuelPermissions' => $fuelPermissions,
            'canViewFuelReport' => $canViewFuelReport,
            'externalFuelDocumentRequired' => app(TenantFiscalSettingService::class)->requires('external_fuel_filling'),
            'fuelReceiptInvoiceRequired' => app(TenantFiscalSettingService::class)->requires('fuel_receipt'),
        ]);
    }

    public function consumptionDashboard(Request $request)
    {
        $context = $this->activeContext();
        if (! $context) abort(422);
        $this->authorizeFuelManagement($context);
        $this->authorizeFuelPermission('fuel.view', $context);

        $period = $request->input('period', 'last_30_days');
        $today = now()->startOfDay();
        [$start, $end, $periodLabel] = match ($period) {
            'current_month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth(), $today->translatedFormat('F/Y')],
            'previous_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth(), $today->copy()->subMonthNoOverflow()->translatedFormat('F/Y')],
            'all' => [null, null, 'Todo o período disponível'],
            default => [$today->copy()->subDays(30), $today->copy()->endOfDay(), 'Últimos 30 dias'],
        };
        $query = FuelFilling::with('vehicle')->where('tenant_id', $context['tenant_id'])->where('division_id', $context['division_id'])->where('location_id', $context['location_id'])->whereNull('cancelled_at');
        if ($start && $end) $query->whereBetween('filled_at', [$start, $end]);
        $fillings = $query->orderBy('filled_at')->get();
        $costs = $this->canFuel('fuel.view_costs', $context);
        $efficiency = $this->vehicleEfficiency($fillings);
        $validEntries = $efficiency->sum('valid_entries');
        $validKm = $efficiency->sum('total_km');
        $validLiters = $efficiency->sum('total_liters');

        return response()->json([
            'period' => $period, 'period_label' => $periodLabel, 'start_date' => $start?->toDateString(), 'end_date' => $end?->toDateString(),
            'summary' => ['total_liters' => (float) $fillings->sum('quantity_liters'), 'fillings_count' => $fillings->count(), 'total_cost' => $costs ? (float) $fillings->sum('total_cost') : null, 'average_km_per_liter' => $validLiters > 0 ? round($validKm / $validLiters, 2) : null, 'average_km_per_liter_entries_count' => $validEntries],
            'by_month' => $fillings->groupBy(fn ($filling) => $filling->filled_at->format('M/Y'))->map(fn ($group, $label) => ['label' => $label, 'liters' => (float) $group->sum('quantity_liters')])->values(),
            'by_weekday' => $fillings->groupBy(fn ($filling) => $filling->filled_at->locale('pt_BR')->isoFormat('ddd'))->map(fn ($group, $label) => ['label' => mb_strtoupper($label), 'liters' => (float) $group->sum('quantity_liters')])->values(),
            'by_day' => $fillings->groupBy(fn ($filling) => $filling->filled_at->toDateString())->map(fn ($group, $label) => ['label' => $label, 'liters' => (float) $group->sum('quantity_liters')])->values(),
            'vehicle_efficiency' => $efficiency->sortByDesc('km_per_liter')->take(10)->values(),
            'top_vehicles_by_liters' => $fillings->groupBy('vehicle_id')->map(fn ($group) => ['vehicle_id' => $group->first()->vehicle_id, 'label' => ($group->first()->vehicle?->name ?? 'Veículo não informado').' · '.($group->first()->vehicle?->plate ?? ''), 'liters' => (float) $group->sum('quantity_liters'), 'total_cost' => $costs ? (float) $group->sum('total_cost') : null])->sortByDesc('liters')->take(10)->values(),
        ]);
    }

    private function vehicleEfficiency($fillings)
    {
        return $fillings->groupBy('vehicle_id')->map(function ($group) {
            $valid = $group->map(fn ($filling) => ['filling' => $filling, 'km' => $this->validImportedDistance($filling)])->filter(fn ($item) => $item['km'] !== null && (float) $item['filling']->quantity_liters > 0)->values();
            $totalKm = (float) $valid->sum('km');
            $totalLiters = (float) $valid->sum(fn ($item) => $item['filling']->quantity_liters);
            $first = $group->first();
            return ['vehicle_id' => $first->vehicle_id, 'label' => ($first->vehicle?->name ?? 'Veículo não informado').' · '.($first->vehicle?->plate ?? ''), 'total_km' => round($totalKm, 2), 'total_liters' => round($totalLiters, 3), 'km_per_liter' => $totalLiters > 0 ? round($totalKm / $totalLiters, 2) : null, 'valid_entries' => $valid->count(), 'ignored_entries' => $group->count() - $valid->count()];
        })->filter(fn ($item) => $item['km_per_liter'] !== null);
    }

    private function validImportedDistance(FuelFilling $filling): ?float
    {
        $notes = (string) $filling->notes;
        if (! str_starts_with($notes, 'Importação histórica Imperatriz;') || ! preg_match('/(?:^|;)\s*percorrido=([^;]+);/u', $notes, $matches)) return null;
        $value = trim($matches[1]);
        $number = (float) str_replace(',', '.', str_replace('.', '', preg_replace('/[^0-9,.-]/', '', $value)));
        return $number > 0 && $number <= 5000 ? $number : null;
    }
    public function store(Request $request)
    {
        $context = $this->activeContext();

        if (! $context) {
            return $this->missingActiveLocationRedirect();
        }

        $this->authorizeFuelManagement($context);
        $this->authorizeFuelPermission('fuel.view', $context);

        $validated = $this->validatedData($request, $context, 'fuelTank');

        FuelTank::query()->create([
            'tenant_id' => $context['tenant_id'],
            'division_id' => $context['division_id'],
            'location_id' => $context['location_id'],
            'fuel_product_id' => $validated['fuel_product_id'],
            'name' => $validated['name'],
            'capacity_liters' => $validated['capacity_liters'],
            'current_balance_liters' => 0,
            'minimum_balance_liters' => $validated['minimum_balance_liters'] ?? 0,
            'active' => (bool) ($validated['active'] ?? true),
        ]);

        return redirect()
            ->route('fuel.tanks.index')
            ->with('success', 'Tanque cadastrado com sucesso.');
    }

    public function update(Request $request, FuelTank $tank)
    {
        $context = $this->activeContext();

        if (! $context) {
            return $this->missingActiveLocationRedirect();
        }

        $this->authorizeFuelManagement($context);
        $this->authorizeFuelPermission('fuel.view', $context);
        $this->ensureTankInActiveContext($tank, $context);

        $validated = $this->validatedData($request, $context, 'fuelTankEdit'.$tank->id);

        $tank->update([
            'fuel_product_id' => $validated['fuel_product_id'],
            'name' => $validated['name'],
            'capacity_liters' => $validated['capacity_liters'],
            'minimum_balance_liters' => $validated['minimum_balance_liters'] ?? 0,
            'active' => (bool) ($validated['active'] ?? false),
        ]);

        return redirect()
            ->route('fuel.tanks.index')
            ->with('success', 'Tanque atualizado com sucesso.');
    }

    public function storeReceipt(Request $request, FuelService $fuelService)
    {
        $context = $this->activeContext();

        if (! $context) {
            return $this->missingActiveLocationRedirect();
        }

        $this->authorizeFuelManagement($context);
        $this->authorizeFuelPermission('fuel.receive', $context);

        if (app(TenantFiscalSettingService::class)->requires('fuel_receipt')) {
            $request->validate(['invoice_number' => ['required', 'string', 'max:255']], ['invoice_number.required' => 'Documento fiscal obrigatório para recebimento de combustível.']);
        }

        try {
            $fuelService->receiveFuel($request->only([
                'source',
                'fuel_tank_id',
                'fuel_product_id',
                'received_at',
                'quantity_liters',
                'unit_cost',
                'total_cost',
                'supplier_name',
                'invoice_number',
                'notes',
            ]));
        } catch (ValidationException $exception) {
            return back()
                ->withErrors($exception->errors(), 'fuelReceipt')
                ->withInput()
                ->with('fuel_modal', 'receipt-'.$request->input('fuel_tank_id'));
        }

        return redirect()
            ->route('fuel.tanks.index')
            ->with('success', 'Recebimento registrado com sucesso.');
    }

    public function storeFilling(Request $request, FuelService $fuelService)
    {
        $context = $this->activeContext();

        if (! $context) {
            return $this->missingActiveLocationRedirect();
        }

        $this->authorizeFuelManagement($context);
        $source = $request->input('source', FuelFilling::SOURCE_INTERNAL_TANK);
        $this->authorizeFuelPermission(
            $source === FuelFilling::SOURCE_EXTERNAL_STATION
                ? 'fuel.fill_external'
                : 'fuel.fill_internal',
            $context
        );

        if ($source === FuelFilling::SOURCE_EXTERNAL_STATION && app(TenantFiscalSettingService::class)->requires('external_fuel_filling')) {
            $request->validate(['document_number' => ['required', 'string', 'max:255']], ['document_number.required' => 'Documento fiscal obrigatório para abastecimento externo.']);
        }

        try {
            $fuelService->registerFilling($request->only([
                'source',
                'fuel_tank_id',
                'fuel_product_id',
                'vehicle_id',
                'driver_id',
                'filled_at',
                'vehicle_km',
                'vehicle_hours',
                'quantity_liters',
                'unit_cost',
                'total_cost',
                'supplier_name',
                'document_number',
                'notes',
                'km_reading_confirmed',
                'hours_reading_confirmed',
            ]));
        } catch (ValidationException $exception) {
            return back()
                ->withErrors($exception->errors(), 'fuelFilling')
                ->withInput()
                ->with('fuel_modal', 'filling');
        }

        return redirect()
            ->route('fuel.tanks.index')
            ->with('success', 'Abastecimento registrado com sucesso.');
    }

    private function activeContext(): ?array
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        $activeContext = app(ActiveContextService::class);
        $division = $activeContext->activeDivision($user);
        $location = $activeContext->activeLocation($user);

        if (! $division || ! $location) {
            return null;
        }

        return [
            'user' => $user,
            'tenant_id' => $user->tenant_id,
            'division_id' => $division->id,
            'location_id' => $location->id,
            'division' => $division,
            'location' => $location,
        ];
    }

    private function validatedData(Request $request, array $context, string $errorBag): array
    {
        return $request->validateWithBag($errorBag, [
            'fuel_product_id' => [
                'required',
                'integer',
                Rule::exists('fuel_products', 'id')
                    ->where('tenant_id', $context['tenant_id'])
                    ->where('active', true),
            ],
            'name' => ['required', 'string', 'max:255'],
            'capacity_liters' => ['required', 'numeric', 'gt:0'],
            'minimum_balance_liters' => ['nullable', 'numeric', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ]);
    }

    private function authorizeFuelManagement(array $context): void
    {
        $allowed = UserDivisionAccess::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('user_id', $context['user']->id)
            ->where('division_id', $context['division_id'])
            ->where('module', 'fleet')
            ->whereIn('profile', ['supervisor', 'manager', 'admin'])
            ->where('active', true)
            ->where(function ($query) use ($context) {
                $query
                    ->where('location_id', $context['location_id'])
                    ->orWhereNull('location_id');
            })
            ->exists();

        if (! $allowed) {
            abort(403);
        }
    }

    private function authorizeFuelPermission(string $permissionKey, array $context): void
    {
        if ($this->canFuel($permissionKey, $context)) {
            return;
        }

        abort(403, 'Você não tem permissão para executar esta ação.');
    }

    private function canFuel(string $permissionKey, array $context): bool
    {
        return app(ProfilePermissionService::class)->allows($context['user'], $permissionKey, [
            'tenant_id' => $context['tenant_id'],
            'division_id' => $context['division_id'],
            'location_id' => $context['location_id'],
            'module' => 'fleet',
        ]);
    }

    private function fuelPermissions(array $context): array
    {
        return [
            'view' => $this->canFuel('fuel.view', $context),
            'receive' => $this->canFuel('fuel.receive', $context),
            'fill_internal' => $this->canFuel('fuel.fill_internal', $context),
            'fill_external' => $this->canFuel('fuel.fill_external', $context),
            'cancel' => $this->canFuel('fuel.cancel', $context),
            'view_costs' => $this->canFuel('fuel.view_costs', $context),
        ];
    }
    private function ensureTankInActiveContext(FuelTank $tank, array $context): void
    {
        if (
            (int) $tank->tenant_id !== (int) $context['tenant_id']
            || (int) $tank->division_id !== (int) $context['division_id']
            || (int) $tank->location_id !== (int) $context['location_id']
        ) {
            abort(403);
        }
    }

    private function balanceStatus(FuelTank $tank): string
    {
        if (! $tank->active) {
            return 'inactive';
        }

        return (float) $tank->current_balance_liters <= (float) $tank->minimum_balance_liters
            ? 'low'
            : 'normal';
    }

    private function balancePercentage(FuelTank $tank): float
    {
        $capacity = (float) $tank->capacity_liters;

        if ($capacity <= 0) {
            return 0;
        }

        return min(100, round(((float) $tank->current_balance_liters / $capacity) * 100, 1));
    }

    private function latestReceipts(array $context)
    {
        return FuelReceipt::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division_id'])
            ->where('location_id', $context['location_id'])
            ->with(['tank.product', 'product', 'responsible'])
            ->latest('received_at')
            ->limit(8)
            ->get();
    }

    private function latestFillings(array $context)
    {
        return FuelFilling::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division_id'])
            ->where('location_id', $context['location_id'])
            ->with(['tank.product', 'product', 'vehicle', 'driver', 'responsible'])
            ->latest('filled_at')
            ->limit(8)
            ->get();
    }

    private function vehiclesForContext(array $context)
    {
        return Vehicle::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division_id'])
            ->where('location_id', $context['location_id'])
            ->orderBy('name')
            ->get(['id', 'name', 'plate', 'current_km', 'current_hours']);
    }

    private function driversForContext(array $context)
    {
        return UserDivisionAccess::query()
            ->with('user')
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division_id'])
            ->where('module', 'fleet')
            ->whereIn('profile', ['driver', 'motorista'])
            ->where('active', true)
            ->where(function ($query) use ($context) {
                $query
                    ->where('location_id', $context['location_id'])
                    ->orWhereNull('location_id');
            })
            ->get()
            ->filter(fn ($access) => $access->user)
            ->map(fn ($access) => $access->user)
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    private function missingActiveLocationRedirect()
    {
        return redirect()
            ->route('portal')
            ->with('warning', 'Selecione uma unidade para gerenciar abastecimentos.');
    }
}
