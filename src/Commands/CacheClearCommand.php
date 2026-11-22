<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
use LuanyCli\Output\Output;
use LuanyCli\Output\StructuredCommand;
use LuanyCli\Env;

class CacheClearCommand extends BaseCommand implements StructuredCommand
{
    public function name(): string
    {
        return 'cache:clear';
    }

    public function description(): string
    {
        return 'Clear compiled view cache';
    }

    /** @param array<int, string> $args */
    public function handle(array $args): void
    {
        $path  = Env::basePath() . '/storage/cache/views';
        $files = glob($path . '/*.php') ?: [];

        $out = Output::current();
        foreach ($files as $file) {
            $out->planned('delete', 'storage/cache/views/' . basename($file));
            if (!$out->dryRun) {
                unlink($file);
            }
        }

        $out->data(['removed' => $out->dryRun ? 0 : count($files)]);
        $out->say($out->dryRun ? 'LUANY-CACHE-002' : 'LUANY-CACHE-001', ['count' => count($files)], $out->dryRun ? 'info' : 'success');
    }
}

