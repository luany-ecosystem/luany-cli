<?php

namespace LuanyCli;

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
}