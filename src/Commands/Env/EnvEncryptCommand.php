<?php

namespace LuanyCli\Commands\Env;

use LuanyCli\BaseCommand;
use LuanyCli\Env;
use LuanyCli\Support\EncryptedEnv;

/**
 * env:encrypt [--env=production] [--key=base64:…] [--force]
 *
 * Encrypts .env.{env} (or .env) into .env.{env}.encrypted. Without a key
 * (--key, LUANY_ENV_KEY_{ENV}, LUANY_ENV_KEY) a new one is generated and
 * printed once.
 */
class EnvEncryptCommand extends BaseCommand
{
    public function name(): string
    {
        return 'env:encrypt';
    }

    public function description(): string
    {
        return 'Encrypt .env into .env.{env}.encrypted (safe to commit)';
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

            $source = EncryptedEnv::plainPath($base, $o['env']);
            if (!is_file($source)) {
                throw new \RuntimeException(basename($source) . ' not found — nothing to encrypt.');
            }
            $target = EncryptedEnv::encryptedPath($base, $o['env']);
            if (is_file($target) && !$o['force']) {
                throw new \RuntimeException(basename($target) . ' already exists. Use luany env:edit / env:set, or --force to replace it.');
            }

            $key = EncryptedEnv::key($o['key'], $o['env']);
            $generated = $key === null;
            $key ??= $cipher::generateKey();

            file_put_contents($target, $cipher::encrypt((string) file_get_contents($source), $key, $o['env']));
        } catch (\RuntimeException | \InvalidArgumentException $e) {
            echo "\n  \033[31m✗\033[0m  {$e->getMessage()}\n\n";
            return 1;
        }

        echo "\n  \033[32m✓\033[0m  " . basename($source) . ' → ' . basename($target) . "\n";
        if ($generated) {
            echo "\n  \033[33mKey\033[0m  {$key}\n\n"
                . "     Store it in a password manager — it is shown only once and cannot be recovered.\n"
                . "     On the server or in CI secrets: \033[36m" . $cipher::keyVariable($o['env']) . "={$key}\033[0m\n";
        }
        echo "\n     " . basename($target) . ' can be committed. Keep ' . basename($source) . " out of git (or delete it).\n\n";

        return 0;
    }
}
