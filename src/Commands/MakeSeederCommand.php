<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
use LuanyCli\Output\CommandError;
use LuanyCli\Output\Output;
use LuanyCli\Output\StructuredCommand;

class MakeSeederCommand extends BaseCommand implements StructuredCommand
{
    public function name(): string
    {
        return 'make:seeder';
    }

    public function description(): string
    {
        return 'Scaffold a new seeder class';
    }

    /** @param array<int, string> $args */
    public function handle(array $args): void
    {
        $name = $args[0] ?? null;

        if (!$name) {
            throw CommandError::usage('make:seeder <Name>', 'make:seeder UserSeeder');
        }

        $name = $this->normalizeName($name, 'Seeder');
        Output::current()->createFile("database/seeders/{$name}.php", $this->stub($name), 'seeder', $name, existsIsError: true);
    }

    private function stub(string $class): string
    {
        return <<<PHP
<?php

use Luany\Database\Seeder\Seeder;

class {$class} extends Seeder
{
    public function run(\\PDO \$pdo): void
    {
        // Portable across SQLite, MySQL and PostgreSQL. With a model:
        //   \App\Models\User::create(['name' => 'Ada', 'email' => 'ada@example.com']);
        //
        // Or with plain SQL:
        //   \$pdo->prepare('INSERT INTO users (name, email) VALUES (?, ?)')
        //       ->execute(['Ada', 'ada@example.com']);
    }
}
PHP;
    }
}