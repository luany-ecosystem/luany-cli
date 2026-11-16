<?php

// Minimal stand-ins for luany/database (a dependency of the project, not of
// the CLI), loaded only when the real package is absent.

namespace Luany\Database;

class Connection
{
    private function __construct(private \PDO $pdo) {}

    public static function fromPdo(\PDO $pdo): static
    {
        return new static($pdo);
    }

    public function getPdo(): \PDO
    {
        return $this->pdo;
    }
}

abstract class Model
{
    public static ?Connection $bound = null;

    public static function setConnection(Connection|\Closure $connection): void
    {
        static::$bound = $connection instanceof \Closure ? $connection() : $connection;
    }
}
