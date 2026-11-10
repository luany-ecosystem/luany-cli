<?php

namespace LuanyCli\Tests\Support;

use LuanyCli\Support\EnvException;
use LuanyCli\Support\EnvRepository;
use PHPUnit\Framework\TestCase;

class EnvRepositoryTest extends TestCase
{
    private string $dir;

    /** @var array<int, string> */
    private array $touched = [];

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/luany_env_' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach ($this->touched as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
        @chmod($this->dir . '/.env', 0644);
        @unlink($this->dir . '/.env');
        @rmdir($this->dir);
    }

    private function setEnv(string $key, string $value): void
    {
        $this->touched[] = $key;
        putenv("{$key}={$value}");
    }

    public function test_reads_values_from_env_file(): void
    {
        file_put_contents($this->dir . '/.env', "DB_HOST=file-host\nDB_NAME=\"file_db\"\n");

        $env = EnvRepository::load($this->dir);

        $this->assertTrue($env->hasFile());
        $this->assertSame('file-host', $env->get('DB_HOST'));
        $this->assertSame('file_db', $env->get('DB_NAME'));
        $this->assertSame('3306', $env->get('DB_PORT', '3306'));
    }

    public function test_real_environment_wins_over_env_file(): void
    {
        file_put_contents($this->dir . '/.env', "DB_HOST=file-host\nDB_NAME=file_db\n");
        $this->setEnv('DB_HOST', 'docker-db');

        $env = EnvRepository::load($this->dir);

        $this->assertSame('docker-db', $env->get('DB_HOST'));
        $this->assertTrue($env->isFromEnvironment('DB_HOST'));
        $this->assertSame('file_db', $env->get('DB_NAME'));
        $this->assertFalse($env->isFromEnvironment('DB_NAME'));
    }

    public function test_works_without_env_file_using_real_environment(): void
    {
        $this->setEnv('DB_NAME', 'from_ci');

        $env = EnvRepository::load($this->dir);

        $this->assertFalse($env->hasFile());
        $this->assertTrue($env->has('DB_NAME'));
        $this->assertSame('from_ci', $env->get('DB_NAME'));
    }

    public function test_unreadable_env_file_is_an_error_not_a_silent_default(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('root can read any file');
        }

        file_put_contents($this->dir . '/.env', "DB_HOST=secret-host\n");
        chmod($this->dir . '/.env', 0000);

        if (is_readable($this->dir . '/.env')) {
            $this->markTestSkipped('filesystem does not enforce permissions here');
        }

        $this->expectException(EnvException::class);
        $this->expectExceptionMessage('cannot be read');
        EnvRepository::load($this->dir);
    }
}
