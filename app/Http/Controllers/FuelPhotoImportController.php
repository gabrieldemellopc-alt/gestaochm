<?php

namespace App\Http\Controllers;

use App\Models\FuelFilling;
use App\Models\FuelTank;
use App\Services\ActiveContextService;
use App\Services\FuelService;
use App\Services\Fuel\GoogleVisionFuelSheetService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FuelPhotoImportController extends Controller
{
    public function analyze(
        Request $request,
        GoogleVisionFuelSheetService $vision
    ) {
        abort_unless(
            (int) $request->user()?->id === 1
            || userHasProfile('admin'),
            403
        );

        $context =
            $this->activeContext(
                $request
            );

        $data = $request->validate([
            'image' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:12288',
            ],
        ]);

        try {
            $analysis =
                $vision->analyze(
                    $data['image'],
                    $context
                );

            return response()->json([
                'ok' => true,
                'analysis' => $analysis,
            ]);

        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'ok' => false,

                'message' =>
                    $exception->getMessage(),
            ], 422);
        }
    }


    public function duplicates(
        Request $request,
        FuelService $fuelService
    ) {
        abort_unless(
            (int) $request->user()?->id === 1
            || userHasProfile('admin'),
            403
        );

        $context =
            $this->activeContext(
                $request
            );

        $validated =
            $request->validate([
                'fuel_tank_id' => [
                    'required',
                    'integer',
                ],

                'arla_tank_id' => [
                    'nullable',
                    'integer',
                ],

                'rows' => [
                    'required',
                    'array',
                    'min:1',
                    'max:100',
                ],

                'rows.*.line' => [
                    'required',
                    'integer',
                    'min:1',
                ],

                'rows.*.vehicle_id' => [
                    'required',
                    'integer',
                ],

                'rows.*.filled_at' => [
                    'required',
                    'date',
                ],

                'rows.*.liters' => [
                    'required',
                    'numeric',
                    'gt:0',
                ],

                'rows.*.arla_liters' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],
            ]);

        $fuelTank =
            FuelTank::query()
                ->where(
                    'tenant_id',
                    $context['tenant_id']
                )
                ->where(
                    'division_id',
                    $context['division_id']
                )
                ->where(
                    'location_id',
                    $context['location_id']
                )
                ->where('active', true)
                ->find(
                    $validated['fuel_tank_id']
                );

        if (! $fuelTank) {
            throw ValidationException::withMessages([
                'fuel_tank_id' =>
                    'Tanque de combustível inválido para a unidade ativa.',
            ]);
        }

        $arlaTank = null;

        if (! empty($validated['arla_tank_id'])) {
            $arlaTank =
                FuelTank::query()
                    ->where(
                        'tenant_id',
                        $context['tenant_id']
                    )
                    ->where(
                        'division_id',
                        $context['division_id']
                    )
                    ->where(
                        'location_id',
                        $context['location_id']
                    )
                    ->where('active', true)
                    ->find(
                        $validated['arla_tank_id']
                    );
        }

        $duplicates = [];

        foreach (
            $validated['rows']
            as $row
        ) {
            $duplicate =
                $fuelService
                    ->findProbableDuplicate(
                        $context,
                        [
                            'source' =>
                                FuelFilling::SOURCE_INTERNAL_TANK,

                            'fuel_tank_id' =>
                                $fuelTank->id,

                            'fuel_product_id' =>
                                $fuelTank->fuel_product_id,

                            'vehicle_id' =>
                                (int) $row['vehicle_id'],

                            'filled_at' =>
                                $row['filled_at'],

                            'quantity_liters' =>
                                (float) $row['liters'],
                        ]
                    );

            if ($duplicate) {
                $duplicates[] = [
                    'line' =>
                        (int) $row['line'],

                    'type' =>
                        'fuel',

                    'filling_id' =>
                        $duplicate->id,

                    'filled_at' =>
                        $duplicate->filled_at
                            ->format('d/m/Y H:i'),

                    'quantity_liters' =>
                        (float) $duplicate
                            ->quantity_liters,

                    'vehicle_km' =>
                        $duplicate->vehicle_km !== null
                            ? (float) $duplicate->vehicle_km
                            : null,

                    'vehicle_km' =>
                        $duplicate->vehicle_km !== null
                            ? (float) $duplicate->vehicle_km
                            : null,
                ];
            }

            $arlaLiters =
                (float) (
                    $row['arla_liters']
                    ?? 0
                );

            if (
                $arlaLiters > 0
                && $arlaTank
            ) {
                $arlaDuplicate =
                    $fuelService
                        ->findProbableDuplicate(
                            $context,
                            [
                                'source' =>
                                    FuelFilling::SOURCE_INTERNAL_TANK,

                                'fuel_tank_id' =>
                                    $arlaTank->id,

                                'fuel_product_id' =>
                                    $arlaTank->fuel_product_id,

                                'vehicle_id' =>
                                    (int) $row['vehicle_id'],

                                'filled_at' =>
                                    $row['filled_at'],

                                'quantity_liters' =>
                                    $arlaLiters,
                            ]
                        );

                if ($arlaDuplicate) {
                    $duplicates[] = [
                        'line' =>
                            (int) $row['line'],

                        'type' =>
                            'arla',

                        'filling_id' =>
                            $arlaDuplicate->id,

                        'filled_at' =>
                            $arlaDuplicate->filled_at
                                ->format('d/m/Y H:i'),

                        'quantity_liters' =>
                            (float) $arlaDuplicate
                                ->quantity_liters,
                    ];
                }
            }
        }

        return response()->json([
            'ok' =>
                true,

            'duplicates' =>
                $duplicates,
        ]);
    }


    public function store(
        Request $request,
        FuelService $fuelService
    ) {
        abort_unless(
            (int) $request->user()?->id === 1
            || userHasProfile('admin'),
            403
        );

        $context =
            $this->activeContext(
                $request
            );

        $validated =
            $request->validate([
                'confirm_duplicates' => [
                    'nullable',
                    'boolean',
                ],

                'fuel_tank_id' => [
                    'required',
                    'integer',
                ],

                'arla_tank_id' => [
                    'nullable',
                    'integer',
                ],

                'rows' => [
                    'required',
                    'array',
                    'min:1',
                    'max:100',
                ],

                'rows.*.vehicle_id' => [
                    'required',
                    'integer',
                ],

                'rows.*.filled_at' => [
                    'required',
                    'date',
                ],

                'rows.*.vehicle_km' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],

                'rows.*.liters' => [
                    'required',
                    'numeric',
                    'gt:0',
                ],

                'rows.*.arla_liters' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],

                'rows.*.line' => [
                    'required',
                    'integer',
                    'min:1',
                ],

                'rows.*.km_reading_confirmed' => [
                    'nullable',
                    'boolean',
                ],
            ]);

        $fuelTank =
            FuelTank::query()
                ->where(
                    'tenant_id',
                    $context['tenant_id']
                )
                ->where(
                    'division_id',
                    $context['division_id']
                )
                ->where(
                    'location_id',
                    $context['location_id']
                )
                ->where('active', true)
                ->with('product')
                ->find(
                    $validated['fuel_tank_id']
                );

        if (! $fuelTank) {
            throw ValidationException::withMessages([
                'fuel_tank_id' =>
                    'Tanque de combustível inválido para a unidade ativa.',
            ]);
        }

        if (
            strtolower(
                (string) $fuelTank->product?->slug
            ) === 'arla'
        ) {
            throw ValidationException::withMessages([
                'fuel_tank_id' =>
                    'O tanque principal não pode ser um tanque de ARLA.',
            ]);
        }

        $totalFuel =
            collect(
                $validated['rows']
            )->sum(
                fn ($row) =>
                    (float) $row['liters']
            );

        if (
            $totalFuel
            > (float) $fuelTank->current_balance_liters
        ) {
            throw ValidationException::withMessages([
                'fuel_tank_id' =>
                    'O tanque de combustível não possui saldo suficiente para a ficha.',
            ]);
        }

        $totalArla =
            collect(
                $validated['rows']
            )->sum(
                fn ($row) =>
                    (float) (
                        $row['arla_liters']
                        ?? 0
                    )
            );

        $arlaTank = null;

        if ($totalArla > 0) {
            if (
                empty(
                    $validated['arla_tank_id']
                )
            ) {
                throw ValidationException::withMessages([
                    'arla_tank_id' =>
                        'Selecione o tanque de ARLA.',
                ]);
            }

            $arlaTank =
                FuelTank::query()
                    ->where(
                        'tenant_id',
                        $context['tenant_id']
                    )
                    ->where(
                        'division_id',
                        $context['division_id']
                    )
                    ->where(
                        'location_id',
                        $context['location_id']
                    )
                    ->where('active', true)
                    ->with('product')
                    ->find(
                        $validated['arla_tank_id']
                    );

            if (
                ! $arlaTank
                || strtolower(
                    (string) $arlaTank->product?->slug
                ) !== 'arla'
            ) {
                throw ValidationException::withMessages([
                    'arla_tank_id' =>
                        'Tanque de ARLA inválido para a unidade ativa.',
                ]);
            }

            if (
                $totalArla
                > (float) $arlaTank->current_balance_liters
            ) {
                throw ValidationException::withMessages([
                    'arla_tank_id' =>
                        'O tanque de ARLA não possui saldo suficiente para a ficha.',
                ]);
            }
        }

        $fillings = [];

        foreach (
            $validated['rows']
            as $row
        ) {
            $line =
                (int) $row['line'];

            $baseNote =
                'Importação assistida via ficha manual / Google Vision; '
                .'linha '
                .$line
                .'; leitura revisada pelo operador.';

            $fillings[] = [
                'source' =>
                    FuelFilling::SOURCE_INTERNAL_TANK,

                'fuel_tank_id' =>
                    $fuelTank->id,

                'fuel_product_id' =>
                    $fuelTank->fuel_product_id,

                'vehicle_id' =>
                    (int) $row['vehicle_id'],

                'filled_at' =>
                    $row['filled_at'],

                'vehicle_km' =>
                    $row['vehicle_km']
                    ?? null,

                'quantity_liters' =>
                    (float) $row['liters'],

                'responsible_user_id' =>
                    $request->user()->id,

                'km_reading_confirmed' =>
                    ! empty(
                        $row['km_reading_confirmed']
                    ),

                'notes' =>
                    $baseNote,


                'confirm_duplicate' =>
                    ! empty(
                        $validated['confirm_duplicates']
                    ),
            ];

            $arla =
                (float) (
                    $row['arla_liters']
                    ?? 0
                );

            if (
                $arla > 0
                && $arlaTank
            ) {
                $fillings[] = [
                    'source' =>
                        FuelFilling::SOURCE_INTERNAL_TANK,

                    'fuel_tank_id' =>
                        $arlaTank->id,

                    'fuel_product_id' =>
                        $arlaTank->fuel_product_id,

                    'vehicle_id' =>
                        (int) $row['vehicle_id'],

                    'filled_at' =>
                        $row['filled_at'],

                    /*
                     * O ARLA guarda o mesmo hodômetro como
                     * referência histórica, mas não atualiza
                     * novamente o contador do veículo.
                     */
                    'vehicle_km' =>
                        $row['vehicle_km']
                        ?? null,

                    'skip_vehicle_counter_update' =>
                        true,

                    'quantity_liters' =>
                        $arla,

                    'responsible_user_id' =>
                        $request->user()->id,

                    'notes' =>
                        'Importação assistida via ficha manual / Google Vision; '
                        .'linha '
                        .$line
                        .'; ARLA; leitura revisada pelo operador.',


                    'confirm_duplicate' =>
                        ! empty(
                            $validated['confirm_duplicates']
                        ),
                ];
            }
        }

        try {
            $created =
                $fuelService
                    ->registerFillingBatch(
                        $fillings
                    );

            return response()->json([
                'ok' => true,

                'message' =>
                    count($validated['rows'])
                    .' abastecimento(s) da ficha lançado(s) com sucesso.',

                'fuel_fillings' =>
                    count(
                        $validated['rows']
                    ),

                'arla_fillings' =>
                    collect(
                        $validated['rows']
                    )
                    ->filter(
                        fn ($row) =>
                            (float) (
                                $row['arla_liters']
                                ?? 0
                            ) > 0
                    )
                    ->count(),

                'records_created' =>
                    count($created),
            ]);

        } catch (
            ValidationException $exception
        ) {
            return response()->json([
                'ok' => false,

                'message' =>
                    collect(
                        $exception->errors()
                    )
                    ->flatten()
                    ->implode(' '),

                'errors' =>
                    $exception->errors(),
            ], 422);
        }
    }


    private function activeContext(
        Request $request
    ): array {
        $user = $request->user();

        abort_unless($user, 401);

        $service =
            app(
                ActiveContextService::class
            );

        $division =
            $service->activeDivision(
                $user
            );

        $location =
            $service->activeLocation(
                $user
            );

        abort_unless(
            $division && $location,
            422,
            'Selecione uma unidade ativa.'
        );

        return [
            'user' => $user,

            'tenant_id' =>
                $user->tenant_id,

            'division_id' =>
                $division->id,

            'location_id' =>
                $location->id,

            'division' => $division,
            'location' => $location,
        ];
    }
}
