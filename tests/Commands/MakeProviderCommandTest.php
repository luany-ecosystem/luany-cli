<?php

namespace LuanyCli\Tests\Commands;

use LuanyCli\Commands\MakeProviderCommand;
use PHPUnit\Framework\TestCase;
use LuanyCli\Env;

class MakeProviderCommandTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/luany_cli_test_' . uniqid();
        mkdir($this->baseDir . '/app/Providers', 0755, true);
        Env::setBasePath($this->baseDir);
    }

    protected function tearDown(): void
    {
        Env::reset();
        $this->removeDir($this->baseDir);
    }

    public function test_creates_provider_file(): void
    {
        $command = new MakeProviderCommand();
        $command->handle(['Mail']);

        $this->assertFileExists($this->baseDir . '/app/Providers/MailServiceProvider.php');
    }

    public function test_does_not_duplicate_suffix(): void
    {
        $command = new MakeProviderCommand();
        $command->handle(['MailServiceProvider']);

        $this->assertFileExists($this->baseDir . '/app/Providers/MailServiceProvider.php');
        $this->assertFileDoesNotExist($this->baseDir . '/app/Providers/MailServiceProviderServiceProvider.php');
    }

    public function test_contains_correct_namespace(): void
    {
        $command = new MakeProviderCommand();
        $command->handle(['Cache']);

        $content = file_get_contents($this->baseDir . '/app/Providers/CacheServiceProvider.php');
        $this->assertStringContainsString('namespace App\Providers;', $content);
    }

    public function test_command_name(): void
    {
        $this->assertSame('make:provider', (new MakeProviderCommand())->name());
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

