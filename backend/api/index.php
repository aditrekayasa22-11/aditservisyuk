<?php
declare(strict_types=1);

/**
 * Router REST API AutoVance.
 *
 * Dipanggil sebagai: backend/api/index.php/<endpoint>?param=...
 * Fallback bila PATH_INFO tidak tersedia: backend/api/index.php?path=/endpoint
 */

require_once __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['PATH_INFO'] ?? ($_GET['path'] ?? '/');
$path = '/' . trim($path, '/');

$routes = require __DIR__ . '/routes.php';

header('X-Content-Type-Options: nosniff');

if ($method === 'OPTIONS') {
    header('Allow: ' . implode(', ', array_unique(array_column($routes, 0))));
    api_json(['success' => true]);
}

foreach ($routes as $route) {
    [$routeMethod, $pattern, $controller, $handler, $requiresAuth] = $route;
    if ($routeMethod !== $method) {
        continue;
    }
    $regex = '#^' . preg_replace('#\{id\}#', '(?P<id>\d+)', $pattern) . '$#';
    if (!preg_match($regex, $path, $matches)) {
        continue;
    }

    if ($requiresAuth) {
        require_admin_api();
    }
    if (!in_array($method, ['GET', 'HEAD'], true)) {
        require_csrf_api();
    }

    $params = array_merge($_GET, array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));

    require_once __DIR__ . '/controllers/' . $controller . '.php';

    try {
        api_ok($handler($params));
    } catch (PDOException $exception) {
        api_fail_db($exception);
    } catch (Throwable $exception) {
        error_log('[AutoVance API] ' . $exception->getMessage());
        api_fail('Terjadi kesalahan tak terduga di server.', 500, 'server_error');
    }
}

api_fail('Endpoint tidak ditemukan.', 404, 'not_found');
