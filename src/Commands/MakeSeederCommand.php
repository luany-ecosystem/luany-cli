<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
use LuanyCli\Env;

class MakeSeederCommand extends BaseCommand
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
            fwrite(STDERR, "\n  \033[31m✗\033[0m  Usage: luany make:seeder <Name>\n");
            fwrite(STDERR, "  Example: luany make:seeder UserSeeder\n\n");
            exit(1);
        }

        $name = $this->normalizeName($name, 'Seeder');
        $dir  = Env::basePath() . '/database/seeders';
        $path = "{$dir}/{$name}.php";

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($path)) {
            fwrite(STDERR, "\n  \033[31m✗\033[0m  Seeder already exists: database/seeders/{$name}.php\n\n");
            exit(1);
        }

        file_put_contents($path, $this->stub($name));
        echo "\n  \033[32m✓\033[0m  Seeder created: database/seeders/{$name}.php\n\n";
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
        // \$stmt = \$pdo->prepare("INSERT IGNORE INTO `table` (`column`) VALUES (?)");
        // \$stmt->execute(['value']);
    }
}
PHP;
    }
}