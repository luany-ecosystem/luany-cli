<?php

namespace LuanyCli;

use LuanyCli\Commands\AboutCommand;
use LuanyCli\Commands\CacheClearCommand;
use LuanyCli\Commands\KeyGenerateCommand;
use LuanyCli\Commands\ListCommand;
use LuanyCli\Commands\MakeControllerCommand;
use LuanyCli\Commands\MakeMiddlewareCommand;
use LuanyCli\Commands\MakeMigrationCommand;
use LuanyCli\Commands\MakeModelCommand;
use LuanyCli\Commands\MakeProviderCommand;
use LuanyCli\Commands\MakeViewCommand;
use LuanyCli\Commands\MigrateCommand;
use LuanyCli\Commands\MigrateFreshCommand;
use LuanyCli\Commands\MigrateRollbackCommand;
use LuanyCli\Commands\MigrateStatusCommand;
use LuanyCli\Commands\ServeCommand;

class Application
{
    public static function run(array $argv): void
    {
        $registry = new CommandRegistry();

        self::registerCommands($registry);

        $commandName = $argv[1] ?? null;
        $args        = array_slice($argv, 2);

        if (!$commandName || $commandName === '--help' || $commandName === '-h') {
            $registry->get('list')->handle([]);
            exit(0);
        }

        $command = $registry->get($commandName);

        if (!$command) {
            fwrite(STDERR, "\n  \033[31m✗\033[0m  Command [{$commandName}] not found.\n\n");
            $registry->get('list')->handle([]);
            exit(1);
        }

        $command->handle($args);
    }

    private static function registerCommands(CommandRegistry $registry): void
    {
        $registry->register(new ServeCommand());
        $registry->register(new MakeControllerCommand());
        $registry->register(new MakeModelCommand());
        $registry->register(new MakeMigrationCommand());
        $registry->register(new MakeMiddlewareCommand());
        $registry->register(new MakeProviderCommand());
        $registry->register(new MakeViewCommand());
        $registry->register(new MigrateCommand());
        $registry->register(new MigrateRollbackCommand());
        $registry->register(new MigrateStatusCommand());
        $registry->register(new MigrateFreshCommand());
        $registry->register(new KeyGenerateCommand());
        $registry->register(new CacheClearCommand());
        $registry->register(new AboutCommand());
        $registry->register(new ListCommand($registry));
    }
}