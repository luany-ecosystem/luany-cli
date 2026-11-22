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

            $tmp = self::privateTempFile(basename(EncryptedEnv::plainPath($base, $o['env'], mustExist: false)), $plain);

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
            if ($tmp !== null) {
                self::shred(dirname($tmp));
            }
        }

        echo "\n  \033[32m✓\033[0m  " . basename($file) . " updated.\n\n";

        return 0;
    }

    /**
     * Writes the plaintext into a fresh 0700 directory as a 0600 file. The
     * umask is tightened before anything is created, so there is no window
     * where the file exists with wider permissions, and 'x' refuses to
     * follow a pre-existing file or symlink.
     */
    private static function privateTempFile(string $name, string $content): string
    {
        $umask = umask(0077);
        try {
            $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'luany-env-' . bin2hex(random_bytes(12));
            if (!@mkdir($dir, 0700)) {
                throw new \RuntimeException('Could not create a private temporary directory.');
            }
            $tmp    = $dir . DIRECTORY_SEPARATOR . $name;
            $handle = @fopen($tmp, 'x');
            if ($handle === false) {
                @rmdir($dir);
                throw new \RuntimeException('Could not create a private temporary file.');
            }
            fwrite($handle, $content);
            fclose($handle);
            @chmod($tmp, 0600);

            return $tmp;
        } finally {
            umask($umask);
        }
    }

    /**
     * Removes the temporary directory and everything an editor left in it
     * (swap files, backups such as "file~"), overwriting files first.
     */
    private static function shred(string $dir): void
    {
        if (!is_dir($dir) || is_link($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path) && !is_link($path)) {
                self::shred($path);
                continue;
            }
            if (is_file($path) && !is_link($path)) {
                @file_put_contents($path, str_repeat("\0", (int) filesize($path)));
            }
            @unlink($path);
        }
        @rmdir($dir);
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
