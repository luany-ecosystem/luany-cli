<?php

namespace LuanyCli\Tests\Commands;

use LuanyCli\Commands\ListCommand;
use LuanyCli\CommandInterface;
use LuanyCli\CommandRegistry;
use PHPUnit\Framework\TestCase;

class ListCommandTest extends TestCase
{
    private function makeCommand(string $name, string $desc): CommandInterface
    {
        return new class($name, $desc) implements CommandInterface {
            public function __construct(private string $n, private string $d) {}
            public function name(): string { return $this->n; }
            public function description(): string { return $this->d; }
            public function handle(array $args): void {}
            public function requiresProject(): bool { return true; }
        };
    }

    public function test_command_name(): void
    {
        $registry = new CommandRegistry();
        $this->assertSame('list', (new ListCommand($registry))->name());
    }

    public function test_outputs_all_command_names(): void
    {
        $registry = new CommandRegistry();
        $registry->register($this->makeCommand('serve', 'Start server'));
        $registry->register($this->makeCommand('migrate', 'Run migrations'));

        $command = new ListCommand($registry);

        ob_start();
        $command->handle([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('serve', $output);
        $this->assertStringContainsString('migrate', $output);
    }

    public function test_outputs_descriptions(): void
    {
        $registry = new CommandRegistry();
        $registry->register($this->makeCommand('serve', 'Start the server'));

        $command = new ListCommand($registry);

        ob_start();
        $command->handle([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('Start the server', $output);
    }
}

