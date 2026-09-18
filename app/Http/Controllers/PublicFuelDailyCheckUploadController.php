<?php

namespace App\Http\Controllers;

use App\Models\FuelDailyCheckFile;
use App\Models\FuelDailyCheckUploadToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PublicFuelDailyCheckUploadController extends Controller
{
    public function show(string $token)
    {
        $session = $this->resolve($token);

        return view(
            'fuel.daily-checks.mobile-upload',
            [
                'token' => $token,
                'check' => $session->check,
            ]
        );
    }

    public function store(
        Request $request,
        string $token
    ) {
        $session = $this->resolve($token);

        $data = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:5'],
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

            FuelDailyCheckFile::create([
                'fuel_daily_check_id' =>
                    $check->id,
                'disk' => 'local',
                'path' => $path,
                'original_name' =>
                    $file->getClientOriginalName(),
                'mime_type' =>
                    $file->getMimeType(),
                'size_bytes' =>
                    Storage::disk('local')
                        ->size($path),
                'source' => 'qr_mobile',
                'uploaded_by' => null,
            ]);
        }

        $session->update([
            'last_used_at' => now(),
        ]);

        $message = 'Arquivo(s) enviado(s) ao CHM com sucesso.';

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
