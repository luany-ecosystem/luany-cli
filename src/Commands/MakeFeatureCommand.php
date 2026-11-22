<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
use LuanyCli\Output\CommandError;
use LuanyCli\Output\Output;
use LuanyCli\Output\StructuredCommand;
use LuanyCli\Env;
use LuanyCli\Support\Console;
use LuanyCli\Support\FieldParser;
use LuanyCli\Support\InvalidNameException;
use LuanyCli\Support\MigrationFinder;
use LuanyCli\Support\NameValidator;
use LuanyCli\Support\StubRenderer;

class MakeFeatureCommand extends BaseCommand implements StructuredCommand
{
    public function name(): string
    {
        return 'make:feature';
    }

    public function description(): string
    {
        return 'Scaffold a complete feature (model, controller, migration, views, routes)';
    }

    /** @param array<int, string> $args */
    public function handle(array $args): void
    {
        $name = $args[0] ?? null;

        if (!$name) {
            throw CommandError::usage('make:feature <FeatureName> [field:type …]', 'make:feature Post title:string body:text?');
        }

        $out = Output::current();

        $model  = NameValidator::className($name, 'feature name');
        $models = $this->pluralize($model);
        $words  = (string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', '_', $models);
        $table  = strtolower($words);                    // BlogPosts → blog_posts
        $slug   = str_replace('_', '-', $table);         // BlogPosts → blog-posts (URL, views, routes file)
        $item   = strtolower($model);
        $items  = strtolower($models);

        // Validate inline fields before any interaction or file write.
        $inlineFields = $this->parseInlineFields(array_slice($args, 1));

        $layout = $this->resolveLayout();

        if (!empty($inlineFields) || $out->json) {
            // --json never prompts: fields come from the command line only.
            $fields = $inlineFields;
        } else {
            echo "  Tip: \033[36mluany make:feature {$model} title:string price:decimal bio:text?\033[0m\n\n";
            $fields = $this->collectFields();

            if (empty($fields)) {
                echo "  \033[33m⚠\033[0m  No fields defined. Generating empty structure.\n\n";
            } elseif (!$this->confirmSummary($model, $table, $fields)) {
                echo "\n  \033[33m→\033[0m  Aborted — nothing was generated.\n\n";
                return;
            }
        }

        // Defence in depth: every field name/type is interpolated into
        // generated PHP, SQL and LTE code.
        $fields = NameValidator::fields($fields);

        $renderer = new StubRenderer();
        $base         = Env::basePath();
        $stubs        = __DIR__ . '/../../stubs';

        $variables = [
            'Model'             => $model,
            'Models'            => $models,
            'table'             => $table,
            'slug'              => $slug,
            'item'              => $item,
            'items'             => $items,
            'layout'            => $layout,
            'fillable'          => FieldParser::toFillable($fields),
            'casts'             => FieldParser::toCasts($fields),
            'relations'         => FieldParser::toRelations($fields),
            'model_imports'     => FieldParser::toModelImports($fields),
            'schema_columns'    => FieldParser::toSchemaColumns($fields),
            'form_fields'       => FieldParser::toFormFields($fields),
            'show_fields'       => FieldParser::toShowFields($fields, $item),
            'index_headers'     => FieldParser::toIndexHeaders($fields),
            'index_rows'        => FieldParser::toIndexRows($fields, $item),
            'fillable_keys'     => FieldParser::toFillableKeys($fields),
            'edit_fields'       => FieldParser::toEditFields($fields, $item),
            'validation_rules'  => FieldParser::toValidationRules($fields, $table),
            'validation_rules_update' => FieldParser::toValidationRules($fields, $table, true),
        ];

        $out->line();
        $out->say('LUANY-MAKE-006', ['name' => $model], 'info');
        $out->compact();

        $this->generateModel($renderer, $base, $stubs, $variables);
        $this->generateController($renderer, $base, $stubs, $variables);
        $this->generateMigration($renderer, $base, $stubs, $variables);
        $this->generateViews($renderer, $base, $stubs, $variables);
        $this->appendRoutes($base, $variables);
        $this->printRoutes($variables);
        $this->warnMissingReferences($base, $fields);

        $out->compact(false);
        $out->line();
        $out->data(['model' => $model, 'table' => $table, 'fields' => $fields]);
    }

    /**
     * Foreign keys need the referenced table to exist before this migration
     * runs — warn when no create_{table}_table migration is found.
     *
     * @param array<int, array{name: string, type: string}> $fields
     */
    private function warnMissingReferences(string $base, array $fields): void
    {
        $existing = [];
        foreach (glob("{$base}/database/migrations/*.php") ?: [] as $file) {
            if (preg_match('/create_([a-z0-9_]+)_table/', basename($file), $m) === 1) {
                $existing[] = $m[1];
            }
        }

        foreach (FieldParser::missingReferences($fields, $existing) as $table) {
            Output::current()->say('LUANY-MAKE-007', ['table' => $table], 'warning');
        }
    }

    // ── Generators ────────────────────────────────────────────────────────────

    /** @param array<string, string> $v */
    private function generateModel(StubRenderer $r, string $base, string $stubs, array $v): void
    {
        $out     = Output::current();
        $gitkeep = "{$base}/app/Models/.gitkeep";
        if (!$out->dryRun && file_exists($gitkeep)) {
            unlink($gitkeep);
        }

        $out->createFile("app/Models/{$v['Model']}.php", self::render($r, "{$stubs}/feature-model.stub", $v), 'model', $v['Model']);
    }

    /** @param array<string, string> $v */
    private function generateController(StubRenderer $r, string $base, string $stubs, array $v): void
    {
        Output::current()->createFile(
            "app/Controllers/{$v['Model']}Controller.php",
            self::render($r, "{$stubs}/feature-controller.stub", $v),
            'controller',
            "{$v['Model']}Controller"
        );
    }

    /** @param array<string, string> $v */
    private function generateMigration(StubRenderer $r, string $base, string $stubs, array $v): void
    {
        $name = "create_{$v['table']}_table";

        // A second Create{X}Table migration would redeclare the same class.
        $existing = MigrationFinder::find("{$base}/database/migrations", $name);
        if ($existing !== null) {
            Output::current()->say('LUANY-MAKE-008', ['path' => "database/migrations/{$existing}"], 'warning');
            return;
        }

        $file = date('Y_m_d_His') . "_{$name}.php";
        Output::current()->createFile("database/migrations/{$file}", self::render($r, "{$stubs}/feature-migration.stub", $v), 'migration', $file);
    }

    /** @param array<string, string> $v */
    private function generateViews(StubRenderer $r, string $base, string $stubs, array $v): void
    {
        foreach (['index' => 'feature-view-index.stub', 'show' => 'feature-view-show.stub', 'create' => 'feature-view-create.stub', 'edit' => 'feature-view-edit.stub'] as $view => $stub) {
            Output::current()->createFile("views/pages/{$v['slug']}/{$view}.lte", self::render($r, "{$stubs}/{$stub}", $v), 'view', "[{$view}]");
        }
    }

    /** @param array<string, string> $v */
    private function appendRoutes(string $base, array $v): void
    {
        $block  = "<?php\n\n";
        $block .= "use Luany\\Core\\Routing\\Route;\n";
        $block .= "use App\\Controllers\\{$v['Model']}Controller;\n\n";
        $block .= "// ── {$v['Models']} " . str_repeat('─', 67) . "\n";
        $block .= "Route::resource('{$v['slug']}', {$v['Model']}Controller::class);\n";

        Output::current()->createFile("routes/{$v['slug']}.php", $block, 'routes', "routes/{$v['slug']}.php");
    }

    /** @param array<string, string> $v */
    private static function render(StubRenderer $r, string $stub, array $v): string
    {
        return (string) preg_replace('/^\xEF\xBB\xBF/', '', $r->render($stub, $v));
    }

    // ── Interactive ───────────────────────────────────────────────────────────

    /**
     * Collect field definitions interactively from the developer.
     * Override in tests to inject predefined fields.
     *
     * @return array<array{name: string, type: string}>
     */
    protected function collectFields(): array
    {
        $io = $this->console();

        if (!$io->confirm('Add fields?', true)) {
            return [];
        }

        $io->write("\n  \033[2mEnter a name (title) or name:type (price:decimal). End with ? for optional (bio:text?).\n"
            . "  Leave empty to finish.\033[0m\n\n");

        $fields = [];

        while (true) {
            $entry = $io->ask('Field');

            if ($entry === '' || strtolower($entry) === 'done') {
                break;
            }

            [$name, $type, $nullable] = self::parseFieldEntry($entry);

            try {
                NameValidator::column($name);
            } catch (InvalidNameException $e) {
                $io->error($e->getMessage());
                continue;
            }

            if ($type === '') {
                $type = $io->choice("Type of [{$name}]", FieldParser::TYPE_DESCRIPTIONS, str_ends_with($name, '_id') ? 'foreign' : 'string');
            }

            $field = ['name' => $name, 'type' => $type, 'nullable' => $nullable];

            try {
                NameValidator::fields([...$fields, $field]);
            } catch (InvalidNameException $e) {
                $io->error($e->getMessage());
                continue;
            }

            $fields[] = $field;
            $io->info("{$name} \033[2m{$type}" . ($nullable ? ', optional' : '') . "\033[0m");
        }

        $io->write("\n");
        return $fields;
    }

    /**
     * Interactive console (overridable in tests).
     */
    protected function console(): Console
    {
        return new Console();
    }

    /**
     * "name", "name:type", "name:type?" or "name?" → [name, type, nullable].
     *
     * @return array{0: string, 1: string, 2: bool}
     */
    private static function parseFieldEntry(string $entry): array
    {
        $entry    = trim($entry);
        $nullable = str_ends_with($entry, '?');
        $entry    = rtrim($entry, '?');

        [$name, $type] = str_contains($entry, ':') ? explode(':', $entry, 2) : [$entry, ''];

        return [trim($name), strtolower(trim($type)), $nullable];
    }

    /**
     * @param array<int, array{name: string, type: string, nullable?: bool}> $fields
     */
    private function confirmSummary(string $model, string $table, array $fields): bool
    {
        $io = $this->console();
        $io->write("  \033[1m{$model}\033[0m  \033[2m(table {$table})\033[0m\n");
        foreach ($fields as $field) {
            $io->write(sprintf("    %-20s %-9s %s\n", $field['name'], $field['type'], !empty($field['nullable']) ? 'optional' : ''));
        }
        $io->write("\n");

        return $io->confirm('Generate model, migration, controller, views and routes?', true);
    }

    private function resolveLayout(): string
    {
        $layoutsDir = Env::basePath() . '/views/layouts';
        $layouts    = [];

        if (is_dir($layoutsDir)) {
            foreach (glob($layoutsDir . '/*.lte') ?: [] as $file) {
                $layouts[] = basename($file, '.lte');
            }
        }

        if (empty($layouts)) {
            return 'layouts.main';
        }

        if (count($layouts) === 1) {
            Output::current()->line("  \033[33m→\033[0m  Using layout: \033[36mlayouts.{$layouts[0]}\033[0m");
            return "layouts.{$layouts[0]}";
        }

        $default = in_array('main', $layouts, true) ? 'main' : $layouts[0];
        if (Output::current()->json) {
            return "layouts.{$default}"; // --json never prompts
        }
        $options = array_combine($layouts, array_map(fn (string $l) => "views/layouts/{$l}.lte", $layouts));

        return 'layouts.' . $this->console()->choice('Layout', $options, $default);
    }

    private function pluralize(string $word): string
    {
        // category → categories (consonant + y → ies)
        if (preg_match('/[^aeiou]y$/i', $word)) {
            return substr($word, 0, -1) . 'ies';
        }
        // class, box, church → es
        if (preg_match('/(s|sh|ch|x|z)$/i', $word)) {
            return $word . 'es';
        }
        return $word . 's';
    }

    /**
     * @param array<int, string> $args
     * @return array<int, array{name: string, type: string}>
     */
    private function parseInlineFields(array $args): array
    {
        $fields = [];
        foreach ($args as $arg) {
            if (!str_contains($arg, ':')) {
                throw new InvalidNameException(
                    "Invalid field definition [{$arg}] — expected name:type (e.g. title:string)."
                );
            }
            [$name, $type, $nullable] = self::parseFieldEntry($arg);

            NameValidator::column($name);
            NameValidator::fieldType($type, $name);

            $fields[] = ['name' => $name, 'type' => $type, 'nullable' => $nullable];
        }
        return NameValidator::fields($fields);
    }

    /** @param array<string, string> $v */
    private function printRoutes(array $v): void
    {
        $routes = [
            ['GET', "/{$v['slug']}", 'index'],
            ['GET', "/{$v['slug']}/create", 'create'],
            ['POST', "/{$v['slug']}", 'store'],
            ['GET', "/{$v['slug']}/{id}", 'show'],
            ['GET', "/{$v['slug']}/{id}/edit", 'edit'],
            ['PUT', "/{$v['slug']}/{id}", 'update'],
            ['DELETE', "/{$v['slug']}/{id}", 'destroy'],
        ];

        $out = Output::current();
        $out->data(['routes' => array_map(fn (array $r): array => ['method' => $r[0], 'uri' => $r[1], 'action' => $r[2]], $routes)]);
        $out->line("  \033[33m" . $out->text->text('LUANY-MAKE-009') . "\033[0m");
        foreach ($routes as [$method, $uri, $action]) {
            $out->line(sprintf("    \033[36m%-7s\033[0m %-28s → %s", $method, $uri, $action));
        }
    }
}