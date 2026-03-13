<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
use LuanyCli\CommandRegistry;

class ListCommand extends BaseCommand
{
    public function requiresProject(): bool
    {
        return false;
    }

    public function __construct(private CommandRegistry $registry) {}

    public function name(): string
    {
        return 'list';
    }

    public function description(): string
    {
        return 'List all available commands';
    }

    public function handle(array $args): void
    {
        echo "\n  \033[32mUsage:\033[0m   luany <command> [arguments]\n\n";
        echo "  \033[32mCommands:\033[0m\n";

        $commands = $this->registry->all();
        $maxLen   = max(array_map(fn($c) => strlen($c->name()), $commands));

        foreach ($commands as $command) {
            printf(
                "    \033[36m%-{$maxLen}s\033[0m   %s\n",
                $command->name(),
                $command->description()
            );
        }

        echo "\n";
    }
}