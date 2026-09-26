<?php

namespace App\Console\Commands;

use App\Models\FuelTank;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ReconcileImperatrizDieselCosts extends Command
{
    protected $signature =
        'chm:reconcile-imperatriz-diesel-costs
        {--dry-run}
        {--commit}
        {--confirm-location=}
        {--confirm-tank=}';

    protected $description =
        'Reconstrói cronologicamente o custo médio do Diesel 01 de Imperatriz a partir de 03/09/2026.';

    private const LOCATION_ID = 3;
    private const TANK_ID = 3;
    private const START_AT = '2026-09-03 00:00:00';

    /*
     * Nestes dias a operação confirmou que o recebimento ocorreu
     * antes dos abastecimentos, apesar dos horários históricos.
     */
    private const RECEIPT_FIRST_DATES = [
        '2026-09-03',
        '2026-09-10',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $commit = (bool) $this->option('commit');

        if ($dryRun === $commit) {
            $this->error(
                'Use exatamente uma opção: --dry-run ou --commit.'
            );

            return self::FAILURE;
        }

        if (
            (int) $this->option('confirm-location') !== self::LOCATION_ID
            || (int) $this->option('confirm-tank') !== self::TANK_ID
        ) {
            $this->error(
                'Confirme explicitamente com '
                .'--confirm-location=3 --confirm-tank=3.'
            );

            return self::FAILURE;
        }

        if ($dryRun) {
            $tank = FuelTank::query()->find(self::TANK_ID);

            if (! $tank || (int) $tank->location_id !== self::LOCATION_ID) {
                $this->error('Tanque Diesel 01 de Imperatriz não encontrado.');

                return self::FAILURE;
            }

            $plan = $this->buildPlan($tank);

            return $this->renderPlan($tank, $plan, false);
        }

        return DB::transaction(function () {
            $tank = FuelTank::query()
                ->whereKey(self::TANK_ID)
                ->lockForUpdate()
                ->first();

            if (! $tank || (int) $tank->location_id !== self::LOCATION_ID) {
                $this->error('Tanque Diesel 01 de Imperatriz não encontrado.');

                return self::FAILURE;
            }

            $plan = $this->buildPlan($tank);

            if (! empty($plan['errors'])) {
                $this->error(
                    'Reconciliação bloqueada: existem inconsistências.'
                );

                $this->renderPlan($tank, $plan, false);

                return self::FAILURE;
            }

            if (
                abs(
                    $plan['balance']
                    - (float) $tank->current_balance_liters
                ) > 0.0005
            ) {
                $this->error(
                    'Reconciliação bloqueada: o saldo reconstruído '
                    .'não coincide com o saldo atual do tanque.'
                );

                $this->renderPlan($tank, $plan, false);

                return self::FAILURE;
            }

            $backupPath = $this->createBackup($tank, $plan);

            foreach ($plan['changes'] as $change) {
                DB::table('fuel_fillings')
                    ->where('id', $change['id'])
                    ->update([
                        'unit_cost' => $change['new_unit'],
                        'total_cost' => $change['new_total'],
                    ]);
            }

            $tank->forceFill([
                /*
                 * O saldo físico não é recalculado por diferença.
                 * Ele obrigatoriamente já deve coincidir com o replay.
                 */
                'current_balance_liters' => $plan['balance'],
                'estimated_stock_value' => $plan['stock_value'],
                'average_unit_cost' => $plan['average'],
            ])->save();

            $this->renderPlan($tank, $plan, true);

            $this->info('Backup: '.$backupPath);
            $this->info('COMMIT concluído com sucesso.');

            return self::SUCCESS;
        });
    }

    private function buildPlan(FuelTank $tank): array
    {
        $receipts = DB::table('fuel_receipts')
            ->where('fuel_tank_id', $tank->id)
            ->whereNull('cancelled_at')
            ->where('received_at', '>=', self::START_AT)
            ->get();

        $missingCost = $receipts
            ->filter(fn ($receipt) => $receipt->total_cost === null);

        if ($missingCost->isNotEmpty()) {
            return [
                'errors' => [
                    'Existem recebimentos válidos sem valor total: '
                    .$missingCost->pluck('id')->implode(', '),
                ],
                'changes' => [],
                'receipts_count' => $receipts->count(),
                'fillings_count' => 0,
                'balance' => 0.0,
                'stock_value' => 0.0,
                'average' => 0.0,
            ];
        }

        $fillings = DB::table('fuel_fillings')
            ->where('fuel_tank_id', $tank->id)
            ->where('source', 'internal_tank')
            ->whereNull('cancelled_at')
            ->where('filled_at', '>=', self::START_AT)
            ->get();

        $events = [];

        foreach ($receipts as $receipt) {
            $events[] = [
                'type' => 'receipt',
                'at' => (string) $receipt->received_at,
                'id' => (int) $receipt->id,
                'qty' => (float) $receipt->quantity_liters,
                'value' => (float) $receipt->total_cost,
            ];
        }

        foreach ($fillings as $filling) {
            $events[] = [
                'type' => 'filling',
                'at' => (string) $filling->filled_at,
                'id' => (int) $filling->id,
                'qty' => (float) $filling->quantity_liters,
                'old_unit' => (float) ($filling->unit_cost ?? 0),
                'old_total' => (float) ($filling->total_cost ?? 0),
            ];
        }

        usort($events, function (array $a, array $b) {
            return strcmp(
                $this->eventSortKey($a),
                $this->eventSortKey($b)
            );
        });

        $balance = 0.0;
        $stockValue = 0.0;
        $average = 0.0;
        $errors = [];
        $changes = [];

        foreach ($events as $event) {
            if ($event['type'] === 'receipt') {
                $balance = round(
                    $balance + $event['qty'],
                    3
                );

                $stockValue = round(
                    $stockValue + $event['value'],
                    2
                );

                $average = $balance > 0
                    ? round($stockValue / $balance, 4)
                    : 0;

                continue;
            }

            if ($event['qty'] > $balance + 0.0001) {
                $errors[] = [
                    'id' => $event['id'],
                    'at' => $event['at'],
                    'qty' => $event['qty'],
                    'balance_before' => $balance,
                ];

                continue;
            }

            /*
             * Mesma regra utilizada pelo FuelService:
             * custo unitário em 4 casas e total em 2 casas.
             */
            $newUnit = round($average, 4);
            $newTotal = round(
                $event['qty'] * $newUnit,
                2
            );

            if (
                abs($event['old_unit'] - $newUnit) >= 0.0001
                || abs($event['old_total'] - $newTotal) >= 0.01
            ) {
                $changes[] = [
                    'id' => $event['id'],
                    'at' => $event['at'],
                    'qty' => $event['qty'],
                    'old_unit' => $event['old_unit'],
                    'new_unit' => $newUnit,
                    'old_total' => $event['old_total'],
                    'new_total' => $newTotal,
                ];
            }

            $balance = round(
                $balance - $event['qty'],
                3
            );

            $stockValue = round(
                max(0, $stockValue - $newTotal),
                2
            );

            $average = $balance > 0
                ? round($stockValue / $balance, 4)
                : 0;
        }

        return [
            'errors' => $errors,
            'changes' => $changes,
            'receipts_count' => $receipts->count(),
            'fillings_count' => $fillings->count(),
            'received_liters' => round(
                $receipts->sum('quantity_liters'),
                3
            ),
            'filled_liters' => round(
                $fillings->sum('quantity_liters'),
                3
            ),
            'balance' => $balance,
            'stock_value' => $stockValue,
            'average' => $average,
        ];
    }

    private function eventSortKey(array $event): string
    {
        $date = substr($event['at'], 0, 10);
        $id = str_pad(
            (string) $event['id'],
            10,
            '0',
            STR_PAD_LEFT
        );

        if (in_array($date, self::RECEIPT_FIRST_DATES, true)) {
            $priority = $event['type'] === 'receipt'
                ? '0'
                : '1';

            return $date
                .'|'.$priority
                .'|'.$event['at']
                .'|'.$id;
        }

        $priority = $event['type'] === 'receipt'
            ? '0'
            : '1';

        return $event['at']
            .'|'.$priority
            .'|'.$id;
    }

    private function createBackup(
        FuelTank $tank,
        array $plan
    ): string {
        $dir = storage_path(
            'app/reconciliations/fuel'
        );

        File::ensureDirectoryExists($dir);

        $path = $dir
            .'/imperatriz-diesel-costs-before-'
            .now()->format('Ymd_His')
            .'.json';

        $fillings = DB::table('fuel_fillings')
            ->where('fuel_tank_id', $tank->id)
            ->where('source', 'internal_tank')
            ->whereNull('cancelled_at')
            ->where('filled_at', '>=', self::START_AT)
            ->orderBy('id')
            ->get([
                'id',
                'filled_at',
                'quantity_liters',
                'unit_cost',
                'total_cost',
                'source_unit_cost',
                'source_total_cost',
                'updated_at',
            ]);

        File::put(
            $path,
            json_encode(
                [
                    'created_at' => now()->toIso8601String(),
                    'tank_before' => $tank->getAttributes(),
                    'expected_after' => [
                        'current_balance_liters' => $plan['balance'],
                        'estimated_stock_value' => $plan['stock_value'],
                        'average_unit_cost' => $plan['average'],
                    ],
                    'fillings_before' => $fillings,
                ],
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            )
        );

        return $path;
    }

    private function renderPlan(
        FuelTank $tank,
        array $plan,
        bool $committed
    ): int {
        $this->table(
            ['Item', 'Atual', 'Reconstruído'],
            [
                [
                    'Saldo (L)',
                    number_format(
                        (float) $tank->current_balance_liters,
                        3,
                        ',',
                        '.'
                    ),
                    number_format(
                        (float) $plan['balance'],
                        3,
                        ',',
                        '.'
                    ),
                ],
                [
                    'Valor estoque (R$)',
                    number_format(
                        (float) $tank->estimated_stock_value,
                        2,
                        ',',
                        '.'
                    ),
                    number_format(
                        (float) $plan['stock_value'],
                        2,
                        ',',
                        '.'
                    ),
                ],
                [
                    'Custo médio (R$/L)',
                    number_format(
                        (float) $tank->average_unit_cost,
                        4,
                        ',',
                        '.'
                    ),
                    number_format(
                        (float) $plan['average'],
                        4,
                        ',',
                        '.'
                    ),
                ],
            ]
        );

        $this->line(
            'Recebimentos válidos: '
            .$plan['receipts_count']
        );

        $this->line(
            'Abastecimentos válidos: '
            .$plan['fillings_count']
        );

        $this->line(
            'Abastecimentos com custo a corrigir: '
            .count($plan['changes'])
        );

        if (! empty($plan['errors'])) {
            foreach ($plan['errors'] as $error) {
                $this->error(
                    is_array($error)
                        ? json_encode(
                            $error,
                            JSON_UNESCAPED_UNICODE
                        )
                        : $error
                );
            }

            return self::FAILURE;
        }

        if (! $committed) {
            $this->info(
                'DRY-RUN: nenhuma alteração foi persistida.'
            );
        }

        return self::SUCCESS;
    }
}
