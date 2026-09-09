<?php

namespace Tests\Feature;

use App\Models\Vehicle;
use App\Services\PreventiveService;
use Carbon\Carbon;
use Tests\TestCase;

class PreventiveServiceOperationalControlsTest extends TestCase
{
    public function test_km_alert_is_ignored_when_km_control_is_disabled(): void
    {
        $alerts = $this->alerts(false, false, false);
        $this->assertEmpty($alerts);
    }

    public function test_km_alert_is_kept_when_km_control_is_enabled(): void
    {
        $alerts = $this->alerts(true, false, false);
        $this->assertTrue(collect($alerts)->contains(fn ($alert) => str_contains($alert['message'], 'KM sem atualização')));
    }

    public function test_hours_alert_respects_hours_control(): void
    {
        $this->assertEmpty($this->alerts(false, false, false));
        $alerts = $this->alerts(false, true, false);
        $this->assertTrue(collect($alerts)->contains(fn ($alert) => str_contains($alert['message'], 'Horímetro sem atualização')));
    }

    public function test_combined_controls_only_emit_alerts_for_enabled_counters(): void
    {
        $hoursOnly = $this->alerts(false, true, false);
        $this->assertFalse(collect($hoursOnly)->contains(fn ($alert) => str_contains($alert['message'], 'KM sem atualização')));
        $this->assertTrue(collect($hoursOnly)->contains(fn ($alert) => str_contains($alert['message'], 'Horímetro sem atualização')));
        $this->assertEmpty($this->alerts(false, false, false));
    }

    public function test_tires_disabled_returns_without_tire_alerts_or_queries(): void
    {
        $alerts = $this->alerts(false, false, false);
        $this->assertFalse(collect($alerts)->contains(fn ($alert) => $alert['procedure'] === 'Controle de pneus'));
    }

    private function alerts(bool $km, bool $hours, bool $tires): array
    {
        $vehicle = new Vehicle([
            'km_control_enabled' => $km,
            'hours_control_enabled' => $hours,
            'tire_control_enabled' => $tires,
            'last_km_update_at' => Carbon::now()->subDays(61),
            'last_hours_update_at' => Carbon::now()->subDays(61),
        ]);
        $vehicle->setRelation('procedures', collect());

        return PreventiveService::getVehicleAlerts($vehicle);
    }
}
