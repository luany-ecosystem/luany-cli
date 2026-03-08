<?php

namespace LuanyCli\Tests\Commands;

use LuanyCli\Commands\MakeModelCommand;
use PHPUnit\Framework\TestCase;
use LuanyCli\Env;

class MakeModelCommandTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/luany_cli_test_' . uniqid();
        mkdir($this->baseDir . '/app/Models', 0755, true);
        Env::setBasePath($this->baseDir);
    }

    protected function tearDown(): void
    {
        Env::reset();
        $this->removeDir($this->baseDir);
    }

    public function test_creates_model_file(): void
    {
        $command = new MakeModelCommand();
        $command->handle(['User']);

        $this->assertFileExists($this->baseDir . '/app/Models/User.php');
    }

    public function test_contains_correct_namespace(): void
    {
        $command = new MakeModelCommand();
        $command->handle(['Post']);

        $content = file_get_contents($this->baseDir . '/app/Models/Post.php');
        $this->assertStringContainsString('namespace App\Models;', $content);
    }

    public function test_contains_table_name(): void
    {
        $command = new MakeModelCommand();
        $command->handle(['User']);

        $content = file_get_contents($this->baseDir . '/app/Models/User.php');
        $this->assertStringContainsString("'users'", $content);
    }

    public function test_camel_case_to_snake_table(): void
    {
        $command = new MakeModelCommand();
        $command->handle(['BlogPost']);

        $content = file_get_contents($this->baseDir . '/app/Models/BlogPost.php');
        $this->assertStringContainsString("'blog_posts'", $content);
    }

    public function test_command_name(): void
    {
        $this->assertSame('make:model', (new MakeModelCommand())->name());
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