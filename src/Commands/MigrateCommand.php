<?php

namespace LuanyCli\Commands;

use Luany\Database\Migration\MigrationRunner;

class MigrateCommand extends MigrateBaseCommand
{
    public function name(): string
    {
        return 'migrate';
    }

    public function description(): string
    {
        return 'Run all pending migrations';
    }

    /** @param array<int, string> $args */
    public function handle(array $args): void
    {
        echo "\n";

        $count = $this->runner()->run(function (string $name, string $status) {
            if ($status === 'nothing') {
                echo "  \033[33m→\033[0m  Nothing to migrate.\n";
                return;
            }
            echo "  \033[32m✓\033[0m  Migrated: {$name}\n";
        });

        if ($count > 0) {
            echo "\n  \033[32m✓\033[0m  {$count} migration(s) complete.\n";
        }

        echo "\n";
    }

    private function runner(): MigrationRunner
    {
        return new MigrationRunner($this->pdo(), $this->migrationPath());
    }
}

