<?php

namespace LuanyCli\Tests\Commands;

use LuanyCli\Commands\CacheClearCommand;
use PHPUnit\Framework\TestCase;
use LuanyCli\Env;

class CacheClearCommandTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/luany_cli_test_' . uniqid();
        mkdir($this->baseDir . '/storage/cache/views', 0755, true);
        Env::setBasePath($this->baseDir);
    }

    protected function tearDown(): void
    {
        Env::reset();
        $this->removeDir($this->baseDir);
    }

    public function test_clears_cached_files(): void
    {
        file_put_contents($this->baseDir . '/storage/cache/views/abc123.php', '<?php echo "cached"; ?>');
        file_put_contents($this->baseDir . '/storage/cache/views/def456.php', '<?php echo "cached2"; ?>');

        $command = new CacheClearCommand();
        $command->handle([]);

        $this->assertFileDoesNotExist($this->baseDir . '/storage/cache/views/abc123.php');
        $this->assertFileDoesNotExist($this->baseDir . '/storage/cache/views/def456.php');
    }

    public function test_handles_empty_cache_directory(): void
    {
        $command = new CacheClearCommand();
        $command->handle([]); // should not throw
        $this->assertTrue(true);
    }

    public function test_command_name(): void
    {
        $this->assertSame('cache:clear', (new CacheClearCommand())->name());
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