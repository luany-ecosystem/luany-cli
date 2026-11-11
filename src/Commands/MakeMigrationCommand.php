<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
use LuanyCli\Env;
use LuanyCli\Support\InvalidNameException;
use LuanyCli\Support\MigrationFinder;
use LuanyCli\Support\NameValidator;

class MakeMigrationCommand extends BaseCommand
{
    private string $currentName = '';

    public function name(): string
    {
        return 'make:migration';
    }

    public function description(): string
    {
        return 'Generate a timestamped migration file';
    }

    /** @param array<int, string> $args */
    public function handle(array $args): void
    {
        $name = $args[0] ?? null;

        if (!$name) {
            fwrite(STDERR, "\n  \033[31m✗\033[0m  Usage: luany make:migration <migration_name>\n");
            fwrite(STDERR, "  Example: luany make:migration create_users_table\n\n");
            exit(1);
        }

        $name = NameValidator::migrationName($name);

        $timestamp = date('Y_m_d_His');
        $filename  = "{$timestamp}_{$name}.php";
        $dir       = Env::basePath() . '/database/migrations';
        $path      = "{$dir}/{$filename}";
        $class     = $this->toClassName($name);
        $table     = $this->tableFromName($name);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Two migrations with the same name declare the same class and make
        // the migration runner fatal with "cannot redeclare class".
        $existing = MigrationFinder::find($dir, $name);
        if ($existing !== null) {
            throw new InvalidNameException(
                "A migration named [{$name}] already exists: database/migrations/{$existing}"
            );
        }

        $this->currentName = $name;
        file_put_contents($path, $this->stub($class, $table));
        echo "\n  \033[32m✓\033[0m  Migration created: database/migrations/{$filename}\n\n";
    }

    /**
     * Anonymous-class migration using the portable schema builder:
     *   create_posts_table          → Schema::create('posts', …)
     *   add_x_to_posts_table        → Schema::table('posts', …)
     *   remove_x_from_posts_table   → Schema::table('posts', …)
     *   anything else               → empty up() / down()
     */
    private function stub(string $class, string $table): string
    {
        $name = $this->currentName;

        if (preg_match('/^create_(.+)_table$/', $name, $m) === 1) {
            return $this->render(
                "        \$this->schema(\$pdo)->create('{$m[1]}', function (Blueprint \$table) {\n"
                . "            \$table->id();\n"
                . "            \$table->timestamps();\n"
                . "        });",
                "        \$this->schema(\$pdo)->dropIfExists('{$m[1]}');"
            );
        }

        if (preg_match('/_(?:to|from|in|on)_(.+)_table$/', $name, $m) === 1) {
            return $this->render(
                "        \$this->schema(\$pdo)->table('{$m[1]}', function (Blueprint \$table) {\n"
                . "            //\n"
                . "        });",
                "        \$this->schema(\$pdo)->table('{$m[1]}', function (Blueprint \$table) {\n"
                . "            //\n"
                . "        });"
            );
        }

        return $this->render('        //', '        //');
    }

    private function render(string $up, string $down): string
    {
        return <<<PHP
<?php

use Luany\Database\Migration\Migration;
use Luany\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(\PDO \$pdo): void
    {
{$up}
    }

    public function down(\PDO \$pdo): void
    {
{$down}
    }
};

PHP;
    }

    private function toClassName(string $name): string
    {
        return str_replace('_', '', ucwords($name, '_'));
    }

    /**
     * Derive table name from migration name.
     * create_users_table      → users
     * create_funcionarios_table → funcionarios
     * Falls back to the full name if pattern does not match.
     */
    private function tableFromName(string $name): string
    {
        if (preg_match('/^create_(.+)_table$/', $name, $matches)) {
            return $matches[1];
        }

        return $name;
    }
}