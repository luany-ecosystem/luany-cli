<?php

namespace LuanyCli\Tests\Commands;

use LuanyCli\Commands\MakeTestCommand;
use LuanyCli\Env;
use PHPUnit\Framework\TestCase;

class MakeTestCommandTest extends TestCase
{
    private string $baseDir;
    private MakeTestCommand $command;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/luany_test_cmd_' . uniqid();
        mkdir($this->baseDir, 0755, true);
        Env::setBasePath($this->baseDir);
        $this->command = new MakeTestCommand();
    }

    protected function tearDown(): void
    {
        Env::reset();
        $this->removeDir($this->baseDir);
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

    public function test_creates_test_file(): void
    {
        ob_start();
        $this->command->handle(['UserTest']);
        ob_end_clean();

        $this->assertFileExists($this->baseDir . '/tests/UserTest.php');
    }

    public function test_appends_test_suffix_if_missing(): void
    {
        ob_start();
        $this->command->handle(['User']);
        ob_end_clean();

        $this->assertFileExists($this->baseDir . '/tests/UserTest.php');
    }

    public function test_does_not_append_duplicate_suffix(): void
    {
        ob_start();
        $this->command->handle(['UserTest']);
        ob_end_clean();

        $this->assertFileDoesNotExist($this->baseDir . '/tests/UserTestTest.php');
    }

    public function test_generated_file_has_correct_namespace(): void
    {
        ob_start();
        $this->command->handle(['UserTest']);
        ob_end_clean();

        $content = file_get_contents($this->baseDir . '/tests/UserTest.php');
        $this->assertStringContainsString('namespace Tests', $content);
    }

    public function test_generated_file_extends_test_case(): void
    {
        ob_start();
        $this->command->handle(['UserTest']);
        ob_end_clean();

        $content = file_get_contents($this->baseDir . '/tests/UserTest.php');
        $this->assertStringContainsString('extends TestCase', $content);
    }

    public function test_generated_file_imports_test_case(): void
    {
        ob_start();
        $this->command->handle(['UserTest']);
        ob_end_clean();

        $content = file_get_contents($this->baseDir . '/tests/UserTest.php');
        $this->assertStringContainsString('use PHPUnit\\Framework\\TestCase', $content);
    }

    public function test_generated_file_has_example_test_method(): void
    {
        ob_start();
        $this->command->handle(['UserTest']);
        ob_end_clean();

        $content = file_get_contents($this->baseDir . '/tests/UserTest.php');
        $this->assertStringContainsString('public function test_example()', $content);
    }

    public function test_generated_file_has_setup_and_teardown(): void
    {
        ob_start();
        $this->command->handle(['UserTest']);
        ob_end_clean();

        $content = file_get_contents($this->baseDir . '/tests/UserTest.php');
        $this->assertStringContainsString('protected function setUp()', $content);
        $this->assertStringContainsString('protected function tearDown()', $content);
    }

    public function test_creates_subdirectory_test(): void
    {
        ob_start();
        $this->command->handle(['Feature/UserControllerTest']);
        ob_end_clean();

        $this->assertFileExists($this->baseDir . '/tests/Feature/UserControllerTest.php');
    }

    public function test_subdirectory_test_has_correct_namespace(): void
    {
        ob_start();
        $this->command->handle(['Feature/UserControllerTest']);
        ob_end_clean();

        $content = file_get_contents($this->baseDir . '/tests/Feature/UserControllerTest.php');
        $this->assertStringContainsString('namespace Tests\\Feature', $content);
    }

    public function test_does_not_overwrite_existing_test(): void
    {
        ob_start();
        $this->command->handle(['UserTest']);
        ob_end_clean();

        $path = $this->baseDir . '/tests/UserTest.php';
        file_put_contents($path, '// custom test content');

        ob_start();
        $this->command->handle(['UserTest']);
        ob_end_clean();

        $this->assertSame('// custom test content', file_get_contents($path));
    }

    public function test_command_name(): void
    {
        $this->assertSame('make:test', $this->command->name());
    }
}
