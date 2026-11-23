<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
use LuanyCli\Env;
use LuanyCli\Support\Portuguese;
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
        // LUANY_SYNTAX=pt + luany/pt → ProdutoControlador with Portuguese helpers.
        $pt = Portuguese::mode(Env::basePath())['code'];
        ['subPath' => $subPath, 'className' => $className] = $this->normalizeClassPath($name, $pt ? 'Controlador' : 'Controller');

        $namespace = 'App\\Controllers' . ($subPath ? '\\' . str_replace('/', '\\', $subPath) : '');
        $relative  = 'app/Controllers/' . ($subPath ? $subPath . '/' : '') . "{$className}.php";

        $stub = $this->stub($className, $namespace);
        if ($pt) {
            $stub = strtr($stub, [
                'Request $request'           => 'Request $requisicao',
                "return view('pages.home');" => "return vista('pages.home');",
            ]);
        }

        Output::current()->createFile($relative, $stub, 'controller', $className);
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



