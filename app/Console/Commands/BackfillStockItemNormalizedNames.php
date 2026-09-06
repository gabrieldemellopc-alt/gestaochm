<?php

namespace App\Console\Commands;

use App\Services\StockItemNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillStockItemNormalizedNames extends Command
{
    protected $signature = 'chm:backfill-stock-item-normalized-names
                            {--commit : Persist calculated normalized names}';

    protected $description = 'Backfill normalized_name for stock items without changing their names or merging duplicates';

    public function handle(StockItemNormalizer $normalizer): int
    {
        $commit = (bool) $this->option('commit');

        $this->components->info($commit ? 'COMMIT mode' : 'DRY-RUN mode (no database writes)');

        $stats = $this->backfill($normalizer, $commit);

        $this->table(
            ['Scanned', $commit ? 'Updated' : 'Would update', 'Unchanged'],
            [[$stats['scanned'], $stats['would_update'], $stats['unchanged']]],
        );
        $this->components->info(
            $commit
                ? 'Backfill committed. No stock item names were changed or merged.'
                : 'Dry-run complete. Run again with --commit to persist normalized names.',
        );

        return self::SUCCESS;
    }

    /** @return array{scanned: int, would_update: int, unchanged: int} */
    public function backfill(StockItemNormalizer $normalizer, bool $commit): array
    {
        $stats = ['scanned' => 0, 'would_update' => 0, 'unchanged' => 0];

        $backfill = function () use ($normalizer, $commit, &$stats): void {
            DB::table('stock_items')
                ->select(['id', 'name', 'normalized_name'])
                ->orderBy('id')
                ->chunkById(200, function ($items) use ($normalizer, $commit, &$stats): void {
                    foreach ($items as $item) {
                        $stats['scanned']++;
                        $normalized = $normalizer->normalizeName((string) $item->name);

                        if ($item->normalized_name === $normalized) {
                            $stats['unchanged']++;
                            continue;
                        }

                        $stats['would_update']++;

                        if ($commit) {
                            DB::table('stock_items')
                                ->where('id', $item->id)
                                ->update(['normalized_name' => $normalized]);
                        }
                    }
                });
        };

        if ($commit) {
            DB::transaction($backfill);
        } else {
            $backfill();
        }

        return $stats;
    }
}
