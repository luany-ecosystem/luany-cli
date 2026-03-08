<?php

namespace LuanyCli\Commands;
use LuanyCli\Env;

use LuanyCli\CommandInterface;

class MakeMiddlewareCommand implements CommandInterface
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

        $name = $this->normalise($name, 'Middleware');
        $dir  = Env::basePath() . '/app/Http/Middleware';
        $path = "{$dir}/{$name}.php";

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($path)) {
            echo "\n  \033[33m⚠\033[0m  {$name} already exists.\n\n";
            exit(0);
        }

        file_put_contents($path, $this->stub($name));
        echo "\n  \033[32m✓\033[0m  Middleware created: app/Http/Middleware/{$name}.php\n\n";
    }

    private function stub(string $name): string
    {
        return <<<PHP
<?php

namespace App\Http\Middleware;

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