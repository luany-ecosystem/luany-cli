<?php

namespace LuanyCli\Tests\Commands;

use LuanyCli\Commands\NewCommand;
use LuanyCli\Support\Console;
use PHPUnit\Framework\TestCase;

class NewCommandFlowTest extends TestCase
{
    private string $cwd;
    private string $dir;

    protected function setUp(): void
    {
        $this->cwd = (string) getcwd();
        $this->dir = sys_get_temp_dir() . '/luany_new_' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        chdir($this->dir);
    }

    protected function tearDown(): void
    {
        chdir($this->cwd);
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    private function command(string $input, bool $interactive): NewCommand
    {
        return new class ($input, $interactive) extends NewCommand {
            public ?int $exit = null;
            public bool $migrated = false;

            public function __construct(private string $input, private bool $tty) {}

            protected function createProject(string $name): bool
            {
                mkdir($name . '/database', 0777, true);
                file_put_contents($name . '/.env.example', "APP_NAME=Luany\nDB_CONNECTION=sqlite\nDB_NAME=database/database.sqlite\n# DB_HOST=127.0.0.1\n");
                return true;
            }

            protected function console(): Console
            {
                $s = fopen('php://memory', 'r+');
                fwrite($s, $this->input);
                rewind($s);
                return new Console($s, false);
            }

            protected function isInteractive(): bool { return $this->tty; }
            protected function gitAvailable(): bool { return false; }
            protected function migrate(string $target): void { $this->migrated = true; }
            protected function terminate(int $code): void { $this->exit = $code; }
        };
    }

    private function exec(NewCommand $command, array $args): string
    {
        ob_start();
        try {
            $command->handle($args);
        } finally {
            return (string) ob_get_clean();
        }
    }

    public function test_non_interactive_defaults_to_sqlite_and_migrates(): void
    {
        $cmd = $this->command('', false);
        $out = $this->exec($cmd, ['shop']);

        $this->assertTrue($cmd->migrated);
        $this->assertFileExists($this->dir . '/shop/database/database.sqlite');
        $this->assertStringContainsString('DB_CONNECTION=sqlite', file_get_contents($this->dir . '/shop/.env'));
        $this->assertStringContainsString('luany dev', $out);
    }

    public function test_interactive_mysql_asks_credentials_and_skips_migrations(): void
    {
        // database=2 (mysql), host default, port default, name, user, password
        $cmd = $this->command("2\n\n\nshop_db\nadmin\nsecret\n", true);
        $out = $this->exec($cmd, ['my-shop']);

        $env = file_get_contents($this->dir . '/my-shop/.env');
        $this->assertStringContainsString('DB_CONNECTION=mysql', $env);
        $this->assertStringContainsString('DB_HOST=127.0.0.1', $env);
        $this->assertStringContainsString('DB_PORT=3306', $env);
        $this->assertStringContainsString('DB_NAME=shop_db', $env);
        $this->assertStringContainsString('DB_USER=admin', $env);
        $this->assertStringContainsString('DB_PASS=secret', $env);
        $this->assertFalse($cmd->migrated);
        $this->assertStringContainsString('luany migrate', $out);
    }

    public function test_database_option_and_no_migrate(): void
    {
        $cmd = $this->command('', true);
        $this->exec($cmd, ['app', '--database=pgsql', '--no-migrate', '--no-git']);

        $env = file_get_contents($this->dir . '/app/.env');
        $this->assertStringContainsString('DB_CONNECTION=pgsql', $env);
        $this->assertStringContainsString('DB_PORT=5432', $env);
        $this->assertStringContainsString('DB_USER=postgres', $env);
    }

    public function test_options_are_validated(): void
    {
        $this->assertSame('x', NewCommand::parseOptions(['x', '-n'])['name']);
        $this->expectException(\InvalidArgumentException::class);
        NewCommand::parseOptions(['x', '--database=oracle']);
    }

    public function test_existing_directory_is_refused(): void
    {
        mkdir($this->dir . '/taken');
        $cmd = $this->command('', false);
        ob_start();
        $cmd->handle(['taken']);
        ob_end_clean();
        $this->assertSame(1, $cmd->exit);
    }
}
