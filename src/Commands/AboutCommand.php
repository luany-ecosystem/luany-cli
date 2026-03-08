<?php

namespace LuanyCli\Commands;

use LuanyCli\CommandInterface;
use LuanyCli\Env;

class AboutCommand implements CommandInterface
{
    public function name(): string
    {
        return 'about';
    }

    public function description(): string
    {
        return 'Display information about the current project';
    }

    public function handle(array $args): void
    {
        $env     = $this->readEnv();
        $phpVersion  = PHP_VERSION;
        $appName     = $env['APP_NAME']    ?? 'Luany App';
        $appEnv      = $env['APP_ENV']     ?? 'unknown';
        $appDebug    = $env['APP_DEBUG']   ?? 'false';
        $appUrl      = $env['APP_URL']     ?? 'http://localhost:8000';

        $fwVersion   = $this->packageVersion('luany/framework');
        $coreVersion = $this->packageVersion('luany/core');
        $lteVersion  = $this->packageVersion('luany/lte');
        $cliVersion  = $this->packageVersion('luany/cli');

        echo "\n";
        echo "  \033[33mLuany\033[0m — Compiler-grade PHP MVC Framework\n";
        echo "  " . str_repeat('─', 50) . "\n";
        printf("  \033[36m%-20s\033[0m %s\n", 'Application',   $appName);
        printf("  \033[36m%-20s\033[0m %s\n", 'Environment',   $appEnv);
        printf("  \033[36m%-20s\033[0m %s\n", 'Debug',         $appDebug);
        printf("  \033[36m%-20s\033[0m %s\n", 'URL',           $appUrl);
        echo "  " . str_repeat('─', 50) . "\n";
        printf("  \033[36m%-20s\033[0m %s\n", 'PHP',           $phpVersion);
        printf("  \033[36m%-20s\033[0m %s\n", 'luany/framework', $fwVersion);
        printf("  \033[36m%-20s\033[0m %s\n", 'luany/core',    $coreVersion);
        printf("  \033[36m%-20s\033[0m %s\n", 'luany/lte',     $lteVersion);
        printf("  \033[36m%-20s\033[0m %s\n", 'luany/cli',     $cliVersion);
        echo "\n";
    }

    private function readEnv(): array
    {
        $file = Env::basePath() . '/.env';
        return file_exists($file) ? (parse_ini_file($file) ?: []) : [];
    }

    private function packageVersion(string $package): string
    {
        $lock = Env::basePath() . '/composer.lock';

        if (!file_exists($lock)) {
            return 'unknown';
        }

        $data = json_decode(file_get_contents($lock), true);

        foreach (array_merge($data['packages'] ?? [], $data['packages-dev'] ?? []) as $pkg) {
            if ($pkg['name'] === $package) {
                return $pkg['version'];
            }
        }

        return 'unknown';
    }
}