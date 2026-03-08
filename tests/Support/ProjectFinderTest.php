<?php

namespace LuanyCli\Tests\Support;

use LuanyCli\Support\ProjectFinder;
use PHPUnit\Framework\TestCase;

class ProjectFinderTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        // Simula um projecto Luany completo
        $this->projectRoot = sys_get_temp_dir() . '/luany_project_' . uniqid();
        mkdir($this->projectRoot . '/vendor', 0755, true);
        file_put_contents($this->projectRoot . '/composer.json', '{}');
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->projectRoot);
    }

    public function test_finds_root_from_project_root(): void
    {
        chdir($this->projectRoot);
        $this->assertSame(realpath($this->projectRoot), ProjectFinder::findRoot());
    }

    public function test_finds_root_from_subdirectory(): void
    {
        $subDir = $this->projectRoot . '/app/Controllers';
        mkdir($subDir, 0755, true);
        chdir($subDir);

        $this->assertSame(realpath($this->projectRoot), ProjectFinder::findRoot());
    }

    public function test_finds_root_from_deeply_nested_subdirectory(): void
    {
        $deepDir = $this->projectRoot . '/app/Http/Controllers/Auth';
        mkdir($deepDir, 0755, true);
        chdir($deepDir);

        $this->assertSame(realpath($this->projectRoot), ProjectFinder::findRoot());
    }

    public function test_ignores_directory_with_composer_json_but_no_vendor(): void
    {
        // Subdir com composer.json mas sem vendor — não é root
        $subDir = $this->projectRoot . '/packages/my-package';
        mkdir($subDir, 0755, true);
        file_put_contents($subDir . '/composer.json', '{}');
        chdir($subDir);

        // Deve subir e encontrar o projectRoot que tem vendor
        $this->assertSame(realpath($this->projectRoot), ProjectFinder::findRoot());
    }

    public function test_falls_back_to_cwd_when_no_project_found(): void
    {
        $isolated = sys_get_temp_dir() . '/luany_isolated_' . uniqid();
        mkdir($isolated, 0755, true);
        chdir($isolated);

        $result = ProjectFinder::findRoot();

        // Deve retornar sempre uma string não vazia — o fallback nunca falha
        $this->assertIsString($result);
        $this->assertNotEmpty($result);

        rmdir($isolated);
    }

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
}