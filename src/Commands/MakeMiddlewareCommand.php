<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
use LuanyCli\Env;

class MakeMiddlewareCommand extends BaseCommand
{
    public function name(): string
    {
        return 'make:middleware';
    }

    public function description(): string
    {
        return 'Scaffold a new middleware';
    }

    public function handle(array $args): void
    {
        $name = $args[0] ?? null;

        if (!$name) {
            fwrite(STDERR, "\n  \033[31m✗\033[0m  Usage: luany make:middleware <MiddlewareName>\n\n");
            exit(1);
        }

        // Support subdirectory paths: Auth/Login → app/Http/Middleware/Auth/LoginMiddleware.php
        $segments  = explode('/', str_replace('\\', '/', $name));
        $className = $this->normalise(array_pop($segments), 'Middleware');
        $subPath   = implode('/', $segments);

        $namespace = 'App\\Http\\Middleware' . ($subPath ? '\\' . str_replace('/', '\\', $subPath) : '');
        $dir       = Env::basePath() . '/app/Http/Middleware' . ($subPath ? '/' . $subPath : '');
        $path      = "{$dir}/{$className}.php";

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($path)) {
            echo "\n  \033[33m⚠\033[0m  {$className} already exists.\n\n";
            exit(0);
        }

        file_put_contents($path, $this->stub($className, $namespace));

        $relative = 'app/Http/Middleware/' . ($subPath ? $subPath . '/' : '') . "{$className}.php";
        echo "\n  \033[32m✓\033[0m  Middleware created: {$relative}\n\n";
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

    private function normalise(string $name, string $suffix): string
    {
        $name = ucfirst($name);
        return str_ends_with($name, $suffix) ? $name : $name . $suffix;
    }
}
