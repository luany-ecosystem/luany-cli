<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
use LuanyCli\Env;

class MakeControllerCommand extends BaseCommand
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

        // Support subdirectory paths: Auth/Login → app/Controllers/Auth/LoginController.php
        $segments  = explode('/', str_replace('\\', '/', $name));
        $className = $this->normalise(array_pop($segments), 'Controller');
        $subPath   = implode('/', $segments);

        $namespace = 'App\\Controllers' . ($subPath ? '\\' . str_replace('/', '\\', $subPath) : '');
        $dir       = Env::basePath() . '/app/Controllers' . ($subPath ? '/' . $subPath : '');
        $path      = "{$dir}/{$className}.php";

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($path)) {
            echo "\n  \033[33m⚠\033[0m  {$className} already exists.\n\n";
            exit(0);
        }

        file_put_contents($path, $this->stub($className, $namespace));

        $relative = 'app/Controllers/' . ($subPath ? $subPath . '/' : '') . "{$className}.php";
        echo "\n  \033[32m✓\033[0m  Controller created: {$relative}\n\n";
    }

    private function stub(string $name, string $namespace): string
    {
        return <<<PHP
<?php

namespace {$namespace};

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
