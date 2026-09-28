<?php

namespace App\Console\Commands;

use App\Support\DatabaseBackup;
use Illuminate\Console\Command;

/**
 * Scheduled in bootstrap/app.php (dailyAt 02:00). Every manual "Download Backup" click in
 * the admin panel already saves a copy to storage/app/backups (see
 * SuperAdminController::backup()) — this is the same thing on a timer, so a backup exists
 * even on a day nobody opens the page, closing the "no automated backups" gap.
 */
class RunScheduledBackup extends Command
{
    protected $signature = 'backup:run {--keep=14 : How many backups to retain, oldest deleted first}';

    protected $description = 'Dump the database to storage/app/backups and prune old backups beyond the retention count';

    public function handle(): int
    {
        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = 'filmspec_backup_' . now()->format('Y-m-d_His') . '.sql';
        file_put_contents($dir . DIRECTORY_SEPARATOR . $filename, DatabaseBackup::dump());
        $this->info("Saved $filename");

        $keep = max(1, (int) $this->option('keep'));
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
