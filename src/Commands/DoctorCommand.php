<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
use LuanyCli\Support\ProjectFinder;
use LuanyCli\Env;

class DoctorCommand extends BaseCommand
{
    public function name(): string
    {
        return 'doctor';
    }

    public function description(): string
    {
        return 'Check the Luany environment and project health';
    }

    public function requiresProject(): bool
    {
        return false;
    }

    /** @param array<int, string> $args */
    public function handle(array $args): void
    {
        echo "\n  \033[33mLuany Environment Check\033[0m\n";
        echo "  " . str_repeat('─', 50) . "\n\n";

        // ── Global checks ─────────────────────────────────────────────────────

        $this->check('PHP version', PHP_VERSION, version_compare(PHP_VERSION, '8.1.0', '>='));
        $this->checkExtension('pdo');
        $this->checkExtension('pdo_mysql');
        $this->checkExtension('mbstring');
        $this->checkExtension('openssl');
        $this->checkExtension('json');
        $this->checkComposer();
        $this->checkCli();

        // ── Project checks ────────────────────────────────────────────────────

        $base = \LuanyCli\Env::basePath();

        if (!ProjectFinder::isLuanyProject($base)) {
            echo "\n  \033[33m→\033[0m  Run inside a Luany project for a full health check.\n\n";
            return;
        }

        echo "\n  \033[33mProject Health\033[0m\n";
        echo "  " . str_repeat('─', 50) . "\n\n";

        $this->checkFile('.env',            $base . '/.env');
        $this->checkDir('vendor',           $base . '/vendor');
        $this->checkDir('vendor/luany/framework', $base . '/vendor/luany/framework');
        $this->checkDir('database/migrations', $base . '/database/migrations');
        // APP_KEY
        $envFile = $base . '/.env';
        $appKey  = '';
        if (file_exists($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (str_starts_with(trim($line), 'APP_KEY=')) {
                    $appKey = trim(substr(trim($line), 8), '"\'');
                    break;
                }
            }
        }
        $keyOk = !empty($appKey);
        $icon  = $keyOk ? "\033[32m✓\033[0m" : "\033[31m✗\033[0m";
        $value = $keyOk ? 'configured' : 'missing — run: luany key:generate';
        printf("  %s  %-30s %s\n", $icon, 'APP_KEY', $value);

        $this->checkFile('bootstrap/app.php',  $base . '/bootstrap/app.php');
        $this->checkFile('public/index.php',   $base . '/public/index.php');
        $this->checkFile('config/app.php',     $base . '/config/app.php');

        // Storage writability
        $this->checkWritable('storage/cache/views', $base . '/storage/cache/views');
        $this->checkWritable('storage/logs',        $base . '/storage/logs');        

        $this->checkDatabase($base);

        echo "\n";
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function check(string $label, string $value, bool $ok): void
    {
        $icon = $ok ? "\033[32m✓\033[0m" : "\033[31m✗\033[0m";
        printf("  %s  %-30s %s\n", $icon, $label, $value);
    }

    private function checkExtension(string $ext): void
    {
        $loaded = extension_loaded($ext);
        $icon   = $loaded ? "\033[32m✓\033[0m" : "\033[31m✗\033[0m";
        $value  = $loaded ? 'loaded' : 'missing';
        printf("  %s  %-30s %s\n", $icon, "ext/{$ext}", $value);
    }

    private function checkFile(string $label, string $path): void
    {
        $exists = file_exists($path);
        $icon   = $exists ? "\033[32m✓\033[0m" : "\033[31m✗\033[0m";
        $value  = $exists ? 'found' : 'missing';
        printf("  %s  %-30s %s\n", $icon, $label, $value);
    }

    private function checkDir(string $label, string $path): void
    {
        $exists = is_dir($path);
        $icon   = $exists ? "\033[32m✓\033[0m" : "\033[31m✗\033[0m";
        $value  = $exists ? 'found' : 'missing';
        printf("  %s  %-30s %s\n", $icon, $label, $value);
    }

    private function checkComposer(): void
    {
        exec('composer --version 2>&1', $output, $code);
        $ok    = $code === 0;
        $icon  = $ok ? "\033[32m✓\033[0m" : "\033[31m✗\033[0m";
        $value = $ok ? trim(preg_replace('/^Composer version /', '', $output[0] ?? '')) : 'not found';
        printf("  %s  %-30s %s\n", $icon, 'Composer', $value);
    }

    private function checkCli(): void
    {
        $version = 'unknown';
        if (class_exists(\Composer\InstalledVersions::class)) {
            $v = \Composer\InstalledVersions::getPrettyVersion('luany/cli');
            if ($v !== null) {
                $version = preg_replace('/\+.+$/', '', $v);
            }
        }
        printf("  \033[32m✓\033[0m  %-30s %s\n", 'luany/cli', $version);
    }

    private function checkDatabase(string $base): void
    {
        $envFile = $base . '/.env';
        if (!file_exists($envFile)) {
            printf("  \033[31m✗\033[0m  %-30s %s\n", 'database connection', '.env not found');
            return;
        }

        $env = \LuanyCli\Support\EnvParser::parse($envFile);

        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $env['DB_HOST'] ?? '127.0.0.1',
                $env['DB_PORT'] ?? '3306',
                $env['DB_NAME'] ?? 'luany',
            );
            $pdo = new \PDO($dsn, $env['DB_USER'] ?? 'root', $env['DB_PASS'] ?? '', [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);
            printf("  \033[32m✓\033[0m  %-30s %s\n", 'database connection', 'ok');

            $tables = $pdo->query("SHOW TABLES LIKE '_migrations'")->fetchAll();
            $exists = !empty($tables);
            $icon   = $exists ? "\033[32m✓\033[0m" : "\033[33m⚠\033[0m";
            $value  = $exists ? 'found' : 'not found — run: luany migrate';
            printf("  %s  %-30s %s\n", $icon, '_migrations table', $value);

        } catch (\PDOException $e) {
            printf("  \033[31m✗\033[0m  %-30s %s\n", 'database connection', $e->getMessage());
        }
    }

    private function checkWritable(string $label, string $path): void
    {
        $ok    = is_dir($path) && is_writable($path);
        $icon  = $ok ? "\033[32m✓\033[0m" : "\033[31m✗\033[0m";
        $value = $ok ? 'writable' : 'not writable';
        printf("  %s  %-30s %s\n", $icon, $label, $value);
    }
}

