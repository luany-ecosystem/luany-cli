<?php

namespace LuanyCli;

class WelcomeMessage
{
    public static function print(): void
    {
        $version = self::resolveVersion();

        echo "\n";
        echo "  \033[35m██╗     ██╗   ██╗ █████╗ ███╗   ██╗██╗   ██╗\033[0m\n";
        echo "  \033[35m██║     ██║   ██║██╔══██╗████╗  ██║╚██╗ ██╔╝\033[0m\n";
        echo "  \033[35m██║     ██║   ██║███████║██╔██╗ ██║ ╚████╔╝ \033[0m\n";
        echo "  \033[35m██║     ██║   ██║██╔══██║██║╚██╗██║  ╚██╔╝  \033[0m\n";
        echo "  \033[35m███████╗╚██████╔╝██║  ██║██║ ╚████║   ██║   \033[0m\n";
        echo "  \033[35m╚══════╝ ╚═════╝ ╚═╝  ╚═╝╚═╝  ╚═══╝   ╚═╝  \033[0m\n";
        echo "\n";
        echo "  \033[32mVersion\033[0m  {$version}\n";
        echo "  \033[32mDocs\033[0m     https://docs.luany.dev\n";
        echo "\n";
    }

    private static function resolveVersion(): string
    {
        $candidates = [
            __DIR__ . '/../../composer.json',             // local dev installation
            __DIR__ . '/../../../luany/cli/composer.json', // global Composer installation
        ];

        foreach ($candidates as $file) {
            if (file_exists($file)) {
                $data = json_decode(file_get_contents($file), true);
                if (!empty($data['version'])) {
                    return $data['version'];
                }
            }
        }

        // Fallback: read version from global composer.lock
        $lock = __DIR__ . '/../../../composer.lock';
        if (file_exists($lock)) {
            $data = json_decode(file_get_contents($lock), true);
            foreach ($data['packages'] ?? [] as $pkg) {
                if ($pkg['name'] === 'luany/cli') {
                    return $pkg['version'];
                }
            }
        }

        return 'unknown';
    }
}