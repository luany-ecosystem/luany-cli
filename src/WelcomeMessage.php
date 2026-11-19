<?php

namespace LuanyCli;

class WelcomeMessage
{
    public static function print(): void
    {
        $version = self::resolveVersion();

        echo "\n";
        echo "  \033[38;5;55m██╗     ██╗   ██╗ █████╗ ███╗   ██╗██╗   ██╗\033[0m\n";
        echo "  \033[38;5;55m██║     ██║   ██║██╔══██╗████╗  ██║╚██╗ ██╔╝\033[0m\n";
        echo "  \033[38;5;55m██║     ██║   ██║███████║██╔██╗ ██║ ╚████╔╝ \033[0m\n";
        echo "  \033[38;5;55m██║     ██║   ██║██╔══██║██║╚██╗██║  ╚██╔╝  \033[0m\n";
        echo "  \033[38;5;55m███████╗╚██████╔╝██║  ██║██║ ╚████║   ██║   \033[0m\n";
        echo "  \033[38;5;55m╚══════╝ ╚═════╝ ╚═╝  ╚═╝╚═╝  ╚═══╝   ╚═╝  \033[0m\n";
        echo "\n";
        echo "  \033[32mVersion\033[0m  {$version}\n";
        echo "  \033[32mDocs\033[0m     https://docs.luany.dev\n";
        echo self::updateNotice($version);
        echo "\n";
    }

    /** One-line notice when a newer luany/cli is on Packagist (checked once a day). */
    public static function updateNotice(string $current): string
    {
        $latest = \LuanyCli\Support\UpdateChecker::newerThan($current);

        return $latest === null ? '' : "\n  \033[33m↑\033[0m  luany/cli \033[1m{$latest}\033[0m is available (you have {$current}). Run \033[36mluany self-update\033[0m\n";
    }

    public static function resolveVersion(): string
    {
        if (class_exists(\Composer\InstalledVersions::class)) {
            $version = \Composer\InstalledVersions::getPrettyVersion('luany/cli');
            if ($version !== null) {
                // Strip dev suffixes like "+no-version-set"
                return preg_replace('/\+.+$/', '', $version);
            }
        }

        return 'unknown';
    }
}

