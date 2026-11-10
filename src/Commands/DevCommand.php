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
     * Warn (not abort) if APP_ENV is not set to "development".
     * The developer may have forgotten, but we should not block them.
     */
    private function assertAppEnv(string $projectRoot): void
    {
        $envFile = $projectRoot . '/.env';

        if (!file_exists($envFile)) {
            return;
        }

        $contents = file_get_contents($envFile);

        if ($contents === false) {
            return;
        }

        // Check if APP_ENV is set to development
        if (!preg_match('/^APP_ENV\s*=\s*development\s*$/im', $contents)) {
            echo "\n  \033[33m⚠\033[0m  APP_ENV is not set to 'development' in your .env\n";
            echo "     DevMiddleware requires APP_ENV=development to inject the live reload client.\n";
            echo "     Run: \033[36mecho 'APP_ENV=development' >> .env\033[0m  (or edit .env manually)\n\n";
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