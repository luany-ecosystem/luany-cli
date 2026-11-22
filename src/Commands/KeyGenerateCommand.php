<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
use LuanyCli\Output\CommandError;
use LuanyCli\Output\Output;
use LuanyCli\Output\StructuredCommand;
use LuanyCli\Env;

class KeyGenerateCommand extends BaseCommand implements StructuredCommand
{
    public function name(): string
    {
        return 'key:generate';
    }

    public function description(): string
    {
        return 'Generate and set APP_KEY in .env';
    }

    /** @param array<int, string> $args */
    public function handle(array $args): void
    {
        $out     = Output::current();
        $envFile = Env::basePath() . '/.env';
        $content = null;

        if (!file_exists($envFile)) {
            $example = Env::basePath() . '/.env.example';
            if (!file_exists($example)) {
                throw new CommandError('LUANY-KEY-001');
            }
            $out->planned('create', '.env');
            $content = (string) file_get_contents($example);
            if (!$out->dryRun) {
                copy($example, $envFile);
                $out->compact();
                $out->say('LUANY-KEY-002');
            }
        }

        // The key is a secret: the plan never carries a diff for it.
        $out->planned('update', '.env');
        if ($out->dryRun) {
            return;
        }

        $key     = 'base64:' . base64_encode(random_bytes(32));
        $content ??= (string) file_get_contents($envFile);

        if (str_contains($content, 'APP_KEY=')) {
            $content = preg_replace('/^APP_KEY=.*/m', "APP_KEY={$key}", $content);
        } else {
            $content .= "\nAPP_KEY={$key}";
        }

        file_put_contents($envFile, $content);
        $out->compact();
        $out->say('LUANY-KEY-003');
        $out->line();
    }
}

