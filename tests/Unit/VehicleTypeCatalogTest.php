<?php
namespace Tests\Unit;
use App\Models\Vehicle;
use Tests\TestCase;
class VehicleTypeCatalogTest extends TestCase
{
    public function test_catalog_contains_new_types_and_icons_with_fallback(): void
    {
        foreach (['caminhonete'=>'caminhonete.png','onibus'=>'onibus.png','varredeira'=>'bobcat.png','pipa'=>'pipa.png','carroceria_aberta'=>'carroceria_aberta.png','retroescavadeira'=>'retro.png'] as $type => $icon) $this->assertSame($icon, Vehicle::iconForType($type));
        $this->assertSame('automovel.png', Vehicle::iconForType('desconhecido'));
    }
}
