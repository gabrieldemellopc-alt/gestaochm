<?php

namespace App\Services\FiscalDocuments;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

class NfeXmlParser
{
    public function parse(string $path): array
    {
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument();
        $loaded = $document->load($path, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            throw new RuntimeException('O arquivo XML é inválido ou está corrompido.');
        }

        $xpath = new DOMXPath($document);
        $infNfe = $xpath->query('//*[local-name()="NFe"]/*[local-name()="infNFe"]')->item(0);

        if (! $infNfe instanceof DOMElement) {
            throw new RuntimeException('O XML informado não contém uma NF-e válida (NFe ou nfeProc).');
        }

        $items = [];
        foreach ($xpath->query('./*[local-name()="det"]', $infNfe) as $detail) {
            $product = $xpath->query('./*[local-name()="prod"]', $detail)->item(0);
            if (! $product instanceof DOMElement) {
                continue;
            }

            $items[] = [
                'product_code' => $this->value($xpath, './*[local-name()="cProd"]', $product),
                'description' => $this->value($xpath, './*[local-name()="xProd"]', $product),
                'ncm' => $this->value($xpath, './*[local-name()="NCM"]', $product),
                'cfop' => $this->value($xpath, './*[local-name()="CFOP"]', $product),
                'unit' => $this->value($xpath, './*[local-name()="uCom"]', $product),
                'quantity' => $this->decimal($this->value($xpath, './*[local-name()="qCom"]', $product)),
                'unit_value' => $this->decimal($this->value($xpath, './*[local-name()="vUnCom"]', $product)),
                'total_value' => $this->decimal($this->value($xpath, './*[local-name()="vProd"]', $product)),
                'discount_value' => $this->decimal($this->value($xpath, './*[local-name()="vDesc"]', $product)),
                'ean' => $this->nullableEan($this->value($xpath, './*[local-name()="cEAN"]', $product)),
            ];
        }

        if ($items === []) {
            throw new RuntimeException('O XML da NF-e não possui itens de produtos (det).');
        }

        $identifier = preg_replace('/^NFe/i', '', $infNfe->getAttribute('Id'));
        $issuedAt = $this->value($xpath, './*[local-name()="ide"]/*[local-name()="dhEmi" or local-name()="dEmi"]', $infNfe);

        return [
            'import_source' => 'xml',
            'text_extracted' => true,
            'warning' => null,
            'warnings' => [],
            'number' => $this->value($xpath, './*[local-name()="ide"]/*[local-name()="nNF"]', $infNfe),
            'series' => $this->value($xpath, './*[local-name()="ide"]/*[local-name()="serie"]', $infNfe),
            'access_key' => strlen($identifier) === 44 ? $identifier : null,
            'issued_at' => $issuedAt,
            'supplier_name' => $this->value($xpath, './*[local-name()="emit"]/*[local-name()="xNome"]', $infNfe),
            'supplier_cnpj' => $this->value($xpath, './*[local-name()="emit"]/*[local-name()="CNPJ" or local-name()="CPF"]', $infNfe),
            'recipient_name' => $this->value($xpath, './*[local-name()="dest"]/*[local-name()="xNome"]', $infNfe),
            'recipient_cnpj' => $this->value($xpath, './*[local-name()="dest"]/*[local-name()="CNPJ" or local-name()="CPF"]', $infNfe),
            'products_total' => $this->decimal($this->value($xpath, './*[local-name()="total"]/*[local-name()="ICMSTot"]/*[local-name()="vProd"]', $infNfe)),
            'total_amount' => $this->decimal($this->value($xpath, './*[local-name()="total"]/*[local-name()="ICMSTot"]/*[local-name()="vNF"]', $infNfe)),
            'discount_total' => $this->decimal($this->value($xpath, './*[local-name()="total"]/*[local-name()="ICMSTot"]/*[local-name()="vDesc"]', $infNfe)),
            'freight_total' => $this->decimal($this->value($xpath, './*[local-name()="total"]/*[local-name()="ICMSTot"]/*[local-name()="vFrete"]', $infNfe)),
            'additional_information' => $this->value($xpath, './*[local-name()="infAdic"]/*[local-name()="infCpl"]', $infNfe),
            'items' => $items,
        ];
    }

    private function value(DOMXPath $xpath, string $query, DOMElement $context): ?string
    {
        $value = trim((string) $xpath->evaluate("string({$query})", $context));
        return $value === '' ? null : $value;
    }

    private function decimal(?string $value): float
    {
        return $value === null ? 0.0 : (float) str_replace(',', '.', $value);
    }

    private function nullableEan(?string $value): ?string
    {
        return in_array(strtoupper((string) $value), ['', 'SEM GTIN'], true) ? null : $value;
    }
}
