<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
use LuanyCli\Env;
use LuanyCli\Support\EnvException;
use LuanyCli\Support\EnvRepository;

/**
 * MigrateBaseCommand
 *
 * Shared bootstrap for all migration commands. Resolves DB credentials
 * like the application (real environment variables first, then .env)
 * without booting the framework, so the CLI stays usable before the app
 * is fully configured.
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
        $env = $this->env();

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $env->get('DB_HOST', '127.0.0.1'),
            $env->get('DB_PORT', '3306'),
            $env->get('DB_NAME', 'luany'),
        );

        return new \PDO($dsn, $env->get('DB_USER', 'root'), $env->get('DB_PASS', ''), [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
    }

    /**
     * Project environment, resolved like the application does: real
     * environment variables win over .env (Docker, CI, PaaS).
     *
     * Exits with a clear message when .env exists but cannot be read, or
     * when there is neither a .env file nor DB_* variables in the
     * environment.
     */
    protected function env(): EnvRepository
    {
        try {
            $env = EnvRepository::load(Env::basePath());
        } catch (EnvException $e) {
            fwrite(STDERR, "\n  \033[31m✗\033[0m  {$e->getMessage()}\n\n");
            exit(1);
        }

        if (!$env->hasFile() && !$env->has('DB_HOST') && !$env->has('DB_NAME')) {
            fwrite(STDERR, "\n  \033[31m✗\033[0m  .env not found and no DB_* environment variables set. Run: luany key:generate\n\n");
            exit(1);
        }

        return $env;
    }

    /**
     * Backward-compatible array view of the resolved environment for the
     * DB_* keys used by the CLI.
     *
     * @deprecated Use env().
     * @return array<string, string>
     */
    protected function loadEnv(): array
    {
        $env = $this->env();
        $out = [];
        foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $key) {
            $value = $env->get($key);
            if ($value !== null) {
                $out[$key] = $value;
            }
        }
        return $out;
    }

    protected function migrationPath(): string
    {
        return Env::basePath() . '/database/migrations';
    }
}


