<?php

namespace LuanyCli\Support;

/**
 * "Luany em Português" names for the CLI (layer L3b).
 *
 * Command aliases are always on (`luany criar:controlador`); field types
 * accept Portuguese names (`nome:texto preco:decimal`).
 */
final class Portuguese
{
    /** Portuguese command => English command. */
    public const COMMANDS = [
        'novo'                 => 'new',
        'servir'               => 'serve',
        'criar:controlador'    => 'make:controller',
        'criar:modelo'         => 'make:model',
        'criar:migracao'       => 'make:migration',
        'criar:seeder'         => 'make:seeder',
        'criar:middleware'     => 'make:middleware',
        'criar:provedor'       => 'make:provider',
        'criar:vista'          => 'make:view',
        'criar:requisicao'     => 'make:request',
        'criar:teste'          => 'make:test',
        'criar:funcionalidade' => 'make:feature',
        'migrar'               => 'migrate',
        'migrar:reverter'      => 'migrate:rollback',
        'migrar:estado'        => 'migrate:status',
        'migrar:recriar'       => 'migrate:fresh',
        'bd:semear'            => 'db:seed',
        'rota:listar'          => 'route:list',
        'rota:cache'           => 'route:cache',
        'rota:limpar'          => 'route:clear',
        'vista:verificar'      => 'view:check',
        'chave:gerar'          => 'key:generate',
        'cache:limpar'         => 'cache:clear',
        'env:cifrar'           => 'env:encrypt',
        'env:decifrar'         => 'env:decrypt',
        'env:editar'           => 'env:edit',
        'env:definir'          => 'env:set',
        'diagnostico'          => 'doctor',
        'sobre'                => 'about',
        'atualizar'            => 'self-update',
    ];

    /** Portuguese field type => English field type (make:feature). */
    public const FIELD_TYPES = [
        'texto'        => 'string',
        'texto_longo'  => 'text',
        'textolongo'   => 'text',
        'inteiro'      => 'integer',
        'booleano'     => 'boolean',
        'data'         => 'date',
        'data_hora'    => 'datetime',
        'datahora'     => 'datetime',
        'estrangeira'  => 'foreign',
        'chave'        => 'foreign',
    ];

    /** The Portuguese alias of an English command, or null. */
    public static function commandAlias(string $english): ?string
    {
        $alias = array_search($english, self::COMMANDS, true);

        return $alias === false ? null : $alias;
    }

    public static function fieldType(string $type): string
    {
        return self::FIELD_TYPES[strtolower($type)] ?? $type;
    }

    /**
     * Portuguese plural of a lowercase word: produto → produtos, autor →
     * autores, papel → papeis, item → itens, cancao → cancoes. Same rule as
     * luany/pt's ChaveEstrangeira::tabelaPt().
     */
    public static function plural(string $word): string
    {
        return match (true) {
            (bool) preg_match('/(r|z|s)$/', $word) => $word . 'es',
            (bool) preg_match('/(a|e|i|o|u)l$/', $word) => substr($word, 0, -1) . 'is',
            (bool) preg_match('/m$/', $word) => substr($word, 0, -1) . 'ns',
            (bool) preg_match('/ão$/u', $word) => substr($word, 0, -strlen('ão')) . 'ões',
            str_ends_with($word, 'ao') => substr($word, 0, -2) . 'oes',
            default => $word . 's',
        };
    }

    // ── Generators (syntax = pt) ────────────────────────────────────────────

    /**
     * What the generators write in Portuguese for this project:
     *   views → LUANY_SYNTAX=pt (diretivas @se, @paracada…)
     *   code  → also luany/pt installed (Modelo, Esquema, Rota, validar()…)
     *
     * @return array{views: bool, code: bool}
     */
    public static function mode(string $base): array
    {
        $views = ProjectSettings::get($base, 'LUANY_SYNTAX', 'en') === 'pt';

        return ['views' => $views, 'code' => $views && self::hasPackage($base)];
    }

    public static function hasPackage(string $base): bool
    {
        if (is_dir($base . '/vendor/luany/pt')) {
            return true;
        }
        $composer = json_decode((string) @file_get_contents($base . '/composer.json'), true);

        return is_array($composer) && isset($composer['require']['luany/pt']);
    }

    /** $table->string('nome')->nullable() → $t->texto('nome')->anulavel() */
    public static function schemaColumns(string $code): string
    {
        $code = str_replace('$table->', '$t->', $code);
        $code = (string) preg_replace_callback('/\$t->(\w+)\(/', fn (array $m): string => '$t->' . ([
            'string' => 'texto', 'text' => 'textoLongo', 'integer' => 'inteiro', 'boolean' => 'booleano',
            'date' => 'data', 'dateTime' => 'dataHora', 'foreignId' => 'chaveEstrangeira',
        ][$m[1]] ?? $m[1]) . '(', $code);

        return strtr($code, [
            '->nullable()'  => '->anulavel()',
            '->default('    => '->padrao(',
            '->unique()'    => '->unico()',
            '->constrained(' => '->restrita(',
        ]);
    }

    /** 'required', 'string', 'max:255' → 'obrigatorio', 'texto', 'max:255' */
    public static function rules(string $code): string
    {
        $code = (string) preg_replace_callback("/'(required|nullable|string|integer|numeric|boolean|date)'/", fn (array $m): string => "'" . [
            'required' => 'obrigatorio', 'nullable' => 'opcional', 'string' => 'texto', 'integer' => 'inteiro',
            'numeric' => 'numerico', 'boolean' => 'booleano', 'date' => 'data',
        ][$m[1]] . "'", $code);

        return strtr($code, ["'unique:" => "'unico:", "'exists:" => "'existe:"]);
    }

    /** Model properties and relations of luany/pt's Modelo. */
    public static function model(string $code): string
    {
        return strtr($code, [
            'protected array $casts' => 'protected array $conversoes',
            '$this->belongsTo('      => '$this->pertenceA(',
        ]);
    }

    /** Texts of the generated form and detail fields. */
    public static function viewFields(string $code): string
    {
        return strtr($code, [
            'placeholder="Enter '             => 'placeholder="Indique ',
            "? 'Yes' : 'No'"                  => "? 'Sim' : 'Não'",
            '{{-- Add form fields here --}}'  => '{{-- Acrescente aqui os campos --}}',
            '{{-- Add fields here --}}'       => '{{-- Acrescente aqui os campos --}}',
        ]);
    }

    /**
     * English LTE directives → their Portuguese names (luany/lte's table);
     * directives without an alias (@style, @push, @csrf…) are kept.
     */
    public static function directives(string $lte): string
    {
        if (!class_exists(\Luany\Lte\Aliases::class)) {
            return $lte;
        }
        $aliases = \Luany\Lte\Aliases::for('pt');

        return (string) preg_replace_callback(
            '/(?<![\w@])@([a-z]+)\b/',
            fn (array $m): string => '@' . $aliases->canonical($m[1]),
            $lte
        );
    }
}
