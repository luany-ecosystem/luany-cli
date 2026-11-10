<?php

namespace LuanyCli\Dev;

/**
 * NodeRunner
 *
 * Spawns and manages the Node.js watcher process (watcher.js).
 *
 * Responsibilities:
 *   - Verify Node.js is available on PATH
 *   - Verify node_modules/chokidar and node_modules/ws are installed
 *   - Spawn watcher.js as a background child process via proc_open()
 *   - Provide the process handle to ProcessManager
 *
 * Design notes:
 *   proc_open() is used instead of shell_exec() or passthru() because it
 *   returns a process resource that ProcessManager can terminate cleanly
 *   on SIGINT/SIGTERM, preventing zombie watcher processes.
 *
 *   stdout/stderr from the Node process are inherited (STDIO descriptors),
 *   so [LDE] log lines appear directly in the terminal alongside PHP output.
 *
 *   With inherited STDIN/STDOUT/STDERR descriptors, proc_open() sets $pipes
 *   to an empty array — there are no pipe resources to manage.
 */
class NodeRunner
{
    private string $watcherScript;

    public function __construct()
    {
        // watcher.js lives next to this file in Resources/dev/
        $this->watcherScript = dirname(__DIR__) . '/Resources/dev/watcher.js';
    }

    /**
     * Verify all prerequisites before attempting to start the watcher.
     *
     * @throws \RuntimeException  With a clear, actionable message on failure
     */
    public function assertReady(string $projectRoot): void
    {
        $this->assertNodeAvailable();
        $this->assertNodeModulesInstalled($projectRoot);
        $this->assertWatcherScript();
    }

    /**
     * Spawn the Node.js watcher process.
     *
     * Returns the proc_open() process resource. The caller (ProcessManager)
     * is responsible for calling proc_terminate() + proc_close() on it.
     *
     * @param  string  $projectRoot  Absolute path to the Luany project root
     * @param  int     $wsPort       WebSocket server port (default: 35729)
     * @param  string  $host         Dev server host — browsers on it may connect
     * @return resource              proc_open() process resource
     * @throws \RuntimeException     If the process could not be started
     */
    public function spawn(string $projectRoot, int $wsPort = 35729, string $host = 'localhost')
    {
        $node = $this->findNode();

        // Array form — bypasses the shell entirely. Safe for paths with
        // spaces or special characters. Supported since PHP 7.4 on all
        // platforms (including Windows via proc_open internals).
        $cmd = [
            $node,
            $this->watcherScript,
            $projectRoot,
            (string) $wsPort,
            $host,
        ];

        // getenv() is used as fallback when $_ENV is empty — this happens
        // when php.ini variables_order does not include 'E' (common in
        // some Windows/WAMP setups). Without this, the Node child process
        // would inherit zero environment variables (no PATH, no HOME).
        $currentEnv = !empty($_ENV) ? $_ENV : (getenv() ?: []);
        $env        = array_merge($currentEnv, [
            'NODE_PATH' => $projectRoot . '/node_modules',
        ]);

        $descriptors = [
            0 => STDIN,
            1 => STDOUT,
            2 => STDERR,
        ];

        // With inherited descriptors, proc_open() sets $pipes to [].
        // There are no pipe resources to close — the variable is declared
        // by reference but intentionally unused after this call.
        $pipes   = [];
        $process = proc_open($cmd, $descriptors, $pipes, $projectRoot, $env);

        if ($process === false) {
            throw new \RuntimeException(
                'Failed to start the Node.js watcher process.'
            );
        }

        return $process;
    }

    /**
     * Shell command that locates $binary on PATH for the given OS family.
     *
     * `2>nul` is only valid on Windows (cmd.exe); on sh it creates a file
     * literally named "nul" in the current directory.
     */
    public static function lookupCommand(string $binary, string $osFamily = PHP_OS_FAMILY): string
    {
        if ($osFamily === 'Windows') {
            return 'where ' . escapeshellarg($binary) . ' 2>nul';
        }

        return 'command -v ' . escapeshellarg($binary) . ' 2>/dev/null';
    }

    /**
     * Find the Node.js binary — checks both "node" and "nodejs".
     */
    public function findNode(): string
    {
        foreach (['node', 'nodejs'] as $candidate) {
            $output = @shell_exec(self::lookupCommand($candidate));

            if (is_string($output) && trim($output) !== '') {
                // `where` may list several matches — take the first one.
                $lines = preg_split('/\r\n|\r|\n/', trim($output));
                if ($lines !== false && isset($lines[0]) && trim($lines[0]) !== '') {
                    return trim($lines[0]);
                }
            }
        }

        throw new \RuntimeException(
            "Node.js not found on PATH.\n"
            . "  Install it from https://nodejs.org or via your package manager.\n"
        );
    }

    // ── Assertions ────────────────────────────────────────────────────────────

    public function assertNodeAvailable(): void
    {
        $this->findNode(); // throws if not found
    }

    public function assertNodeModulesInstalled(string $projectRoot): void
    {
        $missing = [];

        if (!is_dir($projectRoot . '/node_modules/chokidar')) {
            $missing[] = 'chokidar';
        }
        if (!is_dir($projectRoot . '/node_modules/ws')) {
            $missing[] = 'ws';
        }

        if (!empty($missing)) {
            throw new \RuntimeException(
                "Required Node.js packages not installed: " . implode(', ', $missing) . "\n"
                . "  Run: \033[36mnpm install\033[0m\n"
            );
        }
    }

    public function assertWatcherScript(): void
    {
        if (!file_exists($this->watcherScript)) {
            throw new \RuntimeException(
                "Watcher script not found: {$this->watcherScript}\n"
                . "  Try reinstalling: \033[36mcomposer global update luany/cli\033[0m\n"
            );
        }
    }
}