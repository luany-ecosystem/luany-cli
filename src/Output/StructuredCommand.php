<?php

namespace LuanyCli\Output;

/**
 * Marker: the command reports through Output, so it supports --json
 * (the luany.cli/1 envelope) and --dry-run (plan and diff, nothing written).
 * It never calls exit(): failures are thrown as CommandError.
 */
interface StructuredCommand
{
}
