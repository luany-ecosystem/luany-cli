<?php

namespace LuanyCli;

class Env
{
    private static ?string $basePath = null;

    public static function basePath(): string
    {
        return self::$basePath ?? (defined('BASE_DIR') ? BASE_DIR : getcwd());
    }

    public static function setBasePath(string $path): void
    {
        self::$basePath = $path;
    }

    public static function reset(): void
    {
        self::$basePath = null;
    }
}