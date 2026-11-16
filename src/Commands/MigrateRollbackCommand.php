<?php

namespace LuanyCli\Commands;

use Luany\Database\Migration\MigrationRunner;

class MigrateRollbackCommand extends MigrateBaseCommand
{
    private const USAGE = 'Usage: luany migrate:rollback [--step=N]';

    public function name(): string
    {
        return 'migrate:rollback';
    }

    public function description(): string
    {
        return 'Rollback the last migration batch (--step=N: the last N migrations)';
    }

    /** @param array<int, string> $args */
    public function handle(array $args): void
    {
        if (in_array('--help', $args, true) || in_array('-h', $args, true)) {
            echo "\n  " . self::USAGE . "\n\n"
                . "  Rolls back the last migration batch.\n\n"
                . "  Options:\n"
                . "    --step=N   Roll back the last N migrations instead (newest first, across batches)\n\n";
            return;
        }

        $step = self::parseStep($args);

        echo "\n";

        $output = function (string $name, string $status) {
            if ($status === 'nothing') {
                echo "  \033[33m→\033[0m  Nothing to rollback.\n";
                return;
            }
            echo "  \033[32m✓\033[0m  Rolled back: {$name}\n";
        };

        $runner = $this->runner();

        if ($step === null) {
            $count = $runner->rollback($output);
        } elseif (method_exists($runner, 'rollbackLast')) {
            $count = $runner->rollbackLast($step, $output);
        } else {
            fwrite(STDERR, "  \033[31m✗\033[0m  --step needs a newer luany/database (MigrationRunner::rollbackLast). Run: composer update luany/database\n\n");
            exit(1);
        }

        if ($count > 0) {
            echo "\n  \033[32m✓\033[0m  {$count} migration(s) rolled back.\n";
        }

        echo "\n";
    }

    /**
     * --step=N / --step N → N (number of migrations), null when absent.
     *
     * @param array<int, string> $args
     * @throws \InvalidArgumentException On an invalid value or unknown option
     */
    public static function parseStep(array $args): ?int
    {
        $step = null;

        for ($i = 0, $n = count($args); $i < $n; $i++) {
            $arg = $args[$i];

            if (str_starts_with($arg, '--step=')) {
                $value = substr($arg, 7);
            } elseif ($arg === '--step') {
                $value = $args[++$i] ?? '';
            } else {
                throw new \InvalidArgumentException("Unknown option [{$arg}]. " . self::USAGE);
            }

            if (preg_match('/^[1-9]\d*$/', $value) !== 1) {
                throw new \InvalidArgumentException('--step must be a positive integer. ' . self::USAGE);
            }

            $step = (int) $value;
        }

        return $step;
    }

    private function runner(): MigrationRunner
    {
        return new MigrationRunner($this->pdo(), $this->migrationPath());
    }
}
