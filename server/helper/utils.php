<?php

class Utils
{
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

  public function buildPaginatedResponse(
    bool $success,
    string $message,
    array $data = [],
    int $page = 1,
    int $limit = 10,
    int $totalItems = 0,
    array $filters = []
  ): array {
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
