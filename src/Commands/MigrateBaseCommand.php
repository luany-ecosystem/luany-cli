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

    /**
     * Default DB_CONNECTION, as in the skeleton's .env.example and
     * DatabaseServiceProvider.
     */
    public const DEFAULT_DRIVER = 'sqlite';

    protected function pdo(): \PDO
    {
        $env    = $this->env();
        $driver = self::driver($env);

        $problem = \LuanyCli\Support\DriverCheck::problem($driver);
        if ($problem !== null) {
            fwrite(STDERR, "\n  \033[31m✗\033[0m  {$problem}\n\n");
            exit(1);
        }

        $pdo = self::connect($env, Env::basePath());

        // Seeders and migrations may use the project's models.
        self::bindModelConnection($pdo);

        return $pdo;
    }

    /**
     * The project's DB_CONNECTION (lowercase), defaulting to SQLite like the
     * application.
     */
    public static function driver(EnvRepository $env): string
    {
        return strtolower((string) $env->get('DB_CONNECTION', self::DEFAULT_DRIVER));
    }

    /**
     * Give the project's models (luany/database) the CLI's connection, as the
     * application's DatabaseServiceProvider does — the app is not booted here.
     *
     * @return bool Whether luany/database's Model was found and bound
     */
    public static function bindModelConnection(\PDO $pdo): bool
    {
        // Resolved at runtime: luany/database belongs to the project.
        $model      = 'Luany\\Database\\Model';
        $connection = 'Luany\\Database\\Connection';

        if (!class_exists($model) || !class_exists($connection)) {
            return false;
        }

        $model::setConnection($connection::fromPdo($pdo));
        return true;
    }

    /**
     * PDO for DB_CONNECTION = sqlite | mysql | pgsql (default sqlite, like
     * the skeleton; SQLite path relative to the project, foreign keys on).
     */
    public static function connect(\LuanyCli\Support\EnvRepository $env, string $basePath): \PDO
    {
        $driver  = self::driver($env);
        $options = [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ];

        switch ($driver) {
            case 'sqlite':
                $path = (string) $env->get('DB_NAME', 'database/database.sqlite');
                if ($path !== ':memory:' && !str_starts_with($path, '/') && preg_match('/^[A-Za-z]:[\\\\\/]/', $path) !== 1) {
                    $path = rtrim($basePath, '/\\') . '/' . $path;
                }
                if ($path !== ':memory:' && !is_dir(dirname($path))) {
                    mkdir(dirname($path), 0755, true);
                }
                $pdo = new \PDO('sqlite:' . $path, null, null, $options);
                // Off by default per SQLite connection; on in the app (luany/database).
                $pdo->exec('PRAGMA foreign_keys = ON');
                return $pdo;

            case 'pgsql':
                $dsn = sprintf(
                    'pgsql:host=%s;port=%s;dbname=%s',
                    $env->get('DB_HOST', '127.0.0.1'),
                    $env->get('DB_PORT', '5432'),
                    $env->get('DB_NAME', 'luany'),
                );
                return new \PDO($dsn, $env->get('DB_USER', 'postgres'), $env->get('DB_PASS', ''), $options);

            case 'mysql':
                $dsn = sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                    $env->get('DB_HOST', '127.0.0.1'),
                    $env->get('DB_PORT', '3306'),
                    $env->get('DB_NAME', 'luany'),
                );
                return new \PDO($dsn, $env->get('DB_USER', 'root'), $env->get('DB_PASS', ''), $options);

            default:
                throw new \InvalidArgumentException("Unsupported DB_CONNECTION [{$driver}] — use sqlite, mysql or pgsql.");
        }
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

        if (!$env->hasFile() && !$env->has('DB_HOST') && !$env->has('DB_NAME') && !$env->has('DB_CONNECTION')) {
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


