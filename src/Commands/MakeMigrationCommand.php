<?php

namespace LuanyCli\Commands;

use LuanyCli\CommandInterface;
use LuanyCli\Env;

class MakeMigrationCommand implements CommandInterface
{
    public function name(): string
    {
        return 'make:migration';
    }

    public function description(): string
    {
        return 'Generate a timestamped migration file';
    }

    public function handle(array $args): void
    {
        $name = $args[0] ?? null;

        if (!$name) {
            fwrite(STDERR, "\n  \033[31m✗\033[0m  Usage: luany make:migration <migration_name>\n");
            fwrite(STDERR, "  Example: luany make:migration create_users_table\n\n");
            exit(1);
        }

        $timestamp = date('Y_m_d_His');
        $filename  = "{$timestamp}_{$name}.php";
        $dir       = Env::basePath() . '/database/migrations';
        $path      = "{$dir}/{$filename}";
        $class     = $this->toClassName($name);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, $this->stub($class));
        echo "\n  \033[32m✓\033[0m  Migration created: database/migrations/{$filename}\n\n";
    }

    private function stub(string $class): string
    {
        return <<<PHP
<?php

use Luany\Database\Migration\Migration;

class {$class} extends Migration
{
    public function up(\PDO \$pdo): void
    {
        \$pdo->exec("
            CREATE TABLE IF NOT EXISTS `example` (
                `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(\PDO \$pdo): void
    {
        \$pdo->exec("DROP TABLE IF EXISTS `example`");
    }
}
PHP;
    }

    private function toClassName(string $name): string
    {
        return str_replace('_', '', ucwords($name, '_'));
    }
}
