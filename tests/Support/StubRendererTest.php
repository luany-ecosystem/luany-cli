<?php

use LuanyCli\Support\StubRenderer;
use PHPUnit\Framework\TestCase;

class StubRendererTest extends TestCase
{
    private string $stubDir;

    protected function setUp(): void
    {
        $this->stubDir = sys_get_temp_dir() . '/luany_stubs_test_' . uniqid();
        mkdir($this->stubDir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->stubDir . '/*') as $file) {
            unlink($file);
        }
        rmdir($this->stubDir);
    }

    public function test_renders_stub_with_variables(): void
    {
        $stub = $this->stubDir . '/test.stub';
        file_put_contents($stub, 'class {{Model}} extends Model {}');

        $result = (new StubRenderer())->render($stub, ['Model' => 'Post']);

        $this->assertSame('class Post extends Model {}', $result);
    }

    public function test_renders_multiple_placeholders(): void
    {
        $stub = $this->stubDir . '/test.stub';
        file_put_contents($stub, "class {{Model}} { protected string \$table = '{{table}}'; }");

        $result = (new StubRenderer())->render($stub, ['Model' => 'Post', 'table' => 'posts']);

        $this->assertStringContainsString('Post',  $result);
        $this->assertStringContainsString('posts', $result);
    }

    public function test_throws_when_stub_not_found(): void
    {
        $this->expectException(\RuntimeException::class);
        (new StubRenderer())->render($this->stubDir . '/nonexistent.stub', []);
    }
}
