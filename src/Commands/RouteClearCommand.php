<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
use LuanyCli\Output\Output;
use LuanyCli\Output\StructuredCommand;
use LuanyCli\Env;

/**
 * route:clear — delete the route cache, so routes/*.php are read again.
 */
class RouteClearCommand extends BaseCommand implements StructuredCommand
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

        $out  = Output::current();

        if (!is_file($path)) {
            $out->say('LUANY-ROUTE-007', [], 'info');
            return 0;
        }

        $out->planned('delete', RouteCacheCommand::DEFAULT_PATH);
        if (!$out->dryRun) {
            unlink($path);
            $out->say('LUANY-ROUTE-006');
        }

        return 0;
    }
}
