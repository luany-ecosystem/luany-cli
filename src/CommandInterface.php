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
    public function handle(array $args): void;
}