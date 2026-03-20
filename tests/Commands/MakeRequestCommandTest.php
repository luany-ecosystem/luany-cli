<?php

namespace LuanyCli\Tests\Commands;

use LuanyCli\Commands\MakeRequestCommand;
use LuanyCli\Env;
use PHPUnit\Framework\TestCase;

class MakeRequestCommandTest extends TestCase
{
    private string $baseDir;
    private MakeRequestCommand $command;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/luany_req_test_' . uniqid();
        mkdir($this->baseDir, 0755, true);
        Env::setBasePath($this->baseDir);
        $this->command = new MakeRequestCommand();
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

    public function test_creates_request_file(): void
    {
        ob_start();
        $this->command->handle(['StoreUserRequest']);
        ob_end_clean();

        $this->assertFileExists($this->baseDir . '/app/Http/Requests/StoreUserRequest.php');
    }

    public function test_appends_request_suffix_if_missing(): void
    {
        ob_start();
        $this->command->handle(['StoreUser']);
        ob_end_clean();

        $this->assertFileExists($this->baseDir . '/app/Http/Requests/StoreUserRequest.php');
    }

    public function test_does_not_append_duplicate_suffix(): void
    {
        ob_start();
        $this->command->handle(['StoreUserRequest']);
        ob_end_clean();

        $this->assertFileDoesNotExist($this->baseDir . '/app/Http/Requests/StoreUserRequestRequest.php');
    }

    public function test_generated_file_has_correct_namespace(): void
    {
        ob_start();
        $this->command->handle(['StoreUserRequest']);
        ob_end_clean();

        $content = file_get_contents($this->baseDir . '/app/Http/Requests/StoreUserRequest.php');
        $this->assertStringContainsString('namespace App\\Http\\Requests', $content);
    }

    public function test_generated_file_imports_validator(): void
    {
        ob_start();
        $this->command->handle(['StoreUserRequest']);
        ob_end_clean();

        $content = file_get_contents($this->baseDir . '/app/Http/Requests/StoreUserRequest.php');
        $this->assertStringContainsString('Luany\\Framework\\Validation\\Validator', $content);
    }

    public function test_generated_file_imports_request(): void
    {
        ob_start();
        $this->command->handle(['StoreUserRequest']);
        ob_end_clean();

        $content = file_get_contents($this->baseDir . '/app/Http/Requests/StoreUserRequest.php');
        $this->assertStringContainsString('Luany\\Core\\Http\\Request', $content);
    }

    public function test_generated_file_has_rules_method(): void
    {
        ob_start();
        $this->command->handle(['StoreUserRequest']);
        ob_end_clean();

        $content = file_get_contents($this->baseDir . '/app/Http/Requests/StoreUserRequest.php');
        $this->assertStringContainsString('public function rules()', $content);
    }

    public function test_generated_file_has_passes_and_fails_methods(): void
    {
        ob_start();
        $this->command->handle(['StoreUserRequest']);
        ob_end_clean();

        $content = file_get_contents($this->baseDir . '/app/Http/Requests/StoreUserRequest.php');
        $this->assertStringContainsString('public function passes()', $content);
        $this->assertStringContainsString('public function fails()', $content);
    }

    public function test_generated_file_has_validated_method(): void
    {
        ob_start();
        $this->command->handle(['StoreUserRequest']);
        ob_end_clean();

        $content = file_get_contents($this->baseDir . '/app/Http/Requests/StoreUserRequest.php');
        $this->assertStringContainsString('public function validated()', $content);
    }

    public function test_creates_subdirectory_request(): void
    {
        ob_start();
        $this->command->handle(['Auth/LoginRequest']);
        ob_end_clean();

        $this->assertFileExists($this->baseDir . '/app/Http/Requests/Auth/LoginRequest.php');
    }

    public function test_subdirectory_request_has_correct_namespace(): void
    {
        ob_start();
        $this->command->handle(['Auth/LoginRequest']);
        ob_end_clean();

        $content = file_get_contents($this->baseDir . '/app/Http/Requests/Auth/LoginRequest.php');
        $this->assertStringContainsString('namespace App\\Http\\Requests\\Auth', $content);
    }

    public function test_does_not_overwrite_existing_request(): void
    {
        ob_start();
        $this->command->handle(['StoreUserRequest']);
        ob_end_clean();

        $path = $this->baseDir . '/app/Http/Requests/StoreUserRequest.php';
        file_put_contents($path, '// custom content');

        ob_start();
        $this->command->handle(['StoreUserRequest']);
        ob_end_clean();

        $this->assertSame('// custom content', file_get_contents($path));
    }

    public function test_command_name(): void
    {
        $this->assertSame('make:request', $this->command->name());
    }
}
