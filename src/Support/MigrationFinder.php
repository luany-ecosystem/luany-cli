<?php

namespace LuanyCli\Support;

/**
 * MigrationFinder
 *
 * Migrations are named YYYY_MM_DD_HHMMSS_{name}.php and declare a class
 * derived from {name}. Two files with the same {name} declare the same class,
 * which makes the migration runner fatal with "cannot redeclare class".
 */
final class MigrationFinder
{
    /**
     * Return the file name of an existing migration called $name, or null.
     */
    public static function find(string $dir, string $name): ?string
    {
        $pattern = '/^\d{4}_\d{2}_\d{2}_\d{6}_' . preg_quote($name, '/') . '\.php$/';

        foreach (glob(rtrim($dir, '/') . '/*.php') ?: [] as $file) {
            $base = basename($file);
            if (preg_match($pattern, $base)) {
                return $base;
            }
        }

        return null;
    }
}
