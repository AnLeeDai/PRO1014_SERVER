<?php

use JetBrains\PhpStorm\NoReturn;

class Utils
{
  private static function optimizeImage(string $sourcePath, string $destPath, string $type): bool
  {
    switch ($type) {
      case 'image/jpeg':
        $image = imagecreatefromjpeg($sourcePath);
        return imagejpeg($image, $destPath, 75); // nén 75%
      case 'image/png':
        $image = imagecreatefrompng($sourcePath);
        imagesavealpha($image, true);
        return imagepng($image, $destPath, 6); // mức nén từ 0 (chất lượng cao) đến 9
      case 'image/webp':
        $image = imagecreatefromwebp($sourcePath);
        return imagewebp($image, $destPath, 75);
      default:
        return false;
    }
  }

  public static function uploadImage(array $file, string $filePrefix, ?string $customName = null): array
  {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
      return ['success' => false, 'message' => 'Chỉ chấp nhận ảnh JPEG, PNG hoặc WEBP'];
    }

    if ($file['size'] > 5 * 1024 * 1024) {
      return ['success' => false, 'message' => 'Ảnh quá lớn (tối đa 5MB)'];
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);

    // Ưu tiên dùng custom name nếu có
    $fileName = $customName
      ? $customName . '.' . $ext
      : "{$filePrefix}_" . time() . ".$ext";

    $absoluteDir = "C:/laragon/www/uploads";
    if (!is_dir($absoluteDir)) {
      if (!mkdir($absoluteDir, 0777, true)) {
        return ['success' => false, 'message' => 'Không thể tạo thư mục lưu ảnh'];
      }
    }

    if (!is_writable($absoluteDir)) {
      return ['success' => false, 'message' => 'Thư mục không có quyền ghi'];
    }

    $tempPath = $file['tmp_name'];
    $finalPath = "$absoluteDir/$fileName";

    if (!self::optimizeImage($tempPath, $finalPath, $file['type'])) {
      return ['success' => false, 'message' => 'Không thể tối ưu ảnh'];
    }

    $relativePath = "uploads/$fileName";

    return [
      'success' => true,
      'message' => 'Upload thành công',
      'file_name' => $fileName,
      'path' => $relativePath,
      'url' => self::buildAbsoluteUrl($relativePath)
    ];
  }

  public static function buildAbsoluteUrl(string $relativePath): string
  {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . '/' . ltrim($relativePath, '/');
  }

  public static function validateInput($data, $rules): void
  {
    foreach ($rules as $key => $message) {
      if (empty($data[$key])) {
        self::respond(["success" => false, "message" => $message], 400);
      }
    }
  }

  #[NoReturn] public static function respond($data, $status = 200): void
  {
    http_response_code($status);
    header('Content-Type: application/json');
    die(json_encode($data));
  }

  public function buildResponse(
    bool $success,
    string $message,
    array $data = [],
    int $page = 1,
    int $limit = 10,
    int $totalItems = 0,
    array $filters = []
  ): array {
    return [
      'success' => $success,
      'message' => $message,
      'filters' => $filters,
      'pagination' => [
        'current_page' => $page,
        'limit' => $limit,
        'total_items' => $success ? $totalItems : 0,
        'total_pages' => $success ? (int)ceil($totalItems / $limit) : 0
      ],
      'data' => $success ? $data : []
    ];
  }
}
