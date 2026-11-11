<?php

namespace LuanyCli\Support;

/**
 * Sets keys in a .env file: replaces an existing "KEY=" line, uncomments a
 * "# KEY=" line, or appends. Values are quoted when needed.
 */
final class EnvWriter
{
    /**
     * @param array<string, string> $values
     */
    public static function set(string $file, array $values): void
    {
        $content = is_file($file) ? (string) file_get_contents($file) : '';

        foreach ($values as $key => $value) {
            if (preg_match('/^[A-Z][A-Z0-9_]*$/', $key) !== 1) {
                throw new \InvalidArgumentException("Invalid .env key [{$key}].");
            }

            $line    = $key . '=' . self::quote($value);
            $pattern = '/^[ \t]*(?:#[ \t]*)?' . preg_quote($key, '/') . '=.*$/m';

            if (preg_match('/^[ \t]*' . preg_quote($key, '/') . '=.*$/m', $content) === 1) {
                $content = (string) preg_replace('/^[ \t]*' . preg_quote($key, '/') . '=.*$/m', self::escapeReplacement($line), $content, 1);
            } elseif (preg_match($pattern, $content) === 1) {
                $content = (string) preg_replace($pattern, self::escapeReplacement($line), $content, 1);
            } else {
                $content = rtrim($content, "\n") . "\n" . $line . "\n";
            }
        }

        file_put_contents($file, $content);
    }

    private static function quote(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_.\/:@-]+$/', $value) === 1) {
            return $value;
        }

        return '"' . str_replace(['\\', '"', "\n"], ['\\\\', '\\"', '\\n'], $value) . '"';
    }

    private static function escapeReplacement(string $value): string
    {
        return str_replace(['\\', '$'], ['\\\\', '\\$'], $value);
    }
}
