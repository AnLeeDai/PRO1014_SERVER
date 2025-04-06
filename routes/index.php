<?php
require_once __DIR__ . "/../helper/Cors.php";

// --- (Session đã được comment out - OK nếu dùng JWT) ---
//session_set_cookie_params([...]);
//session_name('HTTPSESSION');
//session_start();

spl_autoload_register(function (string $className) {
    $baseDir = __DIR__ . '/../';

    $classMap = [
        'Utils' => $baseDir . 'helper/Utils.php',
        'JwtHelper' => $baseDir . 'helper/JwtHelper.php',
        'AuthMiddleware' => $baseDir . 'helper/AuthMiddleware.php',
        'Database' => $baseDir . 'config/Database.php',
    ];

    if (isset($classMap[$className])) {
        $filePath = $classMap[$className];
        if (file_exists($filePath)) {
            require_once $filePath;
            return;
        } else {
            error_log("Autoload Error: Mapped file not found for class {$className} at {$filePath}");
        }
    }

    $controllerPath = $baseDir . 'app/controllers/' . $className . '.php';
    if (file_exists($controllerPath)) {
        require_once $controllerPath;
        return;
    }

    $modelPath = $baseDir . 'app/models/' . $className . '.php';
    if (file_exists($modelPath)) {
        require_once $modelPath;
        return;
    }

    error_log("Autoload Error: Class '{$className}' could not be found by the autoloader.");
});

// --- KHỞI TẠO ADMIN MẶC ĐỊNH (NẾU CHƯA CÓ) ---
try {
    $initAuthModel = new authmodel();

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

        $avatar_url = 'https://picsum.photos/id/' . rand(1, 1000) . '/300';

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
                'phone_number' => '0334920373',
                'address' => 'Nhân cầu 3, Thị Trấn Hưng Hà, Thái Bình',
                'avatar_url' => $avatar_url,
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
                error_log("[Auto Init] CRITICAL: Failed to create default admin account '{$defaultAdminUsername}'. Check database connection, permissions, and authmodel::createUser logs.");
            }
        }
    }

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
    // Authentication
    "post-login" => ["POST" => "AuthController@handleLogin"],
    "post-register" => ["POST" => "AuthController@handleRegister"],
    "post-forgot-password" => ["POST" => "AuthController@handleForgotPassword"],
    "post-change-password" => ["POST" => "AuthController@handleChangePassword"],
    "get-admin-password-requests" => ["GET" => "AuthController@listPendingPasswordRequests"],
    "post-admin-process-password-request" => ["POST" => "AuthController@handleAdminPasswordRequestAction"],

    // Categories
    "get-category" => ["GET" => "CategoryController@handleListCategories"],
    "post-category" => ["POST" => "CategoryController@handleCreateCategory"],
    "put-category" => ["PUT" => "CategoryController@handleUpdateCategory"],
    "post-hide-category" => ["POST" => "CategoryController@handleHideCategory"],
    "post-unhide-category" => ["POST" => "CategoryController@handleUnhideCategory"],

    // Users
    "get-users" => ["GET" => "UserController@handleListUsers"],
    "get-user-by-id" => ["GET" => "UserController@handleGetUserById"],
    "put-user" => ["PUT" => "UserController@handleUpdateUserProfile"],
    "post-avatar" => ["POST" => "UserController@handleUpdateAvatar"],
    "post-deactivate-user" => ["POST" => "UserController@handleDeactivateUser"],
    "post-reactivate_user" => ["POST" => "UserController@handleReactivateUser"],

    // Banners
    "get-banners" => ["GET" => "BannerController@handleListBannersPaginated"],
    "post-banner" => ["POST" => "BannerController@handleCreateBanner"],
    "post-update-banner" => ["POST" => "BannerController@handleUpdateBanner"],
    "delete-banner" => ["DELETE" => "BannerController@handleDeleteBanner"],

    // Discounts
    "post-discount" => ["POST" => "DiscountController@handleCreateDiscount"],
    "get-discounts" => ["GET" => "DiscountController@handleListDiscounts"],
    "delete-discount" => ["DELETE" => "DiscountController@handleDeleteDiscount"],

    // Products
    "post-product" => ["POST" => "ProductController@handleCreateProduct"],
    "get-products" => ["GET" => "ProductController@handleListProducts"],
    "get-product-by-id" => ["GET" => "ProductController@handleGetProductById"],
    "post-edit-product" => ["POST" => "ProductController@handleUpdateProduct"],
    "post-hide-product" => ["POST" => "ProductController@handleHideProduct"],
    "post-unhide-product" => ["POST" => "ProductController@handleRestoreProduct"],

    // cart
    "post-cart" => ["POST" => "CartController@handleAddToCart"],
    "get-cart" => ["GET" => "CartController@handleGetCartItems"],
    "put-cart" => ["PUT" => "CartController@handleUpdateCartItem"],
    "delete-cart-item" => ["DELETE" => "CartController@handleDeleteCartItem"],

    // orders
    "post-checkout" => ["POST" => "OrderController@handleCheckout"],
    "get-order-history" => ["GET" => "OrderController@handleGetOrderHistory"],
    "post-admin-update-order-status" => ["POST" => "OrderController@handleAdminUpdateOrderStatus"],
    "get-admin-orders" => ["GET" => "OrderController@handleAdminListOrdersPaginated"],
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

