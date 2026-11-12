<?php

namespace App\Console\Commands;

use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DemoResetAndSeed extends Command
{
    protected $signature = 'demo:reset-and-seed {--force : Skip the confirmation prompt}';

    protected $description = 'Truncate all data tables and load the FilmSpec demo dataset';

    private const SKIP = ['migrations', 'sessions', 'laravel_sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This will PERMANENTLY delete all data in this database. Continue?')) {
            $this->warn('Cancelled.');

            return self::FAILURE;
        }

        $tables = array_map(fn ($r) => array_values((array) $r)[0], DB::select('SHOW TABLES'));

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $table) {
            if (in_array($table, self::SKIP, true)) {
                continue;
            }
            DB::table($table)->truncate();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        $this->info('All data tables truncated (' . (count($tables) - count(self::SKIP)) . ' tables).');

        $this->call('db:seed', ['--class' => DemoDataSeeder::class, '--force' => true]);
        $this->info('Demo dataset loaded.');

        return self::SUCCESS;
    }
}
