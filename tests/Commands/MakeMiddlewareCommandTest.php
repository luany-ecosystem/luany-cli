<?php

namespace LuanyCli\Tests\Commands;

use LuanyCli\Commands\MakeMiddlewareCommand;
use PHPUnit\Framework\TestCase;
use LuanyCli\Env;

class MakeMiddlewareCommandTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/luany_cli_test_' . uniqid();
        mkdir($this->baseDir . '/app/Http/Middleware', 0755, true);
        Env::setBasePath($this->baseDir);
    }

    protected function tearDown(): void
    {
        Env::reset();
        $this->removeDir($this->baseDir);
    }

    public function test_creates_middleware_file(): void
    {
        $command = new MakeMiddlewareCommand();
        $command->handle(['Auth']);

        $this->assertFileExists($this->baseDir . '/app/Http/Middleware/AuthMiddleware.php');
    }

    public function test_appends_middleware_suffix(): void
    {
        $command = new MakeMiddlewareCommand();
        $command->handle(['Csrf']);

        $this->assertFileExists($this->baseDir . '/app/Http/Middleware/CsrfMiddleware.php');
    }

    public function test_does_not_duplicate_suffix(): void
    {
        $command = new MakeMiddlewareCommand();
        $command->handle(['AuthMiddleware']);

        $this->assertFileExists($this->baseDir . '/app/Http/Middleware/AuthMiddleware.php');
        $this->assertFileDoesNotExist($this->baseDir . '/app/Http/Middleware/AuthMiddlewareMiddleware.php');
    }

    public function test_contains_correct_namespace(): void
    {
        $command = new MakeMiddlewareCommand();
        $command->handle(['Auth']);

        $content = file_get_contents($this->baseDir . '/app/Http/Middleware/AuthMiddleware.php');
        $this->assertStringContainsString('namespace App\Http\Middleware;', $content);
    }

    public function test_command_name(): void
    {
        $this->assertSame('make:middleware', (new MakeMiddlewareCommand())->name());
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