<?php

namespace LuanyCli\Commands;

use LuanyCli\CommandInterface;
use LuanyCli\Env;

class MakeControllerCommand implements CommandInterface
{
    public function name(): string
    {
        return 'make:controller';
    }

    public function description(): string
    {
        return 'Scaffold a new controller';
    }

    public function handle(array $args): void
    {
        $name = $args[0] ?? null;

        if (!$name) {
            fwrite(STDERR, "\n  \033[31m✗\033[0m  Usage: luany make:controller <ControllerName>\n\n");
            exit(1);
        }

        $name = $this->normalise($name, 'Controller');
        $dir  = Env::basePath() . '/app/Controllers';
        $path = "{$dir}/{$name}.php";

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($path)) {
            echo "\n  \033[33m⚠\033[0m  {$name} already exists.\n\n";
            exit(0);
        }

        file_put_contents($path, $this->stub($name));
        echo "\n  \033[32m✓\033[0m  Controller created: app/Controllers/{$name}.php\n\n";
    }

    private function stub(string $name): string
    {
        return <<<PHP
<?php

namespace App\Controllers;

use Luany\Core\Http\Request;

class {$name} extends Controller
{
    public function index(Request \$request): string
    {
        return view('pages.home');
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