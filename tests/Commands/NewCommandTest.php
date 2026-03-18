<?php

namespace LuanyCli\Tests\Commands;

use LuanyCli\Commands\NewCommand;
use LuanyCli\Env;
use PHPUnit\Framework\TestCase;

class NewCommandTest extends TestCase
{
    private string $workDir;

    protected function setUp(): void
    {
        $this->workDir = sys_get_temp_dir() . '/luany_new_test_' . uniqid();
        mkdir($this->workDir, 0755, true);
        Env::setBasePath($this->workDir);
        chdir($this->workDir);
    }

    protected function tearDown(): void
    {
        Env::reset();
        $this->removeDir($this->workDir);
    }

    public function test_name_is_new(): void
    {
        $this->assertSame('new', (new NewCommand())->name());
    }

    public function test_requires_project_returns_false(): void
    {
        $this->assertFalse((new NewCommand())->requiresProject());
    }

    public function test_description_is_not_empty(): void
    {
        $this->assertNotEmpty((new NewCommand())->description());
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

