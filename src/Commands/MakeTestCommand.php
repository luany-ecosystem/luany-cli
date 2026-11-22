<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
use LuanyCli\Output\CommandError;
use LuanyCli\Output\Output;
use LuanyCli\Output\StructuredCommand;

class MakeTestCommand extends BaseCommand implements StructuredCommand
{
    public function name(): string
    {
        return 'make:test';
    }

    public function description(): string
    {
        return 'Scaffold a new PHPUnit test class';
    }

    /** @param array<int, string> $args */
    public function handle(array $args): void
    {
        $name = $args[0] ?? null;

        if (!$name) {
            throw CommandError::usage('make:test <TestName>', 'make:test UserControllerTest');
        }

        // Normalise — append 'Test' suffix if missing
        ['subPath' => $subPath, 'className' => $className] = $this->normalizeClassPath($name, 'Test');

        $namespace = 'Tests' . ($subPath ? '\\' . str_replace('/', '\\', $subPath) : '');
        $relative  = 'tests/' . ($subPath ? $subPath . '/' : '') . "{$className}.php";

        Output::current()->createFile($relative, $this->stub($className, $namespace), 'test', $className);
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
