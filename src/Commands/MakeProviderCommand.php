<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
use LuanyCli\Output\CommandError;
use LuanyCli\Output\Output;
use LuanyCli\Output\StructuredCommand;

class MakeProviderCommand extends BaseCommand implements StructuredCommand
{
    public function name(): string
    {
        return 'make:provider';
    }

    public function description(): string
    {
        return 'Scaffold a new service provider';
    }

    /** @param array<int, string> $args */
    public function handle(array $args): void
    {
        $name = $args[0] ?? null;

        if (!$name) {
            throw CommandError::usage('make:provider <ProviderName>', 'make:provider Payment');
        }

        $name = $this->normalizeName($name, 'ServiceProvider');
        Output::current()->createFile("app/Providers/{$name}.php", $this->stub($name), 'provider', $name);
    }

    private function stub(string $name): string
    {
        return <<<PHP
<?php

namespace App\Providers;

use Luany\Framework\Application;
use Luany\Framework\ServiceProvider;

class {$name} extends ServiceProvider
{
    public function register(Application \$app): void
    {
        //
    }

    public function boot(Application \$app): void
    {
        //
    }
}
PHP;
    }

}



