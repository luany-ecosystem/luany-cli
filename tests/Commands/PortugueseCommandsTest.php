<?php

namespace LuanyCli\Tests\Commands;

use LuanyCli\Support\Portuguese;
use PHPUnit\Framework\TestCase;

/**
 * "Luany em Português" (L3b): command aliases, Portuguese generators.
 */
class PortugueseCommandsTest extends TestCase
{
    private string $project;

    protected function setUp(): void
    {
        $this->project = sys_get_temp_dir() . '/luany_cli_pt_' . bin2hex(random_bytes(4));
        mkdir($this->project . '/vendor/luany/framework', 0777, true);
        file_put_contents($this->project . '/composer.json', json_encode(['require' => ['luany/framework' => '^1.8']]));
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->project));
    }

    /** @return array{int, string} */
    private function luany(string $args): array
    {
        $cmd = 'cd ' . escapeshellarg($this->project) . ' && LUANY_NO_UPDATE_CHECK=1 ' . escapeshellarg(PHP_BINARY) . ' '
            . escapeshellarg(dirname(__DIR__, 2) . '/bin/luany') . ' ' . $args . ' 2>&1';
        exec($cmd, $out, $code);

        return [$code, (string) preg_replace('/\e\[[0-9;]*m/', '', implode("\n", $out))];
    }

    private function portugueseProject(bool $package = true): void
    {
        file_put_contents($this->project . '/.env', "LUANY_SYNTAX=pt\nLUANY_DEV_LANGUAGE=pt\n");
        if ($package) {
            mkdir($this->project . '/vendor/luany/pt', 0777, true);
        }
    }

    public function test_every_alias_points_to_a_command(): void
    {
        [, $out] = $this->luany('list');
        foreach (array_unique(Portuguese::COMMANDS) as $english) {
            $this->assertStringContainsString($english, $out, "alias target [{$english}] is not a command");
        }
    }

    public function test_aliases_run_the_english_command(): void
    {
        [$code, $out] = $this->luany('criar:modelo Post --json');
        $this->assertSame(0, $code, $out);
        $this->assertSame('make:model', json_decode($out, true)['command']);
        $this->assertFileExists($this->project . '/app/Models/Post.php');
    }

    public function test_list_in_portuguese_shows_both_names(): void
    {
        $this->portugueseProject(false);
        [, $out] = $this->luany('list');

        $this->assertMatchesRegularExpression('/criar:controlador\s+make:controller\s+Criar um controlador/', $out);
        $this->assertStringContainsString('Comandos (o nome inglês também funciona):', $out);
    }

    public function test_feature_in_portuguese(): void
    {
        $this->portugueseProject();
        [$code, $out] = $this->luany('criar:funcionalidade Autor nome:texto bio:texto_longo? ativo:booleano papel_id:estrangeira');
        $this->assertSame(0, $code, $out);

        $model = (string) file_get_contents($this->project . '/app/Models/Autor.php');
        $this->assertStringContainsString('class Autor extends Modelo', $model);
        $this->assertStringContainsString("protected string \$tabela = 'autores';", $model);
        $this->assertStringContainsString('protected array $conversoes', $model);
        $this->assertStringContainsString('$this->pertenceA(Papel::class', $model);

        $controller = (string) file_get_contents($this->project . '/app/Controllers/AutorControlador.php');
        $this->assertStringContainsString("'nome' => ['obrigatorio', 'texto', 'max:255']", $controller);
        $this->assertStringContainsString("'existe:papeis,id'", $controller);
        $this->assertStringContainsString('Autor::criar($dados)', $controller);

        $migration = (string) file_get_contents((string) glob($this->project . '/database/migrations/*_create_autores_table.php')[0]);
        $this->assertStringContainsString("\$t->textoLongo('bio')->anulavel();", $migration);
        $this->assertStringContainsString("\$t->chaveEstrangeira('papel_id')->restrita('papeis');", $migration);

        $this->assertStringContainsString("Rota::recurso('autores', AutorControlador::class);", (string) file_get_contents($this->project . '/routes/autores.php'));

        foreach (['app/Models/Autor.php', 'app/Controllers/AutorControlador.php', 'routes/autores.php'] as $file) {
            exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($this->project . '/' . $file), $lint, $lintCode);
            $this->assertSame(0, $lintCode, $file);
        }

        // The generated views compile with the Portuguese directives.
        $engine = (new \Luany\Lte\Engine($this->project . '/views', $this->project . '/cache'))->setSyntax('pt');
        foreach (glob($this->project . '/views/pages/autores/*.lte') ?: [] as $view) {
            $source = (string) file_get_contents($view);
            $this->assertStringContainsString('@secao(', $source);
            $engine->getCompiler()->buildTree($engine->getParser()->parse($source));
        }
    }

    public function test_views_only_without_the_package(): void
    {
        $this->portugueseProject(false);
        $this->luany('criar:funcionalidade Post title:string');

        $this->assertStringContainsString('extends Model', (string) file_get_contents($this->project . '/app/Models/Post.php'));
        $this->assertFileExists($this->project . '/app/Controllers/PostController.php');
        $this->assertStringContainsString('@listar(', (string) file_get_contents($this->project . '/views/pages/posts/index.lte'));
    }

    public function test_single_generators_in_portuguese(): void
    {
        $this->portugueseProject();
        $this->luany('criar:controlador Painel');
        $this->luany('criar:migracao create_cancoes_table');
        $this->luany('criar:vista pages.sobre');

        $this->assertStringContainsString("return vista('pages.home');", (string) file_get_contents($this->project . '/app/Controllers/PainelControlador.php'));
        $this->assertStringContainsString("Esquema::para(\$pdo)->criar('cancoes'", (string) file_get_contents((string) glob($this->project . '/database/migrations/*.php')[0]));
        $this->assertStringContainsString("@estende('layouts.main')", (string) file_get_contents($this->project . '/views/pages/sobre.lte'));
    }

    public function test_portuguese_plural(): void
    {
        foreach (['produto' => 'produtos', 'autor' => 'autores', 'papel' => 'papeis', 'item' => 'itens', 'cancao' => 'cancoes', 'pais' => 'paises', 'mes' => 'meses'] as $one => $many) {
            $this->assertSame($many, Portuguese::plural($one), $one);
        }
    }
}
