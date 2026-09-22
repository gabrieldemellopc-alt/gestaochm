<?php

namespace App\Http\Controllers;

use App\Models\FuelDailyCheck;
use App\Models\FuelFilling;
use App\Models\FuelProduct;
use App\Models\FuelReceipt;
use App\Models\FuelTank;
use App\Models\Vehicle;
use App\Services\ActiveContextService;
use App\Services\FuelService;
use App\Services\VehicleFuelPolicy;

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

        $vehicles = $this->vehiclesForContext($context);
        $vehicles->load('fuelProducts');
        $policy = app(VehicleFuelPolicy::class);
        $fuelCompatibility = $vehicles->mapWithKeys(fn (Vehicle $vehicle) => [$vehicle->id => $policy->compatibilityForVehicle($vehicle, $products)]);

        /*
         * Últimos abastecimentos válidos por veículo.
         * Usado somente como apoio visual no modal de abastecimento.
         */
        $vehicleFillingHistory =
            FuelFilling::query()
                ->with([
                    'tank:id,name',
                    'product:id,name',
                    'responsible:id,name',
                ])
                ->where('tenant_id', $context['tenant_id'])
                ->where('division_id', $context['division_id'])
                ->where('location_id', $context['location_id'])
                ->whereNull('cancelled_at')
                ->whereNotNull('vehicle_id')
                ->whereIn('vehicle_id', $vehicles->pluck('id'))
                ->orderBy('vehicle_id')
                ->orderByDesc('filled_at')
                ->orderByDesc('id')
                ->get();

        $lastFillingByVehicle =
            $vehicleFillingHistory
                ->groupBy('vehicle_id')
                ->mapWithKeys(function ($items, $vehicleId) {

                    $last = $items->first();

                    $previousWithKm =
                        $items
                            ->skip(1)
                            ->first(
                                fn ($item) =>
                                    $item->vehicle_km !== null
                            );

                    return [
                        (string) $vehicleId => [
                            'filled_at' =>
                                $last->filled_at?->format('d/m/Y H:i'),

                            'quantity_liters' =>
                                $last->quantity_liters !== null
                                    ? number_format(
                                        (float) $last->quantity_liters,
                                        3,
                                        ',',
                                        '.'
                                    )
                                    : null,

                            'product' =>
                                $last->product?->name,

                            'tank' =>
                                $last->tank?->name,

                            'vehicle_km' =>
                                $last->vehicle_km !== null
                                    ? number_format(
                                        (float) $last->vehicle_km,
                                        0,
                                        ',',
                                        '.'
                                    )
                                    : null,

                            'previous_km' =>
                                $previousWithKm?->vehicle_km !== null
                                    ? number_format(
                                        (float) $previousWithKm->vehicle_km,
                                        0,
                                        ',',
                                        '.'
                                    )
                                    : null,

                            'source_label' =>
                                $last->source === FuelFilling::SOURCE_EXTERNAL_STATION
                                    ? 'Posto externo'
                                    : 'Tanque da unidade',

                            'responsible' =>
                                $last->responsible?->name,
                        ],
                    ];
                })
                ->all();
        return view('fuel.tanks.index', [
            'activeDivision' => $context['division'],
            'activeLocation' => $context['location'],
            'products' => $products,
            'tanks' => $tanks,

            'fuelBalanceByProduct' => $fuelBalanceByProduct,
            'fuelLast30Days' => $fuelLast30Days,

            'vehicles' => $vehicles,
            'fuelCompatibility' => $fuelCompatibility,
            'lastFillingByVehicle' => $lastFillingByVehicle,
            'latestReceipts' => $this->latestReceipts($context),
            'latestFillings' => $this->latestFillings($context),
            'openFuelModal' => request('fuel_modal') ?: session('fuel_modal'),
            'selectedFuelVehicleId' => request('fuel_vehicle_id') ?: old('vehicle_id'),
            'fuelReturnTo' => request('return_to') === 'fleet_dashboard' || session('fuel_return_to') === 'fleet_dashboard' ? 'fleet_dashboard' : 'fuel_tanks',
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
        abort_unless(
            (int) auth()->id() === 1 || userHasProfile('admin'),
            403
        );

        $context = $this->activeContext();

        if (! $context) {
            return $this->missingActiveLocationRedirect();
        }

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
        abort_unless(
            (int) auth()->id() === 1 || userHasProfile('admin'),
            403
        );

        $context = $this->activeContext();

        if (! $context) {
            return $this->missingActiveLocationRedirect();
        }

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

    public function storeReceipt(
        Request $request,
        FuelService $fuelService
    ) {
        $context = $this->activeContext();

        if (! $context) {
            return $this->missingActiveLocationRedirect();
        }

        $this->authorizeFuelPermission(
            'fuel.receive',
            $context
        );

        $invoiceRequired = app(
            TenantFiscalSettingService::class
        )->requires('fuel_receipt');

        $invoicePending =
            $invoiceRequired
            && blank($request->input('invoice_number'));

        $invoiceFile =
            $request->file('invoice_file');

        $storedInvoicePath = null;

        try {
            validator(
                [
                    'invoice_file' => $invoiceFile,
                ],
                [
                    'invoice_file' => [
                        'nullable',
                        'file',
                        'mimes:jpg,jpeg,png,webp,pdf',
                        'max:12288',
                    ],
                ],
                [
                    'invoice_file.file' =>
                        'O anexo da NF deve ser um arquivo válido.',

                    'invoice_file.mimes' =>
                        'A NF deve estar em JPG, JPEG, PNG, WEBP ou PDF.',

                    'invoice_file.max' =>
                        'O arquivo da NF deve possuir no máximo 12 MB.',
                ]
            )->validate();

            if ($invoiceFile) {
                $errors = [];

                if (
                    blank(
                        $request->input('invoice_number')
                    )
                ) {
                    $errors['invoice_number'] =
                        'Informe o número da NF para anexar o documento.';
                }

                if (
                    blank(
                        $request->input('invoice_date')
                    )
                ) {
                    $errors['invoice_date'] =
                        'Informe a data da NF para anexar o documento.';
                }

                if (
                    blank(
                        $request->input('supplier_name')
                    )
                ) {
                    $errors['supplier_name'] =
                        'Informe o fornecedor para anexar a NF.';
                }

                if ($errors) {
                    throw ValidationException::withMessages(
                        $errors
                    );
                }
            }

            DB::transaction(function () use (
                $request,
                $fuelService,
                $context,
                $invoicePending,
                $invoiceFile,
                &$storedInvoicePath
            ) {
                $receipt = $fuelService->receiveFuel(
                    array_merge(
                        $request->only([
                            'source',
                            'fuel_tank_id',
                            'fuel_product_id',
                            'received_at',
                            'quantity_liters',
                            'unit_cost',
                            'total_cost',
                            'supplier_name',
                            'supplier_id',
                            'supplier_document',
                            'invoice_number',
                            'invoice_date',
                            'notes',
                        ]),
                        [
                            'invoice_pending' =>
                                $invoicePending,
                        ]
                    )
                );

                if (! $invoiceFile) {
                    return;
                }

                $operationDate =
                    Carbon::parse(
                        $receipt->received_at
                    )->toDateString();

                $check =
                    FuelDailyCheck::query()
                        ->firstOrCreate(
                            [
                                'tenant_id' =>
                                    $receipt->tenant_id,

                                'division_id' =>
                                    $receipt->division_id,

                                'location_id' =>
                                    $receipt->location_id,

                                'operation_date' =>
                                    $operationDate,
                            ],
                            [
                                'status' => 'pending',
                            ]
                        );

                $extension = strtolower(
                    $invoiceFile
                        ->getClientOriginalExtension()
                    ?: $invoiceFile->extension()
                    ?: 'jpg'
                );

                $path =
                    'protected/fuel-daily-checks/'
                    .$check->tenant_id.'/'
                    .$check->location_id.'/'
                    .$check->id.'/'
                    .Str::uuid().'.'.$extension;

                Storage::disk('local')->putFileAs(
                    dirname($path),
                    $invoiceFile,
                    basename($path)
                );

                $storedInvoicePath = $path;

                $storedFile =
                    $check->files()->create([
                        'disk' => 'local',

                        'path' => $path,

                        'original_name' =>
                            $invoiceFile
                                ->getClientOriginalName(),

                        'mime_type' =>
                            $invoiceFile->getMimeType(),

                        'size_bytes' =>
                            Storage::disk('local')
                                ->size($path),

                        'source' =>
                            'receipt_entry',

                        'document_type' =>
                            'fuel_invoice',

                        'document_date' =>
                            Carbon::parse(
                                $request->input(
                                    'invoice_date'
                                )
                            )->toDateString(),

                        'invoice_number' =>
                            trim(
                                (string)
                                $request->input(
                                    'invoice_number'
                                )
                            ),

                        'supplier_id' =>
                            $receipt->supplier_id,

                        'supplier_name' =>
                            $receipt->supplier_name,

                        'supplier_document' =>
                            $receipt->supplier_document,

                        'uploaded_by' =>
                            $context['user']->id,
                    ]);

                $storedFile
                    ->receipts()
                    ->sync([
                        $receipt->id,
                    ]);
            });

        } catch (ValidationException $exception) {
            if (
                $storedInvoicePath
                && Storage::disk('local')
                    ->exists($storedInvoicePath)
            ) {
                Storage::disk('local')
                    ->delete($storedInvoicePath);
            }

            return back()
                ->withErrors(
                    $exception->errors(),
                    'fuelReceipt'
                )
                ->withInput()
                ->with(
                    'fuel_modal',
                    'receipt-'
                    .$request->input('fuel_tank_id')
                );

        } catch (\Throwable $exception) {
            if (
                $storedInvoicePath
                && Storage::disk('local')
                    ->exists($storedInvoicePath)
            ) {
                Storage::disk('local')
                    ->delete($storedInvoicePath);
            }

            throw $exception;
        }

        return redirect()
            ->route('fuel.tanks.index')
            ->with(
                'success',
                $invoiceFile
                    ? 'Recebimento registrado e NF arquivada com sucesso.'
                    : 'Recebimento registrado com sucesso. A NF permanece pendente.'
            );
    }


    public function replaceReceipt(
        Request $request,
        FuelReceipt $receipt,
        FuelService $fuelService
    ) {
        $context = $this->activeContext();

        if (! $context) {
            return $this->missingActiveLocationRedirect();
        }

        $this->authorizeFuelPermission(
            'fuel.receive',
            $context
        );

        $invoiceRequired = app(
            TenantFiscalSettingService::class
        )->requires('fuel_receipt');

        $invoicePending =
            $invoiceRequired
            && blank($request->input('invoice_number'));

        try {

            $newReceipt = $fuelService->replaceReceipt(
                $receipt,
                array_merge(
                    $request->only([
                        'received_at',
                        'quantity_liters',
                        'unit_cost',
                        'total_cost',
                        'supplier_name',
                        'supplier_id',
                        'supplier_document',
                        'invoice_number',
                        'invoice_date',
                        'notes',
                    ]),
                    [
                        'invoice_pending' =>
                            $invoicePending,
                    ]
                ),
                (string) $request->input('reason')
            );

        } catch (ValidationException $exception) {

            return back()
                ->withErrors(
                    $exception->errors(),
                    'fuelReceiptEdit'.$receipt->id
                )
                ->withInput()
                ->with(
                    'edit_receipt_id',
                    $receipt->id
                );
        }

        return redirect()
            ->route('fuel.receipts.history')
            ->with(
                'success',
                'Recebimento #'.$receipt->id
                .' substituído pelo #'
                .$newReceipt->id.'.'
            );
    }


    public function storeFilling(Request $request, FuelService $fuelService)
    {
        $context = $this->activeContext();

        if (! $context) {
            return $this->missingActiveLocationRedirect();
        }

        $source = $request->input('source', FuelFilling::SOURCE_INTERNAL_TANK);
        $this->authorizeFuelPermission(
            $source === FuelFilling::SOURCE_EXTERNAL_STATION
                ? 'fuel.fill_external'
                : 'fuel.fill_internal',
            $context
        );
        $vehicle = \App\Models\Vehicle::query()->where('id', $request->input('vehicle_id'))->where('tenant_id', $context['tenant_id'])->where('division_id', $context['division_id'])->where('location_id', $context['location_id'])->first();
        if ($vehicle) app(\App\Services\AggregatedVehiclePolicy::class)->ensureFuelAllowed($vehicle, $context['location']);

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
                'supplier_id',
                'document_number',
                'notes',
                'km_reading_confirmed',
                'hours_reading_confirmed',
                'confirm_duplicate',
                'return_to',
            ]));
        } catch (ValidationException $exception) {
            return back()
                ->withErrors($exception->errors(), 'fuelFilling')
                ->withInput()
                ->with('fuel_modal', 'filling')
                ->with('fuel_return_to', $this->fuelReturnTo($request));
        }

        return redirect()->route($this->fuelReturnTo($request) === 'fleet_dashboard' ? 'dashboard' : 'fuel.tanks.index')
            ->with('success', 'Abastecimento registrado com sucesso.');
    }

    private function fuelReturnTo(Request $request): string { return $request->input('return_to') === 'fleet_dashboard' ? 'fleet_dashboard' : 'fuel_tanks'; }

    public function fillingsHistory(Request $request)
    {
        $context = $this->historyContext(); $this->authorizeFuelPermission('fuel.view', $context);

        $fleetRelation = $this->resolveFuelFleetRelation(
            $request,
            (int) $context['location_id']
        );

        $query = FuelFilling::query()->where('tenant_id', $context['tenant_id'])->where('division_id', $context['division_id'])->where('location_id', $context['location_id'])->with(['vehicle', 'tank', 'product', 'responsible', 'canceller', 'replacesFilling', 'replacedByFilling']);
        $query->when(
            $fleetRelation !== 'all',
            fn ($query) => $query->whereHas(
                'vehicle',
                fn ($vehicleQuery) => $vehicleQuery->where(
                    'fleet_relation',
                    $fleetRelation
                )
            )
        );

        $this->applyPeriod($query, $request, 'filled_at');
        foreach (['vehicle_id', 'fuel_product_id', 'fuel_tank_id'] as $field) if ($request->filled($field)) $query->where($field, $request->integer($field));
        if ($request->filled('source')) {
            $request->input('source') === FuelFilling::SOURCE_INTERNAL_TANK
                ? $query->where(fn ($q) => $q->where('source', FuelFilling::SOURCE_INTERNAL_TANK)->orWhereNull('source'))
                : $query->where('source', $request->input('source'));
        }
        $historyStatus = $request->input('status', 'active');

        if ($historyStatus === 'active') {
            $query->whereNull('cancelled_at');
        }

        if ($historyStatus === 'cancelled') {
            $query->whereNotNull('cancelled_at');
        }

        $filteredCount = (clone $query)->count();

        $historyVehicles = $this->vehiclesForContext($context);

        if ($fleetRelation !== 'all') {
            $historyVehicles = $historyVehicles
                ->where('fleet_relation', $fleetRelation)
                ->values();
        }

        return view('fuel.tanks.fillings-history', [
            'fillings' => $query->latest('filled_at')->paginate(25)->withQueryString(),
            'filteredCount' => $filteredCount,
            'vehicles' => $historyVehicles,
            'fleetRelation' => $fleetRelation,
            'products' => FuelProduct::where('tenant_id', $context['tenant_id'])->orderBy('name')->get(),
            'tanks' => FuelTank::where('tenant_id', $context['tenant_id'])
                ->where('division_id', $context['division_id'])
                ->where('location_id', $context['location_id'])
                ->orderBy('name')
                ->get(),
            'fuelPermissions' => $this->fuelPermissions($context),
        ]);
    }

    public function manualFuelSheetPdf(Request $request)
    {
        $context = $this->historyContext();

        $this->authorizeFuelPermission('fuel.view', $context);

        $validated = $request->validate([
            'sheet_mode' => ['required', Rule::in(['blank', 'prefilled'])],

            'vehicle_ids' => ['nullable', 'array', 'max:100'],
            'vehicle_ids.*' => ['integer'],

            'show_code' => ['nullable', 'boolean'],
            'show_plate' => ['nullable', 'boolean'],
            'show_km' => ['nullable', 'boolean'],
            'show_arla' => ['nullable', 'boolean'],

            'blank_rows' => ['required', 'integer', 'min:0', 'max:40'],
        ]);

        $mode = $validated['sheet_mode'];

        $selectedIds = collect(
            $validated['vehicle_ids'] ?? []
        )
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $vehicles = collect();

        if ($mode === 'prefilled' && $selectedIds->isNotEmpty()) {

            /*
             * Usa a própria coleção contextual do módulo.
             * Assim nenhum veículo de outra unidade/tenant pode
             * ser inserido no PDF por manipulação do formulário.
             */
            $vehicles = $this->vehiclesForContext($context)
                ->whereIn('id', $selectedIds)
                ->sortBy(fn ($vehicle) => strtolower(
                    trim(
                        ($vehicle->name ?? '')
                        .'|'.
                        ($vehicle->plate ?? '')
                    )
                ))
                ->values();
        }

        $blankRows = (int) $validated['blank_rows'];

        /*
         * Folha totalmente em branco:
         * garante uma página utilizável mesmo que o usuário
         * informe zero por engano.
         */
        if ($mode === 'blank' && $blankRows === 0) {
            $blankRows = 28;
        }

        $location = \App\Models\Location::query()
            ->find($context['location_id']);

        $options = [
            'mode' => $mode,

            'show_code' => $request->boolean('show_code'),
            'show_plate' => $request->boolean('show_plate'),
            'show_km' => $request->boolean('show_km'),
            'show_arla' => $request->boolean('show_arla'),

            'blank_rows' => $blankRows,
        ];

        return \Barryvdh\DomPDF\Facade\Pdf::loadView(
            'fuel.tanks.manual-fuel-sheet-pdf',
            [
                'vehicles' => $vehicles,
                'location' => $location,
                'options' => $options,
            ]
        )
            ->setPaper('a4', 'portrait')
            ->stream(
                'ficha-manual-abastecimento-'
                .now()->format('Y-m-d-His')
                .'.pdf'
            );
    }


    public function fillingsHistoryPdf(Request $request)
    {
        $context = $this->historyContext();
        $this->authorizeFuelPermission('fuel.view', $context);

        $permissions = $this->fuelPermissions($context);

        $fleetRelation = $this->resolveFuelFleetRelation(
            $request,
            (int) $context['location_id']
        );

        $query = FuelFilling::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division_id'])
            ->where('location_id', $context['location_id'])
            ->with([
                'vehicle',
                'tank',
                'product',
                'responsible',
                'canceller',
                'replacesFilling',
                'replacedByFilling',
            ]);

        $query->when(
            $fleetRelation !== 'all',
            fn ($query) => $query->whereHas(
                'vehicle',
                fn ($vehicleQuery) => $vehicleQuery->where(
                    'fleet_relation',
                    $fleetRelation
                )
            )
        );

        $this->applyPeriod($query, $request, 'filled_at');

        foreach (['vehicle_id', 'fuel_product_id', 'fuel_tank_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->integer($field));
            }
        }

        if ($request->filled('source')) {
            $request->input('source') === FuelFilling::SOURCE_INTERNAL_TANK
                ? $query->where(fn ($q) => $q
                    ->where('source', FuelFilling::SOURCE_INTERNAL_TANK)
                    ->orWhereNull('source'))
                : $query->where('source', $request->input('source'));
        }

        $historyStatus = $request->input('status', 'active');

        if ($historyStatus === 'active') {
            $query->whereNull('cancelled_at');
        }

        if ($historyStatus === 'cancelled') {
            $query->whereNotNull('cancelled_at');
        }

        $pdfRecordCount = (clone $query)->count();

        if ($pdfRecordCount > 250) {
            return redirect()
                ->route('fuel.fillings.history', $request->query())
                ->with(
                    'error',
                    'O limite para geração de PDF é de até 250 registros de abastecimentos. Refine o período ou os filtros.'
                );
        }

        $summaryQuery = clone $query;

        /*
         * Cancelados podem aparecer na listagem conforme o filtro,
         * mas nunca compõem os totais operacionais.
         */
        $activeSummary = (clone $summaryQuery)->whereNull('cancelled_at');

        $summary = [
            'fillings_count' => (clone $activeSummary)->count(),
            'vehicles_count' => (clone $activeSummary)
                ->distinct('vehicle_id')
                ->count('vehicle_id'),
            'liters_total' => (float) (clone $activeSummary)
                ->sum('quantity_liters'),
            'cost_total' => $permissions['view_costs']
                ? (float) (clone $activeSummary)
                    ->selectRaw('COALESCE(SUM(COALESCE(source_total_cost, total_cost, 0)), 0) AS total')
                    ->value('total')
                : null,
        ];

        $fillings = $query
            ->orderByDesc('filled_at')
            ->orderByDesc('id')
            ->get();

        $division = \App\Models\Division::find($context['division_id']);
        $location = \App\Models\Location::find($context['location_id']);

        $vehicle = $request->filled('vehicle_id')
            ? \App\Models\Vehicle::query()
                ->where('id', $request->integer('vehicle_id'))
                ->where('tenant_id', $context['tenant_id'])
                ->where('division_id', $context['division_id'])
                ->where('location_id', $context['location_id'])
                ->first()
            : null;

        $generatedAt = now();
        $generatedBy = auth()->user();

        return \Barryvdh\DomPDF\Facade\Pdf::loadView(
            'fuel.tanks.fillings-history-pdf',
            compact(
                'fillings',
                'summary',
                'permissions',
                'division',
                'location',
                'vehicle',
                'generatedAt',
                'generatedBy',
                'request',
                'fleetRelation'
            )
        )
            ->setPaper('a4', 'landscape')
            ->download(
                'historico-abastecimentos-'
                .$generatedAt->format('Ymd-His')
                .'.pdf'
            );
    }

    public function receiptsHistory(Request $request)
    {
        $context = $this->historyContext(); $this->authorizeFuelPermission('fuel.view', $context);
        $query = FuelReceipt::query()->where('tenant_id', $context['tenant_id'])->where('division_id', $context['division_id'])->where('location_id', $context['location_id'])->with([
            'tank',
            'product',
            'responsible',
            'canceller',
            'invoiceFiles',
        ]);
        $this->applyPeriod($query, $request, 'received_at');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function ($q) use ($search) {
                $q->where('supplier_name', 'like', '%'.$search.'%')
                    ->orWhere('invoice_number', 'like', '%'.$search.'%')
                    ->orWhereHas(
                        'responsible',
                        fn ($userQuery) => $userQuery->where(
                            'name',
                            'like',
                            '%'.$search.'%'
                        )
                    );

                $numericSearch = str_replace(
                    ',',
                    '.',
                    preg_replace('/[^0-9,.-]/', '', $search)
                );

                if ($numericSearch !== '' && is_numeric($numericSearch)) {
                    $q->orWhere(
                        'quantity_liters',
                        (float) $numericSearch
                    );
                }
            });
        }
        foreach (['fuel_product_id', 'fuel_tank_id'] as $field) if ($request->filled($field)) $query->where($field, $request->integer($field));
        if ($request->filled('supplier_name')) $query->where('supplier_name', 'like', '%'.$request->input('supplier_name').'%');
        $receiptHistoryStatus =
            $request->input('status', 'active');

        if ($receiptHistoryStatus === 'active') {
            $query->whereNull('cancelled_at');
        }

        if ($receiptHistoryStatus === 'cancelled') {
            $query->whereNotNull('cancelled_at');
        }

        return view('fuel.tanks.receipts-history', ['receipts' => $query->latest('received_at')->paginate(25)->withQueryString(), 'products' => FuelProduct::where('tenant_id', $context['tenant_id'])->orderBy('name')->get(), 'tanks' => FuelTank::where('tenant_id', $context['tenant_id'])->where('division_id', $context['division_id'])->where('location_id', $context['location_id'])->orderBy('name')->get(), 'fuelPermissions' => $this->fuelPermissions($context)]);
    }

    public function replaceFilling(Request $request, FuelFilling $filling, FuelService $fuelService)
    {
        $context = $this->historyContext();
        $this->authorizeFuelPermission('fuel.cancel', $context);

        if (
            (int) $filling->tenant_id !== (int) $context['tenant_id']
            || (int) $filling->division_id !== (int) $context['division_id']
            || (int) $filling->location_id !== (int) $context['location_id']
        ) {
            abort(403);
        }

        $source = $request->input('source', FuelFilling::SOURCE_INTERNAL_TANK);

        $vehicle = \App\Models\Vehicle::query()
            ->where('id', $request->input('vehicle_id'))
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division_id'])
            ->where('location_id', $context['location_id'])
            ->first();

        if ($vehicle) {
            app(\App\Services\AggregatedVehiclePolicy::class)
                ->ensureFuelAllowed($vehicle, $context['location']);
        }

        if (
            $source === FuelFilling::SOURCE_EXTERNAL_STATION
            && app(TenantFiscalSettingService::class)->requires('external_fuel_filling')
        ) {
            $request->validate([
                'document_number' => ['required', 'string', 'max:255'],
            ], [
                'document_number.required' => 'Documento fiscal obrigatório para abastecimento externo.',
            ]);
        }

        try {
            $fuelService->replaceFilling($filling, $request->only([
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
                'supplier_document',
                'document_number',
                'notes',
                'km_reading_confirmed',
                'hours_reading_confirmed',
                'confirm_duplicate',
            ]));
        } catch (ValidationException $exception) {
            return back()
                ->withErrors($exception->errors())
                ->withInput()
                ->with('edit_filling_id', $filling->id);
        }

        return back()->with(
            'success',
            'Abastecimento corrigido. O lançamento original foi cancelado e preservado para auditoria.'
        );
    }

    public function cancelFilling(Request $request, FuelFilling $filling, FuelService $fuelService)
    {
        $context = $this->historyContext(); $this->authorizeFuelPermission('fuel.cancel', $context);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']]);
        try { $fuelService->cancelFilling($filling, $data['reason']); } catch (ValidationException $e) { return back()->withErrors($e->errors())->withInput(); }
        return back()->with('success', 'Abastecimento cancelado e mantido no histórico para auditoria.');
    }

    public function cancelReceipt(Request $request, FuelReceipt $receipt, FuelService $fuelService)
    {
        $context = $this->historyContext(); $this->authorizeFuelPermission('fuel.cancel', $context);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']]);
        try { $fuelService->cancelReceipt($receipt, $data['reason']); } catch (ValidationException $e) { return back()->withErrors($e->errors())->withInput(); }
        return back()->with('success', 'Recebimento cancelado e mantido no histórico para auditoria.');
    }

    private function resolveFuelFleetRelation(Request $request, int $locationId): string
    {
        $allowed = [
            \App\Models\Vehicle::FLEET_RELATION_INTERNAL,
            \App\Models\Vehicle::FLEET_RELATION_AGGREGATED,
            \App\Models\Vehicle::FLEET_RELATION_RENTED,
            'all',
        ];

        $cookieName = 'chm_fleet_relation_'.$locationId;

        if ($request->has('fleet_relation')) {
            $fleetRelation = (string) $request->query('fleet_relation');

            abort_unless(
                in_array($fleetRelation, $allowed, true),
                404
            );

            cookie()->queue(
                cookie(
                    $cookieName,
                    $fleetRelation,
                    0
                )
            );

            return $fleetRelation;
        }

        $saved = $request->cookie($cookieName);

        return in_array($saved, $allowed, true)
            ? $saved
            : \App\Models\Vehicle::FLEET_RELATION_INTERNAL;
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

    private function historyContext(): array
    {
        $context = $this->activeContext();
        if (! $context) abort(422, 'Selecione uma unidade ativa.');
        return $context;
    }

    private function applyPeriod($query, Request $request, string $column): void
    {
        if ($request->filled('start_date')) $query->whereDate($column, '>=', $request->input('start_date'));
        if ($request->filled('end_date')) $query->whereDate($column, '<=', $request->input('end_date'));
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
            ->with(['tank.product', 'product', 'vehicle', 'responsible'])
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
            ->get([
                'id',
                'name',
                'plate',
                'type',
                'current_km',
                'current_hours',
                'km_control_enabled',
                'hours_control_enabled',
                'km_meter_status',
                'hours_meter_status',
                'fleet_relation',
            ]);
    }

    private function missingActiveLocationRedirect()
    {
        return redirect()
            ->route('portal')
            ->with('warning', 'Selecione uma unidade para gerenciar abastecimentos.');
    }
}
