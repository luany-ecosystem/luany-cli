<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
use LuanyCli\Env;

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

    public function handle(array $args): void
    {
        $host = $args[0] ?? 'localhost';
        $port = $args[1] ?? '8000';

        echo "\n  \033[32m✓\033[0m  Luany development server started\n";
        echo "  \033[36m→\033[0m  http://{$host}:{$port}\n";
        echo "  Press Ctrl+C to stop.\n\n";

        passthru("php -S {$host}:{$port} -t " . escapeshellarg(Env::basePath() . '/public'));
    }
}

