<?php

namespace LuanyCli\Tests;

use PHPUnit\Framework\TestCase;

class ComposerConfigTest extends TestCase
{
    /** @return array<string, mixed> */
    private function composer(): array
    {
        $json = file_get_contents(__DIR__ . '/../composer.json');
        $this->assertNotFalse($json);

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_composer_scripts_reference_existing_files(): void
    {
        $scripts = $this->composer()['scripts'] ?? [];
        $root    = dirname(__DIR__);

        foreach ($scripts as $event => $commands) {
            foreach ((array) $commands as $command) {
                if (preg_match('/^@php\s+(\S+)/', (string) $command, $m)) {
                    $this->assertFileExists($root . '/' . $m[1], "Script [{$event}] references a missing file: {$m[1]}");
                }
            }
        }

        $this->assertTrue(true);
    }

    public function test_declared_binaries_exist(): void
    {
        foreach ($this->composer()['bin'] ?? [] as $bin) {
            $this->assertFileExists(dirname(__DIR__) . '/' . $bin);
        }
    }
}
