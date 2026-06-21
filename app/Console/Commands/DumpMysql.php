<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Produces a MySQL-dialect .sql dump (schema + seed data) that can be imported
 * with `mysql forex_trader < dump.sql`. Schema is compiled from the migrations
 * via the MySQL grammar (pretend mode), so it works without a live MySQL server.
 *
 * Regenerable/runtime tables are dumped as schema only — re-seed candles with
 * `php artisan db:seed` or `php artisan quotes:poll`.
 */
class DumpMysql extends Command
{
    protected $signature = 'db:dump-mysql {--path= : Output file path}';

    protected $description = 'Export the schema + seed data as a MySQL .sql dump';

    /** Tables whose data is regenerated and therefore excluded from the dump. */
    private const SKIP_DATA = ['candles', 'ticks', 'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs'];

    public function handle(): int
    {
        $path = $this->option('path') ?: base_path('database/dump/mysql-dump.sql');
        @mkdir(dirname($path), 0o755, true);

        $source = DB::connection('sqlite'); // current dev data

        // Compile MySQL DDL from each migration's up() without a live server.
        // A stub PDO satisfies grammar lookups (e.g. isMaria()/server version)
        // that some column types trigger during compilation.
        config(['database.default' => 'mysql']);
        DB::connection('mysql')->setPdo(new class extends \PDO
        {
            public function __construct()
            {
            }

            public function getAttribute(int $attribute): mixed
            {
                return '8.0.36';
            }
        });

        $schema = [];
        foreach (glob(database_path('migrations/*.php')) as $file) {
            $migration = require $file;
            foreach (DB::connection('mysql')->pretend(fn () => $migration->up()) as $q) {
                $schema[] = $q['query'];
            }
        }

        $out = "-- Forex Web Trader — MySQL dump\n";
        $out .= '-- Generated '.now()->toDateTimeString()."\n\n";
        $out .= "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";
        foreach ($schema as $s) {
            $out .= $s.";\n";
        }
        $out .= "\n-- Data\n";

        $tables = collect($source->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"))
            ->pluck('name')
            ->reject(fn ($t) => in_array($t, self::SKIP_DATA, true));

        foreach ($tables as $table) {
            $rows = $source->table($table)->get();
            if ($rows->isEmpty()) {
                continue;
            }
            foreach ($rows->chunk(200) as $chunk) {
                $cols = array_keys((array) $chunk->first());
                $colList = implode(', ', array_map(fn ($c) => "`{$c}`", $cols));
                $tuples = $chunk->map(function ($row) {
                    return '('.implode(', ', array_map([$this, 'quote'], array_values((array) $row))).')';
                })->all();
                $out .= "INSERT INTO `{$table}` ({$colList}) VALUES\n".implode(",\n", $tuples).";\n";
            }
        }

        $out .= "\nSET FOREIGN_KEY_CHECKS = 1;\n";
        file_put_contents($path, $out);

        $this->info('Wrote '.$path.' ('.number_format(strlen($out) / 1024, 1).' KB, '.$tables->count().' tables with data).');

        return self::SUCCESS;
    }

    private function quote(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return "'".str_replace(['\\', "'"], ['\\\\', "''"], (string) $value)."'";
    }
}
