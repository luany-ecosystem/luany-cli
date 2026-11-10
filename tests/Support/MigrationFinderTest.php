<?php

namespace LuanyCli\Tests\Support;

use LuanyCli\Support\MigrationFinder;
use PHPUnit\Framework\TestCase;

class MigrationFinderTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/luany_migfinder_' . uniqid();
        mkdir($this->dir, 0755, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    public function test_finds_existing_migration(): void
    {
        touch($this->dir . '/2024_05_01_120000_create_posts_table.php');

        $this->assertSame('2024_05_01_120000_create_posts_table.php', MigrationFinder::find($this->dir, 'create_posts_table'));
    }

    public function test_returns_null_when_missing(): void
    {
        $this->assertNull(MigrationFinder::find($this->dir, 'create_posts_table'));
    }

    public function test_does_not_match_suffix_of_other_name(): void
    {
        touch($this->dir . '/2024_05_01_120000_create_blog_posts_table.php');
        touch($this->dir . '/2024_05_01_120000_recreate_posts_table.php');

        $this->assertNull(MigrationFinder::find($this->dir, 'create_posts_table'));
        $this->assertNull(MigrationFinder::find($this->dir, 'posts_table'));
    }

    public function test_returns_null_for_missing_directory(): void
    {
        $this->assertNull(MigrationFinder::find($this->dir . '/nope', 'create_posts_table'));
    }
}
