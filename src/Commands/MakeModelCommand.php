<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
use LuanyCli\Env;

class MakeModelCommand extends BaseCommand
{
    public function name(): string
    {
        return 'make:model';
    }

    public function description(): string
    {
        return 'Scaffold a new model';
    }

    public function handle(array $args): void
    {
        $name = $args[0] ?? null;

        if (!$name) {
            fwrite(STDERR, "\n  \033[31m✗\033[0m  Usage: luany make:model <ModelName>\n\n");
            exit(1);
        }

        $name  = ucfirst($name);
        $table = $this->toTableName($name);
        $dir   = Env::basePath() . '/app/Models';
        $path  = "{$dir}/{$name}.php";

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($path)) {
            echo "\n  \033[33m⚠\033[0m  {$name} already exists.\n\n";
            exit(0);
        }

        file_put_contents($path, $this->stub($name, $table));
        echo "\n  \033[32m✓\033[0m  Model created: app/Models/{$name}.php\n\n";
    }

    private function stub(string $name, string $table): string
    {
        return <<<PHP
<?php

namespace App\Models;

use Luany\Database\Model;

class {$name} extends Model
{
    protected string \$table      = '{$table}';
    protected string \$primaryKey = 'id';

    protected array \$fillable = [
        // 'column_name',
    ];

    protected array \$hidden = [
        // 'password',
    ];
}
PHP;
    }

    private function toTableName(string $name): string
    {
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
        return $snake . 's';
    }
}
