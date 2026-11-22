<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
use LuanyCli\Output\Output;
use LuanyCli\Output\StructuredCommand;
use LuanyCli\Env;
use LuanyCli\Support\EnvException;
use LuanyCli\Support\EnvRepository;

class AboutCommand extends BaseCommand implements StructuredCommand
{
    public function requiresProject(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'about';
    }

    public function description(): string
    {
        return 'Display information about the current project';
    }

    /** @param array<int, string> $args */
    public function handle(array $args): void
    {
        $env         = $this->readEnv();
        $appName     = $env['APP_NAME'] ?? 'Luany App';
        $appEnv      = $env['APP_ENV']  ?? 'unknown';
        $appDebug    = $env['APP_DEBUG'] ?? 'false';
        $appUrl      = $env['APP_URL']  ?? 'http://localhost:8000';

        $fwVersion   = $this->packageVersion('luany/framework');
        $coreVersion = $this->packageVersion('luany/core');
        $lteVersion  = $this->packageVersion('luany/lte');
        $cliVersion  = $this->cliVersion();

        Output::current()->data([
            'app'      => ['name' => $appName, 'env' => $appEnv, 'debug' => $appDebug, 'url' => $appUrl],
            'versions' => ['php' => PHP_VERSION, 'luany/framework' => $fwVersion, 'luany/core' => $coreVersion, 'luany/lte' => $lteVersion, 'luany/cli' => $cliVersion],
        ]);
        if (Output::current()->json) {
            return;
        }

        echo "\n";
        echo "  \033[33mLuany\033[0m — Compiler-Driven PHP MVC Framework\n";
        echo "  " . str_repeat('─', 50) . "\n";
        printf("  \033[36m%-20s\033[0m %s\n", 'Application', $appName);
        printf("  \033[36m%-20s\033[0m %s\n", 'Environment', $appEnv);
        printf("  \033[36m%-20s\033[0m %s\n", 'Debug',       $appDebug);
        printf("  \033[36m%-20s\033[0m %s\n", 'URL',         $appUrl);
        echo "  " . str_repeat('─', 50) . "\n";
        printf("  \033[36m%-20s\033[0m %s\n", 'PHP',             PHP_VERSION);
        printf("  \033[36m%-20s\033[0m %s\n", 'luany/framework', $fwVersion);
        printf("  \033[36m%-20s\033[0m %s\n", 'luany/core',      $coreVersion);
        printf("  \033[36m%-20s\033[0m %s\n", 'luany/lte',       $lteVersion);
        printf("  \033[36m%-20s\033[0m %s\n", 'luany/cli',       $cliVersion);
        echo "\n";
    }

    /**
     * APP_* values resolved like the framework's env(): real environment
     * variables first, then .env.
     *
     * @return array<string, string>
     */
    private function readEnv(): array
    {
        try {
            $env = EnvRepository::load(Env::basePath());
        } catch (EnvException) {
            $env = null; // unreadable .env: real environment variables only
        }

        $result = [];
        foreach (['APP_NAME', 'APP_ENV', 'APP_DEBUG', 'APP_URL'] as $key) {
            $value = $env !== null ? $env->get($key) : (getenv($key) ?: null);
            if ($value !== null) {
                $result[$key] = $value;
            }
        }

        return $result;
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

    private function cliVersion(): string
    {
        if (class_exists(\Composer\InstalledVersions::class)) {
            $version = \Composer\InstalledVersions::getPrettyVersion('luany/cli');
            if ($version !== null) {
                return preg_replace('/\+.+$/', '', $version);
            }
        }

        return 'unknown';
    }
}

