<?php

namespace App\Http\Controllers;

use App\Models\FuelDailyCheckMobileUpload;
use App\Models\FuelDailyCheckUploadToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PublicFuelDailyCheckUploadController extends Controller
{
    public function show(string $token)
    {
        $session = $this->resolve($token);

        $documentTypeLabel = match (
            $session->document_type
        ) {
            'fuel_invoice' =>
                'NF de Recebimentos',

            'fuel_sheet' =>
                'Folha de Abastecimentos',

            'other' =>
                'Outros',

            default =>
                'Documento',
        };

        return view(
            'fuel.daily-checks.mobile-upload',
            [
                'token' => $token,
                'check' => $session->check,
                'documentType' =>
                    $session->document_type,
                'documentTypeLabel' =>
                    $documentTypeLabel,
            ]
        );
    }

    public function store(
        Request $request,
        string $token
    ) {
        $session = $this->resolve($token);

        $maxFiles =
            $session->document_type === 'fuel_invoice'
                ? 1
                : 5;

        $data = $request->validate([
            'files' => [
                'required',
                'array',
                'min:1',
                'max:'.$maxFiles,
            ],
            'files.*' => [
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'max:12288',
            ],
        ]);

        foreach ($data['files'] as $file) {
            $extension = strtolower(
                $file->getClientOriginalExtension()
                ?: $file->extension()
                ?: 'jpg'
            );

            $check = $session->check;

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

            FuelDailyCheckMobileUpload::create([
                'fuel_daily_check_upload_token_id' =>
                    $session->id,
                'disk' => 'local',
                'path' => $path,
                'original_name' =>
                    $file->getClientOriginalName(),
                'mime_type' =>
                    $file->getMimeType(),
                'size_bytes' =>
                    Storage::disk('local')
                        ->size($path),
            ]);
        }

        $session->update([
            'last_used_at' => now(),
        ]);

        $message =
            'Arquivo enviado ao computador. '
            .'Volte ao CHM para conferir os dados '
            .'e concluir o arquivamento.';

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'files_count' => count($data['files']),
            ]);
        }

        return back()->with(
            'success',
            $message
        );
    }

    private function resolve(
        string $plainToken
    ): FuelDailyCheckUploadToken {
        $session = FuelDailyCheckUploadToken::query()
            ->where(
                'token_hash',
                hash('sha256', $plainToken)
            )
            ->with('check')
            ->firstOrFail();

        abort_unless(
            $session->isValid(),
            410,
            'Este link expirou.'
        );

        return $session;
    }
}
