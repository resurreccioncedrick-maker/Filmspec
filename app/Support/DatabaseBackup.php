<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

/**
 * Super Admin's Database Backup/Restore. Ports pages/logic/superadmin.logic.php's two-tier
 * approach (mysqldump/mysql CLI first, pure-PHP dump/multi_query fallback) — the legacy
 * version hardcoded a XAMPP path that was already stale on this WAMP install, so the PHP
 * fallback is what has actually been running backups in practice. findBinary() looks the
 * CLI tools up on PATH instead of hardcoding any version-specific install path.
 */
class DatabaseBackup
{
    public static function dump(): string
    {
        $conn = self::connection();
        $binary = self::findBinary('mysqldump', 'mysqldump_path');

        if ($binary) {
            $args = [$binary, '-h', $conn['host'], '-u', $conn['username']];
            if ($conn['password'] !== '') {
                $args[] = '-p' . $conn['password'];
            }
            array_push($args, '--single-transaction', '--routines', '--triggers', $conn['database']);

            $result = Process::timeout(120)->run($args);
            $output = $result->output();
            if ($result->successful() && strlen($output) > 200 && stripos($output, 'error') === false) {
                return $output;
            }
        }

        return self::phpNativeDump($conn['database']);
    }

    private static function phpNativeDump(string $dbname): string
    {
        $sql = "-- FilmSpec Database Backup\n-- Generated: " . now()->toDateTimeString() . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        $pdo = DB::connection()->getPdo();
        $tables = array_map(fn ($r) => array_values((array) $r)[0], DB::select('SHOW TABLES'));

        foreach ($tables as $table) {
            $createRow = (array) DB::select("SHOW CREATE TABLE `$table`")[0];
            // A view returns a "Create View" key (and "View"/"character_set_client"/... columns)
            // instead of "Create Table" — dump it as a view, with no row data of its own.
            if (array_key_exists('Create View', $createRow)) {
                $sql .= "DROP VIEW IF EXISTS `$table`;\n";
                $sql .= $createRow['Create View'] . ";\n\n";

                continue;
            }

            $sql .= "DROP TABLE IF EXISTS `$table`;\n";
            $sql .= $createRow['Create Table'] . ";\n\n";

            // Generated (computed) columns can't be targeted by an INSERT — MySQL derives
            // their value itself. Restrict the INSERT column list to real, storable columns
            // so a table with one (e.g. booking_equipment.subtotal) still restores cleanly.
            $columns = array_filter(
                DB::select(
                    'SELECT COLUMN_NAME, GENERATION_EXPRESSION FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
                    [$dbname, $table]
                ),
                fn ($c) => ($c->GENERATION_EXPRESSION ?? '') === ''
            );
            $columnNames = array_map(fn ($c) => $c->COLUMN_NAME, $columns);
            $columnList = '`' . implode('`,`', $columnNames) . '`';

            foreach (DB::table($table)->get() as $row) {
                $row = (array) $row;
                $vals = array_map(
                    fn ($col) => $row[$col] === null ? 'NULL' : $pdo->quote((string) $row[$col]),
                    $columnNames
                );
                $sql .= "INSERT INTO `$table` ($columnList) VALUES(" . implode(',', $vals) . ");\n";
            }
            $sql .= "\n";
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        return $sql;
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public static function restore(string $sqlContent): array
    {
        $conn = self::connection();
        $binary = self::findBinary('mysql', 'mysql_path');

        if ($binary) {
            $tmpFile = storage_path('app/tmp/restore_' . uniqid() . '.sql');
            if (! is_dir(dirname($tmpFile))) {
                mkdir(dirname($tmpFile), 0755, true);
            }
            file_put_contents($tmpFile, $sqlContent);

            $cmd = '"' . $binary . '" -h ' . escapeshellarg($conn['host']) . ' -u ' . escapeshellarg($conn['username'])
                . ($conn['password'] !== '' ? ' -p' . $conn['password'] : '')
                . ' ' . escapeshellarg($conn['database'])
                . ' < ' . escapeshellarg($tmpFile) . ' 2>&1';
            $result = shell_exec($cmd);
            @unlink($tmpFile);

            if ($result === null || trim((string) $result) === '') {
                return ['success' => true];
            }
        }

        // Fallback: PHP mysqli multi_query (PDO has no clean multi-statement execution).
        try {
            $mysqli = new \mysqli($conn['host'], $conn['username'], $conn['password'], $conn['database']);
            $mysqli->set_charset('utf8mb4');
            if ($mysqli->multi_query($sqlContent)) {
                do {
                    if ($r = $mysqli->store_result()) {
                        $r->free();
                    }
                } while ($mysqli->more_results() && $mysqli->next_result());
                $mysqli->close();

                return ['success' => true];
            }
            $error = $mysqli->error;
            $mysqli->close();

            return ['success' => false, 'error' => $error];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array{host: string, username: string, password: string, database: string}
     */
    private static function connection(): array
    {
        $conn = config('database.connections.' . config('database.default'));

        return [
            'host' => $conn['host'] ?? '127.0.0.1',
            'username' => $conn['username'] ?? 'root',
            'password' => (string) ($conn['password'] ?? ''),
            'database' => $conn['database'] ?? '',
        ];
    }

    // Tries an explicit config override first, then looks the binary up on PATH (Windows
    // `where`) — deliberately never hardcodes a version-pinned install path (e.g.
    // C:\wamp64\bin\mysql\mysql8.4.7\bin\...), since that breaks on the next MySQL upgrade.
    // Returns null (triggering the pure-PHP fallback) if nothing is found.
    private static function findBinary(string $name, string $configKey): ?string
    {
        $override = config("filmspec.$configKey");
        if ($override && file_exists($override)) {
            return $override;
        }

        $lookup = stripos(PHP_OS, 'WIN') === 0 ? 'where' : 'which';
        $result = @shell_exec($lookup . ' ' . escapeshellarg($name) . ' 2>NUL');
        if ($result) {
            $first = trim(explode("\n", trim($result))[0]);
            if ($first && file_exists($first)) {
                return $first;
            }
        }

        return null;
    }
}
