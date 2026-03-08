<?php

namespace LuanyCli\Tests\Commands;

use LuanyCli\Commands\MakeViewCommand;
use PHPUnit\Framework\TestCase;
use LuanyCli\Env;

class MakeViewCommandTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/luany_cli_test_' . uniqid();
        mkdir($this->baseDir . '/views', 0755, true);
        Env::setBasePath($this->baseDir);
    }

    protected function tearDown(): void
    {
        Env::reset();
        $this->removeDir($this->baseDir);
    }

    public function test_creates_page_view(): void
    {
        $command = new MakeViewCommand();
        $command->handle(['pages.about']);

        $this->assertFileExists($this->baseDir . '/views/pages/about.lte');
    }

    public function test_page_view_extends_layout(): void
    {
        $command = new MakeViewCommand();
        $command->handle(['pages.contact']);

        $content = file_get_contents($this->baseDir . '/views/pages/contact.lte');
        $this->assertStringContainsString("@extends('layouts.main')", $content);
    }

    public function test_creates_component_view(): void
    {
        $command = new MakeViewCommand();
        $command->handle(['components.card', 'component']);

        $this->assertFileExists($this->baseDir . '/views/components/card.lte');
    }

    public function test_component_does_not_extend_layout(): void
    {
        $command = new MakeViewCommand();
        $command->handle(['components.alert', 'component']);

        $content = file_get_contents($this->baseDir . '/views/components/alert.lte');
        $this->assertStringNotContainsString('@extends', $content);
    }

    public function test_component_has_style_block(): void
    {
        $command = new MakeViewCommand();
        $command->handle(['components.button', 'component']);

        $content = file_get_contents($this->baseDir . '/views/components/button.lte');
        $this->assertStringContainsString('@style', $content);
        $this->assertStringContainsString('@endstyle', $content);
    }

    public function test_creates_layout_view(): void
    {
        $command = new MakeViewCommand();
        $command->handle(['layouts.admin', 'layout']);

        $this->assertFileExists($this->baseDir . '/views/layouts/admin.lte');
    }

    public function test_layout_contains_yield_content(): void
    {
        $command = new MakeViewCommand();
        $command->handle(['layouts.app', 'layout']);

        $content = file_get_contents($this->baseDir . '/views/layouts/app.lte');
        $this->assertStringContainsString("@yield('content')", $content);
        $this->assertStringContainsString('@styles', $content);
        $this->assertStringContainsString('@scripts', $content);
    }

    public function test_dot_notation_creates_subdirectory(): void
    {
        $command = new MakeViewCommand();
        $command->handle(['pages.auth.login']);

        $this->assertFileExists($this->baseDir . '/views/pages/auth/login.lte');
    }

    public function test_command_name(): void
    {
        $this->assertSame('make:view', (new MakeViewCommand())->name());
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