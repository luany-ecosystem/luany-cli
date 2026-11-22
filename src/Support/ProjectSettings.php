<?php

namespace LuanyCli\Support;

/**
 * Project settings the CLI needs without booting the app: read from the real
 * environment first, then from the project's .env.
 *
 *   ProjectSettings::get($base, 'LUANY_DEV_LANGUAGE', 'en');
 */
final class ProjectSettings
{
    public static function get(string $base, string $name, string $default): string
    {
        $value = getenv($name);
        if (is_string($value) && $value !== '') {
            return strtolower(trim($value));
        }

        $env = @file_get_contents($base . '/.env');
        if (is_string($env) && preg_match('/^\s*' . preg_quote($name, '/') . '\s*=\s*["\']?([A-Za-z_]+)/m', $env, $m) === 1) {
            return strtolower($m[1]);
        }

        return $default;
    }

    /** Language of the messages for developers: "en" or "pt" (LUANY_DEV_LANGUAGE). */
    public static function devLanguage(string $base): string
    {
        $language = self::get($base, 'LUANY_DEV_LANGUAGE', 'en');

        return str_starts_with($language, 'pt') ? 'pt' : 'en';
    }
}
