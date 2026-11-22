<?php

namespace LuanyCli\Commands;

use Luany\Database\Migration\MigrationRunner;
use LuanyCli\Output\Output;
use LuanyCli\Output\StructuredCommand;

class MigrateStatusCommand extends MigrateBaseCommand implements StructuredCommand
{
    public function name(): string
    {
        return 'migrate:status';
    }

    public function description(): string
    {
        return 'Show the status of all migrations';
    }

    /** @param array<int, string> $args */
    public function handle(array $args): void
    {
        $runner = new MigrationRunner($this->pdo(), $this->migrationPath());
        $status = $runner->status();

        $out = Output::current();
        $out->data(['migrations' => array_map(fn (array $m): array => [
            'name'  => $m['name'],
            'ran'   => (bool) $m['ran'],
            'batch' => $m['batch'] ?? null,
        ], array_values($status))]);

        if (empty($status)) {
            $out->say('LUANY-MIGRATE-001', [], 'info');
            return;
        }
        if ($out->json) {
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

