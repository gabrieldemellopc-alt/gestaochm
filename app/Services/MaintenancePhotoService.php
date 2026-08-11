<?php

namespace App\Services;

use App\Models\MaintenancePhoto;
use App\Models\MaintenancePhotoUploadToken;
use App\Models\MaintenanceRecord;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MaintenancePhotoService
{
    public const MIN_REQUIRED_PHOTOS = 2;
    public const MAX_PHOTOS_PER_MAINTENANCE = 20;

    public function createUploadToken(MaintenanceRecord $maintenance, User $user): array
    {
        $this->ensureOpen($maintenance);
        $this->ensureCanReceivePhotos($maintenance, 1);
        $plain = Str::random(64);
        $token = $maintenance->photoUploadTokens()->create([
            'token' => hash('sha256', $plain), 'created_by' => $user->id,
            'expires_at' => now()->addMinutes(30), 'max_uploads' => 10,
        ]);
        $this->audit('maintenance_photo_upload_token_created', $maintenance, ['token_id' => $token->id]);
        return [$token, $plain];
    }

    public function validateUploadToken(string $plain, int $incoming = 0): MaintenancePhotoUploadToken
    {
        $token = MaintenancePhotoUploadToken::query()->with('maintenanceRecord.vehicle')
            ->where('token', hash('sha256', $plain))->firstOrFail();
        if ($token->isExpired() || $token->isRevoked()
            || $token->maintenanceRecord?->workflow_status !== 'open'
            || $token->maintenanceRecord?->cancelled_at) {
            throw ValidationException::withMessages(['photos' => 'Este link expirou. Gere um novo QR Code no computador.']);
        }
        $tokenRemaining = $token->max_uploads === null ? null : max(0, $token->max_uploads - $token->photos()->count());
        if ($tokenRemaining !== null && $incoming > $tokenRemaining) {
            throw ValidationException::withMessages(['photos' => 'Este link permite mais '.$tokenRemaining.' envio(s). Selecione uma quantidade menor.']);
        }
        return $token;
    }

    public function storeAuthenticatedPhoto(MaintenanceRecord $maintenance, UploadedFile $file, ?string $caption, User $user): MaintenancePhoto
    {
        $this->ensureCanReceivePhotos($maintenance, 1);
        return $this->store($maintenance, $file, $caption, 'web', $user->id, null);
    }

    public function storePublicPhoto(MaintenancePhotoUploadToken $token, UploadedFile $file, ?string $caption): MaintenancePhoto
    {
        $this->ensureCanReceivePhotos($token->maintenanceRecord, 1);
        $photo = $this->store($token->maintenanceRecord, $file, $caption, 'qr_mobile', null, $token->id);
        $token->forceFill(['used_at' => now()])->save();
        return $photo;
    }

    private function store(MaintenanceRecord $maintenance, UploadedFile $file, ?string $caption, string $source, ?int $userId, ?int $tokenId): MaintenancePhoto
    {
        $this->ensureOpen($maintenance);
        $vehicle = $maintenance->vehicle;
        $name = Str::uuid().'.'.strtolower($file->extension());
        $path = $file->storeAs("maintenance/{$maintenance->tenant_id}/{$maintenance->id}", $name, 'public');
        try {
            $photo = $maintenance->photos()->create([
                'tenant_id' => $maintenance->tenant_id, 'division_id' => $vehicle?->division_id,
                'location_id' => $vehicle?->location_id, 'uploaded_by_user_id' => $userId,
                'upload_token_id' => $tokenId, 'file_path' => $path, 'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(), 'size' => $file->getSize(), 'caption' => $caption, 'source' => $source,
            ]);
        } catch (\Throwable $e) { Storage::disk('public')->delete($path); throw $e; }
        $this->audit('maintenance_photo_uploaded', $maintenance, ['photo_id' => $photo->id, 'source' => $source, 'token_id' => $tokenId]);
        return $photo;
    }

    public function deletePhoto(MaintenanceRecord $maintenance, MaintenancePhoto $photo): void
    {
        $this->ensureOpen($maintenance);
        abort_unless((int) $photo->maintenance_record_id === (int) $maintenance->id, 404);
        DB::transaction(function () use ($maintenance, $photo) {
            $metadata = ['photo_id' => $photo->id, 'source' => $photo->source];
            $path = $photo->file_path; $photo->delete(); Storage::disk('public')->delete($path);
            $this->audit('maintenance_photo_deleted', $maintenance, $metadata);
        });
    }

    public function photoCount(MaintenanceRecord $maintenance): int { return $maintenance->photos()->count(); }

    public function remainingCapacity(MaintenanceRecord $maintenance): int
    {
        return max(0, self::MAX_PHOTOS_PER_MAINTENANCE - $this->photoCount($maintenance));
    }

    public function ensureCanReceivePhotos(MaintenanceRecord $maintenance, int $incoming): void
    {
        $this->ensureOpen($maintenance);
        $remaining = $this->remainingCapacity($maintenance);
        if ($incoming > 0 && $incoming <= $remaining) {
            return;
        }

        throw ValidationException::withMessages([
            'photos' => 'Esta ordem permite no máximo '.self::MAX_PHOTOS_PER_MAINTENANCE.' fotos. Restam apenas '.$remaining.' envio(s).',
        ]);
    }

    public function ensureCanClose(MaintenanceRecord $maintenance): void
    {
        $count = $this->photoCount($maintenance);
        if ($count >= self::MIN_REQUIRED_PHOTOS) return;
        $this->audit('maintenance_close_blocked_missing_photos', $maintenance, ['count' => $count, 'required' => self::MIN_REQUIRED_PHOTOS]);
        throw ValidationException::withMessages(['maintenance' => 'Envie pelo menos 2 fotos da manutenção antes de encerrar a ordem.']);
    }
    private function ensureOpen(MaintenanceRecord $maintenance): void
    {
        if ($maintenance->workflow_status !== 'open' || $maintenance->cancelled_at) {
            throw ValidationException::withMessages(['photos' => 'Esta manutenção não aceita mais fotos.']);
        }
    }
    private function audit(string $action, MaintenanceRecord $maintenance, array $metadata): void
    {
        $vehicle = $maintenance->vehicle;
        app(AuditLogService::class)->record([
            'action' => $action, 'auditable' => $maintenance, 'tenant_id' => $maintenance->tenant_id,
            'division_id' => $vehicle?->division_id, 'location_id' => $vehicle?->location_id,
            'module' => 'maintenance', 'summary' => config("chm_labels.audit_action.$action", $action),
            'metadata' => array_merge(['maintenance_record_id' => $maintenance->id, 'vehicle_id' => $maintenance->vehicle_id,
                'count' => $maintenance->photos()->count(), 'required' => self::MIN_REQUIRED_PHOTOS,
                'maximum' => self::MAX_PHOTOS_PER_MAINTENANCE], $metadata),
        ]);
    }
}
