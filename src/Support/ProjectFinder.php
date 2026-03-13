<?php

namespace LuanyCli\Support;

class ProjectFinder
{
    /**
     * Find the root of the nearest Luany project by walking up the
     * directory tree from the current working directory.
     *
     * A directory is considered a Luany project if its composer.json
     * declares luany/framework or luany/core as a dependency.
     * The vendor/ directory does not need to exist — this allows the CLI
     * to detect projects that have not yet run composer install.
     *
     * Falls back to the current working directory if no project is found.
     */
    public static function findRoot(): string
    {
        $dir = getcwd();

        while ($dir !== dirname($dir)) {
            if (self::isLuanyProject($dir)) {
                return realpath($dir);
            }
            $dir = dirname($dir);
        }

        return realpath(getcwd());
    }

    /**
     * Return true if the directory contains a composer.json that
     * declares luany/framework or luany/core as a dependency.
     */
    public static function isLuanyProject(string $dir): bool
    {
        $composerJson = $dir . '/composer.json';

        if (!file_exists($composerJson)) {
            return false;
        }

        $data    = json_decode(file_get_contents($composerJson), true);
        $require = $data['require'] ?? [];

        return isset($require['luany/framework']) || isset($require['luany/core']);
    }
}