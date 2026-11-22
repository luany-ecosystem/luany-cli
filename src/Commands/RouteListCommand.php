<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
use LuanyCli\Output\CommandError;
use LuanyCli\Output\Output;
use LuanyCli\Output\StructuredCommand;
use LuanyCli\Env;

/**
 * RouteListCommand
 *
 * Displays all registered routes in a formatted table.
 * Loads the project's routes/http.php file in isolation to collect
 * the registered routes without booting the full framework.
 *
 * Uses Luany\Core\Routing\Route::getRoutes() to retrieve the route list.
 */
class RouteListCommand extends BaseCommand implements StructuredCommand
{
    public function name(): string
    {
        return 'route:list';
    }

    public function description(): string
    {
        return 'List all registered application routes';
    }

    /** @param array<int, string> $args */
    public function handle(array $args): void
    {
        $base = Env::basePath();
        $out  = Output::current();

        // Load project autoload so Route class is available
        $autoload = $base . '/vendor/autoload.php';
        if (!file_exists($autoload)) {
            throw new CommandError('LUANY-ROUTE-001', [], 'composer install');
        }
        require_once $autoload;

        // Load routes file
        $routesDir = $base . '/routes';
        if (!is_dir($routesDir)) {
            $out->data(['routes' => []]);
            $out->say('LUANY-ROUTE-003', [], 'info');
            return;
        }

        // Guard: Route class must exist
        if (!class_exists(\Luany\Core\Routing\Route::class)) {
            throw new CommandError('LUANY-ROUTE-002');
        }

        // Reset route state before loading
        \Luany\Core\Routing\Route::reset();

        // Load http.php first, then all other *.php files alphabetically
        $files = glob($routesDir . '/*.php') ?: [];
        usort($files, function (string $a, string $b): int {
            if (basename($a) === 'http.php') return -1;
            if (basename($b) === 'http.php') return 1;
            return strcmp($a, $b);
        });

        foreach ($files as $file) {
            ob_start();
            require $file;
            ob_end_clean();
        }

        $routes = \Luany\Core\Routing\Route::getRoutes();

        $out->data(['routes' => array_map(fn (array $r): array => [
            'methods' => explode('|', $this->formatMethods($r)),
            'uri'     => (string) ($r['uri'] ?? ''),
            'action'  => $this->formatAction($r, short: false),
            'name'    => $r['name'] ?? null,
        ], array_values($routes))]);

        if (empty($routes)) {
            $out->say('LUANY-ROUTE-004', [], 'info');
            return;
        }

        if ($out->json) {
            return;
        }

        // Calculate column widths
        $methodWidth = max(6, ...array_map(fn($r) => strlen($this->formatMethods($r)), $routes));
        $uriWidth    = max(3, ...array_map(fn($r) => strlen($r['uri'] ?? ''), $routes));
        $nameWidth   = max(4, ...array_map(fn($r) => strlen($r['name'] ?? ''), $routes));
        $actionWidth = max(6, ...array_map(fn($r) => strlen($this->formatAction($r)), $routes));

        // Cap columns to reasonable widths
        $uriWidth    = min($uriWidth, 50);
        $actionWidth = min($actionWidth, 60);
        $nameWidth   = min($nameWidth, 30);

        $this->printHeader($methodWidth, $uriWidth, $actionWidth, $nameWidth);

        foreach ($routes as $route) {
            $this->printRoute($route, $methodWidth, $uriWidth, $actionWidth, $nameWidth);
        }

        echo "\n  " . $out->text->text('LUANY-ROUTE-005', ['count' => count($routes)]) . "\n\n";
    }

    private function printHeader(int $mw, int $uw, int $aw, int $nw): void
    {
        echo "\n";
        echo '  ' . str_pad('Method', $mw) . '  '
           . str_pad('URI', $uw) . '  '
           . str_pad('Action', $aw) . '  '
           . str_pad('Name', $nw) . "\n";
        echo '  ' . str_repeat('─', $mw) . '  '
           . str_repeat('─', $uw) . '  '
           . str_repeat('─', $aw) . '  '
           . str_repeat('─', $nw) . "\n";
    }

    /** @param array<string, mixed> $route */
    private function printRoute(array $route, int $mw, int $uw, int $aw, int $nw): void
    {
        $methods = $this->formatMethods($route);
        $uri     = $route['uri'] ?? '';
        $action  = $this->formatAction($route);
        $name    = $route['name'] ?? '';

        // Colour-code by method
        $coloured = $this->colourMethod($methods);

        // Truncate long values
        $uri    = $this->truncate($uri, $uw);
        $action = $this->truncate($action, $aw);
        $name   = $this->truncate($name, $nw);

        echo '  ' . $coloured . str_repeat(' ', max(0, $mw - strlen($methods))) . '  '
           . str_pad($uri, $uw) . '  '
           . str_pad($action, $aw) . '  '
           . str_pad($name, $nw) . "\n";
    }

    /** @param array<string, mixed> $route */
    private function formatMethods(array $route): string
    {
        $methods = $route['methods'] ?? ($route['method'] ?? ['GET']);
        if (is_string($methods)) {
            $methods = [$methods];
        }
        return implode('|', array_map('strtoupper', $methods));
    }

    /** @param array<string, mixed> $route */
    private function formatAction(array $route, bool $short = true): string
    {
        $action = $route['action'] ?? ($route['controller'] ?? '');

        if (is_array($action)) {
            [$class, $method] = $action;
            $class = is_string($class) ? $class : get_class($class);
            // Strip namespace for brevity in the table
            $name = $short ? substr((string) strrchr('\\' . $class, '\\'), 1) : $class;
            return "{$name}@{$method}";
        }

        if ($action instanceof \Closure || is_callable($action)) {
            return 'Closure';
        }

        return (string) $action;
    }

    private function colourMethod(string $methods): string
    {
        $colour = match (true) {
            str_contains($methods, 'GET')    => "\033[32m",   // green
            str_contains($methods, 'POST')   => "\033[33m",   // yellow
            str_contains($methods, 'PUT'),
            str_contains($methods, 'PATCH')  => "\033[34m",   // blue
            str_contains($methods, 'DELETE') => "\033[31m",   // red
            default                          => "\033[0m",
        };
        return $colour . $methods . "\033[0m";
    }

    private function truncate(string $value, int $max): string
    {
        if (strlen($value) <= $max) {
            return $value;
        }
        return substr($value, 0, $max - 1) . '…';
    }
}
