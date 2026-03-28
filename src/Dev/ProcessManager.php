<?php

namespace LuanyCli\Dev;

/**
 * ProcessManager
 *
 * Orchestrates the two child processes that make up the LDE v1:
 *   1. PHP built-in server  (php -S localhost:8000 -t public/)
 *   2. Node.js watcher      (node watcher.js <root> <wsPort>)
 *
 * Lifecycle:
 *   start() → spawns both processes → blocks in tick loop
 *   On SIGINT/SIGTERM or any child exit → shutdown() → terminates both
 *
 * Design notes:
 *   Both processes are spawned with proc_open() so their resources can be
 *   tracked and cleanly terminated. stdin/stdout/stderr are inherited so
 *   all output flows directly to the terminal.
 *
 *   The tick loop uses proc_get_status() to detect unexpected child exits
 *   (e.g. PHP server port conflict) and shuts down cleanly instead of
 *   leaving the watcher running as an orphan.
 *
 *   pcntl_* functions are used when available (CLI SAPI on Linux/macOS).
 *   On Windows, pcntl is not available — the shutdown function is
 *   registered via register_shutdown_function() as a fallback.
 */
class ProcessManager
{
    private const TICK_INTERVAL_US = 250_000; // 250ms between status checks

    /** @var resource|null */
    private $phpProcess = null;

    /** @var resource|null */
    private $nodeProcess = null;

    private bool $running = false;

    public function __construct(
        private readonly NodeRunner $nodeRunner
    ) {}

    /**
     * Start both processes and block until one exits or a signal is received.
     *
     * @param string $projectRoot  Absolute path to the Luany project
     * @param string $host         PHP server host (default: localhost)
     * @param int    $port         PHP server port (default: 8000)
     * @param int    $wsPort       WebSocket port  (default: 35729)
     */
    public function start(
        string $projectRoot,
        string $host   = 'localhost',
        int    $port   = 8000,
        int    $wsPort = 35729
    ): void {
        $this->registerSignalHandlers();

        // Preflight checks
        $this->nodeRunner->assertReady($projectRoot);
        $this->assertPortAvailable($host, $port);
        $this->assertPortAvailable('localhost', $wsPort);

        // Expose the WS port to the PHP server process via environment.
        // putenv() must be called BEFORE spawnPhp() so the child process
        // inherits LDE_WS_PORT — proc_open() without explicit $env inherits
        // the current process environment including putenv() changes.
        putenv("LDE_WS_PORT={$wsPort}");

        $this->phpProcess  = $this->spawnPhp($projectRoot, $host, $port);
        $this->nodeProcess = $this->nodeRunner->spawn($projectRoot, $wsPort);

        $this->running = true;

        $this->tick();
    }

    /**
     * Terminate both child processes and exit.
     * Safe to call multiple times — idempotent.
     */
    public function shutdown(): void
    {
        if (!$this->running) {
            return;
        }

        $this->running = false;

        echo "\n  \033[33m⚠\033[0m  Shutting down LDE...\n";

        $this->kill($this->phpProcess,  'PHP server');
        $this->kill($this->nodeProcess, 'Node watcher');

        echo "  \033[32m✓\033[0m  Done.\n\n";

        exit(0);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Main tick loop — polls process status every 250ms.
     * Exits when either child process terminates unexpectedly.
     */
    private function tick(): void
    {
        while ($this->running) {
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            if (!$this->isAlive($this->phpProcess)) {
                echo "\n  \033[31m✗\033[0m  PHP server stopped unexpectedly.\n";
                $this->shutdown();
            }

            if (!$this->isAlive($this->nodeProcess)) {
                echo "\n  \033[31m✗\033[0m  Node watcher stopped unexpectedly.\n";
                $this->shutdown();
            }

            usleep(self::TICK_INTERVAL_US);
        }
    }

    /**
     * Assert that a TCP port is not already in use before spawning a process.
     *
     * Uses a non-blocking socket connection attempt. If the connection
     * succeeds, the port is occupied — we abort with a clear message
     * instead of spawning a process that will die immediately with a
     * cryptic error.
     *
     * @throws \RuntimeException  With actionable message naming the port
     */
    private function assertPortAvailable(string $host, int $port): void
    {
        $socket = @fsockopen($host, $port, $errno, $errstr, 0.5);

        if ($socket !== false) {
            fclose($socket);
            throw new \RuntimeException(
                "Port {$port} is already in use on {$host}.\n"
                . "  Stop the process using it, or run:\n"
                . "  \033[36mluany dev {$host} " . ($port !== 8000 ? $port : '8080') . "\033[0m\n"
            );
        }
    }

    /**
     * Spawn the PHP built-in server process.
     *
     * No explicit $env is passed to proc_open() — the child inherits the
     * current process environment, which already contains LDE_WS_PORT
     * set via putenv() moments before this call.
     *
     * @return resource
     */
    private function spawnPhp(string $projectRoot, string $host, int $port)
    {
        $publicDir = $projectRoot . '/public';

        if (!is_dir($publicDir)) {
            throw new \RuntimeException(
                "Public directory not found: {$publicDir}\n"
                . "  Expected a valid Luany project structure.\n"
            );
        }

        $cmd = [
            PHP_BINARY,
            '-S',
            "{$host}:{$port}",
            '-t',
            $publicDir,
        ];

        $descriptors = [
            0 => STDIN,
            1 => STDOUT,
            2 => STDERR,
        ];

        $process = proc_open($cmd, $descriptors, $pipes, $projectRoot);

        if ($process === false) {
            throw new \RuntimeException(
                "Failed to start PHP server on {$host}:{$port}."
            );
        }

        return $process;
    }

    /**
     * Check whether a proc_open() process resource is still running.
     *
     * @param  resource|null $process
     */
    private function isAlive($process): bool
    {
        if ($process === null) {
            return false;
        }
        $status = proc_get_status($process);
        return $status !== false && $status['running'] === true;
    }

    /**
     * Terminate a proc_open() process resource gracefully.
     *
     * Strategy:
     *   1. SIGTERM (or taskkill /F /T on Windows) — give the child a chance
     *      to clean up (close sockets, flush buffers, etc.)
     *   2. Wait 200ms.
     *   3. Fresh proc_get_status() — if still running, SIGKILL.
     *
     * @param resource|null $process
     */
    private function kill(&$process, string $label): void
    {
        if ($process === null) {
            return;
        }

        $status = proc_get_status($process);

        if ($status !== false && $status['running']) {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                exec("taskkill /F /T /PID {$status['pid']}");
            } else {
                proc_terminate($process, 15); // SIGTERM
            }

            // Give the child time to exit cleanly before force-killing.
            usleep(200_000);

            // Fresh status check — the previous $status is stale after sleep.
            $freshStatus = proc_get_status($process);
            if ($freshStatus !== false && $freshStatus['running']) {
                proc_terminate($process, 9); // SIGKILL
            }
        }

        proc_close($process);
        $process = null;

        echo "  \033[32m✓\033[0m  {$label} stopped.\n";
    }

    /**
     * Register OS signal handlers when pcntl is available (Linux/macOS).
     * Falls back to register_shutdown_function() for Windows compatibility.
     */
    private function registerSignalHandlers(): void
    {
        // Shutdown on script end (covers Windows + any unhandled exit)
        register_shutdown_function(function (): void {
            $this->shutdown();
        });

        if (!function_exists('pcntl_signal')) {
            return;
        }

        $handler = function (): void {
            $this->shutdown();
        };

        pcntl_signal(SIGINT,  $handler);
        pcntl_signal(SIGTERM, $handler);

        // Async signal dispatch — avoids blocking between ticks
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
        }
    }
}