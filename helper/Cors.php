<?php

$allowed_origins = [
    "http://127.0.0.1:5173",
    "http://localhost:5173",
    "http://localhost:3000",
    "https://pro-1014-client.vercel.app",
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? "";

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
} else {
    if (!empty($origin)) {
        http_response_code(403);
        echo json_encode(["error" => "Origin not allowed"]);
        exit();
    }
}

header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Max-Age: 86400");

if ($_SERVER['REQUEST_METHOD'] == "OPTIONS") {
    http_response_code(204);
    exit();
}

header("Content-Type: application/json; charset=utf-8");
