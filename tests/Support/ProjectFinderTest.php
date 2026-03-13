<?php

namespace LuanyCli\Tests\Support;

use LuanyCli\Support\ProjectFinder;
use PHPUnit\Framework\TestCase;

class ProjectFinderTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir() . '/luany_project_' . uniqid();
        mkdir($this->projectRoot, 0755, true);
        $this->writeLuanyComposerJson($this->projectRoot);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->projectRoot);
    }

    // ── findRoot() ────────────────────────────────────────────────────────────

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

    public function test_detects_luany_project_without_vendor(): void
    {
        // Project declared in composer.json but composer install not yet run
        $dir = sys_get_temp_dir() . '/luany_no_vendor_' . uniqid();
        mkdir($dir, 0755, true);
        $this->writeLuanyComposerJson($dir);
        chdir($dir);

        $this->assertSame(realpath($dir), ProjectFinder::findRoot());

        $this->removeDir($dir);
    }

    public function test_ignores_non_luany_composer_json(): void
    {
        // A plain PHP project — composer.json has no luany dependency
        $plain = sys_get_temp_dir() . '/plain_project_' . uniqid();
        mkdir($plain . '/vendor', 0755, true);
        file_put_contents($plain . '/composer.json', json_encode([
            'require' => ['monolog/monolog' => '^3.0'],
        ]));
        chdir($plain);

        $result = ProjectFinder::findRoot();

        // Must NOT return the plain project — fallback to cwd
        $this->assertSame(realpath(getcwd()), $result);

        $this->removeDir($plain);
    }

    public function test_ignores_composer_json_without_require_key(): void
    {
        $dir = sys_get_temp_dir() . '/empty_composer_' . uniqid();
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/composer.json', json_encode(['name' => 'vendor/package']));
        chdir($dir);

        $result = ProjectFinder::findRoot();
        $this->assertIsString($result);
        $this->assertNotEmpty($result);

        $this->removeDir($dir);
    }

    public function test_falls_back_to_cwd_when_no_project_found(): void
    {
        $isolated = sys_get_temp_dir() . '/luany_isolated_' . uniqid();
        mkdir($isolated, 0755, true);
        chdir($isolated);

        $result = ProjectFinder::findRoot();

        $this->assertIsString($result);
        $this->assertNotEmpty($result);

        rmdir($isolated);
    }

    // ── isLuanyProject() ──────────────────────────────────────────────────────

    public function test_is_luany_project_returns_true_for_framework_dependency(): void
    {
        $this->assertTrue(ProjectFinder::isLuanyProject($this->projectRoot));
    }

    public function test_is_luany_project_returns_true_for_core_dependency(): void
    {
        $dir = sys_get_temp_dir() . '/luany_core_' . uniqid();
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/composer.json', json_encode([
            'require' => ['luany/core' => '^0.2'],
        ]));

        $this->assertTrue(ProjectFinder::isLuanyProject($dir));

        $this->removeDir($dir);
    }

    public function test_is_luany_project_returns_false_without_composer_json(): void
    {
        $dir = sys_get_temp_dir() . '/luany_empty_' . uniqid();
        mkdir($dir, 0755, true);

        $this->assertFalse(ProjectFinder::isLuanyProject($dir));

        rmdir($dir);
    }

    public function test_is_luany_project_returns_false_for_non_luany_project(): void
    {
        $dir = sys_get_temp_dir() . '/non_luany_' . uniqid();
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/composer.json', json_encode([
            'require' => ['laravel/framework' => '^11.0'],
        ]));

        $this->assertFalse(ProjectFinder::isLuanyProject($dir));

        $this->removeDir($dir);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function writeLuanyComposerJson(string $dir): void
    {
        file_put_contents($dir . '/composer.json', json_encode([
            'require' => ['luany/framework' => '^0.3'],
        ]));
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