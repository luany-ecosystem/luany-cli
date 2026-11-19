<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
use LuanyCli\Support\UpdateChecker;
use LuanyCli\WelcomeMessage;

/**
 * self-update — update the globally installed luany/cli with Composer.
 *
 * A CLI installed inside a project (vendor/bin/luany) is updated with the
 * project's own Composer: the command prints that command instead.
 */
class SelfUpdateCommand extends BaseCommand
{
    public function name(): string
    {
        return 'self-update';
    }

    public function description(): string
    {
        return 'Update the global Luany CLI to the latest version';
    }

    public function requiresProject(): bool
    {
        return false;
    }

    /** @param array<int, string> $args */
    public function handle(array $args): void
    {
        $current = WelcomeMessage::resolveVersion();
        $latest  = UpdateChecker::latest(refresh: true);

        if ($latest !== null && $current !== 'unknown' && version_compare($latest, ltrim($current, 'v'), '<=')) {
            echo "\n  \033[32m✓\033[0m  luany/cli {$current} is the latest version.\n\n";
            return;
        }

        $local = self::projectInstall(dirname(__DIR__, 2));
        if ($local !== null) {
            echo "\n  \033[33m→\033[0m  This luany is installed in the project at {$local}.\n"
                . "     Update it with: \033[36mcomposer update luany/cli\033[0m\n\n";
            return;
        }

        echo "\n  \033[36m→\033[0m  Updating luany/cli " . ($latest !== null ? "to {$latest} " : '') . "with Composer…\n\n";
        passthru('composer global update luany/cli --with-all-dependencies', $code);

        if ($code !== 0) {
            echo "\n  \033[31m✗\033[0m  Composer failed. Run it yourself: \033[36mcomposer global update luany/cli\033[0m\n\n";
            exit(1);
        }
        echo "\n  \033[32m✓\033[0m  Done. Run \033[36mluany\033[0m to see the new version.\n\n";
    }

    /**
     * The project directory when this CLI lives in a project's vendor/
     * (vendor/luany/cli) rather than in Composer's global home; else null.
     */
    public static function projectInstall(string $cliRoot): ?string
    {
        $cliRoot = str_replace('\\', '/', $cliRoot);
        if (!preg_match('#^(.*)/vendor/luany/cli$#', $cliRoot, $m)) {
            return null;   // running from a git checkout
        }
        $owner = $m[1];
        $home  = str_replace('\\', '/', (string) (getenv('COMPOSER_HOME') ?: ''));
        if ($home === '') {
            $home = trim((string) @shell_exec('composer global config home 2>' . (DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null')));
            $home = str_replace('\\', '/', $home);
        }

        return $home !== '' && rtrim($owner, '/') === rtrim($home, '/') ? null : $owner;
    }
}
