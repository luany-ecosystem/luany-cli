<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
use LuanyCli\CommandRegistry;
use LuanyCli\Output\Output;
use LuanyCli\Support\Portuguese;

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

    /** @param array<int, string> $args */
    public function handle(array $args): void
    {
        $text     = Output::current()->text;
        $pt       = $text->language === 'pt';
        $commands = $this->registry->all();

        echo "\n  \033[32m" . $text->text('LUANY-CLI-009') . "\033[0m   luany <" . $text->text('LUANY-CLI-011') . '> [' . $text->text('LUANY-CLI-012') . "]\n\n";
        echo "  \033[32m" . $text->text('LUANY-CLI-010') . "\033[0m\n";

        $rows = [];
        foreach ($commands as $command) {
            $alias = $pt ? Portuguese::commandAlias($command->name()) : null;
            $description = $pt ? $text->text('cmd.' . $command->name()) : $command->description();
            if ($description === 'cmd.' . $command->name()) {
                $description = $command->description();
            }
            $rows[] = [$alias ?? $command->name(), $alias !== null ? $command->name() : '', $description];
        }

        $nameWidth  = max(array_map(fn (array $r): int => mb_strlen($r[0]), $rows));
        $aliasWidth = max(array_map(fn (array $r): int => mb_strlen($r[1]), $rows));

        foreach ($rows as [$name, $english, $description]) {
            echo "    \033[36m" . str_pad($name, $nameWidth) . "\033[0m   "
                . ($pt ? "\033[2m" . str_pad($english, $aliasWidth) . "\033[0m   " : '')
                . $description . "\n";
        }

        echo "\n";
    }
}

