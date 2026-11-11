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

        $this->assertStringContainsString('return new class extends Migration', $content);
        $this->assertStringContainsString("create('blog_posts', function (Blueprint \$table)", $content);
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

        $this->assertStringContainsString("create('orders'", $content);
        $this->assertStringContainsString("dropIfExists('orders')", $content);
    }

    public function test_table_name_for_non_standard_name(): void
    {
        (new MakeMigrationCommand())->handle(['add_status_to_orders_table']);

        $files   = glob($this->baseDir . '/database/migrations/*.php');
        $content = file_get_contents($files[0]);

        // add_*_to_{table}_table → alter template on that table
        $this->assertStringContainsString("table('orders', function (Blueprint \$table)", $content);

        (new MakeMigrationCommand())->handle(['backfill_slugs']);
        $blank = file_get_contents(glob($this->baseDir . '/database/migrations/*_backfill_slugs.php')[0]);
        $this->assertStringNotContainsString('schema(', $blank);
        $this->assertStringContainsString('public function up(\\PDO $pdo): void', $blank);
    }

    // -- Name validation (#1) --------------------------------------------------

    public function test_rejects_path_traversal(): void
    {
        try {
            (new MakeMigrationCommand())->handle(['../../evil']);
            $this->fail('Expected InvalidNameException');
        } catch (\LuanyCli\Support\InvalidNameException) {
        }

        $this->assertSame([], glob($this->baseDir . '/database/migrations/*') ?: []);
        $this->assertSame([], glob($this->baseDir . '/*evil*') ?: []);
    }

    public function test_rejects_code_injection_in_name(): void
    {
        $this->expectException(\LuanyCli\Support\InvalidNameException::class);
        (new MakeMigrationCommand())->handle(["x{}\";system('id');//"]);
    }

    // -- Duplicate migration (#2) ----------------------------------------------

    public function test_refuses_duplicate_migration_name(): void
    {
        file_put_contents($this->baseDir . '/database/migrations/2020_01_01_000000_create_users_table.php', '<?php');

        try {
            (new MakeMigrationCommand())->handle(['create_users_table']);
            $this->fail('Expected InvalidNameException');
        } catch (\LuanyCli\Support\InvalidNameException $e) {
            $this->assertStringContainsString('already exists', $e->getMessage());
        }

        $this->assertCount(1, glob($this->baseDir . '/database/migrations/*.php') ?: []);
    }
}
