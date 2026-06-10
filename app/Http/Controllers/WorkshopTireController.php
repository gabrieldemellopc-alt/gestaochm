<?php

namespace App\Http\Controllers;

use App\Models\Tire;
use App\Models\TireEntry;
use App\Models\TireEntryItem;
use App\Services\ActiveContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                    ->count(),
    
            'available' =>
                Tire::where('tenant_id', $user->tenant_id)
                    ->where('location_id', $activeLocation->id)
                    ->where('status', 'available')
                    ->count(),
    
            'installed' =>
                Tire::where('tenant_id', $user->tenant_id)
                    ->where('location_id', $activeLocation->id)
                    ->where('status', 'installed')
                    ->count(),
    
            'maintenance' =>
                Tire::where('tenant_id', $user->tenant_id)
                    ->where('location_id', $activeLocation->id)
                    ->where('status', 'maintenance')
                    ->count(),
    
            'discarded' =>
                Tire::where('tenant_id', $user->tenant_id)
                    ->where('location_id', $activeLocation->id)
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
    
    public function history(Tire $tire)
    {
        if ($redirect = $this->ensureTireInActiveContext($tire)) {
            return $redirect;
        }

        $tire->load([
            'entry.items',
            'installations.vehicle',
            'measurements.vehicle',
            'retreads',
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
                'title' => 'Entrada no estoque',
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

        foreach ($tire->measurements as $measurement) {
            $vehicle = $measurement->vehicle;
            $vehicleLabel = $vehicle
                ? trim(($vehicle->name ?? '') . ($vehicle->plate ? ' · ' . $vehicle->plate : ''))
                : null;

            $timeline->push([
                'type' => 'measurement',
                'title' => 'Medição de sulco',
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
            ]);
        }

        $tire->retreads
            ->sortBy([
                ['retreaded_at', 'asc'],
                ['id', 'asc'],
            ])
            ->values()
            ->each(function ($retread, $index) use ($timeline) {
                $retreadNumber = $index + 1;

                $timeline->push([
                    'type' => 'retread',
                    'title' => 'Recapagem R' . $retreadNumber,
                    'date' => $retread->retreaded_at,
                    'sort_key' => $this->tireHistorySortKey($retread->retreaded_at, 50, $retread->id),
                    'details' => [
                        'Novo sulco' => number_format((float) $retread->new_tread_depth, 2, ',', '.') . ' mm',
                        'Referência anterior' => $retread->previous_tread_reference !== null
                            ? number_format((float) $retread->previous_tread_reference, 2, ',', '.') . ' mm'
                            : null,
                        'Fornecedor' => $retread->provider_name,
                    ],
                    'notes' => $retread->notes,
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

            $lockedTire->retreads()->create([
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
        });

        return back()->with(
            'success',
            'Recapagem registrada e pneu disponibilizado.'
        );
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
