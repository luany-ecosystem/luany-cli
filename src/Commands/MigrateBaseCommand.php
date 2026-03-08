<?php

namespace LuanyCli\Commands;

use LuanyCli\CommandInterface;
use LuanyCli\Env;

/**
 * MigrateBaseCommand
 *
 * Shared PDO bootstrap for migration commands.
 * Reads DB credentials directly from .env via parse_ini_file —
 * intentionally avoids the framework bootstrap so the CLI
 * remains usable before the app is fully configured.
 */
abstract class MigrateBaseCommand implements CommandInterface
{
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

    protected function loadEnv(): array
    {
        $file = Env::basePath() . '/.env';

        if (!file_exists($file)) {
            fwrite(STDERR, "\n  \033[31m✗\033[0m  .env not found. Run: luany key:generate\n\n");
            exit(1);
        }

        return parse_ini_file($file) ?: [];
    }

    protected function migrationPath(): string
    {
        return Env::basePath() . '/database/migrations';
    }
}