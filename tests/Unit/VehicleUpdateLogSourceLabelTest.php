<?php

namespace Tests\Unit;

use App\Models\VehicleUpdateLog;
use Tests\TestCase;

class VehicleUpdateLogSourceLabelTest extends TestCase
{
    public function test_known_reading_sources_have_friendly_labels(): void
    {
        $this->assertSame('Abastecimento', VehicleUpdateLog::sourceLabel('fuel_filling'));
        $this->assertSame('Abertura de OM', VehicleUpdateLog::sourceLabel('maintenance_open'));
        $this->assertSame('Atualização manual', VehicleUpdateLog::sourceLabel('dashboard_quick_update'));
    }

    public function test_unknown_source_has_a_safe_fallback(): void
    {
        $this->assertSame('Registro de leitura', VehicleUpdateLog::sourceLabel('legacy_unknown_source'));
    }
}
