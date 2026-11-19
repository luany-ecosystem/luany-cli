<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
use LuanyCli\Env;
use LuanyCli\Support\NetworkValidator;

class ServeCommand extends BaseCommand
{
    public function name(): string
    {
        return 'serve';
    }

    public function description(): string
    {
        return 'Start the built-in PHP development server';
    }

    /** @param array<int, string> $args */
    public function handle(array $args): void
    {
        $host = NetworkValidator::host($args[0] ?? 'localhost');
        $port = NetworkValidator::port($args[1] ?? '8000');

        echo "\n  \033[32m✓\033[0m  Luany development server started\n";
        echo "  \033[36m→\033[0m  http://{$host}:{$port}\n";
        echo "  Press Ctrl+C to stop.\n\n";

        passthru(self::buildCommand($host, $port, Env::basePath() . '/public'));
    }

    /**
     * Build the shell command that starts the PHP built-in server.
     * Every argument is escaped; the host and port must already be validated.
     */
    public static function buildCommand(string $host, int $port, string $docRoot): string
    {
        return implode(' ', [
            escapeshellarg(PHP_BINARY),
            '-S',
            escapeshellarg("{$host}:{$port}"),
            '-t',
            escapeshellarg($docRoot),
            escapeshellarg(self::routerPath()),
        ]);
    }

    /**
     * Router script that sends every non-file URI to public/index.php
     * (the built-in server would otherwise 404 URIs such as /sitemap.xml).
     */
    public static function routerPath(): string
    {
        return dirname(__DIR__, 2) . '/resources/server.php';
    }
}
