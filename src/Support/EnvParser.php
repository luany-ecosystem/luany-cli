<?php

namespace LuanyCli\Support;

class EnvParser
{
    /**
     * Parse a .env file into an associative array.
     * Handles base64 values with '=', quoted values, and comments.
     *
     * @return array<string, string>
     */
    public static function parse(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [];
        }

        $result = [];
        $lines  = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            if (str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            $pos   = strpos($line, '=');
            $key   = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));

            if (
                strlen($value) >= 2 &&
                (
                    (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                    (str_starts_with($value, "'") && str_ends_with($value, "'"))
                )
            ) {
                $value = substr($value, 1, -1);
            }

            $result[$key] = $value;
        }

        return $result;
    }
}

