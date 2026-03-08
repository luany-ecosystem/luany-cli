<?php

namespace LuanyCli\Tests;

use LuanyCli\CommandRegistry;
use LuanyCli\CommandInterface;
use PHPUnit\Framework\TestCase;

class CommandRegistryTest extends TestCase
{
    private function makeCommand(string $name, string $description = 'Test'): CommandInterface
    {
        return new class($name, $description) implements CommandInterface {
            public function __construct(private string $n, private string $d) {}
            public function name(): string { return $this->n; }
            public function description(): string { return $this->d; }
            public function handle(array $args): void {}
        };
    }

    public function test_register_and_get(): void
    {
        $registry = new CommandRegistry();
        $command  = $this->makeCommand('serve');
        $registry->register($command);

        $this->assertSame($command, $registry->get('serve'));
    }

    public function test_get_returns_null_for_unknown(): void
    {
        $registry = new CommandRegistry();
        $this->assertNull($registry->get('nonexistent'));
    }

    public function test_has_returns_true_for_registered(): void
    {
        $registry = new CommandRegistry();
        $registry->register($this->makeCommand('serve'));
        $this->assertTrue($registry->has('serve'));
    }

    public function test_has_returns_false_for_unregistered(): void
    {
        $registry = new CommandRegistry();
        $this->assertFalse($registry->has('serve'));
    }

    public function test_all_returns_all_registered_commands(): void
    {
        $registry = new CommandRegistry();
        $registry->register($this->makeCommand('serve'));
        $registry->register($this->makeCommand('migrate'));

        $all = $registry->all();
        $this->assertCount(2, $all);
        $this->assertArrayHasKey('serve', $all);
        $this->assertArrayHasKey('migrate', $all);
    }

    public function test_register_overwrites_same_name(): void
    {
        $registry = new CommandRegistry();
        $first    = $this->makeCommand('serve', 'First');
        $second   = $this->makeCommand('serve', 'Second');

        $registry->register($first);
        $registry->register($second);

        $this->assertSame($second, $registry->get('serve'));
    }
}