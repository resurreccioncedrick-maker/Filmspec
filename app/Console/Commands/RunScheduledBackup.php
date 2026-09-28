<?php

namespace App\Console\Commands;

use App\Support\DatabaseBackup;
use App\Support\Settings;
use Illuminate\Console\Command;

/**
 * Scheduled hourly in bootstrap/app.php but only actually backs up once the current hour
 * matches the configured time — the Scheduled Backups toggle on the Backup and Restore page
 * writes backup_schedule_enabled/hour/keep via Settings, this reads them at run time. Hourly
 * (not dailyAt a fixed time) is what makes "change the time in the dropdown" take effect
 * without a container restart, at hour-level granularity.
 */
class RunScheduledBackup extends Command
{
    protected $signature = 'backup:run {--force : Run even if disabled or the hour does not match, for manual testing}';

    protected $description = 'If enabled and the current hour matches, dump the database to storage/app/backups and prune old backups';

    public function handle(): int
    {
        $enabled = Settings::get('backup_schedule_enabled', '0') === '1';
        $hour = (int) Settings::get('backup_schedule_hour', '2');
        $keep = max(1, (int) Settings::get('backup_schedule_keep', '14'));

        if (! $this->option('force')) {
            if (! $enabled) {
                $this->line('Scheduled backups are disabled — skipping.');

                return self::SUCCESS;
            }
            if ((int) now()->format('G') !== $hour) {
                $this->line("Not the configured hour ($hour:00) — skipping.");

                return self::SUCCESS;
            }
        }

        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = 'filmspec_backup_' . now()->format('Y-m-d_His') . '.sql';
        file_put_contents($dir . DIRECTORY_SEPARATOR . $filename, DatabaseBackup::dump());
        $this->info("Saved $filename");

        $files = collect(glob($dir . DIRECTORY_SEPARATOR . 'filmspec_backup_*.sql'))
            ->sortByDesc(fn ($f) => filemtime($f))
            ->values();

        foreach ($files->slice($keep) as $old) {
            unlink($old);
            $this->line('Pruned ' . basename($old));
        }

        return self::SUCCESS;
    }
}
