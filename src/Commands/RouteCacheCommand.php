<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
use LuanyCli\Env;

/**
 * route:cache — compile the route table into storage/cache/routes.php.
 *
 * Boots the application exactly as a request would (providers, every file
 * in routes/), then writes the table with Route::cache(). luany/framework
 * (>= 1.4) loads that file instead of the route files while it exists, so
 * run this on every deploy and `route:clear` in development.
 *
 * Closure routes cannot be cached: the command fails and lists them.
 */
class RouteCacheCommand extends BaseCommand
{
    public const DEFAULT_PATH = 'storage/cache/routes.php';

    public function name(): string
    {
        return 'route:cache';
    }

    public function description(): string
    {
        return 'Cache the route table for production (storage/cache/routes.php)';
    }

    /** @param array<int, string> $args */
    public function handle(array $args): void
    {
        $code = self::run(Env::basePath());
        if ($code !== 0) {
            exit($code);
        }
    }

    public static function run(string $base): int
    {
        $base = rtrim($base, '/\\');

        if (!is_file($base . '/vendor/autoload.php')) {
            echo "\n  \033[31m✗\033[0m  vendor/autoload.php not found. Run: composer install\n\n";
            return 1;
        }
        if (!is_file($base . '/bootstrap/app.php')) {
            echo "\n  \033[31m✗\033[0m  bootstrap/app.php not found — is this a Luany project?\n\n";
            return 1;
        }

        // The Kernel loads the cache instead of the route files while it exists.
        $default = $base . '/' . self::DEFAULT_PATH;
        if (is_file($default)) {
            @unlink($default);
        }

        $level = ob_get_level();
        ob_start();
        try {
            $app = (static fn (string $file) => require $file)($base . '/bootstrap/app.php');
            $kernel = is_object($app) && method_exists($app, 'make') ? $app->make('Luany\\Framework\\Http\\Kernel') : null;
            if (!is_object($kernel) || !method_exists($kernel, 'routeCachePath')) {
                throw new \RuntimeException('route:cache needs luany/framework 1.4 or later (composer update luany/framework).');
            }
            $kernel->boot();
            $path = $kernel->routeCachePath();
        } catch (\Throwable $e) {
            self::endBuffers($level);
            echo "\n  \033[31m✗\033[0m  Could not boot the application: {$e->getMessage()}\n\n";
            return 1;
        }
        self::endBuffers($level);

        if ($path === null) {
            echo "\n  \033[33m→\033[0m  The route cache is disabled in your HTTP Kernel (\$routeCacheFile = '').\n\n";
            return 1;
        }
        if ($path !== $default && is_file($path)) {
            @unlink($path);
            echo "\n  \033[33m→\033[0m  Routes were loaded from an existing cache at {$path}; it was removed. Run route:cache again.\n\n";
            return 1;
        }

        try {
            \Luany\Core\Routing\Route::cache($path);
        } catch (\RuntimeException $e) {
            echo "\n  \033[31m✗\033[0m  {$e->getMessage()}\n"
                . "     Move closure actions to controllers (or Route::view()) to cache the routes.\n\n";
            return 1;
        }

        $count  = count(\Luany\Core\Routing\Route::getRoutes());
        $shown  = str_starts_with($path, $base . '/') ? substr($path, strlen($base) + 1) : $path;
        echo "\n  \033[32m✓\033[0m  Routes cached ({$count} route" . ($count === 1 ? '' : 's') . ") → {$shown}\n"
            . "     Run \033[36mluany route:clear\033[0m after changing routes.\n\n";

        return 0;
    }

    private static function endBuffers(int $level): void
    {
        while (ob_get_level() > $level) {
            ob_end_clean();
        }
    }
}
