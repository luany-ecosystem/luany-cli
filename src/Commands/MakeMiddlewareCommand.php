<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
use LuanyCli\Output\CommandError;
use LuanyCli\Output\Output;
use LuanyCli\Output\StructuredCommand;

class MakeMiddlewareCommand extends BaseCommand implements StructuredCommand
{
    public function name(): string
    {
        return 'make:middleware';
    }

    public function description(): string
    {
        return 'Scaffold a new middleware';
    }

    /** @param array<int, string> $args */
    public function handle(array $args): void
    {
        $name = $args[0] ?? null;

        if (!$name) {
            throw CommandError::usage('make:middleware <MiddlewareName>', 'make:middleware Auth/EnsureAdmin');
        }

        // Support subdirectory paths: Auth/Login → app/Http/Middleware/Auth/LoginMiddleware.php
        ['subPath' => $subPath, 'className' => $className] = $this->normalizeClassPath($name, 'Middleware');

        $namespace = 'App\\Http\\Middleware' . ($subPath ? '\\' . str_replace('/', '\\', $subPath) : '');
        $relative  = 'app/Http/Middleware/' . ($subPath ? $subPath . '/' : '') . "{$className}.php";

        Output::current()->createFile($relative, $this->stub($className, $namespace), 'middleware', $className);
    }

    private function stub(string $name, string $namespace): string
    {
        return <<<PHP
<?php

namespace {$namespace};

use Luany\Core\Http\Request;
use Luany\Core\Http\Response;
use Luany\Core\Middleware\MiddlewareInterface;

class {$name} implements MiddlewareInterface
{
    public function handle(Request \$request, callable \$next): Response
    {
        // Before
        \$response = \$next(\$request);
        // After
        return \$response;
    }
}
PHP;
    }

}



