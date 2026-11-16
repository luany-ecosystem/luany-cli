<?php

namespace LuanyCli\Tests\Commands;

use LuanyCli\Commands\MigrateBaseCommand;
use LuanyCli\Commands\MigrateRollbackCommand;
use LuanyCli\Support\EnvRepository;
use PHPUnit\Framework\TestCase;

class MigrateBaseCommandTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        foreach (['DB_CONNECTION', 'DB_NAME'] as $key) {
            if (getenv($key) !== false || isset($_ENV[$key]) || isset($_SERVER[$key])) {
                $this->markTestSkipped("{$key} is set in the real environment.");
            }
        }

        $this->baseDir = sys_get_temp_dir() . '/luany_migrate_base_' . uniqid();
        mkdir($this->baseDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (isset($this->baseDir) && is_dir($this->baseDir)) {
            @unlink($this->baseDir . '/database/database.sqlite');
            @rmdir($this->baseDir . '/database');
            @unlink($this->baseDir . '/.env');
            rmdir($this->baseDir);
        }
    }

    // ── Default driver (skeleton default: SQLite) ─────────────────────────────

    public function test_driver_defaults_to_sqlite(): void
    {
        file_put_contents($this->baseDir . '/.env', "APP_NAME=Test\n");

        $this->assertSame('sqlite', MigrateBaseCommand::driver(EnvRepository::load($this->baseDir)));
    }

    public function test_driver_is_read_from_env_and_lowercased(): void
    {
        file_put_contents($this->baseDir . '/.env', "DB_CONNECTION=PgSQL\n");

        $this->assertSame('pgsql', MigrateBaseCommand::driver(EnvRepository::load($this->baseDir)));
    }

    public function test_connect_without_db_connection_uses_the_skeleton_sqlite_database(): void
    {
        file_put_contents($this->baseDir . '/.env', "APP_NAME=Test\n");

        $pdo = MigrateBaseCommand::connect(EnvRepository::load($this->baseDir), $this->baseDir);

        $this->assertSame('sqlite', $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME));
        $this->assertFileExists($this->baseDir . '/database/database.sqlite');
    }

    public function test_connect_enables_sqlite_foreign_keys(): void
    {
        file_put_contents($this->baseDir . '/.env', "DB_CONNECTION=sqlite\nDB_NAME=:memory:\n");

        $pdo = MigrateBaseCommand::connect(EnvRepository::load($this->baseDir), $this->baseDir);

        $this->assertSame(1, (int) $pdo->query('PRAGMA foreign_keys')->fetchColumn());
    }

    // ── Model connection for seeders / migrations ─────────────────────────────

    public function test_binds_the_model_connection_when_the_model_class_exists(): void
    {
        if (class_exists(\Luany\Database\Model::class)) {
            $this->markTestSkipped('Real luany/database loaded; covered by the fake below only.');
        }
        require_once __DIR__ . '/../Fixtures/FakeDatabase.php';

        $pdo = new \PDO('sqlite::memory:');

        $this->assertTrue(MigrateBaseCommand::bindModelConnection($pdo));
        $this->assertSame($pdo, \Luany\Database\Model::$bound?->getPdo());
    }

    // ── migrate:rollback --step ───────────────────────────────────────────────

    public function test_rollback_step_option_is_parsed(): void
    {
        $this->assertNull(MigrateRollbackCommand::parseStep([]));
        $this->assertSame(3, MigrateRollbackCommand::parseStep(['--step=3']));
        $this->assertSame(2, MigrateRollbackCommand::parseStep(['--step', '2']));
    }

    public function test_rollback_step_option_rejects_invalid_values(): void
    {
        foreach ([['--step=0'], ['--step=-1'], ['--step=abc'], ['--step'], ['--bogus']] as $args) {
            try {
                MigrateRollbackCommand::parseStep($args);
                $this->fail('Expected InvalidArgumentException for ' . implode(' ', $args));
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('migrate:rollback', $e->getMessage());
            }
        }
    }

    public function test_rollback_help_mentions_step(): void
    {
        ob_start();
        (new MigrateRollbackCommand())->handle(['--help']);
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('--step=N', $output);
        $this->assertStringContainsString('--step', (new MigrateRollbackCommand())->description());
    }
}
