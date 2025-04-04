<?php
// --- 1. CORS ---
require_once __DIR__ . "/../helper/cors.php";

// --- (Session đã được comment out - OK nếu dùng JWT) ---
//session_set_cookie_params([...]);
//session_name('HTTPSESSION');
//session_start();

spl_autoload_register(function ($class) {
    $paths = [
        'controller' => __DIR__ . "/../app/controllers/" . $class . ".php",
        'model' => __DIR__ . "/../app/models/" . $class . ".php",
        'helper_utils' => __DIR__ . "/../helper/utils.php",
        'helper_jwt' => __DIR__ . "/../helper/jwt_helper.php",
    ];

    if ($class === 'Utils' && file_exists($paths['helper_utils'])) {
        require_once $paths['helper_utils'];
        return;
    }

    if ($class === 'JwtHelper' && file_exists($paths['helper_jwt'])) {
        require_once $paths['helper_jwt'];
        return;
    }

    if (file_exists($paths['controller'])) {
        require_once $paths['controller'];
    } elseif (file_exists($paths['model'])) {
        require_once $paths['model'];
    }
});

// --- KHỞI TẠO ADMIN MẶC ĐỊNH (NẾU CHƯA CÓ) ---
try {
    $initAuthModel = new AuthModel();

    // Kiểm tra xem có admin chưa
    if (!$initAuthModel->hasAdminAccount()) {
        error_log("[Auto Init] No admin account found. Attempting to create default admin...");

        $defaultAdminUsername = getenv('DEFAULT_ADMIN_USERNAME') ?: 'admin';
        $defaultAdminFullName = getenv('DEFAULT_ADMIN_FULLNAME') ?: 'admin';
        $defaultAdminPassword = getenv('DEFAULT_ADMIN_PASSWORD') ?: 'Admin@123!';
        $defaultAdminEmail = getenv('DEFAULT_ADMIN_EMAIL') ?: 'admin@localhost.local';

        if (!getenv('DEFAULT_ADMIN_PASSWORD')) {
            error_log("[Auto Init] WARNING: DEFAULT_ADMIN_PASSWORD environment variable is not set. Using an insecure default or potentially failing.");
        }

        // Hash mật khẩu
        $hashedPassword = password_hash($defaultAdminPassword, PASSWORD_DEFAULT, ['cost' => 12]);

        if ($hashedPassword === false) {
            error_log("[Auto Init] CRITICAL: Failed to hash default admin password.");
        } else {
            // Chuẩn bị dữ liệu
            $adminData = [
                'username' => $defaultAdminUsername,
                'password' => $hashedPassword,
                'full_name' => $defaultAdminFullName,
                'email' => $defaultAdminEmail,
                'role' => 'admin',
                'phone_number' => null,
                'address' => null,
                'avatar_url' => 'https://avatar.iran.liara.run/public/admin?username=' . $defaultAdminUsername,
            ];

            // Tạo admin user thông qua model
            $adminId = $initAuthModel->createUser($adminData);

            if ($adminId !== false) {
                error_log("[Auto Init] Default admin account '{$defaultAdminUsername}' created successfully with ID: {$adminId}.");
                // Nhắc nhở đổi mật khẩu mặc định nếu nó yếu hoặc được công khai
                if ($defaultAdminPassword === 'Admin@123!') {
                    error_log("[Auto Init] SECURITY WARNING: Default admin password is weak or known. Please change it immediately!");
                }
            } else {
                error_log("[Auto Init] CRITICAL: Failed to create default admin account '{$defaultAdminUsername}'. Check database connection, permissions, and AuthModel::createUser logs.");
            }
        }
    }
    // Giải phóng biến, không cần nữa trong luồng request chính
    unset($initAuthModel);

} catch (\Throwable $e) {
    error_log("[Auto Init] CRITICAL ERROR during initial admin check/creation: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "System initialization failed."]);
    exit();
}

$request = $_GET['request'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

$routes = [
    // Authentication routes
    "post-login" => ["POST" => "AuthController@handleLogin"],
    "post-register" => ["POST" => "AuthController@handleRegister"],
];

// Kiểm tra route hợp lệ
if (isset($routes[$request])) {
    if (isset($routes[$request][$method])) {
        list($controllerName, $methodName) = explode("@", $routes[$request][$method]);

        // Kiểm tra controller tồn tại trước khi gọi (Autoloader đã chạy)
        if (class_exists($controllerName)) {
            $controller = new $controllerName();

            if (method_exists($controller, $methodName)) {
                try {
                    $controller->$methodName();
                } catch (\Throwable $e) {
                    // Xử lý lỗi không mong muốn từ Controller
                    error_log("Controller Execution Error [{$controllerName}@{$methodName}]: " . $e->getMessage() . "\n" . $e->getTraceAsString());
                    Utils::respond(['success' => false, 'message' => 'Lỗi máy chủ nội bộ.'], 500);
                }
            } else {
                // Lỗi 500: Method không tồn tại trong Controller (lỗi cấu hình route)
                error_log("Routing Error: Method '{$methodName}' not found in controller '{$controllerName}'. Check routes definition.");
                http_response_code(500);
                echo json_encode(["error" => "Internal Server Error (Invalid method configuration)"]);
            }
        } else {
            // Lỗi 500: Controller không tồn tại (lỗi cấu hình route hoặc autoloader)
            error_log("Routing Error: Controller class '{$controllerName}' not found. Check routes definition and autoloader.");
            http_response_code(500);
            echo json_encode(["error" => "Internal Server Error (Invalid controller configuration)"]);
        }
    } else {
        // Lỗi 405: Tìm thấy route key nhưng sai phương thức HTTP
        http_response_code(405);
        // Lấy danh sách các method được phép cho route này
        $allowedMethods = array_keys($routes[$request]);
        header('Allow: ' . implode(', ', $allowedMethods));
        echo json_encode(["error" => "Method Not Allowed. Allowed methods: " . implode(', ', $allowedMethods)]);
    }
    exit();
}

// Lỗi 404: Không khớp route key nào
http_response_code(404);
echo json_encode(["error" => "Invalid API request (Route key not found)"]);
exit();

