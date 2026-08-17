<?php

namespace Tests\Feature;

use App\Services\FiscalDocuments\DanfePdfParser;
use Tests\TestCase;

class DanfePdfParserTest extends TestCase
{
    public function test_it_extracts_the_three_items_from_nfe_10509_layout(): void
    {
        $data = app(DanfePdfParser::class)->parseText(
            file_get_contents(base_path('tests/Fixtures/danfe-10509-smalot.txt'))
        );

        $this->assertCount(3, $data['items']);
        $this->assertSame('MOLA SAPATA FREIO GRANDE BRINCO', $data['items'][0]['description']);
        $this->assertSame('MOLA SAPATA FREIO PEQ 13/40T 7/9T', $data['items'][1]['description']);
        $this->assertSame('TAMBOR FREIO 10F LONA TH166/TH165', $data['items'][2]['description']);
        $this->assertSame(['87089990', '73209000', '87083090'], array_column($data['items'], 'ncm'));
        $this->assertSame(['0500', '0500', '0500'], array_column($data['items'], 'cst'));
        $this->assertSame(8.0, $data['items'][0]['quantity']);
        $this->assertSame(930.0, $data['items'][2]['unit_value']);
        $this->assertSame(1860.0, $data['items'][2]['total_value']);
        $this->assertSame(2050.0, array_sum(array_column($data['items'], 'total_value')));
    }

    public function test_it_extracts_the_header_from_nfe_10509_layout(): void
    {
        $data = app(DanfePdfParser::class)->parseText(
            file_get_contents(base_path('tests/Fixtures/danfe-10509-smalot.txt'))
        );

        $this->assertSame('000.010.509', $data['number']);
        $this->assertSame('001', $data['series']);
        $this->assertSame('11/08/2026', $data['issued_at']);
        $this->assertSame('D BRANDAO NEVES', $data['supplier_name']);
        $this->assertSame('11893920000100', $data['supplier_cnpj']);
        $this->assertSame('AKSA SERVICOS DE LOCACAO DE MAO DE OBRA TEMPORARIA', $data['recipient_name']);
        $this->assertSame('35942532000122', $data['recipient_cnpj']);
        $this->assertSame('21260811893920000100550010000105091702206180', $data['access_key']);
        $this->assertSame(2050.0, $data['products_total']);
        $this->assertSame(2050.0, $data['total_amount']);
    }

    public function test_it_keeps_items_empty_when_no_valid_product_line_exists(): void
    {
        $data = app(DanfePdfParser::class)->parseText("NF-e\nNº 123\nSÉRIE 1");

        $this->assertSame([], $data['items']);
        $this->assertStringContainsString('nenhum item foi reconhecido', $data['warning']);
    }

    public function test_it_supports_code_first_description_last_lines(): void
    {
        $text = '000299 6403 UN 8,0000 20,0000 160,00 0,00 0,00 0,00 0,00 0,00 0,00 MOLA SAPATA FREIO GRANDE BRINCO 87089990 0500';
        $data = app(DanfePdfParser::class)->parseText($text);

        $this->assertCount(1, $data['items']);
        $this->assertSame('000299', $data['items'][0]['product_code']);
        $this->assertSame('MOLA SAPATA FREIO GRANDE BRINCO', $data['items'][0]['description']);
        $this->assertSame('87089990', $data['items'][0]['ncm']);
        $this->assertSame('0500', $data['items'][0]['cst']);
    }
}
