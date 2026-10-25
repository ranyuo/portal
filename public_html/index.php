<?php
declare(strict_types=1);
require dirname(__DIR__).'/portal-private/bootstrap.php';
portal_headers();
$route = is_string($_GET['route'] ?? null) ? trim($_GET['route'], '/') : '';
try {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (!in_array($path, ['/', '/index.php'], true)) throw new PortalError(404, 'Página no encontrada.');
    $app = portal_app();
    if ($route !== '') {
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method !== 'GET') {
            $app->checkCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        }
        $input = $method === 'GET' ? [] : $app->input();
        if ($route === 'api/portal' && $method === 'GET') portal_json($app->bootstrap());
        if ($route === 'api/portal' && $method === 'PUT') portal_json($app->save($input));
        if ($route === 'api/session' && $method === 'POST') portal_json($app->login($input));
        if ($route === 'api/session' && $method === 'DELETE') portal_json($app->logout());
        if (preg_match('#^api/solicitudes/([a-z]+)$#', $route, $match) && $method === 'POST') {
            $result = $app->submit($match[1], $input);
            $status = $result['status']; unset($result['status']);
            portal_json($result, $status);
        }
        if (preg_match('#^api/consultas/([a-z]+)$#', $route, $match) && $method === 'POST') portal_json($app->lookup($match[1], $input));
        throw new PortalError(404, 'Ruta no encontrada.');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') throw new PortalError(405, 'Método no permitido.');
    $bootstrap = $app->bootstrap();
    header('Content-Type: text/html; charset=utf-8');
    require dirname(__DIR__).'/portal-private/views/portal.php';
} catch (Throwable $error) {
    portal_error($error, $route !== '');
}
