<?php

namespace Tests\Unit;

use App\Services\MaintenanceService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MaintenanceCloseDateTimeTest extends TestCase
{
    public function test_datetime_local_is_interpreted_in_application_timezone(): void
    {
        config(['app.timezone' => 'America/Sao_Paulo']);
        $date = MaintenanceService::parseLocalDateTime('2026-09-09T10:30');
        $this->assertSame('America/Sao_Paulo', $date->getTimezone()->getName());
        $this->assertSame('2026-09-09 10:30:00', $date->format('Y-m-d H:i:s'));
    }

    public function test_datetime_local_with_seconds_is_supported_and_invalid_value_is_rejected(): void
    {
        config(['app.timezone' => 'America/Sao_Paulo']);
        $this->assertSame('10:30:45', MaintenanceService::parseLocalDateTime('2026-09-09T10:30:45')->format('H:i:s'));
        $this->expectException(ValidationException::class);
        MaintenanceService::parseLocalDateTime('09/09/2026 10:30');
    }

    public function test_current_minute_and_small_clock_difference_are_accepted(): void
    {
        config(['app.timezone' => 'America/Sao_Paulo']);
        Carbon::setTestNow(Carbon::create(2026, 9, 9, 10, 30, 20, 'America/Sao_Paulo'));

        $this->assertSame('10:30:00', MaintenanceService::validateClosingDateTime('2026-09-09T10:30')->format('H:i:s'));
        $this->assertSame('10:31:00', MaintenanceService::validateClosingDateTime('2026-09-09T10:31')->format('H:i:s'));
    }

    public function test_future_and_pre_opening_closures_are_rejected(): void
    {
        config(['app.timezone' => 'America/Sao_Paulo']);
        Carbon::setTestNow(Carbon::create(2026, 9, 9, 10, 30, 20, 'America/Sao_Paulo'));

        try {
            MaintenanceService::validateClosingDateTime('2026-09-09T10:32');
            $this->fail('A future closing date must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame('A data e hora do encerramento não pode ser futura.', $exception->errors()['finished_at'][0]);
        }

        $this->expectException(ValidationException::class);
        MaintenanceService::validateClosingDateTime('2026-09-09T09:59', '2026-09-09 10:00:00');
    }
}
