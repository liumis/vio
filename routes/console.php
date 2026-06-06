<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:import-local-data {--source=sqlite_import : Source DB connection name} {--truncate : Truncate target tables before import}', function () {
    $sourceConnection = (string) $this->option('source');
    $targetConnection = (string) config('database.default');

    if (! array_key_exists($sourceConnection, config('database.connections', []))) {
        $this->error("Source connection '{$sourceConnection}' is not defined.");

        return self::FAILURE;
    }

    if ($sourceConnection === $targetConnection) {
        $this->error('Source and target connections must be different.');

        return self::FAILURE;
    }

    $tables = [
        'users',
        'authorities',
        'email_settings',
        'imports',
        'violations',
        'activity_logs',
    ];

    $sourceDb = DB::connection($sourceConnection);
    $targetDb = DB::connection($targetConnection);
    $targetDriver = $targetDb->getDriverName();
    $truncate = (bool) $this->option('truncate');

    $this->info("Import source: {$sourceConnection}");
    $this->info("Import target: {$targetConnection} ({$targetDriver})");

    try {
        if ($targetDriver === 'mysql') {
            $targetDb->statement('SET FOREIGN_KEY_CHECKS=0');
        } elseif ($targetDriver === 'sqlite') {
            $targetDb->statement('PRAGMA foreign_keys = OFF');
        }

        foreach ($tables as $table) {
            if (! $sourceDb->getSchemaBuilder()->hasTable($table)) {
                $this->warn("Skipping {$table}: source table not found.");
                continue;
            }

            if (! $targetDb->getSchemaBuilder()->hasTable($table)) {
                $this->warn("Skipping {$table}: target table not found.");
                continue;
            }

            if ($truncate) {
                $targetDb->table($table)->delete();
            }

            $rows = $sourceDb->table($table)->get()->map(
                fn ($row): array => (array) $row
            )->all();

            if ($rows === []) {
                $this->line("{$table}: no rows.");
                continue;
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                $targetDb->table($table)->insert($chunk);
            }

            $this->info("{$table}: imported ".count($rows).' rows.');
        }
    } finally {
        if ($targetDriver === 'mysql') {
            $targetDb->statement('SET FOREIGN_KEY_CHECKS=1');
        } elseif ($targetDriver === 'sqlite') {
            $targetDb->statement('PRAGMA foreign_keys = ON');
        }
    }

    $this->newLine();
    $this->info('Local data import completed.');
})->purpose('Import local SQLite data into current database');
