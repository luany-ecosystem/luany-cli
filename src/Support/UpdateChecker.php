<?php

namespace LuanyCli\Support;

/**
 * Tells the user when a newer luany/cli is on Packagist.
 *
 * Checks at most once a day (cached in ~/.luany/update-check.json), with a
 * short timeout, and never fails a command: any network or parsing problem
 * simply means "no notice". Disabled when LUANY_NO_UPDATE_CHECK is set or
 * when running in CI.
 */
final class UpdateChecker
{
    public const PACKAGE  = 'luany/cli';
    public const ENDPOINT = 'https://repo.packagist.org/p2/luany/cli.json';
    private const TTL     = 86400;

    /** @var (callable(string): ?string)|null */
    private static $fetcher = null;
    private static ?string $cacheFile = null;

    /** Newer stable version than $current, or null. */
    public static function newerThan(string $current): ?string
    {
        if (self::disabled() || !preg_match('/^v?\d+\.\d+\.\d+$/', $current)) {
            return null;
        }
        $latest = self::latest();

        return $latest !== null && version_compare(ltrim($latest, 'v'), ltrim($current, 'v'), '>') ? $latest : null;
    }

    /** Latest stable version (cached for a day); null when unknown. */
    public static function latest(bool $refresh = false): ?string
    {
        $file   = self::cacheFile();
        $cached = is_file($file) ? json_decode((string) @file_get_contents($file), true) : null;

        if (!$refresh && is_array($cached) && (time() - (int) ($cached['checked_at'] ?? 0)) < self::TTL) {
            return is_string($cached['latest'] ?? null) ? $cached['latest'] : null;
        }

        $latest = self::fetch() ?? (is_array($cached) && is_string($cached['latest'] ?? null) ? $cached['latest'] : null);

        if (!is_dir(dirname($file))) {
            @mkdir(dirname($file), 0755, true);
        }
        @file_put_contents($file, json_encode(['checked_at' => time(), 'latest' => $latest]));

        return $latest;
    }

    /** Latest stable version from Packagist metadata JSON. */
    public static function parse(string $json): ?string
    {
        $data = json_decode($json, true);
        $versions = $data['packages'][self::PACKAGE] ?? null;
        if (!is_array($versions)) {
            return null;
        }
        $best = null;
        foreach ($versions as $entry) {
            $v = is_array($entry) ? (string) ($entry['version'] ?? '') : '';
            if (preg_match('/^v?\d+\.\d+\.\d+$/', $v) && ($best === null || version_compare(ltrim($v, 'v'), ltrim($best, 'v'), '>'))) {
                $best = $v;
            }
        }

        return $best === null ? null : ltrim($best, 'v');
    }

    public static function disabled(): bool
    {
        return (string) getenv('LUANY_NO_UPDATE_CHECK') !== '' || (string) getenv('CI') !== '';
    }

    // ── Testing seams ──────────────────────────────────────────────────

    /** @param (callable(string): ?string)|null $fetcher */
    public static function useFetcher(?callable $fetcher): void
    {
        self::$fetcher = $fetcher;
    }

    public static function useCacheFile(?string $file): void
    {
        self::$cacheFile = $file;
    }

    private static function fetch(): ?string
    {
        if (self::$fetcher !== null) {
            $json = (self::$fetcher)(self::ENDPOINT);
        } else {
            $context = stream_context_create(['http' => [
                'timeout' => 2, 'ignore_errors' => true,
                'header'  => "User-Agent: luany-cli\r\nAccept: application/json\r\n",
            ]]);
            $json = @file_get_contents(self::ENDPOINT, false, $context);
        }

        return is_string($json) && $json !== '' ? self::parse($json) : null;
    }

    private static function cacheFile(): string
    {
        if (self::$cacheFile !== null) {
            return self::$cacheFile;
        }
        $home = (string) (getenv('HOME') ?: getenv('USERPROFILE') ?: sys_get_temp_dir());

        return rtrim($home, '/\\') . '/.luany/update-check.json';
    }
}
