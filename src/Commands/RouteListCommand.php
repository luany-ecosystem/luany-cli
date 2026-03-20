<?php

namespace LuanyCli\Commands;

use LuanyCli\BaseCommand;
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
class RouteListCommand extends BaseCommand
{
    public function name(): string
    {
        return 'route:list';
    }

    public function description(): string
    {
        return 'List all registered application routes';
    }

    public function handle(array $args): void
    {
        $base = Env::basePath();

        // Load project autoload so Route class is available
        $autoload = $base . '/vendor/autoload.php';
        if (!file_exists($autoload)) {
            fwrite(STDERR, "\n  \033[31m✗\033[0m  vendor/autoload.php not found. Run: composer install\n\n");
            return;
        }
        require_once $autoload;

        // Load routes file
        $routesFile = $base . '/routes/http.php';
        if (!file_exists($routesFile)) {
            echo "\n  \033[33m→\033[0m  No routes file found at routes/http.php\n\n";
            return;
        }

        // Guard: Route class must exist
        if (!class_exists(\Luany\Core\Routing\Route::class)) {
            fwrite(STDERR, "\n  \033[31m✗\033[0m  Luany\\Core\\Routing\\Route not found in vendor.\n\n");
            return;
        }

        // Reset route state before loading
        \Luany\Core\Routing\Route::reset();

        // Suppress any output from the routes file itself
        ob_start();
        require $routesFile;
        ob_end_clean();

        $routes = \Luany\Core\Routing\Route::getRoutes();

        if (empty($routes)) {
            echo "\n  \033[33m→\033[0m  No routes registered.\n\n";
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

        echo "\n  " . count($routes) . " route(s) registered.\n\n";
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

    private function formatMethods(array $route): string
    {
        $methods = $route['methods'] ?? ($route['method'] ?? ['GET']);
        if (is_string($methods)) {
            $methods = [$methods];
        }
        return implode('|', array_map('strtoupper', $methods));
    }

    private function formatAction(array $route): string
    {
        $action = $route['action'] ?? ($route['controller'] ?? '');

        if (is_array($action)) {
            [$class, $method] = $action;
            $class = is_string($class) ? $class : get_class($class);
            // Strip namespace for brevity
            $short = substr($class, strrpos($class, '\\') + 1);
            return "{$short}@{$method}";
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
