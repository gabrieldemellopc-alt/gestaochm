<?php

namespace App\Http\Controllers;

use App\Services\MaintenancePhotoService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PublicMaintenancePhotoController extends Controller
{
    public function show(string $token, MaintenancePhotoService $service)
    {
        try { $uploadToken = $service->validateUploadToken($token); }
        catch (ValidationException|\Illuminate\Database\Eloquent\ModelNotFoundException) { return response()->view('maintenance-photos.expired', [], 410); }
        $maintenance = $uploadToken->maintenanceRecord;
        $photoCount = $service->photoCount($maintenance);
        $tokenRemaining = $uploadToken->max_uploads === null ? MaintenancePhotoService::MAX_PHOTOS_PER_MAINTENANCE : $uploadToken->max_uploads - $uploadToken->photos()->count();
        return view('maintenance-photos.upload', ['token' => $token, 'maintenance' => $maintenance,
            'photoCount' => $photoCount, 'remaining' => min($service->remainingCapacity($maintenance), $tokenRemaining),
            'minPhotos' => MaintenancePhotoService::MIN_REQUIRED_PHOTOS,
            'maxPhotos' => MaintenancePhotoService::MAX_PHOTOS_PER_MAINTENANCE]);
    }

    public function store(Request $request, string $token, MaintenancePhotoService $service)
    {
        $data = $request->validate(['photos' => ['required', 'array', 'min:1'], 'photos.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'], 'caption' => ['nullable', 'string', 'max:500']]);
        $uploadToken = $service->validateUploadToken($token, count($data['photos']));
        $service->ensureCanReceivePhotos($uploadToken->maintenanceRecord, count($data['photos']));
        foreach ($data['photos'] as $photo) $service->storePublicPhoto($uploadToken, $photo, $data['caption'] ?? null);
        return redirect()->route('public.maintenance-photos.show', $token)->with('success', count($data['photos']).' foto(s) enviada(s) com sucesso.');
    }
}
