<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$requestPath = is_string($requestPath) ? rawurldecode($requestPath) : '/';
$pageRoutes = 'about|admin|assessments|contact|dashboard|findings|login|news|pending|platform|profile|register|reports|sitemap';

if (preg_match('#^/(?:assets|screenshots)/[A-Za-z0-9._/-]+$#', $requestPath) === 1) {
    $staticPath = realpath($root . str_replace('/', DIRECTORY_SEPARATOR, $requestPath));
    $staticRoot = str_starts_with($requestPath, '/assets/')
        ? realpath($root . DIRECTORY_SEPARATOR . 'assets')
        : realpath($root . DIRECTORY_SEPARATOR . 'screenshots');
    if ($staticPath !== false
        && $staticRoot !== false
        && str_starts_with($staticPath, $staticRoot . DIRECTORY_SEPARATOR)
        && is_file($staticPath)
    ) {
        return false;
    }
}

if ($requestPath === '/' || preg_match('#^/(?:index(?:\.php|\.html)?)$#i', $requestPath) === 1) {
    $route = 'index.php';
} elseif (preg_match('#^/assets\.(?:php|html)$#i', $requestPath) === 1) {
    $route = 'assets.php';
} elseif (preg_match('#^/news/([a-z0-9][a-z0-9-]{0,179})/?$#i', $requestPath, $matches) === 1) {
    $_GET['slug'] = $matches[1];
    $route = 'news.php';
} elseif (preg_match('#^/(dashboard|reports)\.html$#i', $requestPath, $matches) === 1) {
    $route = strtolower($matches[1]) . '.php';
} elseif (preg_match('#^/(' . $pageRoutes . ')\.php$#i', $requestPath, $matches) === 1) {
    $route = strtolower($matches[1]) . '.php';
} elseif (preg_match('#^/(' . $pageRoutes . ')$#i', $requestPath, $matches) === 1) {
    $route = strtolower($matches[1]) . '.php';
} elseif ($requestPath === '/digital-assets') {
    $route = 'assets.php';
} elseif (preg_match(
    '#^/(about|admin|assessment-pdf|assessments|assets|contact|dashboard|findings|index|login|news|pending|platform|profile-image|profile|register|report-pdf|reports|sitemap)\.php$#i',
    $requestPath,
    $matches
) === 1) {
    $route = strtolower($matches[1]) . '.php';
} elseif (preg_match('#^/api(?:/.*)?$#', $requestPath) === 1) {
    $route = 'api/index.php';
    $_SERVER['SCRIPT_NAME'] = '/api/index.php';
} else {
    http_response_code(404);
    exit('Not found.');
}

$script = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR
    . str_replace('/', DIRECTORY_SEPARATOR, $route);
if (!is_file($script)) {
    http_response_code(404);
    exit('Not found.');
}

if (!str_starts_with($requestPath, '/api')) {
    $_SERVER['SCRIPT_NAME'] = $requestPath === '/' ? '/index.php' : $requestPath;
}
$_SERVER['SCRIPT_FILENAME'] = $script;
require $script;
