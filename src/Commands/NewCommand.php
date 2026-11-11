<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
use LuanyCli\Env;
use LuanyCli\Support\Console;
use LuanyCli\Support\DriverCheck;
use LuanyCli\Support\EnvWriter;

class NewCommand extends BaseCommand
{
    public function name(): string
    {
        return 'new';
    }

    public function description(): string
    {
        return 'Create a new Luany project';
    }

    public function requiresProject(): bool
    {
        return false;
    }

    /** @param array<int, string> $args */
    /**
     * luany new <name> [--database=sqlite|mysql|pgsql] [--git|--no-git] [--no-migrate] [-n]
     *
     * @param array<int, string> $args
     */
    public function handle(array $args): void
    {
        $options = self::parseOptions($args);

        if ($options['name'] === null) {
            fwrite(STDERR, "\n  \033[31m✗\033[0m  Usage: luany new <project-name> [--database=sqlite|mysql|pgsql] [--git] [--no-migrate]\n\n");
            $this->terminate(1);
            return;
        }

        $name   = self::validateProjectName($options['name']);
        $target = getcwd() . DIRECTORY_SEPARATOR . $name;

        if (is_dir($target)) {
            fwrite(STDERR, "\n  \033[31m✗\033[0m  Project [{$name}] already exists.\n\n");
            $this->terminate(1);
            return;
        }

        $io          = $this->console();
        $interactive = !$options['no-interaction'] && $this->isInteractive();

        // Ask everything up front, so the user can walk away during composer.
        $database = $options['database']
            ?? ($interactive ? $io->choice('Database', [
                'sqlite' => 'file-based, nothing to install (recommended to start)' . $this->missingTag('sqlite'),
                'mysql'  => 'MySQL / MariaDB server' . $this->missingTag('mysql'),
                'pgsql'  => 'PostgreSQL server' . $this->missingTag('pgsql'),
            ], 'sqlite') : 'sqlite');

        $credentials = [];
        if ($database !== 'sqlite') {
            $credentials = $interactive
                ? $this->askCredentials($io, $database, $name)
                : self::defaultCredentials($database, $name);
        }

        $git = $options['git'] ?? ($interactive && $this->gitAvailable() ? $io->confirm('Initialize a git repository?', true) : false);

        echo "\n  \033[33m→\033[0m  Creating Luany project [{$name}]...\n\n";

        // Lets the skeleton's post-install script leave the summary to us.
        putenv('LUANY_CLI=1');

        if (!$this->createProject($name)) {
            fwrite(STDERR, "\n  \033[31m✗\033[0m  Project creation failed.\n\n");
            $this->terminate(1);
            return;
        }

        echo "\n";
        $this->configureDatabase($target, $database, $credentials);
        $io->info("Database: {$database}");

        $driverProblem = $this->driverProblem($database);
        $migrated      = false;

        if ($driverProblem !== null) {
            $io->error($driverProblem);
        } elseif ($database === 'sqlite' && !$options['no-migrate']) {
            $migrated = $this->migrate($target);
        }

        if ($git && $this->gitAvailable()) {
            exec('git init -q ' . escapeshellarg($target) . ' 2>&1', $out, $code);
            $code === 0 ? $io->info('Git repository initialized') : $io->error('git init failed');
        }

        echo "\n  \033[32m✓\033[0m  \033[1m{$name}\033[0m is ready.\n\n";
        echo "    cd {$name}\n";
        if ($database !== 'sqlite') {
            echo "    \033[2m# create the database, check .env, then:\033[0m\n";
            echo "    luany migrate\n";
        } elseif (!$migrated) {
            echo "    luany migrate\n";
        }
        echo "    luany dev        \033[2m→ http://localhost:8000\033[0m\n\n";
    }

    /**
     * Write DB_* settings to the project's .env and create the SQLite file.
     *
     * @param array<string, string> $credentials
     */
    public function configureDatabase(string $target, string $database, array $credentials = []): void
    {
        $env = $target . '/.env';

        if (!is_file($env) && is_file($target . '/.env.example')) {
            copy($target . '/.env.example', $env);
        }

        if ($database === 'sqlite') {
            EnvWriter::set($env, ['DB_CONNECTION' => 'sqlite', 'DB_NAME' => 'database/database.sqlite']);
            $file = $target . '/database/database.sqlite';
            if (!is_dir(dirname($file))) {
                mkdir(dirname($file), 0755, true);
            }
            if (!is_file($file)) {
                touch($file);
            }
            return;
        }

        EnvWriter::set($env, ['DB_CONNECTION' => $database] + $credentials);
    }

    /**
     * Run the new project's migrations in-process (SQLite needs no server).
     */
    protected function migrate(string $target): bool
    {
        $autoload = $target . '/vendor/autoload.php';
        if (!is_file($autoload)) {
            return false;
        }

        require_once $autoload;
        $previous = Env::basePath();
        Env::setBasePath($target);

        try {
            (new MigrateCommand())->handle([]);
            return true;
        } catch (\Throwable $e) {
            $this->console()->error('Migrations failed: ' . $e->getMessage() . ' — run "luany migrate" inside the project.');
            return false;
        } finally {
            Env::setBasePath($previous);
        }
    }

    /**
     * Explanation when the PHP PDO extension for $driver is missing (overridable in tests).
     */
    protected function driverProblem(string $driver): ?string
    {
        return DriverCheck::problem($driver);
    }

    private function missingTag(string $driver): string
    {
        return $this->driverProblem($driver) === null ? '' : '  (PHP extension missing)';
    }

    /**
     * Run composer create-project (overridable in tests).
     */
    protected function createProject(string $name): bool
    {
        passthru(self::buildCommand($name), $exitCode);
        return $exitCode === 0;
    }

    protected function console(): Console
    {
        return new Console();
    }

    protected function isInteractive(): bool
    {
        return Console::isInteractive();
    }

    protected function gitAvailable(): bool
    {
        exec('git --version 2>&1', $out, $code);
        return $code === 0;
    }

    protected function terminate(int $code): void
    {
        exit($code);
    }

    /**
     * @return array<string, string>
     */
    private function askCredentials(Console $io, string $driver, string $name): array
    {
        $defaults = self::defaultCredentials($driver, $name);

        return [
            'DB_HOST' => $io->ask('Host', $defaults['DB_HOST']),
            'DB_PORT' => $io->ask('Port', $defaults['DB_PORT']),
            'DB_NAME' => $io->ask('Database name', $defaults['DB_NAME']),
            'DB_USER' => $io->ask('User', $defaults['DB_USER']),
            'DB_PASS' => $io->ask('Password', ''),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function defaultCredentials(string $driver, string $name): array
    {
        return [
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => $driver === 'pgsql' ? '5432' : '3306',
            'DB_NAME' => (string) preg_replace('/[^a-z0-9_]/', '_', strtolower($name)),
            'DB_USER' => $driver === 'pgsql' ? 'postgres' : 'root',
            'DB_PASS' => '',
        ];
    }

    /**
     * @param array<int, string> $args
     * @return array{name: ?string, database: ?string, git: ?bool, no-migrate: bool, no-interaction: bool}
     */
    public static function parseOptions(array $args): array
    {
        $options = ['name' => null, 'database' => null, 'git' => null, 'no-migrate' => false, 'no-interaction' => false];

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--database=')) {
                $db = strtolower(substr($arg, 11));
                if (!in_array($db, ['sqlite', 'mysql', 'pgsql'], true)) {
                    throw new \InvalidArgumentException("Invalid --database [{$db}] — use sqlite, mysql or pgsql.");
                }
                $options['database'] = $db;
            } elseif ($arg === '--git') {
                $options['git'] = true;
            } elseif ($arg === '--no-git') {
                $options['git'] = false;
            } elseif ($arg === '--no-migrate') {
                $options['no-migrate'] = true;
            } elseif ($arg === '-n' || $arg === '--no-interaction') {
                $options['no-interaction'] = true;
            } elseif (str_starts_with($arg, '-')) {
                throw new \InvalidArgumentException("Unknown option [{$arg}].");
            } elseif ($options['name'] === null) {
                $options['name'] = $arg;
            } else {
                throw new \InvalidArgumentException("Unexpected argument [{$arg}].");
            }
        }

        return $options;
    }

    /**
     * A project name is a single directory name: letters, digits, '.', '_'
     * and '-', starting with a letter or digit (so it can never be parsed
     * as an option such as --repository=…), and never '.' or '..'.
     *
     * @throws \InvalidArgumentException
     */
    public static function validateProjectName(string $name): string
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $name) || strlen($name) > 255) {
            throw new \InvalidArgumentException(
                "Invalid project name [{$name}] — use letters, digits, '.', '_' and '-', starting with a letter or digit."
            );
        }

        return $name;
    }

    /**
     * Build the composer command. `--` ends option parsing so the positional
     * arguments can never be interpreted as composer options.
     */
    public static function buildCommand(string $name): string
    {
        return 'composer create-project -- luany/luany ' . escapeshellarg($name);
    }
}

