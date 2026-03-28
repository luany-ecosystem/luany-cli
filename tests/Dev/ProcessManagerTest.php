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
        foreach (['node', 'nodejs'] as $candidate) {
            $which = @shell_exec('which ' . escapeshellarg($candidate) . ' 2>/dev/null');
            if ($which !== null && trim($which) !== '') {
                return true;
            }
            $where = @shell_exec('where ' . escapeshellarg($candidate) . ' 2>nul');
            if ($where !== null && trim($where) !== '') {
                return true;
            }
        }
        return false;
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
}