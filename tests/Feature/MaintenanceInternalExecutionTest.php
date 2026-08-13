<?php

namespace Tests\Feature;

use App\Models\Procedure;
use App\Services\MaintenanceService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MaintenanceInternalExecutionTest extends TestCase
{
    public function test_external_execution_is_allowed_for_any_procedure(): void
    {
        $procedure = new Procedure(['can_be_internal' => false]);

        MaintenanceService::assertExecutionTypeAllowed($procedure, 'external');

        $this->assertTrue(true);
    }

    public function test_internal_execution_is_allowed_when_procedure_supports_it(): void
    {
        $procedure = new Procedure(['can_be_internal' => true]);

        MaintenanceService::assertExecutionTypeAllowed($procedure, 'internal');

        $this->assertTrue(true);
    }

    public function test_internal_execution_is_rejected_when_procedure_does_not_support_it(): void
    {
        $procedure = new Procedure(['can_be_internal' => false]);

        try {
            MaintenanceService::assertExecutionTypeAllowed($procedure, 'internal');
            $this->fail('A execução interna deveria ter sido rejeitada.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Este procedimento não permite execução em oficina interna.',
                $exception->errors()['maintenance_type'][0]
            );
        }
    }

    public function test_mutation_flows_use_the_central_execution_guard(): void
    {
        $service = file_get_contents(app_path('Services/MaintenanceService.php'));

        $this->assertGreaterThanOrEqual(
            2,
            substr_count($service, 'self::assertExecutionTypeAllowed(')
        );
        $this->assertStringContainsString('public static function replaceItem(', $service);
        $this->assertStringContainsString('$newItem = self::addItem($maintenance, $data, true);', $service);
    }
}
