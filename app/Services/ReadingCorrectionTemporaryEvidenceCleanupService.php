<?php

namespace App\Services;

use App\Models\VehicleReadingCorrectionEvidence;
use Illuminate\Support\Facades\Storage;

class ReadingCorrectionTemporaryEvidenceCleanupService
{
    private const PREFIX = 'protected/vehicle-reading-evidence/tmp/';

    public function plan(): array
    {
        $cutoff = now()->subDay();
        $sessions = VehicleReadingCorrectionEvidence::query()->where('evidence_type', 'mobile_photo_session')->whereNull('correction_id')
            ->where('expires_at', '<=', $cutoff)->get();
        $files = collect(); $skipped = collect();
        foreach ($sessions as $session) {
            $children = VehicleReadingCorrectionEvidence::query()->where('checksum', $session->token_hash)->whereNull('correction_id')->whereIn('evidence_type', ['identification', 'reading'])->get();
            foreach ($children as $evidence) {
                if ($this->safePath($evidence->path)) $files->push($evidence); else $skipped->push($evidence);
            }
        }
        return ['sessions' => $sessions, 'files' => $files, 'skipped' => $skipped, 'bytes' => $files->sum(fn ($e) => (int) $e->size_bytes), 'safe' => $skipped->isEmpty()];
    }

    public function commit(array $plan): array
    {
        if (! $plan['safe']) return ['files' => 0, 'bytes' => 0, 'sessions' => 0];
        $deleted = 0; $bytes = 0;
        foreach ($plan['files'] as $evidence) {
            $fresh = $evidence->fresh();
            if (! $fresh || $fresh->correction_id || ! $this->safePath($fresh->path)) continue;
            $disk = Storage::disk($fresh->disk ?: 'local');
            if ($disk->exists($fresh->path)) $disk->delete($fresh->path);
            $bytes += (int) $fresh->size_bytes; $fresh->delete(); $deleted++;
        }
        $sessionCount = 0;
        foreach ($plan['sessions'] as $session) {
            $fresh = $session->fresh();
            if (! $fresh || $fresh->correction_id) continue;
            if (! VehicleReadingCorrectionEvidence::query()->where('checksum', $fresh->token_hash)->whereNull('correction_id')->exists()) { $fresh->delete(); $sessionCount++; }
        }
        return ['files' => $deleted, 'bytes' => $bytes, 'sessions' => $sessionCount];
    }

    public function purgeSession(VehicleReadingCorrectionEvidence $session): void
    {
        $files = VehicleReadingCorrectionEvidence::query()->where('checksum', $session->token_hash)->whereNull('correction_id')->whereIn('evidence_type', ['identification', 'reading'])->get();
        foreach ($files as $evidence) {
            if (! $this->safePath($evidence->path)) continue;
            Storage::disk($evidence->disk ?: 'local')->delete($evidence->path); $evidence->delete();
        }
        if (! $session->correction_id) $session->update(['status' => 'expired']);
    }

    private function safePath(?string $path): bool { return is_string($path) && str_starts_with($path, self::PREFIX) && ! str_contains($path, '..'); }
}
