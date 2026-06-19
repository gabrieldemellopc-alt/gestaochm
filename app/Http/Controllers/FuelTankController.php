<?php

namespace App\Http\Controllers;

use App\Models\FuelProduct;
use App\Models\FuelReceipt;
use App\Models\FuelTank;
use App\Models\UserDivisionAccess;
use App\Services\ActiveContextService;
use App\Services\FuelService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FuelTankController extends Controller
{
    public function index()
    {
        $context = $this->activeContext();

        if (! $context) {
            return $this->missingActiveLocationRedirect();
        }

        $this->authorizeFuelManagement($context);

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

        return view('fuel.tanks.index', [
            'activeDivision' => $context['division'],
            'activeLocation' => $context['location'],
                'products' => $products,
                'tanks' => $tanks,
                'latestReceipts' => $this->latestReceipts($context),
            ]);
    }

    public function store(Request $request)
    {
        $context = $this->activeContext();

        if (! $context) {
            return $this->missingActiveLocationRedirect();
        }

        $this->authorizeFuelManagement($context);

        $validated = $this->validatedData($request, $context);

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
        $this->ensureTankInActiveContext($tank, $context);

        $validated = $this->validatedData($request, $context);

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

        $fuelService->receiveFuel($request->only([
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

        return redirect()
            ->route('fuel.tanks.index')
            ->with('success', 'Recebimento registrado com sucesso.');
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

    private function validatedData(Request $request, array $context): array
    {
        return $request->validate([
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

    private function missingActiveLocationRedirect()
    {
        return redirect()
            ->route('portal')
            ->with('warning', 'Selecione uma unidade para gerenciar abastecimentos.');
    }
}
