<?php

namespace App\Services;

use App\Models\SystemAuditLog;
use App\Models\UserDivisionAccess;
use Illuminate\Database\Eloquent\Model;

class AuditLogService
{
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'remember_token',
        'api_token',
        'csrf_token',
        '_token',
    ];

    public function record(array $data): SystemAuditLog
    {
        $user = auth()->user();
        $request = request();
        $auditable = $data['auditable'] ?? null;

        $payload = [
            'tenant_id' => $data['tenant_id'] ?? $user?->tenant_id,
            'division_id' => $data['division_id'] ?? session('active_division_id'),
            'location_id' => $data['location_id'] ?? session('active_location_id'),
            'user_id' => $data['user_id'] ?? $user?->id,
            'user_profile' => $data['user_profile'] ?? $this->currentUserProfile(),
            'auditable_type' => $data['auditable_type'] ?? $this->auditableType($auditable),
            'auditable_id' => $data['auditable_id'] ?? $this->auditableId($auditable),
            'module' => $data['module'] ?? null,
            'action' => $data['action'],
            'summary' => $data['summary'] ?? null,
            'before_data' => $this->sanitize($data['before_data'] ?? null),
            'after_data' => $this->sanitize($data['after_data'] ?? null),
            'metadata' => $this->sanitize($data['metadata'] ?? null),
            'reason' => $data['reason'] ?? null,
            'ip_address' => $data['ip_address'] ?? $request?->ip(),
            'user_agent' => $data['user_agent'] ?? $request?->userAgent(),
        ];

        return SystemAuditLog::create($payload);
    }

    public function created(Model $auditable, array $data = []): SystemAuditLog
    {
        return $this->record([
            ...$data,
            'auditable' => $auditable,
            'action' => 'created',
            'after_data' => $data['after_data'] ?? $auditable->toArray(),
        ]);
    }

    public function updated(Model $auditable, array $data = []): SystemAuditLog
    {
        return $this->record([
            ...$data,
            'auditable' => $auditable,
            'action' => 'updated',
        ]);
    }

    public function cancelled(Model $auditable, array $data = []): SystemAuditLog
    {
        return $this->record([
            ...$data,
            'auditable' => $auditable,
            'action' => 'cancelled',
        ]);
    }

    public function deleted(Model $auditable, array $data = []): SystemAuditLog
    {
        return $this->record([
            ...$data,
            'auditable' => $auditable,
            'action' => 'deleted',
        ]);
    }

    public function reversed(Model $auditable, array $data = []): SystemAuditLog
    {
        return $this->record([
            ...$data,
            'auditable' => $auditable,
            'action' => 'reversed',
        ]);
    }

    public function restored(Model $auditable, array $data = []): SystemAuditLog
    {
        return $this->record([
            ...$data,
            'auditable' => $auditable,
            'action' => 'restored',
        ]);
    }

    private function auditableType($auditable): ?string
    {
        return $auditable instanceof Model
            ? $auditable::class
            : null;
    }

    private function auditableId($auditable): ?int
    {
        return $auditable instanceof Model
            ? $auditable->getKey()
            : null;
    }

    private function currentUserProfile(): ?string
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        $divisionId = session('active_division_id');
        $locationId = session('active_location_id');

        if ($divisionId) {
            $access = UserDivisionAccess::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('user_id', $user->id)
                ->where('division_id', $divisionId)
                ->where('active', true)
                ->when($locationId, function ($query) use ($locationId) {
                    $query->where(function ($query) use ($locationId) {
                        $query
                            ->where('location_id', $locationId)
                            ->orWhereNull('location_id');
                    });
                })
                ->orderByRaw('location_id is null')
                ->value('profile');

            if ($access) {
                return $access;
            }
        }

        return $user->profile
            ?? $user->role
            ?? $user->type
            ?? null;
    }

    private function sanitize($value)
    {
        if ($value instanceof Model) {
            $value = $value->toArray();
        }

        if (! is_array($value)) {
            return $value;
        }

        return collect($value)
            ->reject(fn ($item, $key) => in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true))
            ->map(function ($item) {
                return is_array($item)
                    ? $this->sanitize($item)
                    : $item;
            })
            ->all();
    }
}
