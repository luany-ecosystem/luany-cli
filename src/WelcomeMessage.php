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
        echo "\n";
    }

    private static function resolveVersion(): string
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

