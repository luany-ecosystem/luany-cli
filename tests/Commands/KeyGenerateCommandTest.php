<?php

namespace LuanyCli\Tests\Commands;

use LuanyCli\Commands\KeyGenerateCommand;
use PHPUnit\Framework\TestCase;
use LuanyCli\Env;

class KeyGenerateCommandTest extends TestCase
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

    public function test_generates_app_key_in_existing_env(): void
    {
        file_put_contents($this->baseDir . '/.env', "APP_NAME=Luany\nAPP_KEY=\n");

        $command = new KeyGenerateCommand();
        $command->handle([]);

        $content = file_get_contents($this->baseDir . '/.env');
        $this->assertMatchesRegularExpression('/APP_KEY=base64:.+/', $content);
    }

    public function test_creates_env_from_example_if_missing(): void
    {
        file_put_contents($this->baseDir . '/.env.example', "APP_NAME=Luany\nAPP_KEY=\n");

        $command = new KeyGenerateCommand();
        $command->handle([]);

        $this->assertFileExists($this->baseDir . '/.env');
    }

    public function test_key_has_base64_prefix(): void
    {
        file_put_contents($this->baseDir . '/.env', "APP_KEY=\n");

        $command = new KeyGenerateCommand();
        $command->handle([]);

        $content = file_get_contents($this->baseDir . '/.env');
        $this->assertStringContainsString('APP_KEY=base64:', $content);
    }

    public function test_command_name(): void
    {
        $this->assertSame('key:generate', (new KeyGenerateCommand())->name());
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