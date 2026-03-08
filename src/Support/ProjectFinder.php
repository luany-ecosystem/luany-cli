<?php

namespace LuanyCli\Support;

class ProjectFinder
{
    public static function findRoot(): string
    {
        $dir = getcwd();

        while ($dir !== dirname($dir)) {
            if (
                file_exists($dir . '/composer.json') &&
                is_dir($dir . '/vendor')
            ) {
                return realpath($dir);
            }
            $dir = dirname($dir);
        }

        return realpath(getcwd());
    }
}