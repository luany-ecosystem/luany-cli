<?php

namespace LuanyCli\Commands;
use LuanyCli\Env;

use LuanyCli\CommandInterface;

class MakeProviderCommand implements CommandInterface
{
    public function name(): string
    {
        return 'make:provider';
    }

    public function description(): string
    {
        return 'Scaffold a new service provider';
    }

    public function handle(array $args): void
    {
        $name = $args[0] ?? null;

        if (!$name) {
            fwrite(STDERR, "\n  \033[31m✗\033[0m  Usage: luany make:provider <ProviderName>\n\n");
            exit(1);
        }

        $name = $this->normalise($name, 'ServiceProvider');
        $dir  = Env::basePath() . '/app/Providers';
        $path = "{$dir}/{$name}.php";

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($path)) {
            echo "\n  \033[33m⚠\033[0m  {$name} already exists.\n\n";
            exit(0);
        }

        file_put_contents($path, $this->stub($name));
        echo "\n  \033[32m✓\033[0m  Provider created: app/Providers/{$name}.php\n\n";
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

    private function normalise(string $name, string $suffix): string
    {
        $name = ucfirst($name);
        return str_ends_with($name, $suffix) ? $name : $name . $suffix;
    }
}