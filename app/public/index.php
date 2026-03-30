<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

// ── Autoload helpers, models, controllers ────────────────────────────────────
require APP_ROOT . '/src/Helpers/Database.php';
require APP_ROOT . '/src/Helpers/Session.php';
require APP_ROOT . '/src/Helpers/Auth.php';
require APP_ROOT . '/src/Helpers/Csrf.php';
require APP_ROOT . '/src/Models/PortModel.php';
require APP_ROOT . '/src/Models/DeviceModel.php';
require APP_ROOT . '/src/Models/ConnectionModel.php';
require APP_ROOT . '/src/Controllers/AuthController.php';
require APP_ROOT . '/src/Controllers/DashboardController.php';
require APP_ROOT . '/src/Controllers/PortController.php';
require APP_ROOT . '/src/Controllers/DeviceController.php';
require APP_ROOT . '/src/Controllers/ApiController.php';

// ── View renderer ─────────────────────────────────────────────────────────────
function render(string $template, array $data = []): void
{
    extract($data, EXTR_SKIP);
    ob_start();
    require APP_ROOT . '/templates/' . $template . '.php';
    $content = ob_get_clean();
    require APP_ROOT . '/templates/layout.php';
}

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// ── Bootstrap ─────────────────────────────────────────────────────────────────
Session::start();

$method = $_SERVER['REQUEST_METHOD'];
$path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path   = rtrim($path, '/') ?: '/';

$db   = new Database();
$auth = new Auth($db);

// ── Public routes (no auth required) ─────────────────────────────────────────
if ($path === '/login') {
    $ctrl = new AuthController($auth);
    if ($method === 'POST') {
        $ctrl->login();
    } else {
        $ctrl->showLogin();
    }
    exit;
}

if ($path === '/logout') {
    (new AuthController($auth))->logout();
    exit;
}

// ── Auth gate ─────────────────────────────────────────────────────────────────
if (!$auth->check()) {
    // API requests get JSON 401 instead of a redirect
    if (str_starts_with($path, '/api/')) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unauthenticated']);
        exit;
    }
    header('Location: /login');
    exit;
}

// ── Authenticated models ──────────────────────────────────────────────────────
$portModel       = new PortModel($db);
$deviceModel     = new DeviceModel($db);
$connectionModel = new ConnectionModel($db);

// ── Router ────────────────────────────────────────────────────────────────────
switch (true) {

    // ── Dashboard ─────────────────────────────────────────────────────────────
    case $path === '/':
        (new DashboardController($portModel, $deviceModel))->index();
        break;

    // ── JSON API (must be before HTML routes to avoid regex conflicts) ────────
    case $path === '/api/ports' && $method === 'GET':
        (new ApiController($portModel, $deviceModel))->listPorts();
        break;

    case $path === '/api/ports/unassigned' && $method === 'GET':
        (new ApiController($portModel, $deviceModel))->listUnassignedPorts();
        break;

    case $path === '/api/devices' && $method === 'GET':
        (new ApiController($portModel, $deviceModel))->listDevices();
        break;

    case $path === '/api/connections' && $method === 'GET':
        (new ApiController($portModel, $deviceModel, $connectionModel))->listConnections();
        break;

    case $path === '/api/connections' && $method === 'POST':
        (new ApiController($portModel, $deviceModel, $connectionModel))->createConnection();
        break;

    case preg_match('#^/api/connections/(\d+)$#', $path, $m) && $method === 'DELETE':
        (new ApiController($portModel, $deviceModel, $connectionModel))->deleteConnection((int) $m[1]);
        break;

    case $path === '/api/devices/reorder' && $method === 'PATCH':
        (new ApiController($portModel, $deviceModel))->reorderDevices();
        break;

    case preg_match('#^/api/devices/(\d+)/ports$#', $path, $m) && $method === 'GET':
        (new ApiController($portModel, $deviceModel))->listDevicePorts((int) $m[1]);
        break;

    case preg_match('#^/api/devices/(\d+)/panel$#', $path, $m) && $method === 'PATCH':
        (new ApiController($portModel, $deviceModel))->updateDevicePanel((int) $m[1]);
        break;

    case $path === '/api/ports' && $method === 'POST':
        (new ApiController($portModel, $deviceModel))->createPort();
        break;

    // Specific sub-resource routes before the bare /{id} pattern
    case preg_match('#^/api/ports/(\d+)/position$#', $path, $m) && $method === 'PATCH':
        (new ApiController($portModel, $deviceModel))->movePort((int) $m[1]);
        break;

    case preg_match('#^/api/ports/(\d+)/assign$#', $path, $m) && $method === 'PATCH':
        (new ApiController($portModel, $deviceModel))->assignPort((int) $m[1]);
        break;

    case preg_match('#^/api/ports/(\d+)$#', $path, $m) && $method === 'PATCH':
        (new ApiController($portModel, $deviceModel))->updatePort((int) $m[1]);
        break;

    case preg_match('#^/api/ports/(\d+)$#', $path, $m) && $method === 'DELETE':
        (new ApiController($portModel, $deviceModel))->deletePort((int) $m[1]);
        break;

    // ── Switch Ports ──────────────────────────────────────────────────────────
    // /panel and /new must be matched before /{id} patterns
    case $path === '/ports/panel' && $method === 'GET':
        (new PortController($portModel, $deviceModel))->panel();
        break;

    case $path === '/ports' && $method === 'GET':
        (new PortController($portModel, $deviceModel))->index();
        break;

    case $path === '/ports/new' && $method === 'GET':
        (new PortController($portModel, $deviceModel))->create();
        break;

    case $path === '/ports' && $method === 'POST':
        (new PortController($portModel, $deviceModel))->store();
        break;

    case preg_match('#^/ports/(\d+)/edit$#', $path, $m) && $method === 'GET':
        (new PortController($portModel, $deviceModel))->edit((int) $m[1]);
        break;

    case preg_match('#^/ports/(\d+)/edit$#', $path, $m) && $method === 'POST':
        (new PortController($portModel, $deviceModel))->update((int) $m[1]);
        break;

    case preg_match('#^/ports/(\d+)/delete$#', $path, $m) && $method === 'POST':
        (new PortController($portModel, $deviceModel))->delete((int) $m[1]);
        break;

    // ── Devices ───────────────────────────────────────────────────────────────
    case $path === '/devices' && $method === 'GET':
        (new DeviceController($deviceModel, $portModel))->index();
        break;

    case $path === '/devices/new' && $method === 'GET':
        (new DeviceController($deviceModel, $portModel))->create();
        break;

    case $path === '/devices' && $method === 'POST':
        (new DeviceController($deviceModel, $portModel))->store();
        break;

    // Device sub-resource routes before the bare /{id} pattern
    case preg_match('#^/devices/(\d+)/ports/panel$#', $path, $m) && $method === 'GET':
        (new DeviceController($deviceModel, $portModel))->portPanel((int) $m[1]);
        break;

    case preg_match('#^/devices/(\d+)/ports/assign$#', $path, $m) && $method === 'POST':
        (new DeviceController($deviceModel, $portModel))->assignPortForm((int) $m[1]);
        break;

    case preg_match('#^/devices/(\d+)$#', $path, $m) && $method === 'GET':
        (new DeviceController($deviceModel, $portModel))->show((int) $m[1]);
        break;

    case preg_match('#^/devices/(\d+)/edit$#', $path, $m) && $method === 'GET':
        (new DeviceController($deviceModel, $portModel))->edit((int) $m[1]);
        break;

    case preg_match('#^/devices/(\d+)/edit$#', $path, $m) && $method === 'POST':
        (new DeviceController($deviceModel, $portModel))->update((int) $m[1]);
        break;

    case preg_match('#^/devices/(\d+)/delete$#', $path, $m) && $method === 'POST':
        (new DeviceController($deviceModel, $portModel))->delete((int) $m[1]);
        break;

    // ── IP Assignments ────────────────────────────────────────────────────────
    case preg_match('#^/devices/(\d+)/ips$#', $path, $m) && $method === 'POST':
        (new DeviceController($deviceModel, $portModel))->addIp((int) $m[1]);
        break;

    case preg_match('#^/ips/(\d+)/delete$#', $path, $m) && $method === 'POST':
        (new DeviceController($deviceModel, $portModel))->deleteIp((int) $m[1]);
        break;

    // ── Switch Port Assignment / Unassignment ─────────────────────────────────
    case preg_match('#^/ports/(\d+)/unassign$#', $path, $m) && $method === 'POST':
        (new PortController($portModel, $deviceModel))->unassign((int) $m[1]);
        break;

    // ── Service Ports ─────────────────────────────────────────────────────────
    case preg_match('#^/devices/(\d+)/services$#', $path, $m) && $method === 'POST':
        (new DeviceController($deviceModel, $portModel))->addService((int) $m[1]);
        break;

    case preg_match('#^/services/(\d+)/delete$#', $path, $m) && $method === 'POST':
        (new DeviceController($deviceModel, $portModel))->deleteService((int) $m[1]);
        break;

    // ── 404 ───────────────────────────────────────────────────────────────────
    default:
        http_response_code(404);
        echo '<h1>404 Not Found</h1>';
        break;
}
