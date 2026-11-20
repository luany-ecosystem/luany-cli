<?php

namespace LuanyCli\Commands\Env;

use LuanyCli\BaseCommand;
use LuanyCli\Env;
use LuanyCli\Support\EncryptedEnv;

/**
 * env:set KEY=VALUE [KEY=VALUE …] [--env=production] [--key=base64:…]
 *
 * Changes values inside .env.{env}.encrypted without ever writing the
 * plain file: decrypt in memory, set, encrypt again.
 */
class EnvSetCommand extends BaseCommand
{
    public function name(): string
    {
        return 'env:set';
    }

    public function description(): string
    {
        return 'Set values in an encrypted environment file (KEY=VALUE …)';
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
            if ($o['rest'] === []) {
                throw new \InvalidArgumentException('Usage: luany env:set KEY=VALUE [KEY=VALUE …] [--env=production]');
            }
            $pairs = [];
            foreach ($o['rest'] as $pair) {
                if (!str_contains($pair, '=')) {
                    throw new \InvalidArgumentException("Expected KEY=VALUE, got [{$pair}].");
                }
                [$name, $value] = explode('=', $pair, 2);
                $pairs[trim($name)] = $value;
            }

            EncryptedEnv::boot($base);
            $cipher = EncryptedEnv::CIPHER;
            $file = EncryptedEnv::encryptedPath($base, $o['env']);
            if (!is_file($file)) {
                throw new \RuntimeException(basename($file) . ' not found. Create it with: luany env:encrypt' . ($o['env'] !== '' ? " --env={$o['env']}" : ''));
            }
            $key = EncryptedEnv::key($o['key'], $o['env']) ?? throw new \RuntimeException(EncryptedEnv::missingKey($o['env']));

            $plain = $cipher::decrypt((string) file_get_contents($file), $key, $o['env']);
            file_put_contents($file, $cipher::encrypt(EncryptedEnv::setValues($plain, $pairs), $key, $o['env']));
        } catch (\RuntimeException | \InvalidArgumentException $e) {
            echo "\n  \033[31m✗\033[0m  {$e->getMessage()}\n\n";
            return 1;
        }

        echo "\n  \033[32m✓\033[0m  " . implode(', ', array_keys($pairs)) . ' set in ' . basename($file) . "\n\n";

        return 0;
    }
}
