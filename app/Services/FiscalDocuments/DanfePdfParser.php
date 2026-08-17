<?php
namespace App\Services\FiscalDocuments;
use Smalot\PdfParser\Parser;
class DanfePdfParser
{
    public function __construct(private readonly DanfeTextParser $textParser)
    {
    }

    public function parse(string $path): array
    {
        return $this->parseText((new Parser)->parseFile($path)->getText());
    }

    public function parseText(string $text): array
    {
        return $this->textParser->parse($text);
    }
}
