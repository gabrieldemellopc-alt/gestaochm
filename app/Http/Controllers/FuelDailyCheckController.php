<?php

namespace App\Http\Controllers;

use App\Models\FuelDailyCheck;
use App\Models\FuelDailyCheckFile;
use App\Models\FuelDailyCheckUploadToken;
use App\Models\FuelFilling;
use App\Services\ActiveContextService;
use App\Services\Permissions\ProfilePermissionService;
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

        $date = $request->date('date')
            ? Carbon::parse($request->input('date'))->startOfDay()
            : today();

        $check = FuelDailyCheck::query()->firstOrCreate(
            [
                'tenant_id' => $context['tenant_id'],
                'division_id' => $context['division_id'],
                'location_id' => $context['location_id'],
                'operation_date' => $date->toDateString(),
            ],
            ['status' => 'pending']
        );

        $check->load([
            'items',
            'files',
            'checker',
        ]);

        $summary = $this->summary(
            $context,
            $date->toDateString()
        );

        $signature = $this->signature(
            $context,
            $date->toDateString()
        );

        $changedAfterCheck =
            $check->checked_at
            && filled($check->system_signature)
            && ! hash_equals(
                (string) $check->system_signature,
                $signature
            );

        $savedItems = $check->items
            ->keyBy(
                fn ($item) =>
                    $item->source.'|'.$item->fuel_product_id
            );

        $history = FuelDailyCheck::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division_id'])
            ->where('location_id', $context['location_id'])
            ->whereNotNull('checked_at')
            ->with('checker:id,name')
            ->latest('operation_date')
            ->limit(20)
            ->get();

        return view(
            'fuel.daily-checks.index',
            compact(
                'check',
                'summary',
                'savedItems',
                'date',
                'changedAfterCheck',
                'history'
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
        ?int $userId
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
            'uploaded_by' => $userId,
        ]);
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
