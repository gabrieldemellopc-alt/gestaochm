<?php

namespace App\Http\Controllers;

use App\Models\MaintenancePhoto;
use App\Models\MaintenanceRecord;
use App\Models\Vehicle;
use App\Services\MaintenancePhotoService;
use App\Services\Permissions\ProfilePermissionService;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\Request;

class MaintenancePhotoController extends Controller
{
    public function store(Request $request, Vehicle $vehicle, MaintenanceRecord $maintenance, MaintenancePhotoService $service)
    {
        $this->authorizeFor($request, $vehicle, $maintenance, 'maintenance.upload_photos');
        $data = $request->validate(['photos' => ['required', 'array', 'min:1'], 'photos.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'], 'caption' => ['nullable', 'string', 'max:500']]);
        $service->ensureCanReceivePhotos($maintenance, count($data['photos']));
        foreach ($data['photos'] as $photo) $service->storeAuthenticatedPhoto($maintenance, $photo, $data['caption'] ?? null, $request->user());
        return back()->with('success', count($data['photos']).' foto(s) anexada(s) com sucesso.');
    }

    public function destroy(Request $request, Vehicle $vehicle, MaintenanceRecord $maintenance, MaintenancePhoto $photo, MaintenancePhotoService $service)
    {
        $this->authorizeFor($request, $vehicle, $maintenance, 'maintenance.delete_photos');
        $service->deletePhoto($maintenance, $photo);
        return back()->with('success', 'Foto removida com sucesso.');
    }

    public function token(Request $request, Vehicle $vehicle, MaintenanceRecord $maintenance, MaintenancePhotoService $service)
    {
        $this->authorizeFor($request, $vehicle, $maintenance, 'maintenance.generate_photo_qr');
        [$model, $plain] = $service->createUploadToken($maintenance, $request->user());
        $url = route('public.maintenance-photos.show', $plain);
        $svg = (new SvgWriter())->write(new QrCode(data: $url, size: 260, margin: 10))->getString();
        return back()->with(['photo_upload_url' => $url, 'photo_upload_qr' => 'data:image/svg+xml;base64,'.base64_encode($svg), 'photo_upload_expires_at' => $model->expires_at->format('d/m/Y H:i')]);
    }

    private function authorizeFor(Request $request, Vehicle $vehicle, MaintenanceRecord $maintenance, string $permission): void
    {
        abort_unless((int) $maintenance->vehicle_id === (int) $vehicle->id, 404);
        abort_unless((int) $vehicle->tenant_id === (int) $request->user()->tenant_id, 403);
        abort_unless((int) $vehicle->division_id === (int) session('active_division_id') && (int) $vehicle->location_id === (int) session('active_location_id'), 403);
        $allowed = app(ProfilePermissionService::class)->allows($request->user(), $permission, ['tenant_id' => $vehicle->tenant_id, 'division_id' => $vehicle->division_id, 'location_id' => $vehicle->location_id, 'module' => 'fleet']);
        abort_unless($allowed, 403, 'Você não tem permissão para executar esta ação.');
    }
}
