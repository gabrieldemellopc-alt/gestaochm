<?php

namespace App\Http\Controllers;

use App\Models\FuelFilling;
use App\Models\FuelDailyCheck;
use App\Models\FuelTank;
use App\Models\Vehicle;
use App\Services\ActiveContextService;
use App\Services\FuelService;
use App\Services\Permissions\ProfilePermissionService;
use App\Services\Fuel\GoogleVisionFuelSheetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class FuelPhotoImportController extends Controller
{
    public function index(
        Request $request
    ) {
        $context =
            $this->activeContext(
                $request
            );

        $this->authorizePhotoImport(
            $context
        );

        /*
         * Mantém exatamente o mesmo conjunto de dados
         * utilizado pela importação via foto dentro de /fuel.
         */
        $vehicles =
            Vehicle::query()
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

        $tanks =
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
                ->with('product')
                ->orderByDesc('active')
                ->orderBy('name')
                ->get();

        return view(
            'fuel.photo-import.index',
            [
                'activeDivision' =>
                    $context['division'],

                'activeLocation' =>
                    $context['location'],

                'vehicles' =>
                    $vehicles,

                'tanks' =>
                    $tanks,
            ]
        );
    }


    public function analyze(
        Request $request,
        GoogleVisionFuelSheetService $vision
    ) {
        $context =
            $this->activeContext(
                $request
            );

        $this->authorizePhotoImport(
            $context
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
        $context =
            $this->activeContext(
                $request
            );

        $this->authorizePhotoImport(
            $context
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
        $context =
            $this->activeContext(
                $request
            );

        $this->authorizePhotoImport(
            $context
        );

        /*
         * O lançamento final chega como multipart/form-data
         * para que a ficha original possa viajar junto.
         * O restante do payload permanece JSON.
         */
        if ($request->filled('payload')) {
            $decodedPayload =
                json_decode(
                    (string) $request->input('payload'),
                    true
                );

            if (! is_array($decodedPayload)) {
                throw ValidationException::withMessages([
                    'payload' =>
                        'Os dados da ficha são inválidos.',
                ]);
            }

            $request->merge(
                $decodedPayload
            );
        }

        $validated =
            $request->validate([
                'source_file' => [
                    'required',
                    'file',
                    'mimes:jpg,jpeg,png,pdf',
                    'max:12288',
                ],

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

            /*
             * Só arquivamos a ficha depois que o lote inteiro
             * tiver sido registrado com sucesso.
             *
             * Se o arquivo falhar, não desfazemos abastecimentos
             * válidos nem induzimos o operador a relançar o lote.
             */
            $archiveFileId = null;
            $archiveWarning = null;

            try {
                $operationDate =
                    Carbon::parse(
                        $validated['rows'][0]['filled_at']
                    )->toDateString();

                $archiveFileId =
                    $this->archiveSourceFile(
                        $request,
                        $context,
                        $operationDate
                    );

            } catch (\Throwable $archiveException) {
                report(
                    $archiveException
                );

                $archiveWarning =
                    'Os abastecimentos foram lançados, '
                    .'mas não foi possível arquivar automaticamente '
                    .'a ficha original. Anexe-a pelo Arquivo diário.';
            }

            return response()->json([
                'ok' => true,

                'message' =>
                    count($validated['rows'])
                    .' abastecimento(s) da ficha lançado(s) com sucesso.',

                'archive_file_id' =>
                    $archiveFileId,

                'archive_warning' =>
                    $archiveWarning,

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


    private function archiveSourceFile(
        Request $request,
        array $context,
        string $operationDate
    ): ?int {
        $file =
            $request->file(
                'source_file'
            );

        if (! $file) {
            return null;
        }

        $check =
            FuelDailyCheck::query()
                ->firstOrCreate(
                    [
                        'tenant_id' =>
                            $context['tenant_id'],

                        'division_id' =>
                            $context['division_id'],

                        'location_id' =>
                            $context['location_id'],

                        'operation_date' =>
                            $operationDate,
                    ],
                    [
                        'status' =>
                            'pending',
                    ]
                );

        $extension =
            strtolower(
                $file->getClientOriginalExtension()
                ?: $file->extension()
                ?: 'jpg'
            );

        $path =
            'protected/fuel-daily-checks/'
            .$check->tenant_id.'/'
            .$check->location_id.'/'
            .$check->id.'/'
            .Str::uuid().'.'.$extension;

        Storage::disk('local')
            ->putFileAs(
                dirname($path),
                $file,
                basename($path)
            );

        $stored =
            $check->files()->create([
                'disk' =>
                    'local',

                'path' =>
                    $path,

                'original_name' =>
                    $file->getClientOriginalName(),

                'mime_type' =>
                    $file->getMimeType(),

                'size_bytes' =>
                    Storage::disk('local')
                        ->size($path),

                'source' =>
                    'photo_import',

                'uploaded_by' =>
                    $context['user']->id,
            ]);

        return $stored->id;
    }


    private function authorizePhotoImport(
        array $context
    ): void {
        $user =
            $context['user'];

        $allowed =
            (int) $user->id === 1
            || userHasProfile('admin')
            || app(
                ProfilePermissionService::class
            )->allows(
                $user,
                'fuel.fill_internal',
                [
                    'tenant_id' =>
                        $context['tenant_id'],

                    'division_id' =>
                        $context['division_id'],

                    'location_id' =>
                        $context['location_id'],

                    'module' =>
                        'fleet',
                ]
            );

        abort_unless(
            $allowed,
            403
        );
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
