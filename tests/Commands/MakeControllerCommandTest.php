<?php

namespace LuanyCli\Tests\Commands;

use LuanyCli\Commands\MakeControllerCommand;
use LuanyCli\Env;
use PHPUnit\Framework\TestCase;

class MakeControllerCommandTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/luany_cli_test_' . uniqid();
        mkdir($this->baseDir . '/app/Controllers', 0755, true);
        Env::setBasePath($this->baseDir);
    }

    protected function tearDown(): void
    {
        Env::reset();
        $this->removeDir($this->baseDir);
    }

    public function test_creates_controller_file(): void
    {
        (new MakeControllerCommand())->handle(['Home']);

        $this->assertFileExists($this->baseDir . '/app/Controllers/HomeController.php');
    }

    public function test_appends_controller_suffix(): void
    {
        (new MakeControllerCommand())->handle(['User']);

        $this->assertFileExists($this->baseDir . '/app/Controllers/UserController.php');
    }

    public function test_does_not_duplicate_suffix(): void
    {
        (new MakeControllerCommand())->handle(['HomeController']);

        $this->assertFileExists($this->baseDir . '/app/Controllers/HomeController.php');
        $this->assertFileDoesNotExist($this->baseDir . '/app/Controllers/HomeControllerController.php');
    }

    public function test_contains_correct_namespace(): void
    {
        (new MakeControllerCommand())->handle(['Blog']);

        $content = file_get_contents($this->baseDir . '/app/Controllers/BlogController.php');
        $this->assertStringContainsString('namespace App\\Controllers;', $content);
    }

    public function test_creates_controller_in_subdirectory(): void
    {
        (new MakeControllerCommand())->handle(['Auth/Login']);

        $this->assertFileExists($this->baseDir . '/app/Controllers/Auth/LoginController.php');
    }

    public function test_subdirectory_controller_has_correct_namespace(): void
    {
        (new MakeControllerCommand())->handle(['Auth/Login']);

        $content = file_get_contents($this->baseDir . '/app/Controllers/Auth/LoginController.php');
        $this->assertStringContainsString('namespace App\\Controllers\\Auth;', $content);
    }

    public function test_command_name(): void
    {
        $this->assertSame('make:controller', (new MakeControllerCommand())->name());
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
