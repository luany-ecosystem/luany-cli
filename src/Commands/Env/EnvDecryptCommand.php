<?php

namespace LuanyCli\Commands\Env;

use LuanyCli\BaseCommand;
use LuanyCli\Env;
use LuanyCli\Support\EncryptedEnv;

/**
 * env:decrypt [--env=production] [--key=base64:…] [--force]
 *
 * Writes the plain .env.{env} (or .env) back from .env.{env}.encrypted.
 */
class EnvDecryptCommand extends BaseCommand
{
    public function name(): string
    {
        return 'env:decrypt';
    }

    public function description(): string
    {
        return 'Decrypt .env.{env}.encrypted back into a plain file';
    }

    /** @param array<int, string> $args */
    public function handle(array $args): void
    {
        exit(self::run(Env::basePath(), $args));
    }

    /** @param array<int, string> $args */
    public static function run(string $base, array $args): int
    {
        try {
            $o = EncryptedEnv::options($args);
            EncryptedEnv::boot($base);
            $cipher = EncryptedEnv::CIPHER;

            $source = EncryptedEnv::encryptedPath($base, $o['env']);
            if (!is_file($source)) {
                throw new \RuntimeException(basename($source) . ' not found.');
            }
            $key = EncryptedEnv::key($o['key'], $o['env']) ?? throw new \RuntimeException(EncryptedEnv::missingKey($o['env']));
            $target = EncryptedEnv::plainPath($base, $o['env'], mustExist: false);
            if (is_file($target) && !$o['force']) {
                throw new \RuntimeException(basename($target) . ' already exists. Use --force to overwrite it.');
            }

            $plain = $cipher::decrypt((string) file_get_contents($source), $key, $o['env']);
            file_put_contents($target, $plain);
            @chmod($target, 0600);
        } catch (\RuntimeException | \InvalidArgumentException $e) {
            echo "\n  \033[31m✗\033[0m  {$e->getMessage()}\n\n";
            return 1;
        }

        echo "\n  \033[32m✓\033[0m  " . basename($source) . ' → ' . basename($target) . "  \033[33m(plain text — keep it out of git)\033[0m\n\n";

        return 0;
    }
}
