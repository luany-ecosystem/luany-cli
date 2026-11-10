<?php

namespace LuanyCli;

use LuanyCli\Support\NameValidator;

/**
 * BaseCommand
 *
 * Abstract base for all CLI commands.
 * Defaults requiresProject() to true — the vast majority of commands
 * must run inside a valid, installed Luany project.
 *
 * Override requiresProject() and return false for commands that
 * are informational and run anywhere (e.g. about, list).
 */
abstract class BaseCommand implements CommandInterface
{
    public function requiresProject(): bool
    {
        return true;
    }

    /**
     * Validate a class name (converting snake/kebab-case to StudlyCase)
     * and append $suffix if missing.
     *
     * @throws \LuanyCli\Support\InvalidNameException
     */
    protected function normalizeName(string $name, string $suffix): string
    {
        $name = NameValidator::className($name);
        return str_ends_with($name, $suffix) ? $name : $name . $suffix;
    }

    /**
     * Validate a "Sub/Dir/Name" class path and append $suffix to the class.
     *
     * @return array{subPath: string, className: string}
     * @throws \LuanyCli\Support\InvalidNameException
     */
    protected function normalizeClassPath(string $name, string $suffix): array
    {
        $parsed = NameValidator::classPath($name);

        return [
            'subPath'   => implode('/', $parsed['segments']),
            'className' => $this->normalizeName($parsed['class'], $suffix),
        ];
    }
}

