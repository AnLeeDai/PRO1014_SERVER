<?php
$allowed_origins = [
  "http://localhost:5173",
  "https://pro-1014-client.vercel.app"
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? "";

if (in_array($origin, $allowed_origins)) {
  header("Access-Control-Allow-Origin: $origin");
  header("Access-Control-Allow-Credentials: true");
}

header("Content-Type: application/json");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] == "OPTIONS") {
  http_response_code(200);
  exit();
}
