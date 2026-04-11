<?php

namespace LuanyCli\Tests\Commands;

use LuanyCli\Commands\MakeSeederCommand;
use LuanyCli\Env;
use PHPUnit\Framework\TestCase;

class MakeSeederCommandTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/luany_cli_test_' . uniqid();
        mkdir($this->baseDir . '/database/seeders', 0755, true);
        Env::setBasePath($this->baseDir);
    }

    protected function tearDown(): void
    {
        Env::reset();
        $this->removeDir($this->baseDir);
    }

    public function test_creates_seeder_file(): void
    {
        (new MakeSeederCommand())->handle(['UserSeeder']);

        $this->assertFileExists($this->baseDir . '/database/seeders/UserSeeder.php');
    }

    public function test_appends_seeder_suffix_if_missing(): void
    {
        (new MakeSeederCommand())->handle(['User']);

        $this->assertFileExists($this->baseDir . '/database/seeders/UserSeeder.php');
    }

    public function test_does_not_duplicate_seeder_suffix(): void
    {
        (new MakeSeederCommand())->handle(['UserSeeder']);

        $this->assertFileDoesNotExist($this->baseDir . '/database/seeders/UserSeederSeeder.php');
    }

    public function test_generated_class_extends_seeder(): void
    {
        (new MakeSeederCommand())->handle(['PostSeeder']);

        $content = file_get_contents($this->baseDir . '/database/seeders/PostSeeder.php');
        $this->assertStringContainsString('extends Seeder', $content);
    }

    public function test_generated_class_has_run_method(): void
    {
        (new MakeSeederCommand())->handle(['PostSeeder']);

        $content = file_get_contents($this->baseDir . '/database/seeders/PostSeeder.php');
        $this->assertStringContainsString('public function run(', $content);
    }

    public function test_command_name(): void
    {
        $this->assertSame('make:seeder', (new MakeSeederCommand())->name());
    }

    public function test_requires_project(): void
    {
        $this->assertTrue((new MakeSeederCommand())->requiresProject());
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}