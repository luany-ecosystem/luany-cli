<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
use LuanyCli\Env;

class MakeViewCommand extends BaseCommand
{
    public function name(): string
    {
        return 'make:view';
    }

    public function description(): string
    {
        return 'Create a new LTE view  (types: page, component, layout)';
    }

    public function handle(array $args): void
    {
        $name = $args[0] ?? null;
        $type = $args[1] ?? 'page'; // page | component | layout

        if (!$name) {
            fwrite(STDERR, "\n  \033[31m✗\033[0m  Usage: luany make:view <name> [page|component|layout]\n");
            fwrite(STDERR, "  Examples:\n");
            fwrite(STDERR, "    luany make:view pages.about\n");
            fwrite(STDERR, "    luany make:view components.card component\n");
            fwrite(STDERR, "    luany make:view layouts.admin layout\n\n");
            exit(1);
        }

        $relativePath = str_replace('.', DIRECTORY_SEPARATOR, $name) . '.lte';
        $fullPath     = Env::basePath() . '/views/' . $relativePath;
        $dir          = dirname($fullPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($fullPath)) {
            echo "\n  \033[33m⚠\033[0m  View [{$name}] already exists.\n\n";
            exit(0);
        }

        $stub = match($type) {
            'component' => $this->componentStub(),
            'layout'    => $this->layoutStub(),
            default     => $this->pageStub(),
        };

        file_put_contents($fullPath, $stub);
        echo "\n  \033[32m✓\033[0m  View created: views/{$relativePath} [{$type}]\n\n";
    }

private function pageStub(): string
{
    return <<<LTE
@extends('layouts.main')

@section('title', 'Page Title')

@push('head')
    {{-- SEO meta, external links --}}
@endpush

@style
    .page {
        padding: 2rem 0;
    }
@endstyle

@section('content')
    <div class="page">
        <h1>Page Title</h1>
    </div>
@endsection

@script(defer)
    // Page-level JS
@endscript
LTE;
}

private function componentStub(): string
{
    return <<<LTE
@style
    .component {
        /* component styles — deduplicated automatically */
    }
@endstyle

<div class="component">
    {{-- component HTML --}}
</div>

@script(defer)
    // Component JS — deduplicated automatically
@endscript
LTE;
}

private function layoutStub(): string
{
    return <<<LTE
<!DOCTYPE html>
<html lang="{{ locale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', env('APP_NAME', 'Luany'))</title>
    @stack('head')
</head>
<body>

    <main>
        @yield('content')
    </main>

    @styles
    @scripts
    @stack('scripts')

</body>
</html>
LTE;
}
}