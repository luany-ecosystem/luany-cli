<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
use LuanyCli\Dev\NodeRunner;
use LuanyCli\Dev\ProcessManager;
use LuanyCli\Env;
use LuanyCli\Support\NetworkValidator;

/**
 * DevCommand — `luany dev`
 *
 * Starts the Luany Dev Engine (LDE):
 *   - PHP built-in server on localhost:8000
 *   - Node.js file watcher with WebSocket server on port 35729
 *   - Browser client injected automatically via DevMiddleware
 *
 * Replaces `npm run dev` (BrowserSync) entirely.
 * No proxy. No port conflicts. No request loops.
 *
 * Usage:
 *   luany dev
 *   luany dev localhost 8080         (custom host/port)
 *   luany dev localhost 8000 35730   (custom WS port)
 */
class DevCommand extends BaseCommand
{
    public function name(): string
    {
        return 'dev';
    }

    public function description(): string
    {
        return 'Start the Luany Dev Engine (PHP server + live reload)';
    }

    /** @param array<int, string> $args */
    public function handle(array $args): void
    {
        $host   = NetworkValidator::host($args[0] ?? 'localhost');
        $port   = NetworkValidator::port($args[1] ?? '8000', 'port');
        $wsPort = NetworkValidator::port($args[2] ?? '35729', 'WebSocket port');

        $projectRoot = Env::basePath();

        $this->assertAppEnv($projectRoot);
        $this->printBanner($host, $port, $wsPort);

        $manager = new ProcessManager(new NodeRunner());

        try {
            $manager->start($projectRoot, $host, $port, $wsPort);
        } catch (\RuntimeException | \InvalidArgumentException $e) {
            fwrite(STDERR, "\n  \033[31m✗\033[0m  " . $e->getMessage() . "\n");
            exit(1);
        }
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Warn (not abort) unless APP_ENV is "development" or "local" — the
     * environments in which the framework's LiveReloadMiddleware injects the
     * client. Resolved like the app: real environment first, then .env.
     * The developer may have forgotten, but we should not block them.
     */
    protected function assertAppEnv(string $projectRoot): void
    {
        try {
            $env = \LuanyCli\Support\EnvRepository::load($projectRoot);
        } catch (\LuanyCli\Support\EnvException) {
            return;
        }

        $appEnv = $env->get('APP_ENV');

        // No .env and no APP_ENV: nothing to say yet (e.g. before key:generate).
        if ($appEnv === null && !$env->hasFile()) {
            return;
        }

        if (!in_array(strtolower(trim((string) $appEnv)), ['development', 'local'], true)) {
            echo "\n  \033[33m⚠\033[0m  APP_ENV is not 'development' or 'local'" . ($appEnv !== null ? " (it is '{$appEnv}')" : '') . "\n";
            echo "     Live reload is only injected when APP_ENV=development or APP_ENV=local.\n";
            echo "     Set \033[36mAPP_ENV=development\033[0m in your .env.\n\n";
        }
    }

    private function printBanner(string $host, int $port, int $wsPort): void
    {
        echo "\n";
        echo "  \033[38;5;55m┌─────────────────────────────────────┐\033[0m\n";
        echo "  \033[38;5;55m│   🚀  Luany Dev Engine  (LDE v1)    │\033[0m\n";
        echo "  \033[38;5;55m└─────────────────────────────────────┘\033[0m\n";
        echo "\n";
        echo "  \033[32m✓\033[0m  PHP server    →  \033[36mhttp://{$host}:{$port}\033[0m\n";
        echo "  \033[32m✓\033[0m  Live reload   →  \033[36mws://localhost:{$wsPort}\033[0m\n";
        echo "  \033[32m✓\033[0m  CSS inject    →  \033[32mactive\033[0m\n";
        echo "\n";
        echo "  Press \033[33mCtrl+C\033[0m to stop.\n";
        echo "\n";
    }
}