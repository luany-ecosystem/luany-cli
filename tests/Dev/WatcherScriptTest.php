<?php

namespace LuanyCli\Tests\Dev;

use LuanyCli\Dev\NodeRunner;
use PHPUnit\Framework\TestCase;

/**
 * Runs the Node unit tests for the LDE watcher helpers
 * (tests/js/watcher-lib.test.js) and checks watcher.js wiring.
 */
class WatcherScriptTest extends TestCase
{
    private const RESOURCES = __DIR__ . '/../../src/Resources/dev';

    private function node(): string
    {
        try {
            return (new NodeRunner())->findNode();
        } catch (\RuntimeException) {
            $this->markTestSkipped('Node.js not found on PATH.');
        }
    }

    public function test_watcher_lib_node_tests_pass(): void
    {
        $node = $this->node();
        $file = realpath(__DIR__ . '/../js/watcher-lib.test.js');
        $this->assertNotFalse($file);

        exec(escapeshellarg($node) . ' --test ' . escapeshellarg($file) . ' 2>&1', $output, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $output));
    }

    public function test_watcher_scripts_are_valid_javascript(): void
    {
        $node = $this->node();

        foreach (['watcher.js', 'watcher-lib.js', 'client.js'] as $script) {
            exec(escapeshellarg($node) . ' --check ' . escapeshellarg(self::RESOURCES . '/' . $script) . ' 2>&1', $output, $exitCode);
            $this->assertSame(0, $exitCode, $script . ': ' . implode("\n", $output));
        }
    }

    public function test_watcher_uses_helpers_and_handles_unlink(): void
    {
        $source = (string) file_get_contents(self::RESOURCES . '/watcher.js');

        $this->assertStringContainsString("require('./watcher-lib')", $source);
        $this->assertStringContainsString('buildWatchPaths(projectRoot)', $source);
        $this->assertStringContainsString('createIgnored(projectRoot)', $source);
        $this->assertStringContainsString('verifyClient', $source);
        $this->assertStringContainsString('unlink', $source);
        $this->assertStringNotContainsString('path.join(projectRoot', $source);
        $this->assertStringNotContainsString('/vendor/,', $source);
    }
}
