<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
use LuanyCli\Env;

/**
 * route:clear — delete the route cache, so routes/*.php are read again.
 */
class RouteClearCommand extends BaseCommand
{
    public function name(): string
    {
        return 'route:clear';
    }

    public function description(): string
    {
        return 'Delete the route cache (storage/cache/routes.php)';
    }

    /** @param array<int, string> $args */
    public function handle(array $args): void
    {
        self::run(Env::basePath());
    }

    public static function run(string $base): int
    {
        $path = rtrim($base, '/\\') . '/' . RouteCacheCommand::DEFAULT_PATH;

        if (!is_file($path)) {
            echo "\n  \033[33m→\033[0m  No route cache to clear.\n\n";
            return 0;
        }

        unlink($path);
        echo "\n  \033[32m✓\033[0m  Route cache cleared.\n\n";

        return 0;
    }
}
