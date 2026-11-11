<?php

namespace LuanyCli\Tests\Support;

use LuanyCli\Support\Console;
use PHPUnit\Framework\TestCase;

class ConsoleTest extends TestCase
{
    private function io(string $input): Console
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $input);
        rewind($stream);
        return new Console($stream, false);
    }

    private function quiet(callable $fn): mixed
    {
        ob_start();
        try {
            return $fn();
        } finally {
            ob_end_clean();
        }
    }

    public function test_ask_returns_answer_or_default(): void
    {
        $io = $this->io("my-app\n\n");
        $this->assertSame('my-app', $this->quiet(fn () => $io->ask('Name', 'x')));
        $this->assertSame('x', $this->quiet(fn () => $io->ask('Name', 'x')));
        $this->assertSame('x', $this->quiet(fn () => $io->ask('Name', 'x')), 'EOF → default');
    }

    public function test_confirm_variants_and_retry(): void
    {
        $io = $this->io("maybe\ny\nN\n\n");
        $this->assertTrue($this->quiet(fn () => $io->confirm('Ok?', false)));
        $this->assertFalse($this->quiet(fn () => $io->confirm('Ok?', true)));
        $this->assertTrue($this->quiet(fn () => $io->confirm('Ok?', true)));
        $this->assertFalse($this->quiet(fn () => $io->confirm('Ok?', false)), 'EOF → default');
    }

    public function test_choice_by_number_name_default_and_retry(): void
    {
        $options = ['sqlite' => 'file', 'mysql' => 'server', 'pgsql' => 'server'];
        $io = $this->io("3\nmysql\n9\n\n");

        $this->assertSame('pgsql', $this->quiet(fn () => $io->choice('DB', $options, 'sqlite')));
        $this->assertSame('mysql', $this->quiet(fn () => $io->choice('DB', $options, 'sqlite')));
        $this->assertSame('sqlite', $this->quiet(fn () => $io->choice('DB', $options, 'sqlite')), 'invalid then empty → default');
    }
}
