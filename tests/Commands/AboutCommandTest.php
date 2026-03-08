<?php

namespace LuanyCli\Tests\Commands;

use LuanyCli\Commands\AboutCommand;
use PHPUnit\Framework\TestCase;
use LuanyCli\Env;

class AboutCommandTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/luany_cli_test_' . uniqid();
        mkdir($this->baseDir, 0755, true);
        Env::setBasePath($this->baseDir);
    }

    protected function tearDown(): void
    {
        Env::reset();
        $this->removeDir($this->baseDir);
    }
    public function test_command_name(): void
    {
        $this->assertSame('about', (new AboutCommand())->name());
    }

    public function test_handle_does_not_throw(): void
    {
        $command = new AboutCommand();
        ob_start();
        $command->handle([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('Luany', $output);
    }

    public function test_outputs_php_version(): void
    {
        $command = new AboutCommand();
        ob_start();
        $command->handle([]);
        $output = ob_get_clean();

        $this->assertStringContainsString(PHP_VERSION, $output);
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