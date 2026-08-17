<?php

namespace App\Services\FiscalDocuments;

class DanfeTextParser
{
    private const NUMBER = '[\d.]+,\d+';

    public function parse(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = array_values(array_filter(array_map('trim', explode("\n", $text))));
        $items = $this->itemsFromClassicConcatenatedLayouts($text);
        if ($items === []) { $items = $this->itemsFromOriginalStrategy($text); }

        if ($items === []) {
            $items = $this->itemsFromDescriptionFirstLines($lines);
        }

        if ($items === []) {
            $items = $this->itemsFromCodeFirstLines($lines);
        }

        $cnpjs = [];
        preg_match_all('/\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}/', $text, $cnpjMatches);
        foreach ($cnpjMatches[0] ?? [] as $cnpj) {
            $normalized = preg_replace('/\D/', '', $cnpj);
            if (! in_array($normalized, $cnpjs, true)) {
                $cnpjs[] = $normalized;
            }
        }

        $key = null;
        if (preg_match('/(?:\d{4}\s+){10}\d{4}/', $text, $keyMatch)) {
            $candidate = preg_replace('/\D/', '', $keyMatch[0]);
            $key = strlen($candidate) === 44 ? $candidate : null;
        }

        return [
            'import_source' => 'pdf',
            'text_extracted' => trim($text) !== '',
            'warning' => trim($text) === ''
                ? 'Não foi possível ler automaticamente os dados deste PDF. Para importação automática completa, envie o XML da NF-e ou preencha os dados manualmente.'
                : ($items === [] ? 'O cabeçalho foi lido, mas nenhum item foi reconhecido. Envie o XML da NF-e ou adicione os itens manualmente.' : null),
            'warnings' => [],
            'number' => $this->match('/N[º°o]\.?\s*([\d.]+)/iu', $text),
            'series' => $this->match('/S[ÉE]RIE\s*(\d+)/iu', $text),
            'access_key' => $key,
            'issued_at' => $this->match('/(?:EMISS[ÃA]O:|DATA (?:DA|DE) EMISS[ÃA]O)\s*(\d{2}\/\d{2}\/\d{4})/iu', $text),
            'supplier_name' => $this->match('/RECEBEMOS DE\s+(.+?)\s+OS PRODUTOS/iu', $text)
                ?? $this->match('/IDENTIFICA(?:Ç|C)[ÃA]O DO EMITENTE\s*\n(?:.*\n){0,3}?([^\n]+)/iu', $text),
            'supplier_cnpj' => $cnpjs[0] ?? null,
            'recipient_name' => $this->match('/DEST\.\s*\/\s*REM\.:\s*(.+?)\s*-\s*VALOR TOTAL/iu', $text)
                ?? $this->match('/DESTINAT[ÁA]RIO \/ REMETENTE.*?NOME \/ RAZ[ÃA]O SOCIAL\s*\n([^\n]+)/isu', $text),
            'recipient_cnpj' => $cnpjs[1] ?? null,
            'products_total' => $this->money($this->match('/VALOR TOTAL DOS PRODUTOS\s*([\d.]+,\d+)/iu', $text)),
            'discount_total' => $this->money($this->match('/(?:VALOR DO )?DESCONTO\s*([\d.]+,\d+)/iu', $text)),
            'total_amount' => $this->money($this->match('/VALOR TOTAL DA NOTA\s*([\d.]+,\d+)/iu', $text)),
            'items' => $items,
        ];
    }

    private function itemsFromClassicConcatenatedLayouts(string $text): array
    {
        $number = '[\d.]+,\d{2}';
        $items = [];
        $first = '/^(?<total>'.$number.')(?<ncm>\d{8})(?<code>[A-Z0-9]+)\s+(?<cst>\d{3,4})(?<cfop>\d{4})(?<unit>[A-Z]{1,6})\s+(?<quantity>'.$number.')\s+(?<unit_value>'.$number.')\s*(?<line_total>'.$number.').*?(?<description>[A-Z][A-Z0-9 \/-]+)$/mu';
        preg_match_all($first, $text, $rows, PREG_SET_ORDER);
        foreach ($rows as $row) $items[] = $this->item($row['code'], trim($row['description']), $row['ncm'], $row['cst'], $row['cfop'], $row['unit'], $row['quantity'], $row['unit_value'], $row['line_total'], 0);
        if ($items !== []) return $items;
        $second = '/^(?<code>[A-Z0-9]+)\s+(?<description>.+?)\t(?<ncm>\d{8})(?<cst>\d{3,4})(?<cfop>\d{4})(?<unit>[A-Z]{1,6})\s+(?<quantity>\d+,\d{4})(?<unit_value>'.$number.')(?<line_total>'.$number.')/mu';
        preg_match_all($second, $text, $rows, PREG_SET_ORDER);
        foreach ($rows as $row) $items[] = $this->item($row['code'], trim($row['description']), $row['ncm'], $row['cst'], $row['cfop'], $row['unit'], $row['quantity'], $row['unit_value'], $row['line_total'], 0);
        return $items;
    }
    private function itemsFromOriginalStrategy(string $text): array
    {
        preg_match_all('/(?:^|\n)(\S+)\s+(.+?)\s+(\d{8})\s+(\d{4})\s+([A-Z]{1,6})\s+([\d.,]+)\s+([\d.,]+)\s+([\d.,]+)(?:\s|$)/mu', $text, $rows, PREG_SET_ORDER);

        return array_map(fn (array $row) => $this->item(
            $row[1], $row[2], $row[3], null, $row[4], $row[5], $row[6], $row[7], $row[8], 0
        ), $rows);
    }

    private function itemsFromDescriptionFirstLines(array $lines): array
    {
        $number = self::NUMBER;
        $money = '[\\d.]+,\\d{2}';
        $pattern = "/^(?<description>.+)(?<code>\\d{6,})\\s+(?<cfop>\\d{4})\\s+(?<unit>[A-Z]{1,6})\\s+(?<quantity>{$number})\\s+(?<unit_value>{$number})\\s+(?<total_value>{$money})\\s+(?<discount>{$money})(?:\\s+{$money}){5}\\s*(?<cst>\\d{3,4})(?<ncm>\\d{8})$/u";

        return $this->matchingLines($lines, $pattern);
    }

    private function itemsFromCodeFirstLines(array $lines): array
    {
        $number = self::NUMBER;
        $money = '[\\d.]+,\\d{2}';
        $pattern = "/^(?<code>\\d+)\\s+(?<cfop>\\d{4})\\s+(?<unit>[A-Z]{1,6})\\s+(?<quantity>{$number})\\s+(?<unit_value>{$number})\\s+(?<total_value>{$money})\\s+(?<discount>{$money})(?:\\s+{$money}){5}\\s+(?<description>.+)\\s+(?<ncm>\\d{8})\\s+(?<cst>\\d{3,4})$/u";

        return $this->matchingLines($lines, $pattern);
    }

    private function matchingLines(array $lines, string $pattern): array
    {
        $items = [];
        foreach ($lines as $line) {
            if (! preg_match($pattern, $line, $match)) {
                continue;
            }
            $items[] = $this->item($match['code'], trim($match['description']), $match['ncm'], $match['cst'], $match['cfop'], $match['unit'], $match['quantity'], $match['unit_value'], $match['total_value'], $match['discount']);
        }
        return $items;
    }

    private function item(string $code, string $description, string $ncm, ?string $cst, string $cfop, string $unit, mixed $quantity, mixed $unitValue, mixed $totalValue, mixed $discount): array
    {
        return ['product_code'=>$code, 'description'=>$description, 'ncm'=>$ncm, 'cst'=>$cst, 'cfop'=>$cfop, 'unit'=>$unit,
            'quantity'=>$this->money($quantity), 'unit_value'=>$this->money($unitValue), 'total_value'=>$this->money($totalValue), 'discount_value'=>$this->money($discount)];
    }

    private function match(string $pattern, string $text): ?string
    {
        return preg_match($pattern, $text, $match) ? trim($match[1]) : null;
    }

    private function money(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        return (float) str_replace(',', '.', str_replace('.', '', (string) $value));
    }
}
