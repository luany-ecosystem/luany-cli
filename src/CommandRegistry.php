<?php

namespace LuanyCli;

class CommandRegistry
{
    /** @var array<string, CommandInterface> */
    private array $commands = [];

    public function register(CommandInterface $command): void
    {
        $this->commands[$command->name()] = $command;
    }

    /** @var array<string, string> alias => command name */
    private array $aliases = [];

    public function alias(string $alias, string $name): void
    {
        $this->aliases[$alias] = $name;
    }

    public function get(string $name): ?CommandInterface
    {
        return $this->commands[$name] ?? $this->commands[$this->aliases[$name] ?? ''] ?? null;
    }

    /** @return array<string, CommandInterface> */
    public function all(): array
    {
        return $this->commands;
    }

    public function has(string $name): bool
    {
        return isset($this->commands[$name]);
    }
}

