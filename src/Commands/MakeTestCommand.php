<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
use LuanyCli\Env;

class MakeTestCommand extends BaseCommand
{
    public function name(): string
    {
        return 'make:test';
    }

    public function description(): string
    {
        return 'Scaffold a new PHPUnit test class';
    }

    public function handle(array $args): void
    {
        $name = $args[0] ?? null;

        if (!$name) {
            fwrite(STDERR, "\n  \033[31m✗\033[0m  Usage: luany make:test <TestName>\n");
            fwrite(STDERR, "  Example: luany make:test UserControllerTest\n\n");
            exit(1);
        }

        // Normalise — append 'Test' suffix if missing
        $segments  = explode('/', str_replace('\\', '/', $name));
        $className = $this->normalizeName(array_pop($segments), 'Test');
        $subPath   = implode('/', $segments);

        $namespace = 'Tests' . ($subPath ? '\\' . str_replace('/', '\\', $subPath) : '');
        $dir       = Env::basePath() . '/tests' . ($subPath ? '/' . $subPath : '');
        $path      = "{$dir}/{$className}.php";

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($path)) {
            echo "\n  \033[33m⚠\033[0m  {$className} already exists.\n\n";
            return;
        }

        file_put_contents($path, $this->stub($className, $namespace));

        $relative = 'tests/' . ($subPath ? $subPath . '/' : '') . "{$className}.php";
        echo "\n  \033[32m✓\033[0m  Test created: {$relative}\n\n";
    }

    private function stub(string $name, string $namespace): string
    {
        return <<<PHP
<?php

namespace {$namespace};

use PHPUnit\Framework\TestCase;

class {$name} extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Set up test fixtures here
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Clean up after tests here
    }

    public function test_example(): void
    {
        \$this->assertTrue(true);
    }
}
PHP;
    }
}
