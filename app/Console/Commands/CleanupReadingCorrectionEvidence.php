<?php

namespace App\Console\Commands;

use App\Services\ReadingCorrectionTemporaryEvidenceCleanupService;
use Illuminate\Console\Command;

class CleanupReadingCorrectionEvidence extends Command
{
    protected $signature = 'chm:cleanup-reading-correction-evidence {--commit : Remove files and records after the dry-run review}';
    protected $description = 'Safely audits and removes expired temporary reading-correction photo evidence';

    public function handle(ReadingCorrectionTemporaryEvidenceCleanupService $cleanup): int
    {
        $plan = $cleanup->plan();
        $this->line('Temporary sessions: '.$plan['sessions']->count());
        $this->line('Evidence files: '.$plan['files']->count());
        $this->line('Total size: '.number_format($plan['bytes'] / 1048576, 2).' MB');
        $this->line('Eligible for cleanup: '.$plan['files']->count().' files');
        $this->line('Protected/persisted or unsafe: '.$plan['skipped']->count().' files');
        $this->line('SAFE: '.($plan['safe'] ? 'YES' : 'NO'));
        if (! $this->option('commit')) { $this->info('Dry-run: nenhuma alteração foi realizada. Use --commit para limpar.'); return self::SUCCESS; }
        if (! $plan['safe']) { $this->error('Commit bloqueado por paths inseguros.'); return self::FAILURE; }
        $result = $cleanup->commit($plan);
        $this->info('Deleted files: '.$result['files']);
        $this->info('Freed: '.number_format($result['bytes'] / 1048576, 2).' MB');
        return self::SUCCESS;
    }
}
