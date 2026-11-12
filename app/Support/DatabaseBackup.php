<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

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
            if (array_key_exists('Create View', $createRow)) {
                $sql .= "DROP VIEW IF EXISTS `$table`;\n";
                $sql .= $createRow['Create View'] . ";\n\n";

                continue;
            }

            $sql .= "DROP TABLE IF EXISTS `$table`;\n";
            $sql .= $createRow['Create Table'] . ";\n\n";

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

    public static function restore(string $sqlContent): array
    {
        $conn = self::connection();
        $binary = self::findBinary('mysql', 'mysql_path');

        if ($binary) {
            $args = [$binary, '-h', $conn['host'], '-u', $conn['username']];
            if ($conn['password'] !== '') {
                $args[] = '-p' . $conn['password'];
            }
            $args[] = $conn['database'];

            $result = Process::timeout(300)->input($sqlContent)->run($args);
            if ($result->successful()) {
                return ['success' => true];
            }

            return ['success' => false, 'error' => trim($result->errorOutput()) ?: trim($result->output()) ?: 'mysql CLI exited with an error.'];
        }

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
