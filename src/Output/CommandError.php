<?php

namespace LuanyCli\Output;

/**
 * A command failure with a stable message code. The Application renders it
 * (human text or the JSON envelope) and exits with $exitCode.
 */
final class CommandError extends \RuntimeException
{
    /**
     * @param array<string, string|int> $params
     */
    public function __construct(
        public readonly string $messageCode,
        public readonly array $params = [],
        public readonly ?string $hint = null,
        public readonly int $exitCode = 1,
    ) {
        parent::__construct($messageCode);
    }

    /**
     * Missing or wrong arguments: "Usage: luany make:controller <Name>".
     */
    public static function usage(string $usage, ?string $example = null): self
    {
        return new self('LUANY-CLI-005', ['usage' => $usage], $example !== null ? 'luany ' . $example : null);
    }
}
