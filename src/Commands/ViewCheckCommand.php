<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
use LuanyCli\Env;

/**
 * luany view:check
 *
 * Validates every .lte view of the project without rendering anything:
 * block structure (unclosed / stray / crossed directives, misplaced @else),
 * required directive arguments and the PHP syntax inside {{ }}, {!! !!},
 * @php and directive arguments (luany/lte >= 1.2).
 *
 * The application is booted when possible so directives registered by its
 * service providers are known; otherwise a plain engine is used.
 *
 * Options:
 *   --path=views        views directory, relative to the project root
 *   --format=github     GitHub Actions annotations (::error file=…,line=…)
 *   --no-app            do not boot the application
 *
 * Exit code 1 when any view has errors — suitable for CI.
 */
class ViewCheckCommand extends BaseCommand
{
    public function name(): string
    {
        return 'view:check';
    }

    public function description(): string
    {
        return 'Validate all LTE views (structure and PHP syntax) without rendering';
    }

    /** @param array<int, string> $args */
    public function handle(array $args): void
    {
        $options = $this->options($args);
        $base    = Env::basePath();
        $views   = $base . '/' . trim($options['path'], '/');

        $autoload = $base . '/vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }

        // Resolved at runtime: the project may ship an older luany/lte.
        $engineClass = $this->engineClass();
        if (!class_exists($engineClass) || !method_exists($engineClass, 'parseView')) {
            $this->fail('view:check needs luany/lte >= 1.2 in this project. Run: composer update luany/lte');
            return;
        }

        if (!is_dir($views)) {
            $this->fail("Views directory not found: {$options['path']}");
            return;
        }

        $engine = $this->engine($base, $views, !$options['no-app'], $options['format'] === 'github');
        $files  = $this->viewFiles($views);
        $errors = 0;

        if ($options['format'] !== 'github') {
            echo "\n  \033[33m→\033[0m  Checking " . count($files) . " view(s) in {$options['path']}/\n\n";
        }

        foreach ($files as $relative) {
            $name = str_replace('/', '.', substr($relative, 0, -4));

            try {
                $engine->parseView($name);
            } catch (\Luany\Lte\TemplateSyntaxException $e) {
                $errors++;
                $this->report($options, $options['path'] . '/' . $relative, $e);
            } catch (\Throwable $e) {
                $errors++;
                $this->report($options, $options['path'] . '/' . $relative, null, $e->getMessage());
            }
        }

        if ($options['format'] !== 'github') {
            if ($errors === 0) {
                echo "  \033[32m✓\033[0m  " . count($files) . " view(s) OK.\n\n";
            } else {
                echo "\n  \033[31m✗\033[0m  {$errors} of " . count($files) . " view(s) have errors.\n\n";
            }
        }

        if ($errors > 0) {
            $this->terminate(1);
        }
    }

    /**
     * Engine class name, checked at runtime against the project's luany/lte.
     */
    protected function engineClass(): string
    {
        return 'Luany\\Lte\\Engine';
    }

    /**
     * Exit hook (overridden in tests).
     */
    protected function terminate(int $code): void
    {
        exit($code);
    }

    /**
     * @param array{path: string, format: string, no-app: bool} $options
     */
    private function report(array $options, string $file, ?\Luany\Lte\TemplateSyntaxException $e, ?string $fallback = null): void
    {
        $line    = $e === null ? 0 : $e->templateLine;
        $column  = $e === null ? 0 : $e->templateColumn;
        $message = $e !== null ? $this->cleanMessage($e) : (string) $fallback;

        if ($options['format'] === 'github') {
            $props = "file={$file}" . ($line > 0 ? ",line={$line}" : '') . ($column > 0 ? ",col={$column}" : '');
            echo "::error {$props}::" . str_replace(["\r", "\n", '%'], ['%0D', '%0A', '%25'], $message) . "\n";
            return;
        }

        $position = $file . ($line > 0 ? ":{$line}" : '') . ($line > 0 && $column > 0 ? ":{$column}" : '');
        echo "  \033[31m✗\033[0m  \033[36m{$position}\033[0m\n     {$message}\n";
    }

    /**
     * Innermost message, without the "In view [x]:" / position suffix added
     * for exceptions (the position is printed separately).
     */
    private function cleanMessage(\Luany\Lte\TemplateSyntaxException $e): string
    {
        $inner = $e;
        while ($inner->getPrevious() instanceof \Luany\Lte\TemplateSyntaxException) {
            $inner = $inner->getPrevious();
        }

        return (string) preg_replace('/ \(line \d+(, column \d+)?\)\.$/', '.', $inner->getMessage());
    }

    /**
     * The application's view engine (knows custom directives), or a plain one.
     */
    private function engine(string $base, string $views, bool $boot, bool $quiet): object
    {
        if ($boot && file_exists($base . '/bootstrap/app.php')) {
            try {
                $level = ob_get_level();
                ob_start();
                try {
                    /** @var mixed $app */
                    $app = (static fn (string $file) => require $file)($base . '/bootstrap/app.php');
                    if (is_object($app) && method_exists($app, 'make')) {
                        // luany/framework is a dependency of the project, not of the CLI.
                        $kernelClass = 'Luany\\Framework\\Http\\Kernel';
                        $app->make($kernelClass)->boot();
                        $engine = $app->make('view');
                        if ($engine instanceof \Luany\Lte\Engine) {
                            return $engine;
                        }
                    }
                } finally {
                    while (ob_get_level() > $level) {
                        ob_end_clean();
                    }
                }
            } catch (\Throwable $e) {
                if (!$quiet) {
                    echo "  \033[33m⚠\033[0m  Could not boot the application ({$e->getMessage()}).\n"
                        . "     Checking without directives registered by your providers.\n";
                }
            }
        }

        return new \Luany\Lte\Engine($views, sys_get_temp_dir() . '/luany_view_check_' . md5($views));
    }

    /**
     * .lte files under $dir, relative paths with '/' separators, sorted.
     *
     * @return list<string>
     */
    private function viewFiles(string $dir): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'lte') {
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1));
                $files[]  = $relative;
            }
        }

        sort($files);
        return $files;
    }

    /**
     * @param array<int, string> $args
     * @return array{path: string, format: string, no-app: bool}
     */
    private function options(array $args): array
    {
        $options = ['path' => 'views', 'format' => 'text', 'no-app' => false];

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--path=')) {
                $path = substr($arg, 7);
                if ($path === '' || str_contains($path, '..')) {
                    throw new \InvalidArgumentException('Invalid --path: use a directory inside the project (e.g. --path=views).');
                }
                $options['path'] = $path;
            } elseif (str_starts_with($arg, '--format=')) {
                $format = substr($arg, 9);
                if (!in_array($format, ['text', 'github'], true)) {
                    throw new \InvalidArgumentException('Invalid --format: use text or github.');
                }
                $options['format'] = $format;
            } elseif ($arg === '--no-app') {
                $options['no-app'] = true;
            } else {
                throw new \InvalidArgumentException("Unknown option [{$arg}]. Usage: luany view:check [--path=views] [--format=github] [--no-app]");
            }
        }

        return $options;
    }

    private function fail(string $message): void
    {
        echo "\n  \033[31m✗\033[0m  {$message}\n\n";
        $this->terminate(1);
    }
}
