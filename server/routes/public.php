<?php
session_start();

require_once __DIR__ . "/../app/controllers/AuthController.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] == "OPTIONS") {
  http_response_code(200);
  exit();
}

$request = $_GET['request'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

$routes = [
  // authentication routes
  "post-register" => ["POST" => "AuthController@handleRegister"],
  "post-login" => ["POST" => "AuthController@handleLogin"],
  "post-change-password" => ["POST" => "AuthController@handleChangePassword"],
  "post-forgot-password" => ["POST" => "AuthController@handleForgotPassword"],
];

if (isset($routes[$request][$method])) {
  list($controllerName, $methodName) = explode("@", $routes[$request][$method]);
  $controller = new $controllerName();
  $controller->$methodName();
} else {
  http_response_code(400);
  echo json_encode(["error" => "Invalid API request"]);
}