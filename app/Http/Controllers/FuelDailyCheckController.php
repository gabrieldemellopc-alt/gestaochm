<?php

namespace App\Http\Controllers;

use App\Models\FuelDailyCheck;
use App\Models\FuelDailyCheckFile;
use App\Models\FuelDailyCheckUploadToken;
use App\Models\FuelFilling;
use App\Models\FuelReceipt;
use App\Services\ActiveContextService;
use App\Services\Permissions\ProfilePermissionService;
use App\Services\SupplierResolverService;
use App\Services\SupplierSnapshotService;
use Carbon\Carbon;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FuelDailyCheckController extends Controller
{
    public function index(Request $request)
    {
        $context = $this->context();
        $this->authorizeFuel($context);

        $date = $request->filled('date')
            ? Carbon::parse($request->input('date'))->startOfDay()
            : today();

        $month = $request->filled('month')
            ? Carbon::createFromFormat(
                'Y-m',
                $request->input('month')
            )->startOfMonth()
            : $date->copy()->startOfMonth();

        /*
         * Mantemos um registro diário único por contexto/data.
         * Ele agora funciona como o "dossiê" documental daquele dia.
         */
        $check = FuelDailyCheck::query()->firstOrCreate(
            [
                'tenant_id' => $context['tenant_id'],
                'division_id' => $context['division_id'],
                'location_id' => $context['location_id'],
                'operation_date' => $date->toDateString(),
            ],
            [
                'status' => 'pending',
            ]
        );

        $check->load([
            'files' => fn ($query) =>
                $query
                    ->with([
                        'receipts' => fn ($receiptQuery) =>
                            $receiptQuery
                                ->with('tank:id,name')
                                ->orderBy('received_at')
                                ->orderBy('fuel_receipts.id'),
                    ])
                    ->latest(),
            'checker',
        ]);

        /*
         * Recebimentos disponíveis para vínculo documental.
         *
         * A seleção final também é revalidada no backend,
         * portanto os IDs enviados pelo navegador nunca são
         * considerados confiáveis por si só.
         */
        $receiptCandidates = FuelReceipt::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division_id'])
            ->where('location_id', $context['location_id'])
            ->whereNull('cancelled_at')
            ->whereDoesntHave('invoiceFiles')
            ->with('tank:id,name')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $dayReceipts = FuelReceipt::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division_id'])
            ->where('location_id', $context['location_id'])
            ->whereDate(
                'received_at',
                $date->toDateString()
            )
            ->whereNull('cancelled_at')
            ->with([
                'tank:id,name',
                'invoiceFiles',
            ])
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->get();

        $summary = $this->summary(
            $context,
            $date->toDateString()
        );

        $fillings = FuelFilling::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division_id'])
            ->where('location_id', $context['location_id'])
            ->whereDate('filled_at', $date->toDateString())
            ->whereNull('cancelled_at')
            ->with([
                'vehicle',
                'product',
            ])
            ->orderBy('filled_at')
            ->orderBy('id')
            ->get();

        $vehicleCount = $fillings
            ->pluck('vehicle_id')
            ->filter()
            ->unique()
            ->count();

        /*
         * Resumo dos lançamentos do mês para pintar o calendário.
         */
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();

        $monthFillings = FuelFilling::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division_id'])
            ->where('location_id', $context['location_id'])
            ->whereBetween(
                'filled_at',
                [
                    $monthStart->copy()->startOfDay(),
                    $monthEnd->copy()->endOfDay(),
                ]
            )
            ->whereNull('cancelled_at')
            ->selectRaw(
                'DATE(filled_at) as operation_day, '
                .'COUNT(*) as fillings_count, '
                .'SUM(quantity_liters) as liters'
            )
            ->groupByRaw('DATE(filled_at)')
            ->get()
            ->keyBy('operation_day');

        $monthChecks = FuelDailyCheck::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division_id'])
            ->where('location_id', $context['location_id'])
            ->whereBetween(
                'operation_date',
                [
                    $monthStart->toDateString(),
                    $monthEnd->toDateString(),
                ]
            )
            ->withCount('files')
            ->get()
            ->keyBy(
                fn ($item) =>
                    $item->operation_date->format('Y-m-d')
            );

        /*
         * Grade completa domingo -> sábado.
         */
        $calendarStart = $monthStart
            ->copy()
            ->startOfWeek(Carbon::SUNDAY);

        $calendarEnd = $monthEnd
            ->copy()
            ->endOfWeek(Carbon::SATURDAY);

        $calendarDays = collect();

        for (
            $cursor = $calendarStart->copy();
            $cursor->lte($calendarEnd);
            $cursor->addDay()
        ) {
            $key = $cursor->format('Y-m-d');

            $dayFillings =
                $monthFillings->get($key);

            $dayCheck =
                $monthChecks->get($key);

            $calendarDays->push([
                'date' => $cursor->copy(),
                'in_month' =>
                    $cursor->month === $month->month,
                'selected' =>
                    $cursor->isSameDay($date),
                'fillings_count' =>
                    (int) (
                        $dayFillings?->fillings_count
                        ?? 0
                    ),
                'liters' =>
                    round(
                        (float) (
                            $dayFillings?->liters
                            ?? 0
                        ),
                        3
                    ),
                'files_count' =>
                    (int) (
                        $dayCheck?->files_count
                        ?? 0
                    ),
            ]);
        }

        $calendarWeeks =
            $calendarDays->chunk(7);

        return view(
            'fuel.daily-checks.index',
            compact(
                'check',
                'summary',
                'fillings',
                'vehicleCount',
                'date',
                'month',
                'calendarWeeks',
                'receiptCandidates',
            'dayReceipts'
            )
        );
    }


    public function store(Request $request)
    {
        $context = $this->context();
        $this->authorizeFuel($context);

        $data = $request->validate([
            'operation_date' => ['required', 'date'],
            'manual' => ['nullable', 'array'],
            'manual.*' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'files' => ['nullable', 'array', 'max:5'],
            'files.*' => [
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'max:12288',
            ],
        ]);

        $date = Carbon::parse(
            $data['operation_date']
        )->toDateString();

        $summary = $this->summary($context, $date);
        $signature = $this->signature($context, $date);

        if ($summary->isEmpty()) {
            throw ValidationException::withMessages([
                'operation_date' =>
                    'Não existem abastecimentos válidos nessa data.',
            ]);
        }

        $check = DB::transaction(function () use (
            $context,
            $data,
            $date,
            $summary,
            $signature,
            $request
        ) {
            $check = FuelDailyCheck::query()
                ->where('tenant_id', $context['tenant_id'])
                ->where('division_id', $context['division_id'])
                ->where('location_id', $context['location_id'])
                ->whereDate('operation_date', $date)
                ->lockForUpdate()
                ->firstOrFail();

            $check->items()->delete();

            $systemTotal = 0;
            $manualTotal = 0;
            $hasDivergence = false;

            foreach ($summary as $row) {
                $key =
                    $row['source'].'|'.$row['fuel_product_id'];

                $manual = data_get(
                    $data,
                    'manual.'.$key
                );

                if ($manual === null || $manual === '') {
                    throw ValidationException::withMessages([
                        'manual.'.$key =>
                            'Informe o total anotado na folha para '
                            .$row['product_name'].'.',
                    ]);
                }

                $manual = round((float) $manual, 3);
                $system = round(
                    (float) $row['system_liters'],
                    3
                );

                $difference = round(
                    $manual - $system,
                    3
                );

                if (abs($difference) > 0.001) {
                    $hasDivergence = true;
                }

                $check->items()->create([
                    'source' => $row['source'],
                    'fuel_product_id' =>
                        $row['fuel_product_id'],
                    'product_name' =>
                        $row['product_name'],
                    'fillings_count' =>
                        $row['fillings_count'],
                    'system_liters' => $system,
                    'manual_liters' => $manual,
                    'difference_liters' =>
                        $difference,
                ]);

                $systemTotal += $system;
                $manualTotal += $manual;
            }

            $differenceTotal = round(
                $manualTotal - $systemTotal,
                3
            );

            $check->update([
                'status' =>
                    $hasDivergence
                        ? 'divergent'
                        : 'checked',
                'system_snapshot' =>
                    $summary->values()->all(),
                'system_signature' =>
                    $signature,
                'system_total_liters' =>
                    round($systemTotal, 3),
                'manual_total_liters' =>
                    round($manualTotal, 3),
                'difference_liters' =>
                    $differenceTotal,
                'checked_by' =>
                    $context['user']->id,
                'checked_at' => now(),
                'notes' =>
                    $data['notes'] ?? null,
            ]);

            foreach ($request->file('files', []) as $file) {
                $this->storeFile(
                    $check,
                    $file,
                    'web',
                    $context['user']->id
                );
            }

            return $check;
        });

        return redirect()
            ->route(
                'fuel.daily-check.index',
                ['date' => $date]
            )
            ->with(
                'success',
                $check->status === 'divergent'
                    ? 'Conferência salva com divergência.'
                    : 'Conferência concluída.'
            );
    }

    public function uploadFiles(Request $request)
    {
        $context = $this->context();
        $this->authorizeFuel($context);

        $data = $request->validate([
            'operation_date' => [
                'required',
                'date',
            ],
            'document_type' => [
                'nullable',
                'string',
                'in:fuel_invoice,fuel_sheet,other',
            ],
            'document_date' => [
                'nullable',
                'date',
            ],
            'invoice_number' => [
                'nullable',
                'string',
                'max:120',
            ],
            'supplier_id' => [
                'nullable',
                'integer',
            ],
            'supplier_name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'supplier_document' => [
                'nullable',
                'string',
                'max:20',
            ],
            'supplier_resolution_action' => [
                'nullable',
                'string',
                'in:enrich_existing,create_new,use_existing',
            ],
            'supplier_candidate_id' => [
                'nullable',
                'integer',
            ],
            'receipt_ids' => [
                'nullable',
                'array',
            ],
            'receipt_ids.*' => [
                'integer',
                'distinct',
            ],
            'files' => [
                'required',
                'array',
                'min:1',
                'max:5',
            ],
            'files.*' => [
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'max:12288',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ]);

        $date = Carbon::parse(
            $data['operation_date']
        )->toDateString();

        /*
         * Compatibilidade temporária com o formulário antigo:
         * até a Blade nova entrar, anexos sem tipo explícito
         * continuam aceitos e passam a ser classificados como
         * "other".
         */
        $documentType =
            $data['document_type'] ?? 'other';

        $documentDate =
            isset($data['document_date'])
                ? Carbon::parse(
                    $data['document_date']
                )->toDateString()
                : $date;

        $invoiceNumber = filled(
            $data['invoice_number'] ?? null
        )
            ? trim((string) $data['invoice_number'])
            : null;

        $receiptIds = collect(
            $data['receipt_ids'] ?? []
        )
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($documentType === 'fuel_invoice') {
            $errors = [];

            if (
                count(
                    $request->file('files', [])
                ) !== 1
            ) {
                $errors['files'] =
                    'A nota fiscal deve possuir exatamente um arquivo.';
            }

            if (
                empty(
                    $data['document_date']
                    ?? null
                )
            ) {
                $errors['document_date'] =
                    'Informe a data de emissão da nota fiscal.';
            }

            if (!$invoiceNumber) {
                $errors['invoice_number'] =
                    'Informe o número da nota fiscal.';
            }

            if (
                !filled(
                    $data['supplier_name']
                    ?? null
                )
            ) {
                $errors['supplier_name'] =
                    'Informe o fornecedor da nota fiscal.';
            }

            if ($receiptIds->isEmpty()) {
                $errors['receipt_ids'] =
                    'Selecione pelo menos um recebimento vinculado à nota fiscal.';
            }

            if ($errors) {
                throw ValidationException::withMessages(
                    $errors
                );
            }
        }

        $check = FuelDailyCheck::query()->firstOrCreate(
            [
                'tenant_id' =>
                    $context['tenant_id'],
                'division_id' =>
                    $context['division_id'],
                'location_id' =>
                    $context['location_id'],
                'operation_date' =>
                    $date,
            ],
            [
                'status' =>
                    'pending',
            ]
        );

        $storedPaths = [];

        try {
            DB::transaction(function () use (
                $request,
                $data,
                $context,
                $check,
                $documentType,
                $documentDate,
                $invoiceNumber,
                $receiptIds,
                &$storedPaths
            ) {
                $validReceiptIds = collect();

                $supplierSnapshot = [
                    'supplier_id' => null,
                    'supplier_name' => null,
                    'supplier_document' => null,
                ];

                if ($documentType === 'fuel_invoice') {
                    $validReceiptIds =
                        FuelReceipt::query()
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
                            ->whereNull('cancelled_at')
                            ->whereDoesntHave(
                                'invoiceFiles'
                            )
                            ->whereIn(
                                'id',
                                $receiptIds->all()
                            )
                            ->lockForUpdate()
                            ->pluck('id')
                            ->map(
                                fn ($id) => (int) $id
                            )
                            ->values();

                    if (
                        $validReceiptIds->count()
                        !== $receiptIds->count()
                    ) {
                        throw ValidationException::withMessages([
                            'receipt_ids' =>
                                'Um ou mais recebimentos selecionados não pertencem à unidade ativa ou já possuem nota fiscal vinculada.',
                        ]);
                    }

                    $supplier = app(
                        SupplierResolverService::class
                    )->resolve(
                        $context['tenant_id'],
                        isset($data['supplier_id'])
                            ? (int) $data['supplier_id']
                            : null,
                        $data['supplier_name']
                            ?? null,
                        $data['supplier_document']
                            ?? null,
                        $data['supplier_resolution_action']
                            ?? null,
                        isset($data['supplier_candidate_id'])
                            ? (int) $data['supplier_candidate_id']
                            : null
                    );

                    $supplierSnapshot = app(
                        SupplierSnapshotService::class
                    )->fromResolvedSupplier(
                        $supplier,
                        $data['supplier_name']
                            ?? null
                    );
                }

                foreach (
                    $request->file('files', [])
                    as $file
                ) {
                    $storedFile =
                        $this->storeFile(
                            $check,
                            $file,
                            'manual_upload',
                            $context['user']->id,
                            [
                                'document_type' =>
                                    $documentType,
                                'document_date' =>
                                    $documentDate,
                                'invoice_number' =>
                                    $documentType
                                        === 'fuel_invoice'
                                            ? $invoiceNumber
                                            : null,
                                'supplier_id' =>
                                    $documentType
                                        === 'fuel_invoice'
                                            ? $supplierSnapshot[
                                                'supplier_id'
                                            ]
                                            : null,
                                'supplier_name' =>
                                    $documentType
                                        === 'fuel_invoice'
                                            ? $supplierSnapshot[
                                                'supplier_name'
                                            ]
                                            : null,
                                'supplier_document' =>
                                    $documentType
                                        === 'fuel_invoice'
                                            ? $supplierSnapshot[
                                                'supplier_document'
                                            ]
                                            : null,
                            ]
                        );

                    $storedPaths[] = [
                        'disk' =>
                            $storedFile->disk
                            ?: 'local',
                        'path' =>
                            $storedFile->path,
                    ];

                    if (
                        $documentType
                        === 'fuel_invoice'
                    ) {
                        $storedFile
                            ->receipts()
                            ->sync(
                                $validReceiptIds->all()
                            );
                    }
                }

                if (
                    array_key_exists(
                        'notes',
                        $data
                    )
                ) {
                    $check->update([
                        'notes' =>
                            $data['notes'],
                    ]);
                }
            });
        } catch (\Throwable $e) {
            foreach ($storedPaths as $storedPath) {
                if (
                    empty($storedPath['path'])
                ) {
                    continue;
                }

                $disk = Storage::disk(
                    $storedPath['disk']
                    ?: 'local'
                );

                if (
                    $disk->exists(
                        $storedPath['path']
                    )
                ) {
                    $disk->delete(
                        $storedPath['path']
                    );
                }
            }

            throw $e;
        }

        return redirect()
            ->route(
                'fuel.daily-check.index',
                [
                    'date' => $date,
                    'month' =>
                        Carbon::parse($date)
                            ->format('Y-m'),
                ]
            )
            ->with(
                'success',
                'Documento arquivado com sucesso.'
            );
    }


    public function searchReceiptCandidates(Request $request)
    {
        $context = $this->context();
        $this->authorizeFuel($context);

        $data = $request->validate([
            'date' => [
                'required',
                'date',
            ],
        ]);

        $date = Carbon::parse(
            $data['date']
        )->toDateString();

        $receipts = FuelReceipt::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division_id'])
            ->where('location_id', $context['location_id'])
            ->whereNull('cancelled_at')
            ->whereDoesntHave('invoiceFiles')
            ->whereDate('received_at', $date)
            ->with('tank:id,name')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn ($receipt) => [
                'id' =>
                    (int) $receipt->id,

                'received_at' =>
                    $receipt->received_at
                        ?->format('d/m/Y H:i'),

                'tank' =>
                    $receipt->tank?->name
                    ?? 'Tanque',

                'quantity_liters' =>
                    number_format(
                        (float) $receipt->quantity_liters,
                        3,
                        ',',
                        '.'
                    ),

                'supplier_name' =>
                    $receipt->supplier_name,

                'invoice_number' =>
                    $receipt->invoice_number,
            ])
            ->values();

        return response()->json([
            'date' => $date,
            'count' => $receipts->count(),
            'receipts' => $receipts,
        ]);
    }

    public function createUploadToken(
        FuelDailyCheck $check
    ) {
        $context = $this->context();
        $this->authorizeFuel($context);
        $this->ensureCheck($check, $context);

        $plain = (string) Str::uuid();

        $token = FuelDailyCheckUploadToken::create([
            'fuel_daily_check_id' => $check->id,
            'token_hash' => hash('sha256', $plain),
            'created_by' => $context['user']->id,
            'expires_at' => now()->copy()->addHours(2),
        ]);

        $url = route(
            'public.fuel-daily-check.upload',
            $plain
        );

        $qr = new QrCode(
            data: $url,
            size: 260,
            margin: 12
        );

        $qrData = (new PngWriter())
            ->write($qr)
            ->getDataUri();

        $payload = [
            'url' => $url,
            'qr' => $qrData,
            'expires_at' =>
                $token->expires_at->format('d/m/Y H:i'),
            'files_status_url' => route(
                'fuel.daily-check.files.status',
                $check
            ),
            'files_count' => $check->files()->count(),
        ];

        if (request()->expectsJson()) {
            return response()->json($payload);
        }

        return back()->with([
            'fuel_daily_upload_url' => $url,
            'fuel_daily_upload_qr' => $qrData,
            'fuel_daily_upload_token_id' => $token->id,
            'fuel_daily_upload_expires_at' =>
                $token->expires_at->format('d/m/Y H:i'),
        ]);
    }

    public function filesStatus(FuelDailyCheck $check)
    {
        $context = $this->context();
        $this->authorizeFuel($context);
        $this->ensureCheck($check, $context);

        return response()->json([
            'count' => $check->files()->count(),
            'files' => $check->files()
                ->latest()
                ->get()
                ->map(fn ($file) => [
                    'id' => $file->id,
                    'name' => $file->original_name,
                    'source' => $file->source,
                    'url' => route(
                        'fuel.daily-check.files.show',
                        [$check, $file]
                    ),
                ]),
        ]);
    }

    public function deleteFile(
        FuelDailyCheck $check,
        FuelDailyCheckFile $file
    ) {
        $context = $this->context();
        $this->authorizeFuel($context);
        $this->ensureCheck($check, $context);

        abort_unless(
            (int) $file->fuel_daily_check_id
                === (int) $check->id,
            404
        );

        $diskName = $file->disk ?: 'local';
        $path = $file->path;

        DB::transaction(function () use (
            $file,
            $diskName,
            $path
        ) {
            $disk = Storage::disk($diskName);

            if ($path && $disk->exists($path)) {
                $disk->delete($path);
            }

            $file->delete();
        });

        return response()->json([
            'ok' => true,
            'message' => 'Arquivo excluído.',
            'files_count' => $check->files()->count(),
        ]);
    }


    public function showFile(
        FuelDailyCheck $check,
        FuelDailyCheckFile $file
    ) {
        $context = $this->context();
        $this->authorizeFuel($context);
        $this->ensureCheck($check, $context);

        abort_unless(
            (int) $file->fuel_daily_check_id
                === (int) $check->id,
            404
        );

        $disk = Storage::disk(
            $file->disk ?: 'local'
        );

        abort_unless(
            $disk->exists($file->path),
            404
        );

        return response()->file(
            $disk->path($file->path),
            [
                'Content-Type' =>
                    $file->mime_type
                    ?: 'application/octet-stream',
            ]
        );
    }

    private function summary(
        array $context,
        string $date
    ) {
        return FuelFilling::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division_id'])
            ->where('location_id', $context['location_id'])
            ->whereDate('filled_at', $date)
            ->whereNull('cancelled_at')
            ->with('product:id,name')
            ->selectRaw(
                'source, fuel_product_id, '
                .'COUNT(*) as fillings_count, '
                .'SUM(quantity_liters) as system_liters'
            )
            ->groupBy('source', 'fuel_product_id')
            ->get()
            ->filter(
                fn ($row) =>
                    (float) $row->system_liters > 0
            )
            ->map(fn ($row) => [
                'source' => $row->source,
                'source_label' =>
                    $row->source === 'internal_tank'
                        ? 'Tanque interno'
                        : 'Posto externo',
                'fuel_product_id' =>
                    (int) $row->fuel_product_id,
                'product_name' =>
                    $row->product?->name
                    ?? 'Combustível',
                'fillings_count' =>
                    (int) $row->fillings_count,
                'system_liters' =>
                    round(
                        (float) $row->system_liters,
                        3
                    ),
            ])
            ->sortBy(fn ($row) =>
                ($row['source'] === 'internal_tank'
                    ? '0'
                    : '1')
                .$row['product_name']
            )
            ->values();
    }

    private function signature(
        array $context,
        string $date
    ): string {
        $rows = FuelFilling::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division_id'])
            ->where('location_id', $context['location_id'])
            ->whereDate('filled_at', $date)
            ->whereNull('cancelled_at')
            ->orderBy('id')
            ->get([
                'id',
                'source',
                'fuel_product_id',
                'quantity_liters',
                'updated_at',
            ])
            ->map(fn ($row) => [
                $row->id,
                $row->source,
                $row->fuel_product_id,
                (string) $row->quantity_liters,
                optional($row->updated_at)
                    ->format('Y-m-d H:i:s.u'),
            ])
            ->all();

        return hash(
            'sha256',
            json_encode($rows)
        );
    }

    private function storeFile(
        FuelDailyCheck $check,
        $file,
        string $source,
        ?int $userId,
        array $metadata = []
    ): FuelDailyCheckFile {
        $extension = strtolower(
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

        Storage::disk('local')->putFileAs(
            dirname($path),
            $file,
            basename($path)
        );

        try {
            return $check->files()->create([
                'disk' => 'local',
                'path' => $path,
                'original_name' =>
                    $file->getClientOriginalName(),
                'mime_type' =>
                    $file->getMimeType(),
                'size_bytes' =>
                    Storage::disk('local')
                        ->size($path),
                'source' => $source,
                'document_type' =>
                    $metadata['document_type']
                    ?? null,
                'document_date' =>
                    $metadata['document_date']
                    ?? null,
                'invoice_number' =>
                    $metadata['invoice_number']
                    ?? null,
                'supplier_id' =>
                    $metadata['supplier_id']
                    ?? null,
                'supplier_name' =>
                    $metadata['supplier_name']
                    ?? null,
                'supplier_document' =>
                    $metadata['supplier_document']
                    ?? null,
                'uploaded_by' => $userId,
            ]);
        } catch (\Throwable $e) {
            $disk = Storage::disk('local');

            if ($disk->exists($path)) {
                $disk->delete($path);
            }

            throw $e;
        }
    }

    private function context(): array
    {
        $user = auth()->user();

        abort_unless($user, 401);

        $service = app(ActiveContextService::class);

        $division =
            $service->activeDivision($user);

        $location =
            $service->activeLocation($user);

        abort_unless(
            $division && $location,
            422,
            'Selecione uma unidade ativa.'
        );

        return [
            'user' => $user,
            'tenant_id' => $user->tenant_id,
            'division_id' => $division->id,
            'location_id' => $location->id,
        ];
    }

    private function authorizeFuel(
        array $context
    ): void {
        abort_unless(
            app(ProfilePermissionService::class)
                ->allows(
                    $context['user'],
                    'fuel.view',
                    [
                        'tenant_id' =>
                            $context['tenant_id'],
                        'division_id' =>
                            $context['division_id'],
                        'location_id' =>
                            $context['location_id'],
                        'module' => 'fleet',
                    ]
                ),
            403
        );
    }

    private function ensureCheck(
        FuelDailyCheck $check,
        array $context
    ): void {
        abort_unless(
            (int) $check->tenant_id
                === (int) $context['tenant_id']
            && (int) $check->division_id
                === (int) $context['division_id']
            && (int) $check->location_id
                === (int) $context['location_id'],
            403
        );
    }
}
