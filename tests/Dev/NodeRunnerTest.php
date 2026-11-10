<?php

namespace LuanyCli\Tests\Dev;

use LuanyCli\Dev\NodeRunner;
use LuanyCli\Env;
use PHPUnit\Framework\TestCase;

class NodeRunnerTest extends TestCase
{
    private string $baseDir;
    private NodeRunner $runner;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/luany_node_test_' . uniqid();
        mkdir($this->baseDir, 0755, true);
        Env::setBasePath($this->baseDir);
        $this->runner = new NodeRunner();
    }

    protected function tearDown(): void
    {
        Env::reset();
        $this->removeDir($this->baseDir);
    }

    // ── Node binary detection — always runs regardless of environment ──────────

    public function test_assert_ready_throws_when_node_not_on_path(): void
    {
        // Only meaningful when Node is NOT available.
        // When Node IS available, assertReady() proceeds to package checks.
        if ($this->isNodeAvailable()) {
            $this->markTestSkipped('Node.js is available — Node-not-found path not reachable.');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Node\.js/');

        //$this->runner->assertNodeModulesInstalled($this->baseDir);
        $this->runner->assertReady($this->baseDir);
    }

    // ── Package detection — requires Node on PATH to be reachable ─────────────
    //
    // assertReady() checks Node availability FIRST. If Node is not installed,
    // the package checks below are never reached — skip rather than fail.

    public function test_assert_ready_throws_when_chokidar_missing(): void
    {
        $this->requireNode();

        mkdir($this->baseDir . '/node_modules/ws', 0755, true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/chokidar/');

        $this->runner->assertNodeModulesInstalled($this->baseDir);
    }

    public function test_assert_ready_throws_when_ws_missing(): void
    {
        $this->requireNode();

        mkdir($this->baseDir . '/node_modules/chokidar', 0755, true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/ws/');

        $this->runner->assertNodeModulesInstalled($this->baseDir);
    }

    public function test_assert_ready_throws_when_both_packages_missing(): void
    {
        $this->requireNode();

        mkdir($this->baseDir . '/node_modules', 0755, true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/chokidar|ws/');

        $this->runner->assertNodeModulesInstalled($this->baseDir);
    }

    public function test_assert_ready_throws_when_npm_install_not_run(): void
    {
        $this->requireNode();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/npm install/');

        $this->runner->assertNodeModulesInstalled($this->baseDir);
    }

    // ── Watcher script presence — no process execution, always runs ───────────

    public function test_watcher_script_exists_in_resources(): void
    {
        $script   = (new \ReflectionClass(NodeRunner::class))->getFileName();
        $expected = dirname(dirname($script)) . '/Resources/dev/watcher.js';

        $this->assertFileExists($expected);
    }

    public function test_client_script_exists_in_resources(): void
    {
        $script   = (new \ReflectionClass(NodeRunner::class))->getFileName();
        $expected = dirname(dirname($script)) . '/Resources/dev/client.js';

        $this->assertFileExists($expected);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Skip the current test if Node.js is not available on PATH.
     */
    private function requireNode(): void
    {
        if (!$this->isNodeAvailable()) {
            $this->markTestSkipped(
                'Node.js not found on PATH — package-detection tests require Node to be installed.'
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

    // ── OS-specific lookup (#4) ───────────────────────────────────────────────

    public function test_lookup_command_uses_where_and_nul_on_windows(): void
    {
        $cmd = NodeRunner::lookupCommand('node', 'Windows');

        $this->assertStringStartsWith('where ', $cmd);
        $this->assertStringEndsWith('2>nul', $cmd);
    }

    public function test_lookup_command_never_redirects_to_nul_on_unix(): void
    {
        foreach (['Linux', 'Darwin', 'BSD', 'Solaris'] as $os) {
            $cmd = NodeRunner::lookupCommand('node', $os);

            $this->assertDoesNotMatchRegularExpression('/2>\s*nul\b/', $cmd, $os);
            $this->assertStringNotContainsString('where', $cmd, $os);
            $this->assertStringEndsWith('2>/dev/null', $cmd, $os);
        }
    }

    public function test_find_node_does_not_create_nul_file(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('"nul" is a reserved device name on Windows.');
        }

        $cwd = getcwd();
        chdir($this->baseDir);

        try {
            $this->runner->findNode();
        } catch (\RuntimeException) {
            // Node not installed — irrelevant for this test.
        } finally {
            chdir((string) $cwd);
        }

        $this->assertFileDoesNotExist($this->baseDir . '/nul');
    }
}
