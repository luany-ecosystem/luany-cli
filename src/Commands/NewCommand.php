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

        $name   = self::validateProjectName($name);
        $target = getcwd() . DIRECTORY_SEPARATOR . $name;

        if (is_dir($target)) {
            fwrite(STDERR, "\n  \033[31m✗\033[0m  Project [{$name}] already exists.\n\n");
            exit(1);
        }

        echo "\n  \033[33m→\033[0m  Creating new Luany project [{$name}]...\n\n";

        passthru(self::buildCommand($name), $exitCode);

        if ($exitCode !== 0) {
            fwrite(STDERR, "\n  \033[31m✗\033[0m  Project creation failed.\n\n");
            exit(1);
        }

        echo "\n  \033[32m✓\033[0m  Done! Now run: \033[36mcd {$name}\033[0m\n\n";
    }

    /**
     * A project name is a single directory name: letters, digits, '.', '_'
     * and '-', starting with a letter or digit (so it can never be parsed
     * as an option such as --repository=…), and never '.' or '..'.
     *
     * @throws \InvalidArgumentException
     */
    public static function validateProjectName(string $name): string
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $name) || strlen($name) > 255) {
            throw new \InvalidArgumentException(
                "Invalid project name [{$name}] — use letters, digits, '.', '_' and '-', starting with a letter or digit."
            );
        }

        return $name;
    }

    /**
     * Build the composer command. `--` ends option parsing so the positional
     * arguments can never be interpreted as composer options.
     */
    public static function buildCommand(string $name): string
    {
        return 'composer create-project -- luany/luany ' . escapeshellarg($name);
    }
}

