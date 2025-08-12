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

// auto add new admin account if not exists
try {
    $initAuthModel = new AuthModel();

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
    Utils::respond(['success' => false, 'message' => 'Khởi tạo hệ thống thất bại'], 500);
}

// auto add new default category if not exists
try {
    // Initialize default admin account
    $initAuthModel = new AuthModel();

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

    // Initialize default categories
    $categoryModel = new CategoryModel();
    $defaultCategories = [
        'Điện thoại' => 'Các loại điện thoại thông minh',
        'Máy tính bảng' => 'Các loại máy tính bảng',
        'Laptop' => 'Các loại laptop và máy tính xách tay',
        'Phụ kiện' => 'Phụ kiện cho điện thoại, máy tính bảng, laptop',
    ];

    foreach ($defaultCategories as $name => $description) {
        if (!$categoryModel->findCategoryByName($name)) {
            $categoryId = $categoryModel->createCategory($name, $description);
            if ($categoryId === false) {
                error_log("[Auto Init] Failed to create default category '{$name}'");
            } else {
                error_log("[Auto Init] Default category '{$name}' created (ID {$categoryId})");
            }
        } else {
            error_log("[Auto Init] Default category '{$name}' already exists");
        }
    }
} catch (Throwable $e) {
    error_log('[Auto Init] ' . $e->getMessage());
    Utils::respond(['success' => false, 'message' => 'Khởi tạo hệ thống thất bại'], 500);
}

$request = trim($_GET['request'] ?? '');
$method  = strtoupper($_SERVER['REQUEST_METHOD']);

$routes = require_once __DIR__ . '/routes/api.php';

if (!isset($routes[$request])) {
    Utils::respond(['success' => false, 'message' => 'Yêu cầu API không hợp lệ (không tìm thấy route).'], 404);
}

if (!isset($routes[$request][$method])) {
    $allowed = implode(', ', array_keys($routes[$request]));
    header("Allow: {$allowed}");
    Utils::respond(['success' => false, 'message' => "Phương thức không được phép. Cho phép: {$allowed}"], 405);
}

[$controllerName, $action] = explode('@', $routes[$request][$method]);

if (!class_exists($controllerName)) {
    Utils::respond(['success' => false, 'message' => 'Lỗi máy chủ nội bộ (thiếu controller).'], 500);
}

$controller = new $controllerName();

if (!method_exists($controller, $action)) {
    Utils::respond(['success' => false, 'message' => 'Lỗi máy chủ nội bộ (thiếu method).'], 500);
}

try {
    $controller->$action();
} catch (Throwable $e) {
    error_log("Controller Error [{$controllerName}@{$action}]: " . $e->getMessage());
    Utils::respond(['success' => false, 'message' => 'Lỗi máy chủ nội bộ'], 500);
}
