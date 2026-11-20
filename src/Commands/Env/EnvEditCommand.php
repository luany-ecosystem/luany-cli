<?php

namespace LuanyCli\Commands\Env;

use LuanyCli\BaseCommand;
use LuanyCli\Env;
use LuanyCli\Support\EncryptedEnv;

/**
 * env:edit [--env=production] [--key=base64:…]
 *
 * Opens the decrypted content in $VISUAL / $EDITOR (notepad on Windows,
 * else nano/vi) from a private temporary file, then encrypts it again and
 * deletes the temporary file.
 */
class EnvEditCommand extends BaseCommand
{
    /** @var (callable(string): int)|null Test seam: opens the file, returns the exit code. */
    public static $editor = null;

    public function name(): string
    {
        return 'env:edit';
    }

    public function description(): string
    {
        return 'Edit an encrypted environment file in your editor';
    }

    /** @param array<int, string> $args */
    public function handle(array $args): void
    {
        exit(self::run(Env::basePath(), $args));
    }

    /** @param array<int, string> $args */
    public static function run(string $base, array $args): int
    {
        $tmp = null;
        try {
            $o = EncryptedEnv::options($args);
            EncryptedEnv::boot($base);
            $cipher = EncryptedEnv::CIPHER;
            $file = EncryptedEnv::encryptedPath($base, $o['env']);
            if (!is_file($file)) {
                throw new \RuntimeException(basename($file) . ' not found. Create it with: luany env:encrypt' . ($o['env'] !== '' ? " --env={$o['env']}" : ''));
            }
            $key = EncryptedEnv::key($o['key'], $o['env']) ?? throw new \RuntimeException(EncryptedEnv::missingKey($o['env']));
            $plain = $cipher::decrypt((string) file_get_contents($file), $key, $o['env']);

            $dir = sys_get_temp_dir() . '/luany-env-' . bin2hex(random_bytes(6));
            mkdir($dir, 0700);
            $tmp = $dir . '/' . basename(EncryptedEnv::plainPath($base, $o['env'], mustExist: false));
            file_put_contents($tmp, $plain);
            chmod($tmp, 0600);

            $code = self::$editor !== null ? (self::$editor)($tmp) : self::openEditor($tmp);
            if ($code !== 0) {
                throw new \RuntimeException("The editor exited with code {$code}; nothing was changed.");
            }

            $edited = (string) file_get_contents($tmp);
            if ($edited === $plain) {
                echo "\n  \033[33m→\033[0m  No changes.\n\n";
                return 0;
            }
            file_put_contents($file, $cipher::encrypt($edited, $key, $o['env']));
        } catch (\RuntimeException | \InvalidArgumentException $e) {
            echo "\n  \033[31m✗\033[0m  {$e->getMessage()}\n\n";
            return 1;
        } finally {
            if ($tmp !== null && is_file($tmp)) {
                file_put_contents($tmp, str_repeat("\0", (int) filesize($tmp)));
                unlink($tmp);
                @rmdir(dirname($tmp));
            }
        }

        echo "\n  \033[32m✓\033[0m  " . basename($file) . " updated.\n\n";

        return 0;
    }

    private static function openEditor(string $file): int
    {
        $editor = (string) (getenv('VISUAL') ?: getenv('EDITOR') ?: (DIRECTORY_SEPARATOR === '\\' ? 'notepad' : (self::which('nano') ? 'nano' : 'vi')));
        passthru($editor . ' ' . escapeshellarg($file), $code);

        return (int) $code;
    }

    private static function which(string $bin): bool
    {
        return trim((string) @shell_exec('command -v ' . escapeshellarg($bin) . ' 2>/dev/null')) !== '';
    }
}
