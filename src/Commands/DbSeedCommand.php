<?php

namespace LuanyCli\Commands;

use Luany\Database\Seeder\SeederRunner;
use LuanyCli\Env;

class DbSeedCommand extends MigrateBaseCommand
{
    public function name(): string
    {
        return 'db:seed';
    }

    public function description(): string
    {
        return 'Run database seeders';
    }

    /** @param array<int, string> $args */
    public function handle(array $args): void
    {
        $this->loadProjectAutoload();

        $class = $this->resolveClass($args);
        $path  = Env::basePath() . '/database/seeders';

        if (!is_dir($path)) {
            fwrite(STDERR, "\n  \033[31m✗\033[0m  Seeders directory not found: database/seeders/\n\n");
            exit(1);
        }

        echo "\n";

        $runner = new SeederRunner($this->pdo(), $path);

        try {
            $runner->run($class, function (string $seeder) {
                echo "  \033[32m✓\033[0m  Seeded: {$seeder}\n";
            });
        } catch (\RuntimeException $e) {
            fwrite(STDERR, "  \033[31m✗\033[0m  {$e->getMessage()}\n\n");
            exit(1);
        }

        echo "\n  \033[32m✓\033[0m  Seeding complete.\n\n";
    }

    /**
     * Resolve the seeder class from args.
     * Supports: --class=UserSeeder  or  --class UserSeeder
     */
    /** @param array<int, string> $args */
    private function resolveClass(array $args): string
    {
        foreach ($args as $i => $arg) {
            if (str_starts_with($arg, '--class=')) {
                return substr($arg, 8);
            }
            if ($arg === '--class' && isset($args[$i + 1])) {
                return $args[$i + 1];
            }
        }

        return 'DatabaseSeeder';
    }
}