<?php

namespace LuanyCli\Tests\Commands;

use LuanyCli\Commands\ViewCheckCommand;
use LuanyCli\Env;
use PHPUnit\Framework\TestCase;

class ViewCheckCommandTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/luany_viewcheck_' . bin2hex(random_bytes(4));
        mkdir($this->base . '/views/pages/users', 0777, true);
        mkdir($this->base . '/views/layouts', 0777, true);
        file_put_contents($this->base . '/views/layouts/main.lte', "<html>@yield('content')</html>");
        file_put_contents($this->base . '/views/pages/users/index.lte', "@extends('layouts.main')\n@section('content')\n@foreach(\$users as \$u)\n{{ \$u->name }}\n@endforeach\n@endsection");
        Env::setBasePath($this->base);
    }

    protected function tearDown(): void
    {
        Env::reset();
        $this->remove($this->base);
    }

    private function remove(string $dir): void
    {
        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        ) as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($dir);
    }

    /**
     * @return array{output: string, exit: ?int}
     */
    private function check(array $args = ['--no-app']): array
    {
        $command = new class extends ViewCheckCommand {
            public ?int $exit = null;
            protected function terminate(int $code): void { $this->exit = $code; }
        };

        ob_start();
        try {
            $command->handle($args);
        } finally {
            $output = (string) ob_get_clean();
        }

        return ['output' => $output, 'exit' => $command->exit];
    }

    public function test_metadata(): void
    {
        $c = new ViewCheckCommand();
        $this->assertSame('view:check', $c->name());
        $this->assertTrue($c->requiresProject());
    }

    public function test_valid_views_pass_without_exit_code(): void
    {
        $r = $this->check();

        $this->assertNull($r['exit']);
        $this->assertStringContainsString('2 view(s) OK', $r['output']);
    }

    public function test_reports_structure_and_php_errors_with_positions(): void
    {
        file_put_contents($this->base . '/views/pages/users/show.lte', "<h1>User</h1>\n  @if(\$user)\n{{ \$user->name }}\n");
        file_put_contents($this->base . '/views/pages/users/edit.lte', "<form>\n  <input value=\"{{ \$user->name( }}\">\n</form>");

        $r = $this->check();

        $this->assertSame(1, $r['exit']);
        $this->assertStringContainsString('views/pages/users/show.lte:2:3', $r['output']);
        $this->assertStringContainsString('Unclosed @if: missing @endif.', $r['output']);
        $this->assertStringContainsString('views/pages/users/edit.lte:2:', $r['output']);
        $this->assertStringContainsString('PHP syntax error in {{ }}', $r['output']);
        $this->assertStringContainsString('2 of 4 view(s) have errors', $r['output']);
    }

    public function test_github_annotation_format(): void
    {
        file_put_contents($this->base . '/views/pages/users/show.lte', "ok\n@endif");

        $r = $this->check(['--no-app', '--format=github']);

        $this->assertSame(1, $r['exit']);
        $this->assertStringContainsString('::error file=views/pages/users/show.lte,line=2,col=1::Unexpected @endif', $r['output']);
        $this->assertStringNotContainsString('view(s)', $r['output']);
    }

    public function test_custom_path_option(): void
    {
        mkdir($this->base . '/resources/views', 0777, true);
        file_put_contents($this->base . '/resources/views/a.lte', '@if($x) x');

        $r = $this->check(['--no-app', '--path=resources/views']);

        $this->assertSame(1, $r['exit']);
        $this->assertStringContainsString('resources/views/a.lte:1:1', $r['output']);
    }

    public function test_unbootable_app_falls_back_with_a_warning(): void
    {
        mkdir($this->base . '/bootstrap');
        file_put_contents($this->base . '/bootstrap/app.php', '<?php throw new RuntimeException("missing .env");');

        $r = $this->check([]);

        $this->assertNull($r['exit']);
        $this->assertStringContainsString('Could not boot the application (missing .env)', $r['output']);
        $this->assertStringContainsString('2 view(s) OK', $r['output']);
    }

    public function test_rejects_bad_options(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->check(['--path=../etc']);
    }

    public function test_missing_views_directory(): void
    {
        $r = $this->check(['--no-app', '--path=nope']);

        $this->assertSame(1, $r['exit']);
        $this->assertStringContainsString('Views directory not found: nope', $r['output']);
    }
}
