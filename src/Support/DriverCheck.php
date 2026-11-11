<?php

namespace LuanyCli\Support;

/**
 * Checks that the PHP PDO extension for a database driver is installed and
 * explains how to install it — instead of PDO's "could not find driver".
 */
final class DriverCheck
{
    private const EXTENSIONS = [
        'sqlite' => 'pdo_sqlite',
        'mysql'  => 'pdo_mysql',
        'pgsql'  => 'pdo_pgsql',
    ];

    private const PACKAGES = [
        'sqlite' => ['debian' => 'sqlite3', 'fedora' => 'pdo'],
        'mysql'  => ['debian' => 'mysql',   'fedora' => 'mysqlnd'],
        'pgsql'  => ['debian' => 'pgsql',   'fedora' => 'pgsql'],
    ];

    /**
     * @param (callable(string): bool)|null $loaded Extension probe (tests)
     */
    public static function isAvailable(string $driver, ?callable $loaded = null): bool
    {
        $extension = self::EXTENSIONS[$driver] ?? null;
        if ($extension === null) {
            return true; // unknown drivers are reported elsewhere
        }

        return ($loaded ?? 'extension_loaded')($extension);
    }

    /**
     * Human explanation for a missing driver, or null when it is installed.
     *
     * @param (callable(string): bool)|null $loaded
     */
    public static function problem(string $driver, ?callable $loaded = null): ?string
    {
        if (self::isAvailable($driver, $loaded)) {
            return null;
        }

        $extension = self::EXTENSIONS[$driver];
        $version   = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        $packages  = self::PACKAGES[$driver];

        return "PHP's {$extension} extension is not installed (needed for DB_CONNECTION={$driver}).\n"
            . "     Ubuntu / Debian:  sudo apt install php{$version}-{$packages['debian']}\n"
            . "     Fedora / RHEL:    sudo dnf install php-{$packages['fedora']}\n"
            . "     Windows:          enable extension={$extension} in php.ini\n"
            . "     macOS (Homebrew): included in the php formula";
    }
}
