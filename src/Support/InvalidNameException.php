<?php

namespace LuanyCli\Support;

/**
 * Thrown when a user-supplied name (class, column, path segment, field type…)
 * cannot be safely used to generate code or file paths.
 */
class InvalidNameException extends \InvalidArgumentException
{
}
