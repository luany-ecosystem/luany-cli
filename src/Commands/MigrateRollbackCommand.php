<?php

namespace LuanyCli\Commands;

use Luany\Database\Migration\MigrationRunner;

class MigrateRollbackCommand extends MigrateBaseCommand
{
    public function name(): string
    {
        return 'migrate:rollback';
    }

    public function description(): string
    {
        return 'Rollback the last migration batch';
    }

    public function handle(array $args): void
    {
        echo "\n";

        $count = $this->runner()->rollback(function (string $name, string $status) {
            if ($status === 'nothing') {
                echo "  \033[33m→\033[0m  Nothing to rollback.\n";
                return;
            }
            echo "  \033[32m✓\033[0m  Rolled back: {$name}\n";
        });

        if ($count > 0) {
            echo "\n  \033[32m✓\033[0m  {$count} migration(s) rolled back.\n";
        }

        echo "\n";
    }

    private function runner(): MigrationRunner
    {
        return new MigrationRunner($this->pdo(), $this->migrationPath());
    }
}

