<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
use LuanyCli\Env;
use LuanyCli\Support\FieldParser;
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

    public function handle(array $args): void
    {
        $name = $args[0] ?? null;

        if (!$name) {
            fwrite(STDERR, "\n  \033[31m✗\033[0m  Usage: luany make:feature <FeatureName>\n\n");
            exit(1);
        }

        $model  = ucfirst($name);
        $models = $this->pluralize($model);
        $slug   = strtolower($models);
        $item   = strtolower($model);
        $items  = strtolower($models);
        $table  = $slug;
        $layout = $this->resolveLayout();

        $inlineFields = $this->parseInlineFields(array_slice($args, 1));

        if (!empty($inlineFields)) {
            $fields = $inlineFields;
        } else {
            echo "  Tip: \033[36mluany make:feature {$model} name:string price:decimal\033[0m\n\n";
            $fields = $this->collectFields();

            if (empty($fields)) {
                echo "  \033[33m⚠\033[0m  No fields defined. Generating empty structure.\n\n";
            }
        }

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
            'migration_columns' => FieldParser::toMigrationColumns($fields),
            'form_fields'       => FieldParser::toFormFields($fields),
            'show_fields'       => FieldParser::toShowFields($fields, $item),
            'index_headers'     => FieldParser::toIndexHeaders($fields),
            'index_rows'        => FieldParser::toIndexRows($fields, $item),
            'fillable_keys'     => FieldParser::toFillableKeys($fields),
            'edit_fields'       => FieldParser::toEditFields($fields, $item),
            'validation_rules'  => FieldParser::toValidationRules($fields),
        ];

        echo "\n  \033[33m→\033[0m  Creating feature [\033[36m{$model}\033[0m]...\n\n";

        $this->generateModel($renderer, $base, $stubs, $variables);
        $this->generateController($renderer, $base, $stubs, $variables);
        $this->generateMigration($renderer, $base, $stubs, $variables);
        $this->generateViews($renderer, $base, $stubs, $variables);
        $this->appendRoutes($base, $variables);
        $this->printRoutes($variables);

        echo "\n";
    }

    // ── Generators ────────────────────────────────────────────────────────────

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

    private function generateMigration(StubRenderer $r, string $base, string $stubs, array $v): void
    {
        $dir  = "{$base}/database/migrations";
        $ts   = date('Y_m_d_His');
        $file = "{$ts}_create_{$v['table']}_table.php";

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
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
        $types = implode('/', FieldParser::TYPES);

        echo "  Add fields? [yes/no]: ";
        $answer = trim(fgets(STDIN));

        if ($answer !== 'yes' && $answer !== 'y') {
            return [];
        }

        $fields = [];
        echo "\n";

        while (true) {
            echo "  Field name (or 'done' to finish): ";
            $fieldName = trim(fgets(STDIN));

            if ($fieldName === 'done' || $fieldName === '') {
                break;
            }

            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $fieldName)) {
                echo "  \033[31m✗\033[0m  Invalid field name — use letters, numbers and underscores only.\n\n";
                continue;
            }

            echo "  Type [{$types}]: ";
            $fieldType = trim(fgets(STDIN));

            if ($fieldType === '') {
                $fieldType = 'string';
                echo "  \033[33m→\033[0m  No type given — defaulting to string.\n";
            } elseif (!in_array($fieldType, FieldParser::TYPES, true)) {
                echo "  \033[33m→\033[0m  Unknown type [{$fieldType}] — defaulting to string.\n";
                $fieldType = 'string';
            }

            $fields[] = ['name' => $fieldName, 'type' => $fieldType];
            echo "\n";
        }

        echo "\n";
        return $fields;
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

        $default = $layouts[0];
        echo "  Available layouts: \033[36m" . implode(', ', $layouts) . "\033[0m\n";
        echo "  Extend layout [\033[36m{$default}\033[0m]: ";
        $input  = trim(fgets(STDIN));
        $choice = ($input && in_array($input, $layouts, true)) ? $input : $default;

        return "layouts.{$choice}";
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

    private function parseInlineFields(array $args): array
    {
        $fields = [];
        foreach ($args as $arg) {
            if (!str_contains($arg, ':')) {
                continue;
            }
            [$name, $type] = explode(':', $arg, 2);
            $name = trim($name);
            $type = trim($type);
            if (!in_array($type, FieldParser::TYPES, true)) {
                $type = 'string';
            }
            if ($name !== '') {
                $fields[] = ['name' => $name, 'type' => $type];
            }
        }
        return $fields;
    }

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
