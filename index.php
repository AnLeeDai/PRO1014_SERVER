<?php
require_once __DIR__ . '/helper/Cors.php';

spl_autoload_register(function (string $className) {
    $baseDir = __DIR__ . '/';

    $classMap = [
        'Utils'         => $baseDir . 'helper/Utils.php',
        'JwtHelper'     => $baseDir . 'helper/JwtHelper.php',
        'AuthMiddleware' => $baseDir . 'helper/AuthMiddleware.php',
        'Database'      => $baseDir . 'config/Database.php',
    ];

    if (isset($classMap[$className]) && file_exists($classMap[$className])) {
        require_once $classMap[$className];
        return;
    }

    foreach (['app/controllers/', 'app/models/'] as $path) {
        $file = $baseDir . $path . $className . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }

    error_log("Autoload Error: {$className} not found");
});

try {
    $initAuthModel = new authmodel();

    if (!$initAuthModel->hasAdminAccount()) {
        $pwdHash = password_hash('Admin123!', PASSWORD_DEFAULT, ['cost' => 12]);
        if ($pwdHash === false) {
            throw new Exception('Hash password failed');
        }

        $adminId = $initAuthModel->createUser([
            'username'      => 'admin',
            'password'      => $pwdHash,
            'full_name'     => 'admin',
            'email'         => 'admin@localhost.local',
            'role'          => 'admin',
            'phone_number'  => '0334920373',
            'address'       => 'Nhân cầu 3, Thị Trấn Hưng Hà, Thái Bình',
            'avatar_url'    => 'https://picsum.photos/id/' . rand(1, 1000) . '/300',
        ]);

        if ($adminId === false) {
            throw new Exception('Create default admin failed');
        }
        error_log("[Auto Init] Default admin created (ID {$adminId})");
    }
} catch (Throwable $e) {
    error_log('[Auto Init] ' . $e->getMessage());
    Utils::respond(['error' => 'System initialization failed'], 500);
}

$request = trim($_GET['request'] ?? '');
$method  = strtoupper($_SERVER['REQUEST_METHOD']);

$routes = require_once __DIR__ . '/routes/api.php';

if (!isset($routes[$request])) {
    Utils::respond(['error' => 'Invalid API request (route not found)'], 404);
}

if (!isset($routes[$request][$method])) {
    $allowed = implode(', ', array_keys($routes[$request]));
    header("Allow: {$allowed}");
    Utils::respond(['error' => "Method Not Allowed. Allowed methods: {$allowed}"], 405);
}

[$controllerName, $action] = explode('@', $routes[$request][$method]);

if (!class_exists($controllerName)) {
    Utils::respond(['error' => 'Internal Server Error (controller missing)'], 500);
}

$controller = new $controllerName();

if (!method_exists($controller, $action)) {
    Utils::respond(['error' => 'Internal Server Error (method missing)'], 500);
}

try {
    $controller->$action();
} catch (Throwable $e) {
    error_log("Controller Error [{$controllerName}@{$action}]: " . $e->getMessage());
    Utils::respond(['success' => false, 'message' => 'Lỗi máy chủ nội bộ'], 500);
}
