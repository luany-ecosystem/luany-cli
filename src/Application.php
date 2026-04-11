<?php

namespace LuanyCli;

use LuanyCli\Commands\AboutCommand;
use LuanyCli\Commands\CacheClearCommand;
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
use LuanyCli\Commands\RouteListCommand;
use LuanyCli\Commands\DbSeedCommand;
use LuanyCli\Commands\MakeSeederCommand;
use LuanyCli\Commands\ServeCommand;
use LuanyCli\Support\ProjectFinder;

class Application
{
    /** @param array<int, string> $argv */
    public static function run(array $argv): void
    {
        $registry = new CommandRegistry();

        self::registerCommands($registry);

        $commandName = $argv[1] ?? null;
        $args        = array_slice($argv, 2);

        if (!$commandName || $commandName === '--help' || $commandName === '-h') {
            WelcomeMessage::print();
            $registry->get('list')->handle([]);
            exit(0);
        }

        $command = $registry->get($commandName);

        if (!$command) {
            fwrite(STDERR, "\n  \033[31m✗\033[0m  Command [{$commandName}] not found.\n\n");
            $registry->get('list')->handle([]);
            exit(1);
        }

        if ($command->requiresProject()) {
            self::assertInsideLuanyProject();
        }

        $command->handle($args);
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
        $registry->register(new KeyGenerateCommand());
        $registry->register(new CacheClearCommand());
        $registry->register(new AboutCommand());
        $registry->register(new ListCommand($registry));
        $registry->register(new NewCommand());
        $registry->register(new DoctorCommand());
        $registry->register(new MakeFeatureCommand());
    }
}