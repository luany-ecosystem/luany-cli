<?php

namespace LuanyCli;

interface CommandInterface
{
    /**
     * The command name as typed in the terminal.
     * Example: 'make:controller', 'serve', 'migrate'
     */
    public function name(): string;

    /**
     * Short description shown in luany list.
     */
    public function description(): string;

    /**
     * Execute the command.
     */
    /** @param array<int, string> $args */
    public function handle(array $args): void;

    /**
     * Whether this command requires a valid, installed Luany project.
     * Commands that return false (e.g. about, list) run anywhere.
     */
    public function requiresProject(): bool;
}

