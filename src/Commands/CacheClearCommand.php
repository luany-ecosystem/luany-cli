<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
use LuanyCli\Env;

class CacheClearCommand extends BaseCommand
{
    public function name(): string
    {
        return 'cache:clear';
    }

    public function description(): string
    {
        return 'Clear compiled view cache';
    }

    public function handle(array $args): void
    {
        $path  = Env::basePath() . '/storage/cache/views';
        $files = glob($path . '/*.php') ?: [];

        foreach ($files as $file) {
            unlink($file);
        }

        $count = count($files);
        echo "\n  \033[32m✓\033[0m  Cache cleared ({$count} file(s) removed).\n\n";
    }
}