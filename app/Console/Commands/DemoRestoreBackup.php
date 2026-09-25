<?php

namespace App\Console\Commands;

use App\Support\DatabaseBackup;
use Illuminate\Console\Command;

/**
 * Restores a SQL dump produced by DatabaseBackup::dump() from a fixed path
 * (storage/app/restore.sql) — a one-shot undo path for the demo reset, run by hand only.
 */
class DemoRestoreBackup extends Command
{
    protected $signature = 'demo:restore-backup {--force : Skip the confirmation prompt}';

    protected $description = 'Restore storage/app/restore.sql over the current database';

    public function handle(): int
    {
        $path = storage_path('app/restore.sql');
        if (! file_exists($path)) {
            $this->error("No file at {$path}.");

            return self::FAILURE;
        }

        $sql = file_get_contents($path);
        $this->info('Loaded ' . strlen($sql) . ' bytes from restore.sql.');

        if (! $this->option('force') && ! $this->confirm('This will PERMANENTLY overwrite the current database with this backup. Continue?')) {
            $this->warn('Cancelled.');

            return self::FAILURE;
        }

        $result = DatabaseBackup::restore($sql);
        if ($result['success']) {
            $this->info('Restore completed successfully.');

            return self::SUCCESS;
        }

        $this->error('Restore failed: ' . ($result['error'] ?? 'unknown error'));

        return self::FAILURE;
    }
}
