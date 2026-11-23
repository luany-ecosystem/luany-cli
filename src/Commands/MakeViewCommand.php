<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
use LuanyCli\Env;
use LuanyCli\Support\Portuguese;
use LuanyCli\Output\CommandError;
use LuanyCli\Output\Output;
use LuanyCli\Output\StructuredCommand;
use LuanyCli\Support\NameValidator;

class MakeViewCommand extends BaseCommand implements StructuredCommand
{
    public function name(): string
    {
        return 'make:view';
    }

    public function description(): string
    {
        return 'Create a new LTE view  (types: page, component, layout)';
    }

    /** @param array<int, string> $args */
    public function handle(array $args): void
    {
        $name = $args[0] ?? null;
        $type = $args[1] ?? 'page'; // page | component | layout

        if (!$name) {
            throw CommandError::usage('make:view <name> [page|component|layout]', 'make:view components.card component');
        }

        $relativePath = implode('/', NameValidator::viewName($name)) . '.lte';
        $stub = match($type) {
            'component' => $this->componentStub(),
            'layout'    => $this->layoutStub(),
            default     => $this->pageStub(),
        };

        if (Portuguese::mode(Env::basePath())['views']) {
            $stub = Portuguese::directives(strtr($stub, [
                "'Page Title'"         => "'Título da página'",
                '<h1>Page Title</h1>'  => '<h1>Título da página</h1>',
                '// Page-level JS'     => '// JS da página',
                '{{-- component HTML --}}' => '{{-- HTML do componente --}}',
            ]));
        }

        Output::current()->createFile('views/' . $relativePath, $stub, 'view', "[{$name}]");
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

