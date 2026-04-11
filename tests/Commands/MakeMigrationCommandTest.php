<?php

namespace LuanyCli\Tests\Commands;

use LuanyCli\Commands\MakeMigrationCommand;
use PHPUnit\Framework\TestCase;
use LuanyCli\Env;

class MakeMigrationCommandTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/luany_cli_test_' . uniqid();
        mkdir($this->baseDir . '/database/migrations', 0755, true);
        Env::setBasePath($this->baseDir);
    }

    protected function tearDown(): void
    {
        Env::reset();
        $this->removeDir($this->baseDir);
    }

    public function test_creates_migration_file(): void
    {
        $command = new MakeMigrationCommand();
        $command->handle(['create_users_table']);

        $files = glob($this->baseDir . '/database/migrations/*_create_users_table.php');
        $this->assertCount(1, $files);
    }

    public function test_migration_has_timestamp_prefix(): void
    {
        $command = new MakeMigrationCommand();
        $command->handle(['create_posts_table']);

        $files = glob($this->baseDir . '/database/migrations/*.php');
        $this->assertCount(1, $files);
        $this->assertMatchesRegularExpression('/\d{4}_\d{2}_\d{2}_\d{6}_create_posts_table\.php/', basename($files[0]));
    }

    public function test_migration_contains_up_and_down(): void
    {
        $command = new MakeMigrationCommand();
        $command->handle(['create_tags_table']);

        $files   = glob($this->baseDir . '/database/migrations/*.php');
        $content = file_get_contents($files[0]);

        $this->assertStringContainsString('public function up(', $content);
        $this->assertStringContainsString('public function down(', $content);
    }

    public function test_class_name_from_snake_case(): void
    {
        $command = new MakeMigrationCommand();
        $command->handle(['create_blog_posts_table']);

        $files   = glob($this->baseDir . '/database/migrations/*.php');
        $content = file_get_contents($files[0]);

        $this->assertStringContainsString('class CreateBlogPostsTable', $content);
    }

    public function test_command_name(): void
    {
        $this->assertSame('make:migration', (new MakeMigrationCommand())->name());
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

    public function test_table_name_derived_from_migration_name(): void
    {
        (new MakeMigrationCommand())->handle(['create_orders_table']);

        $files   = glob($this->baseDir . '/database/migrations/*.php');
        $content = file_get_contents($files[0]);

        $this->assertStringContainsString('`orders`', $content);
        $this->assertStringNotContainsString('`example`', $content);
    }

    public function test_table_name_for_non_standard_name(): void
    {
        (new MakeMigrationCommand())->handle(['add_status_to_orders']);

        $files   = glob($this->baseDir . '/database/migrations/*.php');
        $content = file_get_contents($files[0]);

        // Fallback: usa o nome completo quando não bate o padrão create_X_table
        $this->assertStringContainsString('`add_status_to_orders`', $content);
    }

}

