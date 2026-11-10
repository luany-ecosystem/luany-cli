<?php

namespace LuanyCli\Support;

/**
 * EnvRepository
 *
 * Resolves configuration the same way the application does
 * (luany/framework loads .env with Dotenv::createImmutable):
 *
 *   1. real environment variables ($_ENV, $_SERVER, getenv()) win
 *   2. values from the project's .env file fill the gaps
 *
 * So Docker, CI and PaaS deployments that inject DB_* variables make the CLI
 * talk to the same database as the app.
 *
 * An .env file that exists but cannot be read is an error — never a silent
 * fallback to defaults.
 */
final class EnvRepository
{
    /** @var array<string, string> */
    private array $values;

    /**
     * @param array<string, string> $fileValues
     */
    private function __construct(array $fileValues, private readonly bool $hasFile)
    {
        $this->values = $fileValues;
    }

    /**
     * @throws EnvException When .env exists but is not readable.
     */
    public static function load(string $basePath): self
    {
        $file = rtrim($basePath, '/\\') . '/.env';

        if (!file_exists($file)) {
            return new self([], false);
        }

        if (!is_file($file) || !is_readable($file)) {
            throw new EnvException(
                ".env exists but cannot be read: {$file}\n"
                . '     Check its permissions (e.g. chmod 640 .env and make sure your user can read it).'
            );
        }

        return new self(EnvParser::parse($file), true);
    }

    public function hasFile(): bool
    {
        return $this->hasFile;
    }

    /**
     * Value of $key: real environment first, then .env, then $default.
     */
    public function get(string $key, ?string $default = null): ?string
    {
        $real = self::fromProcess($key);

        if ($real !== null) {
            return $real;
        }

        return $this->values[$key] ?? $default;
    }

    /**
     * Whether $key is defined in the real environment or in .env.
     */
    public function has(string $key): bool
    {
        return self::fromProcess($key) !== null || array_key_exists($key, $this->values);
    }

    /**
     * Whether $key comes from the real environment (not from .env).
     */
    public function isFromEnvironment(string $key): bool
    {
        return self::fromProcess($key) !== null;
    }

    private static function fromProcess(string $key): ?string
    {
        foreach ([$_ENV[$key] ?? null, $_SERVER[$key] ?? null] as $value) {
            if (is_scalar($value)) {
                return (string) $value;
            }
        }

        $value = getenv($key);

        return $value === false ? null : $value;
    }
}
