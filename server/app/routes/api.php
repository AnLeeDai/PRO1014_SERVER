<?php

require_once __DIR__ . "/../controllers/UserController.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == "OPTIONS") {
    http_response_code(200);
    exit();
}

$request = isset($_GET['request']) ? $_GET['request'] : '';
$method = $_SERVER['REQUEST_METHOD'];

$routes = [
    // user routes
    "users" => [
        "GET" => "UserController@getUsers",
        "POST" => "UserController@createUser",
        "PUT" => "UserController@updateUser",
        "DELETE" => "UserController@deleteUser"
    ],
];

if (isset($routes[$request][$method])) {
    list($controllerName, $methodName) = explode("@", $routes[$request][$method]);
    $controller = new $controllerName();
    $controller->$methodName();
} else {
    http_response_code(400);
    echo json_encode(["error" => "Invalid API request"]);
}
