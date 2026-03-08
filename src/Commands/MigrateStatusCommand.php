<?php

namespace LuanyCli\Commands;

use Luany\Database\Migration\MigrationRunner;

class MigrateStatusCommand extends MigrateBaseCommand
{
    public function name(): string
    {
        return 'migrate:status';
    }

    public function description(): string
    {
        return 'Show the status of all migrations';
    }

    public function handle(array $args): void
    {
        $runner = new MigrationRunner($this->pdo(), $this->migrationPath());
        $status = $runner->status();

        if (empty($status)) {
            echo "\n  \033[33m→\033[0m  No migrations found.\n\n";
            return;
        }

        echo "\n";
        printf("  %-5s  %-50s  %s\n", 'Ran?', 'Migration', 'Batch');
        echo "  " . str_repeat('─', 70) . "\n";

        foreach ($status as $migration) {
            $ran   = $migration['ran'] ? "\033[32mYes\033[0m" : "\033[33mNo \033[0m";
            $batch = $migration['batch'] ?? '—';
            printf("  %s    %-50s  %s\n", $ran, $migration['name'], $batch);
        }

        echo "\n";
    }
}