<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;

class NewCommand extends BaseCommand
{
    public function name(): string
    {
        return 'new';
    }

    public function description(): string
    {
        return 'Create a new Luany project';
    }

    public function requiresProject(): bool
    {
        return false;
    }

    /** @param array<int, string> $args */
    public function handle(array $args): void
    {
        $name = $args[0] ?? null;

        if (!$name) {
            fwrite(STDERR, "\n  \033[31m✗\033[0m  Usage: luany new <project-name>\n\n");
            exit(1);
        }

        $target = getcwd() . DIRECTORY_SEPARATOR . $name;

        if (is_dir($target)) {
            fwrite(STDERR, "\n  \033[31m✗\033[0m  Project [{$name}] already exists.\n\n");
            exit(1);
        }

        echo "\n  \033[33m→\033[0m  Creating new Luany project [{$name}]...\n\n";

        passthru('composer create-project luany/luany ' . escapeshellarg($name), $exitCode);

        if ($exitCode !== 0) {
            fwrite(STDERR, "\n  \033[31m✗\033[0m  Project creation failed.\n\n");
            exit(1);
        }

        echo "\n  \033[32m✓\033[0m  Done! Now run: \033[36mcd {$name}\033[0m\n\n";
    }
}

