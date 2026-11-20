<?php

namespace LuanyCli\Support;

/**
 * Shared plumbing for the env:* commands.
 *
 * The cryptography is luany/framework's Support\EnvironmentCipher, loaded
 * from the project's vendor/ — the CLI never implements it a second time.
 */
final class EncryptedEnv
{
    public const CIPHER = 'Luany\\Framework\\Support\\EnvironmentCipher';

    /**
     * @param  array<int, string> $args
     * @return array{env: string, key: ?string, force: bool, rest: list<string>}
     */
    public static function options(array $args): array
    {
        $options = ['env' => '', 'key' => null, 'force' => false, 'rest' => []];
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--env=')) {
                $options['env'] = substr($arg, 6);
            } elseif (str_starts_with($arg, '--key=')) {
                $options['key'] = substr($arg, 6);
            } elseif ($arg === '--force') {
                $options['force'] = true;
            } elseif (str_starts_with($arg, '--')) {
                throw new \InvalidArgumentException("Unknown option [{$arg}].");
            } else {
                $options['rest'][] = $arg;
            }
        }

        return $options;
    }

    /** Load the project's autoloader and check the framework provides the cipher. */
    public static function boot(string $base): void
    {
        if (!class_exists(self::CIPHER)) {
            $autoload = $base . '/vendor/autoload.php';
            if (!is_file($autoload)) {
                throw new \RuntimeException('vendor/autoload.php not found. Run: composer install');
            }
            require_once $autoload;
        }
        if (!class_exists(self::CIPHER)) {
            throw new \RuntimeException('Encrypted environments need luany/framework 1.6 or later. Run: composer update luany/framework');
        }
    }

    /** The key from --key, else LUANY_ENV_KEY_{ENV} / LUANY_ENV_KEY. */
    public static function key(?string $option, string $environment): ?string
    {
        if ($option !== null && $option !== '') {
            return $option;
        }
        /** @var ?string $key */
        $key = (self::CIPHER)::keyFromEnvironment($environment);

        return $key;
    }

    public static function encryptedPath(string $base, string $environment): string
    {
        return $base . '/' . (self::CIPHER)::fileName($environment);
    }

    /** .env.{env} when an environment is given and that file exists, else .env */
    public static function plainPath(string $base, string $environment, bool $mustExist = true): string
    {
        $environment = (self::CIPHER)::normalize($environment);
        $specific = $base . '/.env' . ($environment === '' ? '' : '.' . $environment);
        if ($environment === '' || !$mustExist || is_file($specific)) {
            return $specific;
        }

        return $base . '/.env';
    }

    public static function missingKey(string $environment): string
    {
        return 'No key: pass --key=base64:… or set ' . (self::CIPHER)::keyVariable($environment) . ' (or LUANY_ENV_KEY).';
    }

    /**
     * Set KEY=VALUE pairs in .env content, replacing existing lines or appending.
     *
     * @param array<string, string> $pairs
     */
    public static function setValues(string $content, array $pairs): string
    {
        $lines = $content === '' ? [] : explode("\n", rtrim(str_replace("\r\n", "\n", $content), "\n"));
        foreach ($pairs as $name => $value) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
                throw new \InvalidArgumentException("Invalid variable name [{$name}].");
            }
            $line = $name . '=' . self::quote($value);
            $found = false;
            foreach ($lines as $i => $existing) {
                if (preg_match('/^\s*(?:export\s+)?' . preg_quote($name, '/') . '\s*=/', $existing) === 1) {
                    $lines[$i] = $line;
                    $found = true;
                }
            }
            if (!$found) {
                $lines[] = $line;
            }
        }

        return implode("\n", $lines) . "\n";
    }

    private static function quote(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_.,:\/@+-]+$/', $value) === 1) {
            return $value;
        }

        // Double quotes with \\, \" and \$ escaped: phpdotenv reads the value back
        // literally (no ${VAR} expansion), whatever characters it contains.
        return '"' . str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value) . '"';
    }
}
