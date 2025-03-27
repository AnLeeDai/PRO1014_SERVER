<?php

class Utils
{
  function loadEnv($envPath)
  {
    if (!file_exists($envPath)) {
      return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
      // Bỏ qua comment
      if (strpos(trim($line), '#') === 0) {
        continue;
      }

      $pair = explode('=', $line, 2);
      if (count($pair) !== 2) {
        continue;
      }

      $name = trim($pair[0]);
      $value = trim($pair[1]);

      // Gán vào ENV nếu chưa tồn tại
      if (!getenv($name)) {
        putenv("$name=$value");
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
      }
    }
  }

  public static function validateInput($data, $rules)
  {
    foreach ($rules as $key => $value) {
      if (empty($data[$key])) {
        http_response_code(400);
        die(json_encode(["success" => false, "message" => $value]));
      }
    }
  }

  public static function respond($data, $status = 200)
  {
    http_response_code($status);
    die(json_encode($data));
  }

  public function buildResponse(
    bool   $success,
    string $message,
    array  $data = [],
    int    $page = 1,
    int    $limit = 10,
    int    $totalItems = 0,
    array  $filters = []
  ): array
  {
    if (!$success) {
      return [
        "success" => false,
        "message" => $message,
        "filters" => [],
        "pagination" => [
          "current_page" => $page,
          "limit" => $limit,
          "total_items" => 0,
          "total_pages" => 0
        ],
        "data" => []
      ];
    }

    return [
      "success" => true,
      "message" => $message,
      "filters" => $filters,
      "pagination" => [
        "current_page" => $page,
        "limit" => $limit,
        "total_items" => $totalItems,
        "total_pages" => (int)ceil($totalItems / $limit)
      ],
      "data" => $data
    ];
  }
}
