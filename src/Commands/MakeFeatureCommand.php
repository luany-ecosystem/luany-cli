<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
use LuanyCli\Env;
use LuanyCli\Support\Console;
use LuanyCli\Support\FieldParser;
use LuanyCli\Support\InvalidNameException;
use LuanyCli\Support\MigrationFinder;
use LuanyCli\Support\NameValidator;
use LuanyCli\Support\StubRenderer;

class MakeFeatureCommand extends BaseCommand
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
            fwrite(STDERR, "\n  \033[31m✗\033[0m  Usage: luany make:feature <FeatureName>\n\n");
            exit(1);
        }

        $model  = NameValidator::className($name, 'feature name');
        $models = $this->pluralize($model);
        $slug   = strtolower($models);
        $item   = strtolower($model);
        $items  = strtolower($models);
        $table  = $slug;

        // Validate inline fields before any interaction or file write.
        $inlineFields = $this->parseInlineFields(array_slice($args, 1));

        $layout = $this->resolveLayout();

        if (!empty($inlineFields)) {
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

        echo "\n  \033[33m→\033[0m  Creating feature [\033[36m{$model}\033[0m]...\n\n";

        $this->generateModel($renderer, $base, $stubs, $variables);
        $this->generateController($renderer, $base, $stubs, $variables);
        $this->generateMigration($renderer, $base, $stubs, $variables);
        $this->generateViews($renderer, $base, $stubs, $variables);
        $this->appendRoutes($base, $variables);
        $this->printRoutes($variables);
        $this->warnMissingReferences($base, $fields);

        echo "\n";
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
            echo "  \033[33m⚠ \033[0m  No migration creates [{$table}] yet — create it before running migrate (foreign key).\n";
        }
    }

    // ── Generators ────────────────────────────────────────────────────────────

    /** @param array<string, string> $v */
    private function generateModel(StubRenderer $r, string $base, string $stubs, array $v): void
    {
        $path = "{$base}/app/Models/{$v['Model']}.php";

        $gitkeep = "{$base}/app/Models/.gitkeep";
        if (file_exists($gitkeep)) {
            unlink($gitkeep);
        }
        
        if (file_exists($path)) {
            echo "  \033[33m⚠ \033[0m  Model already exists — skipped.\n";
            return;
        }

        if (!is_dir("{$base}/app/Models")) {
            mkdir("{$base}/app/Models", 0755, true);
        }

        $content = $r->render("{$stubs}/feature-model.stub", $v);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        $result = file_put_contents($path, $content);

        if ($result === false) {
            echo "  \033[31m✗\033[0m  Failed to create model.\n";
            return;
        }

        echo "  \033[32m✓\033[0m  Model created:      app/Models/{$v['Model']}.php\n";
    }

    /** @param array<string, string> $v */
    private function generateController(StubRenderer $r, string $base, string $stubs, array $v): void
    {
        $dir = "{$base}/app/Controllers";

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = "{$dir}/{$v['Model']}Controller.php";

        if (file_exists($path)) {
            echo "  \033[33m⚠ \033[0m  Controller already exists — skipped.\n";
            return;
        }

        $content = $r->render("{$stubs}/feature-controller.stub", $v);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        $result = file_put_contents($path, $content);

        if ($result === false) {
            echo "  \033[31m✗\033[0m  Failed to create controller.\n";
            return;
        }

        echo "  \033[32m✓\033[0m  Controller created: app/Controllers/{$v['Model']}Controller.php\n";
    }

    /** @param array<string, string> $v */
    private function generateMigration(StubRenderer $r, string $base, string $stubs, array $v): void
    {
        $dir  = "{$base}/database/migrations";
        $name = "create_{$v['table']}_table";
        $ts   = date('Y_m_d_His');
        $file = "{$ts}_{$name}.php";

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // A second Create{X}Table migration would redeclare the same class.
        $existing = MigrationFinder::find($dir, $name);
        if ($existing !== null) {
            echo "  \033[33m⚠ \033[0m  Migration already exists — skipped: database/migrations/{$existing}\n";
            return;
        }

        $content = $r->render("{$stubs}/feature-migration.stub", $v);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        $result = file_put_contents("{$dir}/{$file}", $content);

        if ($result === false) {
            echo "  \033[31m✗\033[0m  Failed to create migration.\n";
            return;
        }

        echo "  \033[32m✓\033[0m  Migration created:  database/migrations/{$file}\n";
    }

    /** @param array<string, string> $v */
    private function generateViews(StubRenderer $r, string $base, string $stubs, array $v): void
    {
        $dir = "{$base}/views/pages/{$v['slug']}";

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        foreach (['index' => 'feature-view-index.stub', 'show' => 'feature-view-show.stub', 'create' => 'feature-view-create.stub', 'edit' => 'feature-view-edit.stub'] as $view => $stub) {
            $path = "{$dir}/{$view}.lte";

            if (file_exists($path)) {
                echo "  \033[33m⚠ \033[0m  View [{$view}] already exists — skipped.\n";
                continue;
            }

            $content = $r->render("{$stubs}/{$stub}", $v);
            $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

            $result = file_put_contents($path, $content);

            if ($result === false) {
                echo "  \033[31m✗\033[0m  Failed to create view: {$view}.\n";
                continue;
            }

            echo "  \033[32m✓\033[0m  View created:       views/pages/{$v['slug']}/{$view}.lte\n";
        }
    }

    /** @param array<string, string> $v */
    private function appendRoutes(string $base, array $v): void
    {
        $routesDir  = "{$base}/routes";
        $routesFile = "{$routesDir}/{$v['slug']}.php";

        if (!is_dir($routesDir)) {
            mkdir($routesDir, 0755, true);
        }

        // If a dedicated routes file for this feature already exists, skip
        if (file_exists($routesFile)) {
            echo "  \033[33m⚠ \033[0m  Routes file already exists — skipped.\n";
            return;
        }

        $useStatement = "use App\\Controllers\\{$v['Model']}Controller;";
        $block  = "<?php\n\n";
        $block .= "use Luany\\Core\\Routing\\Route;\n";
        $block .= "{$useStatement}\n\n";
        $block .= "// ── {$v['Models']} " . str_repeat('─', 67) . "\n";
        $block .= "Route::resource('{$v['slug']}', {$v['Model']}Controller::class);\n";

        file_put_contents($routesFile, $block);
        echo "  \033[32m✓\033[0m  Routes created:     routes/{$v['slug']}.php\n";
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
            echo "  \033[33m→\033[0m  Using layout: \033[36mlayouts.{$layouts[0]}\033[0m\n";
            return "layouts.{$layouts[0]}";
        }

        $default = in_array('main', $layouts, true) ? 'main' : $layouts[0];
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
        echo "  \033[33mRoutes available:\033[0m\n";
        echo "    \033[36mGET\033[0m     /{$v['slug']}                → index\n";
        echo "    \033[36mGET\033[0m     /{$v['slug']}/create         → create\n";
        echo "    \033[36mPOST\033[0m    /{$v['slug']}                → store\n";
        echo "    \033[36mGET\033[0m     /{$v['slug']}/{id}           → show\n";
        echo "    \033[36mGET\033[0m     /{$v['slug']}/{id}/edit      → edit\n";
        echo "    \033[36mPUT\033[0m     /{$v['slug']}/{id}           → update\n";
        echo "    \033[36mDELETE\033[0m  /{$v['slug']}/{id}           → destroy\n";
    }
}