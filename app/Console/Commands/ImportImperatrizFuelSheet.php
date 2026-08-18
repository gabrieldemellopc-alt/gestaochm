<?php

namespace App\Console\Commands;

use App\Models\FuelFilling;
use App\Models\FuelProduct;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ImportImperatrizFuelSheet extends Command
{
    protected $signature = 'chm:import-imperatriz-fuel {file} {--tenant-id=} {--division-id=} {--location-id=} {--fuel-product-id=} {--dry-run} {--commit} {--update-vehicle-readings}';
    protected $description = 'Importa histórico de abastecimentos de Imperatriz (dry-run por padrão)';

    public function handle(): int
    {
        if ($this->option('update-vehicle-readings')) {
            $this->warn('A opção --update-vehicle-readings está preterida e não sincroniza leituras. Use chm:sync-fuel-readings explicitamente após revisar o dry-run.');
        }
        $path = $this->argument('file');
        if (! is_file($path)) { $this->error('Arquivo não encontrado.'); return self::FAILURE; }
        foreach (['tenant-id', 'division-id', 'location-id'] as $option) {
            if (! $this->option($option)) { $this->error("Informe --{$option}."); return self::FAILURE; }
        }
        $tenantId = (int) $this->option('tenant-id'); $divisionId = (int) $this->option('division-id'); $locationId = (int) $this->option('location-id');
        $product = $this->fuelProduct($tenantId);
        if (! $product) { $this->error('Produto diesel não encontrado; informe --fuel-product-id.'); return self::FAILURE; }

        $indices = $this->vehicleIndices($tenantId, $divisionId, $locationId);
        $stats = ['lidas' => 0, 'importáveis' => 0, 'importadas' => 0, 'duplicadas' => 0, 'não encontradas' => 0, 'erros de dados' => 0, 'warnings' => 0];
        $totals = ['liters' => 0.0, 'value' => 0.0]; $found = []; $missing = []; $readySamples = []; $missingSamples = []; $header = null;
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $index => $line) {
            $cells = array_map('trim', preg_split('/\t|\|/', trim($line, " |\t")));
            if (! $header) { if (str_contains(mb_strtolower($line), 'data')) $header = array_map(fn ($value) => mb_strtolower(trim($value)), $cells); continue; }
            if (preg_match('/^-+$/', str_replace(['|', ' ', "\t"], '', $line))) continue;
            $lineNumber = $index + 1; $stats['lidas']++; $row = array_combine($header, array_pad($cells, count($header), ''));
            try {
                $date = $this->date($row['data'] ?? ''); $liters = $this->number($row['volume (l)'] ?? ''); $total = $this->number($row['valor total'] ?? ''); $unit = $this->number($row['valor por litro'] ?? ''); $km = $this->number($row['km atual'] ?? '');
                if (! $date || $liters <= 0 || ($total <= 0 && $unit <= 0)) throw new \RuntimeException('data/volume/valor inválido');
                if ($total <= 0) $total = round($liters * $unit, 2);
                [$vehicle, $method, $plateCandidate, $fleetCandidate] = $this->findVehicle($row, $indices);
                if (! $vehicle) {
                    $stats['não encontradas']++; $key = $plateCandidate ?: ($fleetCandidate ?: '(sem identificação)'); $missing[$key] = ($missing[$key] ?? 0) + 1;
                    if (count($missingSamples) < 10) $missingSamples[] = [$lineNumber, $row['veiculo'] ?? '', $row['frota'] ?? '', $row['veiculo2'] ?? '', $plateCandidate, $fleetCandidate];
                    continue;
                }
                if ($method === 'frota') { $stats['warnings']++; $this->warn("Linha {$lineNumber}: localizado por frota, placa da planilha divergente/ausente."); }
                $hash = sha1(implode('|', [$date->toDateString(), $vehicle->id, $liters, $total, $km])); $tag = "IMP-IMPERATRIZ-FUEL:{$hash}";
                if (FuelFilling::where('tenant_id', $tenantId)->where('notes', 'like', "%{$tag}%")->exists()) { $stats['duplicadas']++; continue; }
                $stats['importáveis']++; $totals['liters'] += $liters; $totals['value'] += $total; $label = "#{$vehicle->id} {$vehicle->plate} / {$vehicle->name}"; $found[$label] = ($found[$label] ?? 0) + 1;
                if (count($readySamples) < 10) $readySamples[] = [$lineNumber, $date->format('d/m/Y'), $plateCandidate, $row['frota'] ?? '', $label, number_format($liters, 3, ',', '.'), 'R$ '.number_format($total, 2, ',', '.')];
                if (! $this->option('commit')) continue;
                FuelFilling::create(['tenant_id' => $tenantId, 'division_id' => $divisionId, 'location_id' => $locationId, 'fuel_product_id' => $product->id, 'source' => FuelFilling::SOURCE_EXTERNAL_STATION, 'vehicle_id' => $vehicle->id, 'filled_at' => $date->setTime(12, 0), 'vehicle_km' => $km, 'quantity_liters' => $liters, 'unit_cost' => $unit ?: round($total / $liters, 4), 'total_cost' => $total, 'supplier_name' => 'Importação planilha Imperatriz', 'document_number' => 'PLANILHA-IMPERATRIZ-2026', 'notes' => "Importação histórica Imperatriz; frota=".($row['frota'] ?? '')."; km anterior=".($row['km anterior'] ?? '')."; percorrido=".($row['percorrido km'] ?? '')."; média=".($row['media km'] ?? '')."; {$tag}"]);
                $stats['importadas']++;
            } catch (\Throwable $exception) { $stats['erros de dados']++; $this->warn("Linha {$lineNumber}: {$exception->getMessage()}"); }
        }
        $this->table(array_keys($stats), [array_values($stats)]);
        $this->line('Total de litros importáveis: '.number_format($totals['liters'], 3, ',', '.'));
        $this->line('Total em R$ importável: R$ '.number_format($totals['value'], 2, ',', '.'));
        $this->line('Quantidade de veículos encontrados: '.count($found)); $this->line('Quantidade de veículos não encontrados: '.count($missing));
        $this->table(['Top 20 veículos não encontrados', 'linhas'], $this->top($missing)); $this->table(['Top 20 veículos encontrados/importáveis', 'linhas'], $this->top($found));
        $this->table(['linha', 'data', 'placa planilha', 'frota planilha', 'veículo encontrado', 'litros', 'valor total'], $readySamples);
        $this->table(['linha', 'Veiculo', 'Frota', 'Veiculo2', 'placa candidata', 'frota normalizada'], $missingSamples);
        $this->info($this->option('commit') ? 'Importação concluída.' : 'Dry-run: nenhuma gravação foi realizada.'); return self::SUCCESS;
    }

    public static function normalizePlate(mixed $value): string { return preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $value)); }
    public static function normalizeFleet(mixed $value): string { $fleet = self::normalizePlate($value); return preg_match('/^([A-Z]+)0*(\d+)$/', $fleet, $matches) ? $matches[1].str_pad($matches[2], 3, '0', STR_PAD_LEFT) : $fleet; }
    private function fuelProduct(int $tenantId): ?FuelProduct { return $this->option('fuel-product-id') ? FuelProduct::where('tenant_id', $tenantId)->find($this->option('fuel-product-id')) : FuelProduct::where('tenant_id', $tenantId)->where('active', true)->where(fn ($query) => $query->where('name', 'like', '%diesel%')->orWhere('name', 'like', '%s10%'))->first(); }
    private function vehicleIndices(int $tenantId, int $divisionId, int $locationId): array { $plates = []; $fleets = []; Vehicle::where('tenant_id', $tenantId)->where('division_id', $divisionId)->where('location_id', $locationId)->get()->each(function (Vehicle $vehicle) use (&$plates, &$fleets) { if ($plate = self::normalizePlate($vehicle->plate)) $plates[$plate] ??= $vehicle; foreach ([$vehicle->name, $vehicle->asset_code] as $fleet) if ($fleet = self::normalizeFleet($fleet)) $fleets[$fleet] ??= $vehicle; }); return compact('plates', 'fleets'); }
    private function findVehicle(array $row, array $indices): array { $values = [$row['veiculo'] ?? '', $row['veiculo2'] ?? '', $row['frota'] ?? '']; foreach ($values as $value) { $plate = self::normalizePlate($value); if ($this->looksLikePlate($plate) && isset($indices['plates'][$plate])) return [$indices['plates'][$plate], 'placa', $plate, self::normalizeFleet($row['frota'] ?? '')]; } foreach ($values as $value) { $fleet = self::normalizeFleet($value); if ($fleet && isset($indices['fleets'][$fleet])) return [$indices['fleets'][$fleet], 'frota', '', $fleet]; } $plate = collect($values)->map(fn ($value) => self::normalizePlate($value))->first(fn ($value) => $this->looksLikePlate($value)) ?: ''; return [null, null, $plate, self::normalizeFleet($row['frota'] ?? '')]; }
    private function looksLikePlate(string $value): bool { return (bool) preg_match('/^[A-Z]{3}[A-Z0-9]{4}$/', $value); }
    private function top(array $items): array { arsort($items); return array_map(fn ($key, $count) => [$key, $count], array_keys(array_slice($items, 0, 20, true)), array_slice($items, 0, 20, true)); }
    private function number(mixed $value): float { $value = preg_replace('/[^0-9,.-]/', '', (string) $value); return (float) str_replace(',', '.', str_replace('.', '', $value)); }
    private function date(mixed $value): ?Carbon { $value = preg_replace('/\s/', '', trim((string) $value)); if (preg_match('/^(\d{1,2})\/(\d{2})(\d{4})$/', $value, $matches)) $value = "{$matches[1]}/{$matches[2]}/{$matches[3]}"; try { return Carbon::createFromFormat('!d/m/Y', $value); } catch (\Throwable) { return null; } }
}
