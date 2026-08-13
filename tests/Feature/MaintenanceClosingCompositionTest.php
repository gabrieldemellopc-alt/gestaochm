<?php

namespace Tests\Feature;

use App\Models\MaintenanceRecord;
use Tests\TestCase;

class MaintenanceClosingCompositionTest extends TestCase
{
    public function test_order_without_active_composition_is_not_ready_to_close(): void
    {
        $this->assertFalse($this->maintenance(false, false, false)->hasAnyActiveComposition());
    }

    public function test_active_service_is_valid_composition(): void
    {
        $this->assertTrue($this->maintenance(true, false, false)->hasAnyActiveComposition());
    }

    public function test_active_material_without_service_is_valid_composition(): void
    {
        $this->assertTrue($this->maintenance(false, true, false)->hasAnyActiveComposition());
    }

    public function test_extra_cost_without_service_is_valid_composition(): void
    {
        $this->assertTrue($this->maintenance(false, false, true)->hasAnyActiveComposition());
    }

    public function test_close_flow_uses_composition_before_photo_validation(): void
    {
        $service = file_get_contents(app_path('Services/MaintenanceService.php'));
        $composition = strpos($service, 'hasAnyActiveComposition()');
        $photos = strpos($service, 'ensureCanClose($maintenance)', $composition);

        $this->assertNotFalse($composition);
        $this->assertNotFalse($photos);
        $this->assertLessThan($photos, $composition);
        $this->assertStringContainsString(
            'Antes de encerrar, registre ao menos um serviço, material utilizado ou custo avulso na ordem.',
            $service
        );
    }

    public function test_active_relations_exclude_cancelled_services_and_materials(): void
    {
        $model = file_get_contents(app_path('Models/MaintenanceRecord.php'));

        $this->assertStringContainsString('return $this->hasMany(MaintenanceRecordItem::class)->active();', $model);
        $this->assertStringContainsString('return $this->hasMany(MaintenanceMaterialUsage::class)->active();', $model);
        $this->assertStringContainsString('$this->extraCosts()->exists()', $model);
    }

    private function maintenance(bool $items, bool $materials, bool $extraCosts): MaintenanceRecord
    {
        $maintenance = new class extends MaintenanceRecord {
            public bool $hasItems = false;
            public bool $hasMaterials = false;
            public bool $hasExtraCosts = false;

            public function items() { return $this->relation($this->hasItems); }
            public function materialUsages() { return $this->relation($this->hasMaterials); }
            public function extraCosts() { return $this->relation($this->hasExtraCosts); }

            private function relation(bool $exists): object
            {
                return new class($exists) {
                    public function __construct(private readonly bool $exists) {}
                    public function exists(): bool { return $this->exists; }
                };
            }
        };

        $maintenance->hasItems = $items;
        $maintenance->hasMaterials = $materials;
        $maintenance->hasExtraCosts = $extraCosts;

        return $maintenance;
    }
}
