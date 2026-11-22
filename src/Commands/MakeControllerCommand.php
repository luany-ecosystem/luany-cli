<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
use LuanyCli\Output\CommandError;
use LuanyCli\Output\Output;
use LuanyCli\Output\StructuredCommand;

class MakeControllerCommand extends BaseCommand implements StructuredCommand
{
    public function name(): string
    {
        return 'make:controller';
    }

    public function description(): string
    {
        return 'Scaffold a new controller';
    }

    /** @param array<int, string> $args */
    public function handle(array $args): void
    {
        $name = $args[0] ?? null;

        if (!$name) {
            throw CommandError::usage('make:controller <ControllerName>', 'make:controller Auth/Login');
        }

        // Support subdirectory paths: Auth/Login → app/Controllers/Auth/LoginController.php
        ['subPath' => $subPath, 'className' => $className] = $this->normalizeClassPath($name, 'Controller');

        $namespace = 'App\\Controllers' . ($subPath ? '\\' . str_replace('/', '\\', $subPath) : '');
        $relative  = 'app/Controllers/' . ($subPath ? $subPath . '/' : '') . "{$className}.php";

        Output::current()->createFile($relative, $this->stub($className, $namespace), 'controller', $className);
    }

    private function stub(string $name, string $namespace): string
    {
        // Nested controllers (App\Controllers\Admin) must import the base class.
        $baseImport = $namespace === 'App\\Controllers' ? '' : "use App\\Controllers\\Controller;\n";

        return <<<PHP
<?php

namespace {$namespace};

{$baseImport}use Luany\Core\Http\Request;
use Luany\Core\Http\Response;

class {$name} extends Controller
{
    public function index(Request \$request): string
    {
        return view('pages.home');
    }
}
PHP;
    }

}



