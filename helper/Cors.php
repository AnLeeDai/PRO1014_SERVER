<?php

$allowed_origins = [
    "http://127.0.0.1:5173",
    "http://localhost:5173",
    "http://localhost:3000",
    "http://localhost:4173",
    "http://pro1014-server",
    "http://pro1014-server:8888",
    "https://pro-1014-client.vercel.app",
    "https://atlas-nr-longitude-clerk.trycloudflare.com",
];

$allow_all = filter_var(getenv('CORS_ALLOW_ALL') ?: 'false', FILTER_VALIDATE_BOOLEAN);
$allow_credentials = filter_var(getenv('CORS_ALLOW_CREDENTIALS') ?: 'true', FILTER_VALIDATE_BOOLEAN);

$origin = $_SERVER['HTTP_ORIGIN'] ?? "";

if (!empty($origin)) {
    if ($allow_all || in_array($origin, $allowed_origins)) {
        // Echo back the origin and vary on it to support credentials and caches
        header("Access-Control-Allow-Origin: $origin");
        header("Vary: Origin");
        if ($allow_credentials) {
            header("Access-Control-Allow-Credentials: true");
        }
    } else {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Nguồn (Origin) không được phép."]);
        exit();
    }
}

header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin");
header("Access-Control-Max-Age: 86400");

if ($_SERVER['REQUEST_METHOD'] == "OPTIONS") {
    http_response_code(204);
    exit();
}

header("Content-Type: application/json; charset=utf-8");
