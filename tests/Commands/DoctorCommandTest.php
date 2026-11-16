<?php

namespace LuanyCli\Tests\Commands;

use LuanyCli\Commands\DoctorCommand;
use LuanyCli\Env;
use PHPUnit\Framework\TestCase;

class DoctorCommandTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/luany_doctor_test_' . uniqid();
        mkdir($this->baseDir, 0755, true);
        Env::setBasePath($this->baseDir);
    }

    protected function tearDown(): void
    {
        Env::reset();
        $this->removeDir($this->baseDir);
    }

    public function test_name_is_doctor(): void
    {
        $this->assertSame('doctor', (new DoctorCommand())->name());
    }

    public function test_requires_project_returns_false(): void
    {
        $this->assertFalse((new DoctorCommand())->requiresProject());
    }

    public function test_description_is_not_empty(): void
    {
        $this->assertNotEmpty((new DoctorCommand())->description());
    }

    public function test_handle_outputs_php_version(): void
    {
        ob_start();
        (new DoctorCommand())->handle([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('PHP version', $output);
        $this->assertStringContainsString(PHP_VERSION, $output);
    }

    public function test_handle_outputs_extension_checks(): void
    {
        ob_start();
        (new DoctorCommand())->handle([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('ext/pdo', $output);
        $this->assertStringContainsString('ext/mbstring', $output);
    }

    public function test_handle_outputs_cli_version(): void
    {
        ob_start();
        (new DoctorCommand())->handle([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('luany/cli', $output);
    }

    public function test_handle_shows_project_hint_outside_project(): void
    {
        ob_start();
        (new DoctorCommand())->handle([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('Run inside a Luany project', $output);
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
    public function test_checks_the_pdo_extension_of_the_configured_driver(): void
    {
        file_put_contents($this->baseDir . '/composer.json', json_encode(['require' => ['luany/framework' => '^1.0']]));
        file_put_contents($this->baseDir . '/.env', "APP_KEY=x\nDB_CONNECTION=pgsql\nDB_HOST=127.0.0.1\nDB_PORT=1\n");

        ob_start();
        (new DoctorCommand())->handle([]);
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('ext/pdo_pgsql', $output);
        $this->assertStringNotContainsString('ext/pdo_mysql', $output);
    }

    public function test_checks_pdo_sqlite_by_default(): void
    {
        ob_start();
        (new DoctorCommand())->handle([]);
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('ext/pdo_sqlite', $output);
        $this->assertStringNotContainsString('ext/pdo_mysql', $output);
    }

}

