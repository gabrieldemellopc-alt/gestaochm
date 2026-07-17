<?php

namespace App\Support;

use App\Models\SystemAuditLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AuditLogPresenter
{
    public function present(SystemAuditLog $log): array
    {
        $before = $this->flatten($this->arrayValue($log->before_data));
        $after = $this->flatten($this->arrayValue($log->after_data));
        $changes = $this->changedFields($before, $after);

        $actorName = $log->user?->name ?: ($log->user_id ? 'Usuário não informado' : 'Sistema');
        $moduleLabel = $this->moduleLabel($log->module);
        $actionLabel = $this->actionLabel($log->action);
        $contextLabel = $this->contextLabel($log);
        $occurredAt = $log->created_at
            ? $log->created_at->format('d/m/Y H:i')
            : 'Data não informada';

        return [
            'id' => $log->id,
            'title' => $this->title($log, $actionLabel, $contextLabel),
            'subtitle' => $this->subtitle($log, $moduleLabel, $occurredAt),
            'narrative' => $this->narrative($actorName, $log, $actionLabel, $contextLabel, $moduleLabel, $occurredAt),
            'actor_name' => $actorName,
            'module_label' => $moduleLabel,
            'action_label' => $actionLabel,
            'occurred_at_label' => $occurredAt,
            'context_label' => $contextLabel,
            'summary' => $log->summary,
            'reason' => $log->reason,
            'profile_label' => $this->profileLabel($log->user_profile),
            'division_label' => $log->division?->name ?: 'Divisão não informada',
            'location_label' => $log->location?->name ?: 'Todas as unidades',
            'changed_fields' => $changes,
            'technical_fields' => $this->technicalFields($log),
            'technical_payloads' => $this->technicalPayloads($log),
            'badges' => $this->badges($log, $moduleLabel, $actionLabel),
            'icon' => $this->icon($log->module, $log->action),
            'browser_label' => $this->browserLabel($log->user_agent),
        ];
    }

    private function title(SystemAuditLog $log, string $actionLabel, string $contextLabel): string
    {
        if ($log->summary) {
            return $log->summary;
        }

        return "{$actionLabel} {$contextLabel}";
    }

    private function subtitle(SystemAuditLog $log, string $moduleLabel, string $occurredAt): string
    {
        $location = $log->location?->name ?: 'todas as unidades';

        return "{$moduleLabel} • {$location} • {$occurredAt}";
    }

    private function narrative(
        string $actorName,
        SystemAuditLog $log,
        string $actionLabel,
        string $contextLabel,
        string $moduleLabel,
        string $occurredAt
    ): string {
        $action = Str::lower($actionLabel);
        $phrase = "{$actorName} {$action} {$contextLabel} no módulo {$moduleLabel}, em {$occurredAt}.";

        if ($log->reason) {
            $phrase .= " Motivo informado: {$log->reason}.";
        }

        return $phrase;
    }

    private function changedFields(Collection $before, Collection $after): array
    {
        return $before
            ->keys()
            ->merge($after->keys())
            ->unique()
            ->filter(fn (string $key) => $before->get($key) !== $after->get($key))
            ->map(function (string $key) use ($before, $after) {
                return [
                    'key' => $key,
                    'label' => $this->fieldLabel($key),
                    'before' => $before->has($key)
                        ? $this->formatValue($before->get($key), $key)
                        : 'Não informado',
                    'after' => $after->has($key)
                        ? $this->formatValue($after->get($key), $key)
                        : 'Não informado',
                ];
            })
            ->values()
            ->all();
    }

    private function flatten(array $value, string $prefix = ''): Collection
    {
        return collect($value)->flatMap(function ($item, $key) use ($prefix) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : (string) $key;

            if (is_array($item)) {
                return $this->flatten($item, $fullKey);
            }

            return [$fullKey => $item];
        });
    }

    private function technicalFields(SystemAuditLog $log): array
    {
        return [
            ['label' => 'ID do log', 'value' => (string) $log->id],
            ['label' => 'Registro técnico', 'value' => $this->technicalEntity($log)],
            ['label' => 'Tenant', 'value' => $log->tenant?->name ?: $this->formatValue($log->tenant_id, 'tenant_id')],
            ['label' => 'Divisão', 'value' => $log->division?->name ?: $this->formatValue($log->division_id, 'division_id')],
            ['label' => 'Unidade', 'value' => $log->location?->name ?: $this->formatValue($log->location_id, 'location_id')],
            ['label' => 'IP', 'value' => $log->ip_address ?: 'Não informado'],
            ['label' => 'Dispositivo', 'value' => $this->browserLabel($log->user_agent)],
        ];
    }

    private function technicalPayloads(SystemAuditLog $log): array
    {
        return collect([
            'Metadados' => $log->metadata,
            'Antes' => $log->before_data,
            'Depois' => $log->after_data,
        ])
            ->filter(fn ($payload) => $payload !== null && $payload !== [] && $payload !== '')
            ->map(fn ($payload, string $label) => [
                'label' => $label,
                'json' => json_encode(
                    $payload,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
            ])
            ->values()
            ->all();
    }

    private function badges(SystemAuditLog $log, string $moduleLabel, string $actionLabel): array
    {
        $badges = [
            ['type' => 'module', 'label' => $moduleLabel],
            ['type' => 'action', 'label' => $actionLabel],
        ];

        if ($log->user_profile) {
            $badges[] = ['type' => 'profile', 'label' => $this->profileLabel($log->user_profile)];
        }

        return $badges;
    }

    private function contextLabel(SystemAuditLog $log): string
    {
        $entity = $this->entityLabel($log->auditable_type);

        if (! $log->auditable_id) {
            return Str::lower($entity);
        }

        return "{$entity} #{$log->auditable_id}";
    }

    private function technicalEntity(SystemAuditLog $log): string
    {
        $type = $log->auditable_type ? class_basename($log->auditable_type) : 'Sistema';

        return $log->auditable_id ? "{$type} #{$log->auditable_id}" : $type;
    }

    private function fieldLabel(string $field): string
    {
        $fieldName = str_contains($field, '.') ? (string) Str::of($field)->afterLast('.') : $field;

        return ChmLabel::for('audit_field', $fieldName);
    }

    private function formatValue(mixed $value, string $field = ''): string
    {
        if ($value === null || $value === '') {
            return 'Não informado';
        }

        if (is_bool($value)) {
            return $value ? 'Sim' : 'Não';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'Não informado';
        }

        $fieldName = str_contains($field, '.') ? (string) Str::of($field)->afterLast('.') : $field;

        if ($this->isMoneyField($fieldName) && is_numeric($value)) {
            return 'R$ ' . number_format((float) $value, 2, ',', '.');
        }

        if ($this->isLiterField($fieldName) && is_numeric($value)) {
            return number_format((float) $value, 2, ',', '.') . ' L';
        }

        if ($this->isKmField($fieldName) && is_numeric($value)) {
            return number_format((float) $value, 0, ',', '.') . ' km';
        }

        if ($this->isHourField($fieldName) && is_numeric($value)) {
            return number_format((float) $value, 1, ',', '.') . ' h';
        }

        if ($this->isDateField($fieldName) && is_string($value)) {
            try {
                return Carbon::parse($value)->format('d/m/Y H:i');
            } catch (\Throwable) {
                return $value;
            }
        }

        $known = ChmLabel::knownToken([
            'workflow_status',
            'operational_status',
            'service_status',
            'maintenance_type',
            'service_type',
            'execution_type',
            'stock_movement',
            'fuel_movement',
            'audit_action',
            'tire_event',
            'vehicle_update_source',
        ], $value);

        return $known !== (string) $value
            ? $known
            : (is_numeric($value) ? number_format((float) $value, 0, ',', '.') : (string) $value);
    }

    private function moduleLabel(?string $module): string
    {
        return ChmLabel::for('audit_module', $module ?: 'system', 'Sistema');
    }

    private function actionLabel(?string $action): string
    {
        return ChmLabel::for('audit_action', $action ?: 'event', 'Registrou');
    }

    private function profileLabel(?string $profile): string
    {
        return ChmLabel::for('user_profile', $profile ?: '', '');
    }

    private function entityLabel(?string $type): string
    {
        if (! $type) {
            return 'Registro do sistema';
        }

        return ChmLabel::for('audit_entity', class_basename($type));
    }

    private function icon(?string $module, ?string $action): string
    {
        if (in_array($action, ['cancelled', 'cancel', 'deleted', 'delete'], true)) {
            return 'ban';
        }

        return match ($module) {
            'fuel' => 'fuel',
            'maintenance' => 'wrench',
            'stock' => 'package',
            'tires' => 'circle-dot',
            'fleet', 'vehicles' => 'truck',
            'access-control', 'access', 'users' => 'shield-check',
            'portal' => 'building-2',
            default => 'fingerprint',
        };
    }

    private function browserLabel(?string $userAgent): string
    {
        if (! $userAgent) {
            return 'Navegador não informado';
        }

        $browser = match (true) {
            str_contains($userAgent, 'OPR/') => 'Opera',
            str_contains($userAgent, 'Edg/') => 'Microsoft Edge',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Chrome/') => 'Google Chrome',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => 'Navegador não identificado',
        };

        $system = match (true) {
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone') => 'iPhone',
            str_contains($userAgent, 'iPad') => 'iPad',
            str_contains($userAgent, 'Mac OS') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'Sistema não identificado',
        };

        return "{$browser} em {$system}";
    }

    private function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function isMoneyField(string $field): bool
    {
        return in_array($field, ['total_cost', 'extra_cost', 'amount', 'unit_cost', 'cost'], true)
            || str_ends_with($field, '_cost')
            || str_ends_with($field, '_amount');
    }

    private function isLiterField(string $field): bool
    {
        return str_contains($field, 'liter') || str_contains($field, 'liters');
    }

    private function isKmField(string $field): bool
    {
        return str_contains($field, 'km');
    }

    private function isHourField(string $field): bool
    {
        return str_contains($field, 'hour');
    }

    private function isDateField(string $field): bool
    {
        return str_ends_with($field, '_at') || str_ends_with($field, '_date');
    }
}
