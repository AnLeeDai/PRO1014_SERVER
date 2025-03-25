<?php
session_set_cookie_params([
  'path' => '/',
  'secure' => true,
  'httponly' => true,
  'samesite' => 'None'
]);

session_name('PHPSESSID');

session_start();

require_once __DIR__ . "../../helper/cors.php";

// Auto-load các controllers
spl_autoload_register(function ($class) {
  $file = __DIR__ . "/../app/controllers/" . $class . ".php";
  if (file_exists($file)) {
    require_once $file;
  }
});

// Lấy request method & endpoint
$request = $_GET['request'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Định nghĩa các route
$routes = [
  // Authentication routes
  "post-register" => ["POST" => "AuthController@handleRegister"],
  "post-login" => ["POST" => "AuthController@handleLogin"],
  "post-change-password" => ["POST" => "AuthController@handleChangePassword"],
  "post-forgot-password" => ["POST" => "AuthController@handleForgotPassword"],
  "post-logout" => ["POST" => "AuthController@handleLogout"],
  "post-admin-change-password" => ["POST" => "AuthController@handleAdminPasswordChange"],
  "get-password-requests" => ["GET" => "AuthController@listPendingPasswordRequests"],

  // User routes
  "get-user" => ["GET" => "UserController@handleGetAllUser"],

  // Category routes
  "get-category" => ["GET" => "CategoryController@handleGetAllCategory"],
  "post-category" => ['POST' => 'CategoryController@handleAddCategory'],
  "put-category" => ['PUT' => 'CategoryController@handleEditCategory'],
  "delete-category" => ['DELETE' => 'CategoryController@handleDeleteCategory'],
];

// Kiểm tra route hợp lệ
if (isset($routes[$request])) {
  if (isset($routes[$request][$method])) {
    list($controllerName, $methodName) = explode("@", $routes[$request][$method]);

    // Kiểm tra controller tồn tại trước khi gọi
    if (class_exists($controllerName)) {
      $controller = new $controllerName();
      if (method_exists($controller, $methodName)) {
        $controller->$methodName();
        exit();
      }
    }
  } else {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit();
  }
}

// Nếu không khớp route nào, trả về lỗi
http_response_code(404);
echo json_encode(["error" => "Invalid API request"]);
