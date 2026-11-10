<?php

namespace LuanyCli\Tests\Dev;

use LuanyCli\Dev\NodeRunner;
use LuanyCli\Dev\ProcessManager;
use PHPUnit\Framework\TestCase;

class ProcessManagerTest extends TestCase
{
    // ── Constructor ───────────────────────────────────────────────────────────

    public function test_instantiates_with_node_runner(): void
    {
        $manager = new ProcessManager(new NodeRunner());
        $this->assertInstanceOf(ProcessManager::class, $manager);
    }

    // ── start() — preflight failures ──────────────────────────────────────────

    public function test_start_throws_when_node_modules_missing(): void
    {
        // Pattern covers both scenarios:
        //   Node NOT available → "Node.js not found on PATH"
        //   Node available, no node_modules → "npm install"
        // Either way a RuntimeException is thrown before any process spawns.
        $projectRoot = sys_get_temp_dir() . '/luany_pm_test_' . uniqid();
        mkdir($projectRoot, 0755, true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Node\.js|npm install|chokidar|ws/');

        try {
            (new ProcessManager(new NodeRunner()))->start($projectRoot);
        } finally {
            rmdir($projectRoot);
        }
    }

    public function test_start_throws_when_chokidar_missing(): void
    {
        // Package-detection is only reachable when Node IS available.
        $this->requireNode();

        $projectRoot = sys_get_temp_dir() . '/luany_pm_test_' . uniqid();
        mkdir($projectRoot . '/node_modules/ws', 0755, true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/chokidar/');

        try {
            (new ProcessManager(new NodeRunner()))->start($projectRoot);
        } finally {
            $this->removeDir($projectRoot);
        }
    }

    // ── assertPortAvailable ────────────────────────────────────────────────────

    public function test_start_throws_when_php_port_is_occupied(): void
    {
        // Port check runs after Node + package checks.
        // Skip if Node is not available — would throw "Node.js not found" first.
        $this->requireNode();

        $projectRoot = sys_get_temp_dir() . '/luany_pm_port_test_' . uniqid();
        mkdir($projectRoot . '/node_modules/ws', 0755, true);
        mkdir($projectRoot . '/node_modules/chokidar', 0755, true);
        mkdir($projectRoot . '/public', 0755, true);

        // Bind a real TCP socket to an ephemeral port, then ask ProcessManager
        // to use that port — assertPortAvailable() must detect it as occupied.
        $server = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($server === false) {
            $this->markTestSkipped('Could not open a test TCP server socket.');
        }

        $name         = stream_socket_get_name($server, false);
        $occupiedPort = (int) substr(strrchr($name, ':'), 1);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Node\.js|already in use/');

        try {
            (new ProcessManager(new NodeRunner()))->start(
                $projectRoot,
                '127.0.0.1',
                $occupiedPort
            );
        } finally {
            fclose($server);
            $this->removeDir($projectRoot);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function requireNode(): void
    {
        if (!$this->isNodeAvailable()) {
            $this->markTestSkipped(
                'Node.js not found on PATH — this test requires Node to be installed.'
            );
        }
    }

    private function isNodeAvailable(): bool
    {
        try {
            (new NodeRunner())->findNode();
            return true;
        } catch (\RuntimeException) {
            return false;
        }
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    // ── Port validation (#6) ──────────────────────────────────────────────────

    /** @return array<string, array{int, int}> */
    public static function invalidPorts(): array
    {
        return [
            'zero port'       => [0, 35729],
            'negative port'   => [-1, 35729],
            'port too large'  => [65536, 35729],
            'zero ws port'    => [8000, 0],
            'ws too large'    => [8000, 70000],
            'same ports'      => [8000, 8000],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidPorts')]
    public function test_start_rejects_invalid_ports_before_any_preflight(int $port, int $wsPort): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ProcessManager(new NodeRunner()))->start(sys_get_temp_dir(), 'localhost', $port, $wsPort);
    }

    public function test_start_rejects_invalid_host(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ProcessManager(new NodeRunner()))->start(sys_get_temp_dir(), 'localhost; id', 8000, 35729);
    }

    public function test_no_duplicate_putenv(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../../src/Dev/ProcessManager.php');
        $this->assertSame(1, substr_count($source, 'putenv("LDE_WS_PORT='));
    }

    // ── Exit code (#6) ────────────────────────────────────────────────────────

    /**
     * ProcessManager whose terminate() records the exit code instead of exiting.
     */
    private function recordingManager(): ProcessManager
    {
        return new class(new NodeRunner()) extends ProcessManager {
            public ?int $exitCode = null;
            protected function terminate(int $exitCode): void
            {
                $this->exitCode = $exitCode;
            }
        };
    }

    /** @return resource */
    private function spawnPhp(string $code)
    {
        $process = proc_open([PHP_BINARY, '-r', $code], [], $pipes);
        $this->assertIsResource($process);
        return $process;
    }

    private function runTick(ProcessManager $manager, mixed $php, mixed $node): void
    {
        $ref = new \ReflectionClass(ProcessManager::class);
        $ref->getProperty('phpProcess')->setValue($manager, $php);
        $ref->getProperty('nodeProcess')->setValue($manager, $node);
        $ref->getProperty('running')->setValue($manager, true);

        ob_start();
        try {
            $ref->getMethod('tick')->invoke($manager);
        } finally {
            ob_end_clean();
        }
    }

    public function test_exit_code_reflects_crashed_child(): void
    {
        $manager = $this->recordingManager();
        $crashed = $this->spawnPhp('exit(3);');
        $alive   = $this->spawnPhp('sleep(30);');

        $this->runTick($manager, $crashed, $alive);

        $this->assertSame(3, $manager->exitCode);

        // The surviving child must have been terminated.
        $ref = new \ReflectionClass(ProcessManager::class);
        $this->assertNull($ref->getProperty('nodeProcess')->getValue($manager));
    }

    public function test_unexpected_clean_child_exit_is_still_a_failure(): void
    {
        $manager = $this->recordingManager();

        $this->runTick($manager, $this->spawnPhp('sleep(30);'), $this->spawnPhp('exit(0);'));

        $this->assertSame(1, $manager->exitCode);
    }

    public function test_user_initiated_shutdown_exits_with_zero(): void
    {
        $manager = $this->recordingManager();
        $ref     = new \ReflectionClass(ProcessManager::class);
        $ref->getProperty('phpProcess')->setValue($manager, $this->spawnPhp('sleep(30);'));
        $ref->getProperty('nodeProcess')->setValue($manager, $this->spawnPhp('sleep(30);'));
        $ref->getProperty('running')->setValue($manager, true);

        ob_start();
        $manager->shutdown();
        ob_end_clean();

        $this->assertSame(0, $manager->exitCode);
    }
}
