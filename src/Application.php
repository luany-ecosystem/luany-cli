<?php

namespace LuanyCli;

use LuanyCli\Commands\AboutCommand;
use LuanyCli\Commands\CacheClearCommand;
use LuanyCli\Commands\ViewCheckCommand;
use LuanyCli\Commands\DevCommand;
use LuanyCli\Commands\DoctorCommand;
use LuanyCli\Commands\KeyGenerateCommand;
use LuanyCli\Commands\ListCommand;
use LuanyCli\Commands\MakeControllerCommand;
use LuanyCli\Commands\MakeFeatureCommand;
use LuanyCli\Commands\MakeMiddlewareCommand;
use LuanyCli\Commands\MakeMigrationCommand;
use LuanyCli\Commands\MakeModelCommand;
use LuanyCli\Commands\MakeProviderCommand;
use LuanyCli\Commands\MakeRequestCommand;
use LuanyCli\Commands\MakeTestCommand;
use LuanyCli\Commands\MakeViewCommand;
use LuanyCli\Commands\MigrateCommand;
use LuanyCli\Commands\MigrateFreshCommand;
use LuanyCli\Commands\MigrateRollbackCommand;
use LuanyCli\Commands\MigrateStatusCommand;
use LuanyCli\Commands\NewCommand;
use LuanyCli\Commands\RouteCacheCommand;
use LuanyCli\Commands\RouteClearCommand;
use LuanyCli\Commands\Env\EnvDecryptCommand;
use LuanyCli\Commands\Env\EnvEditCommand;
use LuanyCli\Commands\Env\EnvEncryptCommand;
use LuanyCli\Commands\Env\EnvSetCommand;
use LuanyCli\Commands\RouteListCommand;
use LuanyCli\Commands\SelfUpdateCommand;
use LuanyCli\Commands\DbSeedCommand;
use LuanyCli\Commands\MakeSeederCommand;
use LuanyCli\Commands\ServeCommand;
use LuanyCli\Output\CommandError;
use LuanyCli\Output\Output;
use LuanyCli\Output\StructuredCommand;
use LuanyCli\Support\ProjectFinder;
use LuanyCli\Support\ProjectSettings;

class Application
{
    /** @param array<int, string> $argv */
    public static function run(array $argv): void
    {
        $registry = new CommandRegistry();

        self::registerCommands($registry);

        $commandName = $argv[1] ?? null;
        $args        = array_slice($argv, 2);

        // Global flags: --json (luany.cli/1 envelope) and --dry-run (plan only).
        $json   = in_array('--json', $args, true);
        $dryRun = in_array('--dry-run', $args, true);
        $args   = array_values(array_filter($args, fn (string $a): bool => $a !== '--json' && $a !== '--dry-run'));

        if (!$commandName || $commandName === '--help' || $commandName === '-h') {
            WelcomeMessage::print();
            $registry->get('list')->handle([]);
            exit(0);
        }

        $command = $registry->get($commandName);
        $output  = Output::begin(new Output(
            command:  $command?->name() ?? $commandName,
            json:     $json,
            dryRun:   $dryRun,
            language: ProjectSettings::devLanguage(BASE_DIR), // @phpstan-ignore constant.notFound
        ));

        if (!$command) {
            self::finish($output, new CommandError('LUANY-CLI-004', ['command' => $commandName]), function () use ($registry): void {
                $registry->get('list')->handle([]);
            });
        }

        // A command that does not report through Output yet must never run
        // for real when a dry run (or machine-readable output) was asked for.
        if (($json || $dryRun) && !$command instanceof StructuredCommand) {
            self::finish($output, new CommandError($json ? 'LUANY-CLI-002' : 'LUANY-CLI-003', ['command' => $command->name()]));
        }

        if ($command->requiresProject()) {
            self::assertInsideLuanyProject();
        }

        if ($json) {
            ob_start(); // stray echo must not break the envelope
        }

        try {
            $command->handle($args);
            self::finish($output, null);
        } catch (CommandError $e) {
            self::finish($output, $e);
        } catch (\InvalidArgumentException $e) {
            // InvalidNameException and other user-input validation errors.
            self::finish($output, new CommandError('LUANY-CLI-001', ['message' => $e->getMessage()]));
        }
    }

    /**
     * Print the result (the error line, or the JSON envelope) and exit.
     */
    private static function finish(Output $output, ?CommandError $error, ?\Closure $afterError = null): never
    {
        if ($output->json) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            echo json_encode($output->envelope(self::version(), $error), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
            exit($error === null ? 0 : $error->exitCode);
        }

        if ($error !== null) {
            fwrite(STDERR, "\n  \033[31m✗\033[0m  " . $output->text->text($error->messageCode, $error->params) . "\n");
            if ($error->hint !== null) {
                fwrite(STDERR, "     \033[2m{$error->hint}\033[0m\n");
            }
            fwrite(STDERR, "\n");
            if ($afterError !== null) {
                $afterError();
            }
            exit($error->exitCode);
        }

        exit(0);
    }

    public static function version(): string
    {
        if (class_exists(\Composer\InstalledVersions::class) && \Composer\InstalledVersions::isInstalled('luany/cli')) {
            return (string) \Composer\InstalledVersions::getPrettyVersion('luany/cli');
        }

        return 'dev';
    }

    /**
     * Abort with a clear, educative message if the command requires
     * a Luany project but the current directory is not one.
     */
    private static function assertInsideLuanyProject(): void
    {
        $base = BASE_DIR; // @phpstan-ignore constant.notFound

        if (!ProjectFinder::isLuanyProject($base)) {
            fwrite(STDERR, "\n  \033[31m✗\033[0m  This command must be run inside a Luany project.\n\n");
            exit(1);
        }

        if (!is_dir($base . '/vendor/luany/framework')) {
            fwrite(STDERR, "\n  \033[33m⚠\033[0m  Luany project detected, but dependencies are not installed.\n");
            fwrite(STDERR, "     Run: \033[36mcomposer install\033[0m\n\n");
            exit(1);
        }
    }

    private static function registerCommands(CommandRegistry $registry): void
    {
        $registry->register(new ServeCommand());
        $registry->register(new DevCommand());
        $registry->register(new MakeControllerCommand());
        $registry->register(new MakeModelCommand());
        $registry->register(new MakeMigrationCommand());
        $registry->register(new MakeSeederCommand());
        $registry->register(new MakeMiddlewareCommand());
        $registry->register(new MakeProviderCommand());
        $registry->register(new MakeViewCommand());
        $registry->register(new MakeRequestCommand());
        $registry->register(new MakeTestCommand());
        $registry->register(new MigrateCommand());
        $registry->register(new MigrateRollbackCommand());
        $registry->register(new MigrateStatusCommand());
        $registry->register(new MigrateFreshCommand());
        $registry->register(new DbSeedCommand());
        $registry->register(new RouteListCommand());
        $registry->register(new RouteCacheCommand());
        $registry->register(new RouteClearCommand());
        $registry->register(new KeyGenerateCommand());
        $registry->register(new EnvEncryptCommand());
        $registry->register(new EnvDecryptCommand());
        $registry->register(new EnvEditCommand());
        $registry->register(new EnvSetCommand());
        $registry->register(new CacheClearCommand());
        $registry->register(new ViewCheckCommand());
        $registry->register(new AboutCommand());
        $registry->register(new ListCommand($registry));
        $registry->register(new NewCommand());
        $registry->register(new DoctorCommand());
        $registry->register(new SelfUpdateCommand());
        $registry->register(new MakeFeatureCommand());

        // "Luany em Português": luany criar:controlador, luany migrar, … (always on).
        foreach (\LuanyCli\Support\Portuguese::COMMANDS as $alias => $name) {
            $registry->alias($alias, $name);
        }
    }
}