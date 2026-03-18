<?php

use LuanyCli\Commands\MakeFeatureCommand;
use LuanyCli\Env;
use PHPUnit\Framework\TestCase;

class MakeFeatureCommandTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/luany_feature_test_' . uniqid();
        mkdir($this->baseDir . '/app/Controllers', 0755, true);
        mkdir($this->baseDir . '/app/Models',      0755, true);
        mkdir($this->baseDir . '/database/migrations', 0755, true);
        mkdir($this->baseDir . '/views/layouts',   0755, true);
        mkdir($this->baseDir . '/routes',           0755, true);

        file_put_contents($this->baseDir . '/views/layouts/main.lte', '');
        file_put_contents(
            $this->baseDir . '/routes/http.php',
            "<?php\n\nuse Luany\\Core\\Routing\\Route;\nuse App\\Controllers\\HomeController;\n\nRoute::get('/', [HomeController::class, 'index']);\n"
        );

        Env::setBasePath($this->baseDir);
    }

    protected function tearDown(): void
    {
        Env::reset();
        $this->removeDir($this->baseDir);
    }

    // â”€â”€ Helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Returns a testable command with predefined fields,
     * bypassing STDIN interaction.
     *
     * @param array<array{name: string, type: string}> $fields
     */
    private function makeCommand(array $fields = []): MakeFeatureCommand
    {
        return new class($fields) extends MakeFeatureCommand {
            public function __construct(private array $testFields) {}
            protected function collectFields(): array { return $this->testFields; }
        };
    }

    // â”€â”€ Metadata â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function test_name_is_make_feature(): void
    {
        $this->assertSame('make:feature', (new class([]) extends MakeFeatureCommand {
            protected function collectFields(): array { return []; }
        })->name());
    }

    public function test_requires_project_returns_true(): void
    {
        $this->assertTrue($this->makeCommand()->requiresProject());
    }

    public function test_description_is_not_empty(): void
    {
        $this->assertNotEmpty($this->makeCommand()->description());
    }

    // â”€â”€ File generation without fields â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function test_generates_model_without_fields(): void
    {
        ob_start();
        $this->makeCommand()->handle(['Post']);
        ob_end_clean();

        $path = $this->baseDir . '/app/Models/Post.php';
        $this->assertFileExists($path);
        $this->assertStringContainsString('class Post extends Model', file_get_contents($path));
        $this->assertStringContainsString("protected string \$table = 'posts'", file_get_contents($path));
    }

    public function test_generates_controller_without_fields(): void
    {
        ob_start();
        $this->makeCommand()->handle(['Post']);
        ob_end_clean();

        $path = $this->baseDir . '/app/Controllers/PostController.php';
        $this->assertFileExists($path);
        $this->assertStringContainsString('class PostController', file_get_contents($path));
        $this->assertStringContainsString("view('pages.posts.index'", file_get_contents($path));
    }

    public function test_generates_migration_without_fields(): void
    {
        ob_start();
        $this->makeCommand()->handle(['Post']);
        ob_end_clean();

        $files = glob($this->baseDir . '/database/migrations/*_create_posts_table.php');
        $this->assertCount(1, $files);
        $this->assertStringContainsString('class CreatePostsTable', file_get_contents($files[0]));
    }

    public function test_generates_views_without_fields(): void
    {
        ob_start();
        $this->makeCommand()->handle(['Post']);
        ob_end_clean();

        $this->assertFileExists($this->baseDir . '/views/pages/posts/index.lte');
        $this->assertFileExists($this->baseDir . '/views/pages/posts/show.lte');
        $this->assertFileExists($this->baseDir . '/views/pages/posts/create.lte');
    }

    public function test_views_use_detected_layout(): void
    {
        ob_start();
        $this->makeCommand()->handle(['Post']);
        ob_end_clean();

        $index = file_get_contents($this->baseDir . '/views/pages/posts/index.lte');
        $this->assertStringContainsString("@extends('layouts.main')", $index);
    }

    public function test_appends_routes(): void
    {
        ob_start();
        $this->makeCommand()->handle(['Post']);
        ob_end_clean();

        $routes = file_get_contents($this->baseDir . '/routes/http.php');
        $this->assertStringContainsString("Route::resource('posts'", $routes);
        $this->assertStringContainsString('PostController::class', $routes);
        $this->assertStringContainsString('use App\Controllers\PostController;', $routes);
    }

    // â”€â”€ File generation with fields â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function test_generates_model_with_fillable_fields(): void
    {
        $fields = [
            ['name' => 'title',     'type' => 'string'],
            ['name' => 'body',      'type' => 'text'],
            ['name' => 'published', 'type' => 'boolean'],
        ];

        ob_start();
        $this->makeCommand($fields)->handle(['Post']);
        ob_end_clean();

        $model = file_get_contents($this->baseDir . '/app/Models/Post.php');
        $this->assertStringContainsString("'title'",     $model);
        $this->assertStringContainsString("'body'",      $model);
        $this->assertStringContainsString("'published'", $model);
    }

    public function test_generates_model_with_casts(): void
    {
        $fields = [
            ['name' => 'published', 'type' => 'boolean'],
            ['name' => 'score',     'type' => 'integer'],
        ];

        ob_start();
        $this->makeCommand($fields)->handle(['Post']);
        ob_end_clean();

        $model = file_get_contents($this->baseDir . '/app/Models/Post.php');
        $this->assertStringContainsString('protected array $casts', $model);
        $this->assertStringContainsString("'published' => 'bool'", $model);
        $this->assertStringContainsString("'score' => 'int'",      $model);
    }

    public function test_generates_migration_with_columns(): void
    {
        $fields = [
            ['name' => 'title', 'type' => 'string'],
            ['name' => 'body',  'type' => 'text'],
        ];

        ob_start();
        $this->makeCommand($fields)->handle(['Post']);
        ob_end_clean();

        $files     = glob($this->baseDir . '/database/migrations/*_create_posts_table.php');
        $migration = file_get_contents($files[0]);
        $this->assertStringContainsString('`title` VARCHAR(255)', $migration);
        $this->assertStringContainsString('`body` TEXT',          $migration);
    }

    public function test_create_view_has_form_fields(): void
    {
        $fields = [
            ['name' => 'title', 'type' => 'string'],
            ['name' => 'body',  'type' => 'text'],
        ];

        ob_start();
        $this->makeCommand($fields)->handle(['Post']);
        ob_end_clean();

        $create = file_get_contents($this->baseDir . '/views/pages/posts/create.lte');
        $this->assertStringContainsString('@csrf',                       $create);
        $this->assertStringContainsString('name="title"',                $create);
        $this->assertStringContainsString('<textarea name="body"',       $create);
    }

    public function test_create_view_has_checkbox_for_boolean_field(): void
    {
        $fields = [['name' => 'published', 'type' => 'boolean']];

        ob_start();
        $this->makeCommand($fields)->handle(['Post']);
        ob_end_clean();

        $create = file_get_contents($this->baseDir . '/views/pages/posts/create.lte');
        $this->assertStringContainsString('type="checkbox"', $create);
        $this->assertStringContainsString('name="published"', $create);
    }

    public function test_show_view_has_field_values(): void
    {
        $fields = [['name' => 'title', 'type' => 'string']];

        ob_start();
        $this->makeCommand($fields)->handle(['Post']);
        ob_end_clean();

        $show = file_get_contents($this->baseDir . '/views/pages/posts/show.lte');
        $this->assertStringContainsString('$post->title', $show);
    }

    public function test_index_view_has_table_headers(): void
    {
        $fields = [
            ['name' => 'title',  'type' => 'string'],
            ['name' => 'body',   'type' => 'text'],
        ];

        ob_start();
        $this->makeCommand($fields)->handle(['Post']);
        ob_end_clean();

        $index = file_get_contents($this->baseDir . '/views/pages/posts/index.lte');
        $this->assertStringContainsString('<th>Title</th>', $index);
        $this->assertStringContainsString('<th>Body</th>',  $index);
    }

    // â”€â”€ Idempotency â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function test_does_not_duplicate_routes_on_second_run(): void
    {
        ob_start();
        $this->makeCommand()->handle(['Post']);
        $this->makeCommand()->handle(['Post']);
        ob_end_clean();

        $routes = file_get_contents($this->baseDir . '/routes/http.php');
        $this->assertSame(1, substr_count($routes, "Route::resource('posts'"));
    }

    public function test_skips_existing_model_without_overwriting(): void
    {
        ob_start();
        $this->makeCommand()->handle(['Post']);
        ob_end_clean();

        $path          = $this->baseDir . '/app/Models/Post.php';
        $originalTime  = filemtime($path);
        sleep(1);

        ob_start();
        $this->makeCommand()->handle(['Post']);
        ob_end_clean();

        $this->assertSame($originalTime, filemtime($path));
    }

    // â”€â”€ Helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function test_generates_edit_view(): void
    {
        ob_start();
        $this->makeCommand()->handle(['Post']);
        ob_end_clean();

        $this->assertFileExists($this->baseDir . '/views/pages/posts/edit.lte');
    }

    public function test_edit_view_has_prefilled_values(): void
    {
        $fields = [['name' => 'title', 'type' => 'string']];

        ob_start();
        $this->makeCommand($fields)->handle(['Post']);
        ob_end_clean();

        $edit = file_get_contents($this->baseDir . '/views/pages/posts/edit.lte');
        $this->assertStringContainsString('$post->title', $edit);
        $this->assertStringContainsString("@method('PUT')", $edit);
    }

    public function test_controller_has_edit_update_destroy(): void
    {
        ob_start();
        $this->makeCommand()->handle(['Post']);
        ob_end_clean();

        $controller = file_get_contents($this->baseDir . '/app/Controllers/PostController.php');
        $this->assertStringContainsString('public function edit', $controller);
        $this->assertStringContainsString('public function update', $controller);
        $this->assertStringContainsString('public function destroy', $controller);
    }

    public function test_inline_fields_skips_interactive(): void
    {
        $command = new class extends MakeFeatureCommand {
            protected function collectFields(): array {
                throw new \RuntimeException('Should not be called');
            }
        };

        ob_start();
        $command->handle(['Post', 'title:string', 'price:decimal']);
        ob_end_clean();

        $model = file_get_contents($this->baseDir . '/app/Models/Post.php');
        $this->assertStringContainsString("'title'", $model);
        $this->assertStringContainsString("'price'", $model);
    }

    public function test_gitkeep_removed_when_model_generated(): void
    {
        file_put_contents($this->baseDir . '/app/Models/.gitkeep', '');

        ob_start();
        $this->makeCommand()->handle(['Post']);
        ob_end_clean();

        $this->assertFileDoesNotExist($this->baseDir . '/app/Models/.gitkeep');
    }
}
