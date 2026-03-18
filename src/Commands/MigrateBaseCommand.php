<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
use LuanyCli\Env;

/**
 * MigrateBaseCommand
 *
 * Shared bootstrap for all migration commands. Reads DB credentials
 * directly from .env — intentionally avoids the framework bootstrap
 * so the CLI remains usable before the app is fully configured.
 */
abstract class MigrateBaseCommand extends BaseCommand
{
    /**
     * Load the project's vendor/autoload.php so framework classes
     * (e.g. MigrationRunner) are available when the CLI is installed
     * globally and the project has its own vendor/ directory.
     */
    protected function loadProjectAutoload(): void
    {
        $autoload = Env::basePath() . '/vendor/autoload.php';

        if (file_exists($autoload)) {
            require_once $autoload;
        }
    }

    protected function pdo(): \PDO
    {
        $env = $this->loadEnv();

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $env['DB_HOST'] ?? '127.0.0.1',
            $env['DB_PORT'] ?? '3306',
            $env['DB_NAME'] ?? 'luany',
        );

        return new \PDO($dsn, $env['DB_USER'] ?? 'root', $env['DB_PASS'] ?? '', [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
    }

    /**
     * Parse .env manually — parse_ini_file() chokes on values that
     * contain '=' (e.g. base64 APP_KEY) or '(' characters.
     */
    protected function loadEnv(): array
    {
        $file = Env::basePath() . '/.env';

        if (!file_exists($file)) {
            fwrite(STDERR, "\n  \033[31m✗\033[0m  .env not found. Run: luany key:generate\n\n");
            exit(1);
        }

        return \LuanyCli\Support\EnvParser::parse($file);
    }

    protected function migrationPath(): string
    {
        return Env::basePath() . '/database/migrations';
    }
}


