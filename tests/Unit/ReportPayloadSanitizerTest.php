<?php

namespace Tests\Unit;

use App\Models\FuelFilling;
use App\Services\Reports\ReportPayloadSanitizer;
use PHPUnit\Framework\TestCase;

class ReportPayloadSanitizerTest extends TestCase
{
    public function test_it_preserves_structure_and_nulls_nested_monetary_values(): void
    {
        $model = new FuelFilling();
        $model->forceFill([
            'quantity_liters' => 25,
            'unit_cost' => 6.25,
            'total_cost' => 156.25,
        ]);

        $payload = [
            'context' => [
                'can_view_costs' => false,
            ],
            'cost_policy' => ['source' => 'registered'],
            'cost_flags' => ['cost_is_estimated' => true],
            'estimated_inventory_value' => 500,
            'nested' => [[
                'amount' => 10,
                'total_cost' => 20,
                'record' => $model,
            ]],
        ];

        $result = (new ReportPayloadSanitizer())->costs($payload, false);

        $this->assertArrayHasKey('estimated_inventory_value', $result);
        $this->assertNull($result['estimated_inventory_value']);
        $this->assertFalse($result['context']['can_view_costs']);
        $this->assertSame(['source' => 'registered'], $result['cost_policy']);
        $this->assertSame(['cost_is_estimated' => true], $result['cost_flags']);
        $this->assertArrayHasKey('amount', $result['nested'][0]);
        $this->assertNull($result['nested'][0]['amount']);
        $this->assertNull($result['nested'][0]['total_cost']);
        $this->assertSame(25.0, (float) $model->quantity_liters);
        $this->assertNull($model->unit_cost);
        $this->assertNull($model->total_cost);
    }
}
