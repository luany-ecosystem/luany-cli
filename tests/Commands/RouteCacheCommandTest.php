<?php

namespace LuanyCli\Tests\Commands;

use Luany\Core\Routing\Route;
use LuanyCli\Commands\RouteCacheCommand;
use LuanyCli\Commands\RouteClearCommand;
use PHPUnit\Framework\TestCase;

/**
 * Runs route:cache / route:clear against a minimal real Luany project
 * (luany/framework from the CLI's dev dependencies).
 */
class RouteCacheCommandTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/luany_route_cache_' . uniqid();
        foreach (['vendor', 'bootstrap', 'routes', 'storage/cache', 'config', 'views'] as $dir) {
            mkdir($this->base . '/' . $dir, 0755, true);
        }
        file_put_contents($this->base . '/vendor/autoload.php', '<?php require ' . var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true) . ';');
        file_put_contents($this->base . '/bootstrap/app.php', <<<'PHP'
<?php
require __DIR__ . '/../vendor/autoload.php';
$app = new Luany\Framework\Application(dirname(__DIR__));
$app->singleton(Luany\Framework\Http\Kernel::class, fn ($app) => new Luany\Framework\Http\Kernel($app));
return $app;
PHP);
        Route::reset();
    }

    protected function tearDown(): void
    {
        Route::reset();
        $this->remove($this->base);
    }

    private function remove(string $dir): void
    {
        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $item) {
            is_dir("$dir/$item") ? $this->remove("$dir/$item") : unlink("$dir/$item");
        }
        rmdir($dir);
    }

    private function routes(string $php): void
    {
        file_put_contents($this->base . '/routes/http.php', "<?php\nuse Luany\\Core\\Routing\\Route;\n" . $php);
    }

    /** @return array{int, string} */
    private function cache(): array
    {
        ob_start();
        $code = RouteCacheCommand::run($this->base);
        return [$code, (string) ob_get_clean()];
    }

    private function cacheFile(): string
    {
        return $this->base . '/' . RouteCacheCommand::DEFAULT_PATH;
    }

    public function test_names(): void
    {
        $this->assertSame('route:cache', (new RouteCacheCommand())->name());
        $this->assertSame('route:clear', (new RouteClearCommand())->name());
    }

    public function test_caches_controller_routes_from_every_route_file(): void
    {
        $this->routes("Route::get('/', ['App\\\\Controllers\\\\HomeController', 'index'])->name('home');\n");
        file_put_contents($this->base . '/routes/posts.php', "<?php\nLuany\\Core\\Routing\\Route::resource('posts', 'App\\\\Controllers\\\\PostController');\n");

        [$code, $out] = $this->cache();

        $this->assertSame(0, $code, $out);
        $this->assertFileExists($this->cacheFile());
        $this->assertStringContainsString('Routes cached (9 routes)', $out);
        $this->assertStringContainsString('storage/cache/routes.php', $out);
        $this->assertStringContainsString('PostController', (string) file_get_contents($this->cacheFile()));
    }

    public function test_stale_cache_is_rebuilt_from_the_route_files(): void
    {
        file_put_contents($this->cacheFile(), '<?php return [];');
        $this->routes("Route::get('/fresh', ['App\\\\Controllers\\\\FreshController', 'show']);\n");

        [$code] = $this->cache();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('FreshController', (string) file_get_contents($this->cacheFile()));
    }

    public function test_closure_routes_fail_with_a_clear_message(): void
    {
        $this->routes("Route::get('/closure', fn () => 'hi');\n");

        [$code, $out] = $this->cache();

        $this->assertSame(1, $code);
        $this->assertFileDoesNotExist($this->cacheFile());
        $this->assertStringContainsString('/closure', $out);
        $this->assertStringContainsString('controllers', $out);
    }

    public function test_missing_vendor_fails(): void
    {
        unlink($this->base . '/vendor/autoload.php');
        [$code, $out] = $this->cache();

        $this->assertSame(1, $code);
        $this->assertStringContainsString('composer install', $out);
    }

    public function test_route_clear(): void
    {
        file_put_contents($this->cacheFile(), '<?php return [];');

        ob_start();
        RouteClearCommand::run($this->base);
        $out = (string) ob_get_clean();
        $this->assertFileDoesNotExist($this->cacheFile());
        $this->assertStringContainsString('Route cache cleared', $out);

        ob_start();
        RouteClearCommand::run($this->base);
        $this->assertStringContainsString('No route cache', (string) ob_get_clean());
    }
}
