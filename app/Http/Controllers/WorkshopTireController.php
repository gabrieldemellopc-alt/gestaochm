<?php

namespace App\Http\Controllers;

use App\Models\Tire;
use App\Models\TireEntry;
use App\Models\TireEntryItem;
use App\Models\TireMeasurement;
use App\Models\TireRetread;
use App\Services\ActiveContextService;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class WorkshopTireController extends Controller
{
    public function index(Request $request)
    {
        $user =
            auth()->user();
    
        $activeLocation = $this->activeLocation();

        if (! $activeLocation) {
            return $this->missingActiveLocationRedirect();
        }

        $status =
            $request->get('status');
    
        $search =
            $request->get('search');
    
        /*
        |--------------------------------------------------------------------------
        | QUERY PRINCIPAL DOS PNEUS
        |--------------------------------------------------------------------------
        */
    
        $tiresQuery =
            Tire::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('location_id', $activeLocation->id)
                ->notCancelled()
                ->withCurrentTreadContext()

                ->with([
                    'activeInstallation.vehicle',
                ])
                ->withCount('retreads');
    
        /*
        |--------------------------------------------------------------------------
        | FILTRO POR STATUS
        |--------------------------------------------------------------------------
        */
    
        if ($status) {
            $tiresQuery->where(
                'status',
                $status
            );
        }
    
        /*
        |--------------------------------------------------------------------------
        | BUSCA
        |--------------------------------------------------------------------------
        */
    
        if ($search) {
            $tiresQuery->where(function ($query) use ($search) {
                $query
                    ->where(
                        'code',
                        'like',
                        '%' . $search . '%'
                    )
                    ->orWhere(
                        'brand',
                        'like',
                        '%' . $search . '%'
                    )
                    ->orWhere(
                        'model',
                        'like',
                        '%' . $search . '%'
                    )
                    ->orWhere(
                        'size',
                        'like',
                        '%' . $search . '%'
                    );
            });
        }
    
        /*
        |--------------------------------------------------------------------------
        | LISTAGEM PAGINADA
        |--------------------------------------------------------------------------
        */
    
        $tires =
            $tiresQuery
                ->orderByRaw("
                    CASE status
                        WHEN 'available' THEN 1
                        WHEN 'installed' THEN 2
                        WHEN 'maintenance' THEN 3
                        WHEN 'discarded' THEN 4
                        ELSE 5
                    END
                ")
                ->orderBy('code')
                ->paginate(15)
                ->withQueryString();
    
        /*
        |--------------------------------------------------------------------------
        | RESUMO GERAL
        |--------------------------------------------------------------------------
        | O resumo não deve respeitar busca/filtro, para mostrar o total real.
        */
    
        $summary = [
            'total' =>
                Tire::where('tenant_id', $user->tenant_id)
                    ->where('location_id', $activeLocation->id)
                    ->notCancelled()
                    ->count(),
    
            'available' =>
                Tire::where('tenant_id', $user->tenant_id)
                    ->where('location_id', $activeLocation->id)
                    ->notCancelled()
                    ->where('status', 'available')
                    ->count(),
    
            'installed' =>
                Tire::where('tenant_id', $user->tenant_id)
                    ->where('location_id', $activeLocation->id)
                    ->notCancelled()
                    ->where('status', 'installed')
                    ->count(),
    
            'maintenance' =>
                Tire::where('tenant_id', $user->tenant_id)
                    ->where('location_id', $activeLocation->id)
                    ->notCancelled()
                    ->where('status', 'maintenance')
                    ->count(),
    
            'discarded' =>
                Tire::where('tenant_id', $user->tenant_id)
                    ->where('location_id', $activeLocation->id)
                    ->notCancelled()
                    ->where('status', 'discarded')
                    ->count(),
        ];
    
        /*
        |--------------------------------------------------------------------------
        | ÚLTIMAS ENTRADAS
        |--------------------------------------------------------------------------
        */
    
        $entries =
            TireEntry::where('tenant_id', $user->tenant_id)
                ->where('location_id', $activeLocation->id)
                ->withCount('items')
                ->latest('entry_date')
                ->latest('id')
                ->limit(10)
                ->get();
    
        return view(
            'workshop.tires.index',
            compact(
                'tires',
                'summary',
                'entries',
                'status',
                'search'
            )
        );
    }

    public function storeEntry(Request $request)
    {
        $user =
            auth()->user();

        $activeLocation = $this->activeLocation();

        if (! $activeLocation) {
            return $this->missingActiveLocationRedirect();
        }

        $data =
            $request->validate([
                'entry_date' => [
                    'required',
                    'date',
                ],

                'quantity' => [
                    'required',
                    'integer',
                    'min:1',
                    'max:500',
                ],

                'code_prefix' => [
                    'required',
                    'string',
                    'max:30',
                ],

                'brand' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'model' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'size' => [
                    'nullable',
                    'string',
                    'max:50',
                ],

                'initial_tread_depth' => [
                    'nullable',
                    'numeric',
                    'min:0',
                    'max:50',
                ],
                'warning_tread_depth' => [
                    'nullable',
                    'numeric',
                    'min:0',
                    'max:50',
                ],
                
                'critical_tread_depth' => [
                    'nullable',
                    'numeric',
                    'min:0',
                    'max:50',
                ],

                'supplier_name' => [
                    'nullable',
                    'string',
                    'max:150',
                ],

                'invoice_number' => [
                    'nullable',
                    'string',
                    'max:80',
                ],

                'unit_cost' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],

                'notes' => [
                    'nullable',
                    'string',
                ],
            ]);
            
        if (
            ! empty($data['warning_tread_depth'])
            &&
            ! empty($data['critical_tread_depth'])
            &&
            (float) $data['critical_tread_depth'] > (float) $data['warning_tread_depth']
        ) {
            return back()
                ->withErrors([
                    'critical_tread_depth' =>
                        'O limite crítico não pode ser maior que o limite de atenção.',
                ])
                ->withInput();
        }

        DB::transaction(function () use ($data, $user, $activeLocation) {

            $quantity =
                (int) $data['quantity'];

            $unitCost =
                isset($data['unit_cost']) && $data['unit_cost'] !== ''
                    ? (float) $data['unit_cost']
                    : null;

            $entry =
                TireEntry::create([
                    'tenant_id' =>
                        $user->tenant_id,

                    'location_id' =>

                        $activeLocation->id,



                    'entry_date' =>
                        $data['entry_date'],

                    'supplier_name' =>
                        $data['supplier_name'] ?? null,

                    'invoice_number' =>
                        $data['invoice_number'] ?? null,

                    'quantity' =>
                        $quantity,

                    'unit_cost' =>
                        $unitCost,

                    'total_cost' =>
                        $unitCost !== null
                            ? $unitCost * $quantity
                            : null,

                    'brand' =>
                        $data['brand'] ?? null,

                    'model' =>
                        $data['model'] ?? null,

                    'size' =>
                        $data['size'] ?? null,

                    'initial_tread_depth' =>
                        $data['initial_tread_depth'] ?? null,
                    'warning_tread_depth' =>
                        $data['warning_tread_depth'] ?? null,
                    
                    'critical_tread_depth' =>
                        $data['critical_tread_depth'] ?? null,
                    'code_prefix' =>
                        $data['code_prefix'],

                    'notes' =>
                        $data['notes'] ?? null,

                    'created_by' =>
                        $user->id,
                ]);

            $nextNumber =
                $this->nextSequenceForPrefix(
                    $user->tenant_id,
                    $data['code_prefix']
                );

            for ($i = 0; $i < $quantity; $i++) {

                $code =
                    $this->formatTireCode(
                        $data['code_prefix'],
                        $nextNumber + $i
                    );

                $tire =
                    Tire::create([
                        'tenant_id' =>
                            $user->tenant_id,

                        'location_id' =>

                            $activeLocation->id,



                        'entry_id' =>
                            $entry->id,

                        'code' =>
                            $code,

                        'brand' =>
                            $data['brand'] ?? null,

                        'model' =>
                            $data['model'] ?? null,

                        'size' =>
                            $data['size'] ?? null,

                        'initial_tread_depth' =>
                            $data['initial_tread_depth'] ?? null,
                        'warning_tread_depth' =>
                            $data['warning_tread_depth'] ?? 5,
                        
                        'critical_tread_depth' =>
                            $data['critical_tread_depth'] ?? 3,
                        'purchase_date' =>
                            $data['entry_date'],

                        'unit_cost' =>
                            $unitCost,

                        'status' =>
                            'available',

                        'notes' =>
                            $data['notes'] ?? null,
                    ]);

                TireEntryItem::create([
                    'tire_entry_id' =>
                        $entry->id,

                    'tire_id' =>
                        $tire->id,
                ]);
            }
        });

        return back()->with(
            'success',
            'Entrada de pneus registrada com sucesso.'
        );
    }
    
    public function cancelEntry(Request $request, TireEntry $entry)
    {
        if (Gate::denies('cancelTireRecords')) {
            abort(403);
        }

        $user = auth()->user();
        $activeLocation = $this->activeLocation();

        if (! $activeLocation) {
            return $this->missingActiveLocationRedirect();
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        DB::transaction(function () use ($entry, $user, $activeLocation, $data) {
            $lockedEntry = TireEntry::query()
                ->where('id', $entry->id)
                ->where('tenant_id', $user->tenant_id)
                ->where('location_id', $activeLocation->id)
                ->lockForUpdate()
                ->first();

            abort_unless($lockedEntry, 403);

            if ($lockedEntry->cancelled_at) {
                throw ValidationException::withMessages([
                    'reason' => 'Esta entrada de pneus ja esta cancelada.',
                ]);
            }

            $items = TireEntryItem::query()
                ->where('tire_entry_id', $lockedEntry->id)
                ->with('tire')
                ->get();

            $tireIds = $items
                ->pluck('tire_id')
                ->filter()
                ->values();

            $tires = Tire::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('location_id', $activeLocation->id)
                ->whereIn('id', $tireIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                $tire = $tires->get($item->tire_id);

                if (! $tire || (int) $tire->entry_id !== (int) $lockedEntry->id) {
                    throw ValidationException::withMessages([
                        'reason' => 'Nao foi possivel validar todos os pneus desta entrada.',
                    ]);
                }

                if ($tire->cancelled_at || $item->cancelled_at) {
                    throw ValidationException::withMessages([
                        'reason' => 'Esta entrada possui pneu ou item ja cancelado.',
                    ]);
                }

                if ($tire->status !== 'available') {
                    throw ValidationException::withMessages([
                        'reason' => 'Nao e seguro cancelar: o pneu ' . $tire->code . ' nao esta disponivel.',
                    ]);
                }

                if (
                    $tire->installations()->exists()
                    || $tire->allMeasurements()->exists()
                    || $tire->allRetreads()->exists()
                ) {
                    throw ValidationException::withMessages([
                        'reason' => 'Nao e seguro cancelar: o pneu ' . $tire->code . ' possui movimentacao posterior.',
                    ]);
                }
            }

            $before = [
                'entry' => $lockedEntry->toArray(),
                'items' => $items->toArray(),
                'tires' => $tires->values()->toArray(),
            ];

            $cancelData = [
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancel_reason' => $data['reason'],
            ];

            TireEntryItem::query()
                ->where('tire_entry_id', $lockedEntry->id)
                ->update($cancelData);

            Tire::query()
                ->whereIn('id', $tireIds)
                ->update($cancelData);

            $lockedEntry->update($cancelData);

            $afterEntry = $lockedEntry->fresh();
            $afterTires = Tire::query()
                ->whereIn('id', $tireIds)
                ->get(['id', 'code', 'status', 'cancelled_at', 'cancelled_by', 'cancel_reason']);

            app(AuditLogService::class)->cancelled($afterEntry, [
                'tenant_id' => $user->tenant_id,
                'location_id' => $activeLocation->id,
                'module' => 'tires',
                'summary' => 'Entrada de pneus cancelada.',
                'before_data' => $before,
                'after_data' => [
                    'entry' => $afterEntry->toArray(),
                    'tires' => $afterTires->toArray(),
                ],
                'metadata' => [
                    'entry_id' => $lockedEntry->id,
                    'invoice_number' => $lockedEntry->invoice_number,
                    'cancelled_tire_ids' => $tireIds->all(),
                    'cancelled_tire_codes' => $afterTires->pluck('code')->all(),
                ],
                'reason' => $data['reason'],
            ]);
        });

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Entrada de pneus cancelada.',
            ]);
        }

        return back()->with('success', 'Entrada de pneus cancelada.');
    }

    public function history(Tire $tire)
    {
        if ($redirect = $this->ensureTireInActiveContext($tire)) {
            return $redirect;
        }

        $tire->load([
            'entry.items',
            'installations.vehicle',
            'allMeasurements.vehicle',
            'allMeasurements.canceller',
            'allRetreads.canceller',
            'latestMeasurement',
            'latestRetread',
        ])->loadCount('retreads');

        $timeline = collect();

        if (
            $tire->entry
            &&
            $tire->entry->items->contains('tire_id', $tire->id)
        ) {
            $entry = $tire->entry;

            $timeline->push([
                'type' => 'entry',
                'title' => $entry->is_cancelled
                    ? 'Entrada no estoque cancelada'
                    : 'Entrada no estoque',
                'date' => $entry->entry_date,
                'sort_key' => $this->tireHistorySortKey($entry->entry_date, 10, $entry->id),
                'details' => [
                    'Fornecedor' => $entry->supplier_name,
                    'Nota fiscal' => $entry->invoice_number,
                    'Custo unitário' => $tire->unit_cost !== null
                        ? 'R$ ' . number_format((float) $tire->unit_cost, 2, ',', '.')
                        : null,
                ],
                'notes' => $entry->notes,
                'record' => $entry,
                'is_cancelled' => $entry->is_cancelled,
                'cancelled_at' => $entry->cancelled_at,
                'cancel_reason' => $entry->cancel_reason,
            ]);
        }

        foreach ($tire->installations as $installation) {
            $vehicle = $installation->vehicle;
            $vehicleLabel = $vehicle
                ? trim(($vehicle->name ?? '') . ($vehicle->plate ? ' · ' . $vehicle->plate : ''))
                : null;

            $timeline->push([
                'type' => 'installation',
                'title' => 'Instalação em veículo',
                'date' => $installation->installed_at,
                'sort_key' => $this->tireHistorySortKey(
                    $installation->installed_at ?? $installation->created_at,
                    20,
                    $installation->id
                ),
                'details' => [
                    'Veículo' => $vehicleLabel,
                    'Posição' => $installation->position_code,
                    'KM de instalação' => $installation->installed_km !== null
                        ? number_format($installation->installed_km, 0, ',', '.') . ' km'
                        : null,
                ],
                'notes' => null,
            ]);

            if ($installation->removed_at) {
                $timeline->push([
                    'type' => 'removal',
                    'title' => 'Retirada do veículo',
                    'date' => $installation->removed_at,
                    'sort_key' => $this->tireHistorySortKey($installation->removed_at, 30, $installation->id),
                    'details' => [
                        'Veículo' => $vehicleLabel,
                        'Posição' => $installation->position_code,
                        'KM de retirada' => $installation->removed_km !== null
                            ? number_format($installation->removed_km, 0, ',', '.') . ' km'
                            : null,
                        'Motivo' => $installation->removal_reason,
                    ],
                    'notes' => null,
                ]);
            }
        }

        foreach ($tire->allMeasurements as $measurement) {
            $vehicle = $measurement->vehicle;
            $vehicleLabel = $vehicle
                ? trim(($vehicle->name ?? '') . ($vehicle->plate ? ' · ' . $vehicle->plate : ''))
                : null;

            $timeline->push([
                'type' => 'measurement',
                'title' => $measurement->is_cancelled
                    ? 'Medicao de sulco cancelada'
                    : 'Medicao de sulco',
                'date' => $measurement->measured_at,
                'sort_key' => $this->tireHistorySortKey($measurement->measured_at, 40, $measurement->id),
                'details' => [
                    'Veículo' => $vehicleLabel,
                    'Posição' => $measurement->position_code,
                    'KM' => $measurement->vehicle_km !== null
                        ? number_format($measurement->vehicle_km, 0, ',', '.') . ' km'
                        : null,
                    'Sulco mínimo' => $measurement->minimum_tread !== null
                        ? number_format((float) $measurement->minimum_tread, 2, ',', '.') . ' mm'
                        : null,
                ],
                'notes' => $measurement->notes,
                'record' => $measurement,
                'is_cancelled' => $measurement->is_cancelled,
                'cancelled_at' => $measurement->cancelled_at,
                'cancel_reason' => $measurement->cancel_reason,
            ]);
        }

        $activeRetreadNumbers = $tire->allRetreads
            ->whereNull('cancelled_at')
            ->sortBy([
                ['retreaded_at', 'asc'],
                ['id', 'asc'],
            ])
            ->values()
            ->mapWithKeys(fn ($retread, $index) => [$retread->id => $index + 1]);

        $tire->allRetreads
            ->sortBy([
                ['retreaded_at', 'asc'],
                ['id', 'asc'],
            ])
            ->values()
            ->each(function ($retread) use ($timeline, $activeRetreadNumbers) {
                $retreadNumber = $activeRetreadNumbers->get($retread->id);

                $timeline->push([
                    'type' => 'retread',
                    'title' => $retread->is_cancelled
                        ? 'Recapagem cancelada'
                        : 'Recapagem R' . $retreadNumber,
                    'date' => $retread->retreaded_at,
                    'sort_key' => $this->tireHistorySortKey($retread->retreaded_at, 50, $retread->id),
                    'details' => [
                        'Identificacao' => $retreadNumber ? 'R' . $retreadNumber : null,
                        'Novo sulco' => number_format((float) $retread->new_tread_depth, 2, ',', '.') . ' mm',
                        'Referencia anterior' => $retread->previous_tread_reference !== null
                            ? number_format((float) $retread->previous_tread_reference, 2, ',', '.') . ' mm'
                            : null,
                        'Fornecedor' => $retread->provider_name,
                    ],
                    'notes' => $retread->notes,
                    'record' => $retread,
                    'is_cancelled' => $retread->is_cancelled,
                    'cancelled_at' => $retread->cancelled_at,
                    'cancel_reason' => $retread->cancel_reason,
                ]);
            });

        $timeline = $timeline
            ->sortByDesc('sort_key')
            ->values();

        return view(
            'workshop.tires.history',
            compact('tire', 'timeline')
        );
    }

    private function tireHistorySortKey($date, int $priority, int $id): string
    {
        return ($date?->format('Ymd') ?? '00000000')
            . str_pad((string) $priority, 3, '0', STR_PAD_LEFT)
            . str_pad((string) $id, 12, '0', STR_PAD_LEFT);
    }

    public function storeRetread(Request $request, Tire $tire)
    {
        $user = auth()->user();

        if ($redirect = $this->ensureTireInActiveContext($tire)) {
            return $redirect;
        }

        $activeLocation = $this->activeLocation();

        $data = $request->validate([
            'new_tread_depth' => ['required', 'numeric', 'min:0.01', 'max:50'],
            'retreaded_at' => ['required', 'date', 'before_or_equal:today'],
            'provider_name' => ['required', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($data, $tire, $user, $activeLocation) {
            $lockedTire = Tire::query()
                ->where('id', $tire->id)
                ->where('tenant_id', $user->tenant_id)
                ->where('location_id', $activeLocation->id)
                ->lockForUpdate()
                ->first();

            abort_unless($lockedTire, 403);

            if ($lockedTire->status !== 'maintenance') {
                throw ValidationException::withMessages([
                    'retread' => 'Somente pneus em manutencao podem ser recapados.',
                ]);
            }

            if ($lockedTire->activeInstallation()->exists()) {
                throw ValidationException::withMessages([
                    'retread' => 'O pneu precisa estar removido do veiculo antes da recapagem.',
                ]);
            }

            $previousTreadReference = $lockedTire->measurements()
                    ->latest('measured_at')
                    ->latest('id')
                    ->value('minimum_tread')
                ?? $lockedTire->retreads()
                    ->latest('retreaded_at')
                    ->latest('id')
                    ->value('new_tread_depth')
                ?? $lockedTire->initial_tread_depth;

            $retread = $lockedTire->retreads()->create([
                'tenant_id' => $user->tenant_id,
                'retreaded_at' => $data['retreaded_at'],
                'new_tread_depth' => $data['new_tread_depth'],
                'previous_tread_reference' => $previousTreadReference,
                'provider_name' => $data['provider_name'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            $lockedTire->update([
                'status' => 'available',
            ]);

            app(AuditLogService::class)->created($retread, [
                'tenant_id' => $user->tenant_id,
                'location_id' => $activeLocation->id,
                'module' => 'tires',
                'summary' => 'Recapagem registrada para o pneu ' . $lockedTire->code . '.',
                'after_data' => $retread->toArray(),
                'metadata' => [
                    'tire_id' => $lockedTire->id,
                    'tire_code' => $lockedTire->code,
                    'status_after' => 'available',
                ],
            ]);
        });

        return back()->with(
            'success',
            'Recapagem registrada e pneu disponibilizado.'
        );
    }

    public function cancelMeasurement(Request $request, Tire $tire, TireMeasurement $measurement)
    {
        if (Gate::denies('cancelTireRecords')) {
            abort(403);
        }

        if ($redirect = $this->ensureTireInActiveContext($tire)) {
            return $redirect;
        }

        $user = auth()->user();
        $activeLocation = $this->activeLocation();

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        DB::transaction(function () use ($measurement, $tire, $user, $activeLocation, $data) {
            $lockedMeasurement = TireMeasurement::query()
                ->where('id', $measurement->id)
                ->where('tenant_id', $user->tenant_id)
                ->where('tire_id', $tire->id)
                ->lockForUpdate()
                ->first();

            abort_unless($lockedMeasurement, 403);

            if ($lockedMeasurement->cancelled_at) {
                throw ValidationException::withMessages([
                    'reason' => 'Esta medicao de sulco ja esta cancelada.',
                ]);
            }

            $before = $lockedMeasurement->toArray();

            $lockedMeasurement->update([
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancel_reason' => $data['reason'],
            ]);

            $after = $lockedMeasurement->fresh();

            app(AuditLogService::class)->cancelled($after, [
                'tenant_id' => $user->tenant_id,
                'location_id' => $activeLocation?->id,
                'module' => 'tires',
                'summary' => 'Medicao de sulco cancelada para o pneu ' . $tire->code . '.',
                'before_data' => $before,
                'after_data' => $after->toArray(),
                'metadata' => [
                    'tire_id' => $tire->id,
                    'tire_code' => $tire->code,
                    'vehicle_id' => $lockedMeasurement->vehicle_id,
                    'position_code' => $lockedMeasurement->position_code,
                ],
                'reason' => $data['reason'],
            ]);
        });

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Medicao de sulco cancelada.',
            ]);
        }

        return back()->with('success', 'Medicao de sulco cancelada.');
    }

    public function cancelRetread(Request $request, Tire $tire, TireRetread $retread)
    {
        if (Gate::denies('cancelTireRecords')) {
            abort(403);
        }

        if ($redirect = $this->ensureTireInActiveContext($tire)) {
            return $redirect;
        }

        $user = auth()->user();
        $activeLocation = $this->activeLocation();

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        DB::transaction(function () use ($retread, $tire, $user, $activeLocation, $data) {
            $lockedTire = Tire::query()
                ->where('id', $tire->id)
                ->where('tenant_id', $user->tenant_id)
                ->where('location_id', $activeLocation?->id)
                ->lockForUpdate()
                ->first();

            abort_unless($lockedTire, 403);

            $lockedRetread = TireRetread::query()
                ->where('id', $retread->id)
                ->where('tenant_id', $user->tenant_id)
                ->where('tire_id', $lockedTire->id)
                ->lockForUpdate()
                ->first();

            abort_unless($lockedRetread, 403);

            if ($lockedRetread->cancelled_at) {
                throw ValidationException::withMessages([
                    'reason' => 'Esta recapagem ja esta cancelada.',
                ]);
            }

            if ($lockedTire->status !== 'available' || $lockedTire->activeInstallation()->exists()) {
                throw ValidationException::withMessages([
                    'reason' => 'A recapagem so pode ser cancelada enquanto o pneu estiver disponivel e sem instalacao ativa.',
                ]);
            }

            $hasLaterRetread = TireRetread::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('tire_id', $lockedTire->id)
                ->whereNull('cancelled_at')
                ->where('id', '<>', $lockedRetread->id)
                ->where(function ($query) use ($lockedRetread) {
                    $query
                        ->where('retreaded_at', '>', $lockedRetread->retreaded_at)
                        ->orWhere(function ($query) use ($lockedRetread) {
                            $query
                                ->where('retreaded_at', $lockedRetread->retreaded_at)
                                ->where('id', '>', $lockedRetread->id);
                        });
                })
                ->exists();

            if ($hasLaterRetread) {
                throw ValidationException::withMessages([
                    'reason' => 'Nao e seguro cancelar esta recapagem porque existe recapagem posterior.',
                ]);
            }

            $hasLaterMeasurement = TireMeasurement::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('tire_id', $lockedTire->id)
                ->whereNull('cancelled_at')
                ->where('measured_at', '>', $lockedRetread->retreaded_at)
                ->exists();

            if ($hasLaterMeasurement) {
                throw ValidationException::withMessages([
                    'reason' => 'Nao e seguro cancelar esta recapagem porque existe medicao posterior.',
                ]);
            }

            $hasLaterInstallation = $lockedTire->installations()
                ->where('installed_at', '>=', $lockedRetread->retreaded_at)
                ->exists();

            if ($hasLaterInstallation) {
                throw ValidationException::withMessages([
                    'reason' => 'Nao e seguro cancelar esta recapagem porque existe instalacao posterior do pneu.',
                ]);
            }

            $before = $lockedRetread->toArray();

            $lockedRetread->update([
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancel_reason' => $data['reason'],
            ]);

            $after = $lockedRetread->fresh();

            app(AuditLogService::class)->cancelled($after, [
                'tenant_id' => $user->tenant_id,
                'location_id' => $activeLocation?->id,
                'module' => 'tires',
                'summary' => 'Recapagem cancelada para o pneu ' . $lockedTire->code . '.',
                'before_data' => $before,
                'after_data' => $after->toArray(),
                'metadata' => [
                    'tire_id' => $lockedTire->id,
                    'tire_code' => $lockedTire->code,
                ],
                'reason' => $data['reason'],
            ]);
        });

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Recapagem cancelada.',
            ]);
        }

        return back()->with('success', 'Recapagem cancelada.');
    }

    public function update(Request $request, Tire $tire)
    {
        if ($redirect = $this->ensureTireInActiveContext($tire)) {
            return $redirect;
        }

        $user =
            auth()->user();
    
        if (
            $user->id !== 1
            &&
            (int) $tire->tenant_id !== (int) $user->tenant_id
        ) {
            abort(403);
        }
    
        $data =
            $request->validate([
                'brand' => [
                    'nullable',
                    'string',
                    'max:100',
                ],
    
                'model' => [
                    'nullable',
                    'string',
                    'max:100',
                ],
    
                'size' => [
                    'nullable',
                    'string',
                    'max:50',
                ],
    
                'initial_tread_depth' => [
                    'nullable',
                    'numeric',
                    'min:0',
                    'max:50',
                ],
    
                'warning_tread_depth' => [
                    'nullable',
                    'numeric',
                    'min:0',
                    'max:50',
                ],
    
                'critical_tread_depth' => [
                    'nullable',
                    'numeric',
                    'min:0',
                    'max:50',
                ],
    
                'status' => [
                    'required',
                    'string',
                    'in:available,installed,maintenance,discarded',
                ],
    
                'notes' => [
                    'nullable',
                    'string',
                ],
            ]);
    
        if (
            ! empty($data['warning_tread_depth'])
            &&
            ! empty($data['critical_tread_depth'])
            &&
            (float) $data['critical_tread_depth'] > (float) $data['warning_tread_depth']
        ) {
            return back()
                ->withErrors([
                    'critical_tread_depth' =>
                        'O limite crítico não pode ser maior que o limite de atenção.',
                ])
                ->withInput();
        }
    
        /*
        |--------------------------------------------------------------------------
        | SEGURANÇA OPERACIONAL
        |--------------------------------------------------------------------------
        | Se o pneu estiver instalado, não permitimos mudar manualmente para
        | disponível/manutenção/descarte por aqui. A remoção deve ser feita no
        | controle de pneus do veículo para registrar histórico corretamente.
        */
    
        if (
            $tire->status === 'installed'
            &&
            $data['status'] !== 'installed'
        ) {
            return back()
                ->withErrors([
                    'status' =>
                        'Pneu instalado deve ser removido pelo controle de pneus do veículo.',
                ])
                ->withInput();
        }
    
        $tire->update([
            'brand' =>
                $data['brand'] ?? null,
    
            'model' =>
                $data['model'] ?? null,
    
            'size' =>
                $data['size'] ?? null,
    
            'initial_tread_depth' =>
                $data['initial_tread_depth'] ?? null,
    
            'warning_tread_depth' =>
                $data['warning_tread_depth'] ?? null,
    
            'critical_tread_depth' =>
                $data['critical_tread_depth'] ?? null,
    
            'status' =>
                $data['status'],
    
            'notes' =>
                $data['notes'] ?? null,
        ]);
    
        return back()->with(
            'success',
            'Pneu atualizado com sucesso.'
        );
    }

    private function activeLocation()
    {
        return app(ActiveContextService::class)
            ->activeLocation(auth()->user());
    }

    private function ensureTireInActiveContext(Tire $tire)
    {
        $activeLocation = $this->activeLocation();

        if (! $activeLocation) {
            return $this->missingActiveLocationRedirect();
        }

        if (
            (int) $tire->tenant_id !== (int) auth()->user()->tenant_id
            || (int) $tire->location_id !== (int) $activeLocation->id
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

    private function nextSequenceForPrefix(
        int $tenantId,
        string $prefix
    ): int {
        $lastCode =
            Tire::where('tenant_id', $tenantId)
                ->where('code', 'like', $prefix . '-%')
                ->orderByDesc('id')
                ->value('code');

        if (! $lastCode) {
            return 1;
        }

        $number =
            (int) preg_replace(
                '/[^0-9]/',
                '',
                substr($lastCode, strlen($prefix))
            );

        return $number > 0
            ? $number + 1
            : 1;
    }

    private function formatTireCode(
        string $prefix,
        int $number
    ): string {
        return $prefix . '-' . str_pad(
            (string) $number,
            4,
            '0',
            STR_PAD_LEFT
        );
    }
}
