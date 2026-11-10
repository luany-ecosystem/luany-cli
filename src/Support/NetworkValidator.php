<?php

namespace LuanyCli\Support;

/**
 * NetworkValidator
 *
 * Validates host names and TCP ports supplied on the command line before
 * they are handed to child processes (php -S, the LDE watcher…).
 */
final class NetworkValidator
{
    /**
     * Accepts a DNS host name (localhost, my-app.test), an IPv4 address,
     * or a bracketed IPv6 address ([::1]).
     *
     * @throws \InvalidArgumentException
     */
    public static function host(string $host): string
    {
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return $host;
        }

        if (preg_match('/^\[(.+)\]$/', $host, $m)
            && filter_var($m[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false
        ) {
            return $host;
        }

        if (strlen($host) <= 253
            && preg_match('/^(?=.*[A-Za-z])[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*$/', $host)
        ) {
            return $host;
        }

        throw new \InvalidArgumentException(
            "Invalid host [{$host}] — use a host name (localhost), an IPv4 address (127.0.0.1) or a bracketed IPv6 address ([::1])."
        );
    }

    /**
     * Accepts an integer 1–65535 given as int or decimal string.
     *
     * @throws \InvalidArgumentException
     */
    public static function port(int|string $port, string $label = 'port'): int
    {
        $value = is_int($port) ? (string) $port : trim($port);

        if (!preg_match('/^[0-9]{1,5}$/', $value) || (int) $value < 1 || (int) $value > 65535) {
            throw new \InvalidArgumentException(
                "Invalid {$label} [{$port}] — expected an integer between 1 and 65535."
            );
        }

        return (int) $value;
    }
}
