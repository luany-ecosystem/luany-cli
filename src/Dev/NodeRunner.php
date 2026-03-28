<?php

namespace LuanyCli\Dev;
use LuanyCli\Env;

/**
 * NodeRunner
 *
 * Spawns and manages the Node.js watcher process (watcher.js).
 *
 * Responsibilities:
 *   - Verify Node.js is available on PATH
 *   - Verify node_modules/chokidar and node_modules/ws are installed
 *   - Spawn watcher.js as a background child process via proc_open()
 *   - Provide the process handle and pipes to ProcessManager
 *
 * Design notes:
 *   proc_open() is used instead of shell_exec() or passthru() because it
 *   returns a process resource that ProcessManager can terminate cleanly
 *   on SIGINT/SIGTERM, preventing zombie watcher processes.
 *
 *   stdout/stderr from the Node process are inherited (STDIO descriptors),
 *   so [LDE] log lines appear directly in the terminal alongside PHP output.
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
     * @return resource              proc_open() process resource
     * @throws \RuntimeException     If the process could not be started
     */
    public function spawn(string $projectRoot, int $wsPort = 35729)
    {
        $node = $this->findNode();
        $cmd = [
            $node,
            $this->watcherScript,
            $projectRoot,
            (string) $wsPort,
        ];

        $currentEnv = !empty($_ENV) ? $_ENV : (getenv() ?: []);
        $env = array_merge($currentEnv, [
            'NODE_PATH' => $projectRoot . '/node_modules',
        ]);

        $descriptors = [
            0 => STDIN,
            1 => STDOUT,
            2 => STDERR,
        ];

        $process = proc_open($cmd, $descriptors, $pipes, $projectRoot, $env);

        if (is_array($pipes)) {
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
        }

        if ($process === false) {
            throw new \RuntimeException(
                'Failed to start the Node.js watcher process.'
            );
        }

        return $process;
    }

    /**
     * Find the Node.js binary — checks both "node" and "nodejs".
     */
    public function findNode(): string
    {
        foreach (['node', 'nodejs'] as $candidate) {

            // ── Windows ───────────────────────────────
            $where = @shell_exec('where ' . escapeshellarg($candidate) . ' 2>nul');
            if ($where !== null && trim($where) !== '') {
                $lines = preg_split('/\r\n|\r|\n/', trim($where));
                return trim($lines[0]);
            }

            // ── UNIX (Linux/macOS) ───────────────────────────────
            $which = @shell_exec('which ' . escapeshellarg($candidate) . ' 2>/dev/null');
            if ($which !== null && trim($which) !== '') {
                return trim($which);
            }
        }

        throw new \RuntimeException(
            "Node.js not found on PATH.\n"
            . "  Install it from https://nodejs.org or via your package manager.\n"
        );
    }

    // ── Private assertions ────────────────────────────────────────────────────

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