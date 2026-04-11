<?php

namespace LuanyCli\Commands;

use Luany\Database\Migration\MigrationRunner;
use Luany\Database\Seeder\SeederRunner;
use LuanyCli\Env;

class MigrateFreshCommand extends MigrateBaseCommand
{
    public function name(): string
    {
        return 'migrate:fresh';
    }

    public function description(): string
    {
        return 'Drop all tables and re-run all migrations';
    }

    /** @param array<int, string> $args */
    public function handle(array $args): void
    {
        $seed = in_array('--seed', $args, true);

        echo "\n  \033[33m⚠\033[0m  This will drop all tables. Continue? [yes/no]: ";
        $confirm = trim(fgets(STDIN));

        if ($confirm !== 'yes') {
            echo "\n  \033[33m→\033[0m  Aborted.\n\n";
            exit(0);
        }

        $this->loadProjectAutoload();

        $pdo    = $this->pdo();
        $runner = new MigrationRunner($pdo, $this->migrationPath());

        echo "\n";

        $runner->dropAll($pdo);
        echo "  \033[32m✓\033[0m  All tables dropped.\n";

        $count = $runner->run(function (string $name, string $status) {
            echo "  \033[32m✓\033[0m  Migrated: {$name}\n";
        });

        echo "\n  \033[32m✓\033[0m  {$count} migration(s) complete.\n";

        if ($seed) {
            $seedersPath = Env::basePath() . '/database/seeders';

            if (!is_dir($seedersPath)) {
                echo "\n  \033[33m→\033[0m  No seeders directory found — skipping.\n\n";
                return;
            }

            echo "\n";

            $seeder = new SeederRunner($pdo, $seedersPath);

            try {
                $seeder->run('DatabaseSeeder', function (string $class) {
                    echo "  \033[32m✓\033[0m  Seeded: {$class}\n";
                });
            } catch (\RuntimeException $e) {
                fwrite(STDERR, "  \033[31m✗\033[0m  {$e->getMessage()}\n\n");
                exit(1);
            }
        }

        echo "\n";
    }
}