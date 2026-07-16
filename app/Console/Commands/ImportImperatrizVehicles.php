<?php

namespace App\Console\Commands;

use App\Models\Division;
use App\Models\Location;
use App\Models\Vehicle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportImperatrizVehicles extends Command
{
    protected $signature = 'chm:import-imperatriz-vehicles
        {--dry-run : Executa a simulacao sem gravar alteracoes. Este e o comportamento padrao.}
        {--commit : Grava as criacoes/atualizacoes. Sem esta opcao roda em dry-run.}
        {--move-test-from-imperatriz-to-barreiras : Diagnostica veiculos de teste em Imperatriz para possivel realocacao segura.}';

    protected $description = 'Importa de forma controlada os veiculos reais da unidade Imperatriz.';

    private const TEST_TERMS = ['teste', 'test', 'homolog'];

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');
        $dryRun = (bool) $this->option('dry-run');

        if ($commit && $dryRun) {
            $this->error('Use apenas uma opcao: --dry-run ou --commit.');

            return self::FAILURE;
        }

        $this->info($commit
            ? 'IMPORTACAO IMPERATRIZ - MODO COMMIT'
            : 'IMPORTACAO IMPERATRIZ - DRY-RUN');

        $locations = $this->resolveLocations();

        if (! $locations) {
            return self::FAILURE;
        }

        [$imperatriz, $barreiras] = $locations;
        $vehicles = collect($this->vehicles());
        $plates = $vehicles->pluck('plate')->all();
        $existingByPlate = Vehicle::query()
            ->with(['division', 'location'])
            ->whereIn('plate', $plates)
            ->get()
            ->keyBy('plate');

        $report = [
            'created' => [],
            'updated' => [],
            'ignored' => [],
            'conflicts' => [],
            'errors' => [],
        ];

        DB::transaction(function () use ($commit, $vehicles, $existingByPlate, $imperatriz, &$report) {
            foreach ($vehicles as $vehicleData) {
                $plate = $vehicleData['plate'];
                $existing = $existingByPlate->get($plate);

                if ($existing && (int) $existing->location_id !== (int) $imperatriz->id) {
                    $report['conflicts'][] = [
                        'plate' => $plate,
                        'message' => "Ja existe em {$existing->location?->name} (vehicle_id {$existing->id}). Nao sera movido.",
                    ];

                    continue;
                }

                if ($existing) {
                    $changes = $this->safeUpdates($existing, $vehicleData);

                    if ($changes === []) {
                        $report['ignored'][] = [
                            'plate' => $plate,
                            'message' => "Ja cadastrado em Imperatriz sem alteracoes necessarias (vehicle_id {$existing->id}).",
                        ];

                        continue;
                    }

                    if ($commit) {
                        $existing->update($changes);
                    }

                    $report['updated'][] = [
                        'plate' => $plate,
                        'id' => $existing->id,
                        'changes' => $changes,
                    ];

                    continue;
                }

                $payload = $this->creationPayload($vehicleData, $imperatriz);

                if ($commit) {
                    $created = Vehicle::create($payload);
                    $payload['id'] = $created->id;
                }

                $report['created'][] = [
                    'plate' => $plate,
                    'payload' => $payload,
                ];
            }
        });

        $this->renderReport($report);

        if ($this->option('move-test-from-imperatriz-to-barreiras')) {
            $this->diagnoseTestVehicles($imperatriz, $barreiras);
        }

        if (! $commit) {
            $this->warn('Dry-run concluido. Nenhuma alteracao foi gravada.');
            $this->line('Para gravar apos conferencia: php artisan chm:import-imperatriz-vehicles --commit');
        }

        return $report['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }

    private function resolveLocations(): ?array
    {
        $imperatriz = Location::query()
            ->whereRaw('LOWER(name) = ?', ['imperatriz'])
            ->first();

        $barreiras = Location::query()
            ->whereRaw('LOWER(name) = ?', ['barreiras'])
            ->first();

        if (! $imperatriz) {
            $this->error('Unidade Imperatriz nao encontrada no banco.');
            return null;
        }

        if (! $barreiras) {
            $this->error('Unidade Barreiras nao encontrada no banco.');
            return null;
        }

        if ((int) $imperatriz->tenant_id !== (int) $barreiras->tenant_id) {
            $this->error('Imperatriz e Barreiras pertencem a tenants diferentes. Importacao bloqueada.');
            return null;
        }

        $division = Division::query()->find($imperatriz->division_id);

        if (! $division) {
            $this->error('Divisao da unidade Imperatriz nao encontrada.');
            return null;
        }

        $this->line("Destino: tenant {$imperatriz->tenant_id}, division {$division->id} ({$division->name}), location {$imperatriz->id} ({$imperatriz->name})");
        $this->line("Homologacao: location {$barreiras->id} ({$barreiras->name})");

        return [$imperatriz, $barreiras];
    }

    private function safeUpdates(Vehicle $vehicle, array $data): array
    {
        $changes = [];

        foreach (['asset_code', 'model', 'year'] as $field) {
            if ((string) ($vehicle->{$field} ?? '') !== (string) $data[$field]) {
                $changes[$field] = $data[$field];
            }
        }

        if (! $vehicle->type) {
            $changes['type'] = 'lixo';
        }

        if (! $vehicle->status) {
            $changes['status'] = 'active';
        }

        if (! $vehicle->operational_status) {
            $changes['operational_status'] = 'operational';
        }

        return $changes;
    }

    private function creationPayload(array $data, Location $imperatriz): array
    {
        return [
            'tenant_id' => $imperatriz->tenant_id,
            'division_id' => $imperatriz->division_id,
            'location_id' => $imperatriz->id,
            'asset_code' => $data['asset_code'],
            'name' => $data['asset_code'],
            'plate' => $data['plate'],
            'brand' => null,
            'model' => $data['model'],
            'year' => $data['year'],
            'type' => 'lixo',
            'tire_layout' => 'truck_6_mixed',
            'current_km' => 0,
            'current_hours' => 0,
            'status' => 'active',
            'operational_status' => 'operational',
            'notes' => 'Importacao controlada de veiculos reais de Imperatriz.',
        ];
    }

    private function renderReport(array $report): void
    {
        foreach (['created' => 'Criados', 'updated' => 'Atualizados', 'ignored' => 'Ignorados', 'conflicts' => 'Conflitos', 'errors' => 'Erros'] as $key => $title) {
            $this->newLine();
            $this->line("{$title}: ".count($report[$key]));

            foreach ($report[$key] as $row) {
                $this->line(' - '.$this->formatRow($key, $row));
            }
        }
    }

    private function formatRow(string $type, array $row): string
    {
        return match ($type) {
            'created' => "{$row['plate']} criar {$row['payload']['asset_code']} {$row['payload']['model']} {$row['payload']['year']}",
            'updated' => "{$row['plate']} vehicle_id {$row['id']} atualizar ".implode(', ', array_keys($row['changes'])),
            default => "{$row['plate']} {$row['message']}",
        };
    }

    private function diagnoseTestVehicles(Location $imperatriz, Location $barreiras): void
    {
        $this->newLine();
        $this->warn('Diagnostico de veiculos de teste em Imperatriz para possivel movimentacao.');

        $candidates = Vehicle::query()
            ->where('tenant_id', $imperatriz->tenant_id)
            ->where('division_id', $imperatriz->division_id)
            ->where('location_id', $imperatriz->id)
            ->get()
            ->filter(fn (Vehicle $vehicle) => $this->looksLikeTestVehicle($vehicle))
            ->values();

        if ($candidates->isEmpty()) {
            $this->line('Nenhum candidato claramente identificado como teste em Imperatriz.');
            return;
        }

        foreach ($candidates as $vehicle) {
            $links = $this->vehicleLinks($vehicle);
            $this->line(" - vehicle_id {$vehicle->id} {$vehicle->name} {$vehicle->plate}: vinculos ".json_encode($links));

            if (array_sum($links) > 0) {
                $this->warn('   Nao mover automaticamente: existem vinculos relevantes.');
            } else {
                $this->line("   Poderia ser movido para Barreiras ({$barreiras->id}) em bloco futuro com confirmacao explicita.");
            }
        }
    }

    private function looksLikeTestVehicle(Vehicle $vehicle): bool
    {
        $text = mb_strtolower(implode(' ', [
            $vehicle->name,
            $vehicle->plate,
            $vehicle->asset_code,
            $vehicle->model,
            $vehicle->notes,
        ]));

        foreach (self::TEST_TERMS as $term) {
            if (str_contains($text, $term)) {
                return true;
            }
        }

        return false;
    }

    private function vehicleLinks(Vehicle $vehicle): array
    {
        $links = [];

        foreach ([
            'maintenance_records',
            'vehicle_update_logs',
            'vehicle_operations',
            'checklist_executions',
            'tire_installations',
            'tire_measurements',
            'fuel_fillings',
            'vehicle_downtime_periods',
        ] as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                $links[$table] = DB::table($table)->where('vehicle_id', $vehicle->id)->count();
            }
        }

        return $links;
    }

    private function vehicles(): array
    {
        return array_map(fn (array $row) => [
            'asset_code' => $row[0],
            'plate' => strtoupper($row[1]),
            'brand' => 'VW',
            'model' => $row[2],
            'year' => (int) substr($row[3], 0, 4),
        ], [
            ['VCA001', 'TMO-8H32', 'VW 18.260 4x2', '2025/2026'],
            ['VCA002', 'TMS-4A23', 'VW 18.260 4x2', '2025/2026'],
            ['VCA003', 'FZO-6F26', 'VW 17.260 4x2 AUT', '2019/2020'],
            ['VCA004', 'JAC-2E39', 'VW 17.190 4x2', '2019/2020'],
            ['VCA005', 'THG-4E23', 'VW 18.260 4x2', '2025/2026'],
            ['VCA006', 'JAC-2E37', 'VW 17.190 4x2', '2020/2021'],
            ['VCA007', 'TMS-1J41', 'VW 26.260 6x2', '2025/2026'],
            ['VCA008', 'TMW-6H93', 'VW 18.260 4x2', '2025/2026'],
            ['VCA009', 'JAC-2E43', 'VW 17.190 4x2', '2020/2021'],
            ['VCA010', 'THG-9B61', 'VW 18.260 4x2', '2025/2026'],
            ['VCA011', 'TMW-7B90', 'VW 18.260 4x2', '2025/2026'],
            ['VCA012', 'TMT-3G93', 'VW 18.260 4x2', '2025/2026'],
            ['VCA013', 'TMN-6E70', 'VW 18.260 4x2', '2025/2026'],
            ['VCA014', 'TMW-6B79', 'VW 18.260 4x2', '2025/2026'],
            ['VCA015', 'TMS-3I82', 'VW 18.260 4x2', '2025/2026'],
            ['VCA016', 'TMR-6E70', 'VW 26.260 4x2', '2025/2026'],
            ['VCA017', 'TMN-8G17', 'VW 18.260 4x2', '2025/2026'],
            ['VCA018', 'TMT-6B36', 'VW 18.260 4x2', '2025/2026'],
        ]);
    }
}
