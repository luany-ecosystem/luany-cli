<?php

namespace LuanyCli\Support;

/**
 * Thrown when the project's environment cannot be loaded
 * (e.g. .env exists but is not readable).
 */
final class EnvException extends \RuntimeException
{
}
