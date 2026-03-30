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
require APP_ROOT . '/src/Controllers/AuthController.php';
require APP_ROOT . '/src/Controllers/DashboardController.php';
require APP_ROOT . '/src/Controllers/PortController.php';
require APP_ROOT . '/src/Controllers/DeviceController.php';

// ── View renderer ─────────────────────────────────────────────────────────────
/**
 * Renders a template wrapped in the layout.
 *
 * The template may set $title via a plain assignment; that variable will be
 * available to layout.php after the template executes (same function scope).
 */
function render(string $template, array $data = []): void
{
    extract($data, EXTR_SKIP);
    ob_start();
    require APP_ROOT . '/templates/' . $template . '.php';
    $content = ob_get_clean();
    require APP_ROOT . '/templates/layout.php';
}

/**
 * Escape a value for safe HTML output.
 */
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
    header('Location: /login');
    exit;
}

// ── Authenticated models ──────────────────────────────────────────────────────
$portModel   = new PortModel($db);
$deviceModel = new DeviceModel($db);

// ── Router ────────────────────────────────────────────────────────────────────
switch (true) {
    // Dashboard
    case $path === '/':
        (new DashboardController($portModel, $deviceModel))->index();
        break;

    // ── Switch Ports ──────────────────────────────────────────────────────────
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
