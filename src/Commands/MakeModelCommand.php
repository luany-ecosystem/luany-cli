<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
use LuanyCli\Env;
use LuanyCli\Support\Portuguese;
use LuanyCli\Output\CommandError;
use LuanyCli\Output\Output;
use LuanyCli\Output\StructuredCommand;
use LuanyCli\Support\NameValidator;

class MakeModelCommand extends BaseCommand implements StructuredCommand
{
    public function name(): string
    {
        return 'make:model';
    }

    public function description(): string
    {
        return 'Scaffold a new model';
    }

    /** @param array<int, string> $args */
    public function handle(array $args): void
    {
        $name = $args[0] ?? null;

        if (!$name) {
            throw CommandError::usage('make:model <ModelName>', 'make:model Post');
        }

        $name  = NameValidator::className($name, 'model name');

        // LUANY_SYNTAX=pt + luany/pt → Modelo, $tabela, Portuguese plural.
        if (Portuguese::mode(Env::basePath())['code']) {
            $table = Portuguese::plural(strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $name)));
            Output::current()->createFile("app/Models/{$name}.php", $this->portugueseStub($name, $table), 'model', $name);
            return;
        }

        $table = $this->toTableName($name);
        Output::current()->createFile("app/Models/{$name}.php", $this->stub($name, $table), 'model', $name);
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

    // ── Relations ─────────────────────────────────────────────────────────────
    // public function posts(): array { return \$this->hasMany(Post::class); }
    // public function profile(): ?object { return \$this->hasOne(Profile::class); }
    // public function role(): ?object { return \$this->belongsTo(Role::class); }
}
PHP;
    }

    private function portugueseStub(string $name, string $table): string
    {
        return <<<PHP
<?php

namespace App\Models;

use Luany\Pt\Modelo;

class {$name} extends Modelo
{
    protected string \$tabela = '{$table}';

    protected array \$preenchiveis = [
        // 'coluna',
    ];

    protected array \$ocultos = [
        // 'senha',
    ];

    // ── Relações ──────────────────────────────────────────────────────────────
    // public function artigos(): HasMany { return \$this->temMuitos(Artigo::class); }
    // public function perfil(): HasOne { return \$this->temUm(Perfil::class); }
    // public function papel(): BelongsTo { return \$this->pertenceA(Papel::class); }
}
PHP;
    }

    private function toTableName(string $name): string
    {
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
        return $snake . 's';
    }
}


