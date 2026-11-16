<?php

/**
 * Router for PHP's built-in server (`luany serve`, `luany dev`).
 *
 * Without a router, the built-in server answers 404 itself for any URI that
 * looks like a file (has an extension) but does not exist — so routes such
 * as /sitemap.xml or /api/users.json never reached the application.
 *
 * Existing files under public/ are served as-is; everything else goes to
 * public/index.php, exactly like Apache/Nginx rewrite rules in production.
 */

$publicPath = rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/\\');
$uri        = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

if ($uri !== '/' && !str_contains($uri, "\0")) {
    $file = realpath($publicPath . $uri);
    $root = realpath($publicPath);

    if ($file !== false && $root !== false && is_file($file) && str_starts_with($file, $root . DIRECTORY_SEPARATOR)) {
        return false;   // let the built-in server send the static file
    }
}

$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['PHP_SELF']        = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $publicPath . DIRECTORY_SEPARATOR . 'index.php';

chdir($publicPath);

require $publicPath . DIRECTORY_SEPARATOR . 'index.php';
