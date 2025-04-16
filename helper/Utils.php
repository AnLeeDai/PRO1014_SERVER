<?php


if (!defined('APP_BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    define('APP_BASE_URL', $protocol . '://' . $host);
}


class Utils
{
    private const UPLOAD_BASE_PATH = 'C:/laragon/www/uploads';
    private const UPLOAD_DIR_PERMISSIONS = 0775;
    private const MAX_UPLOAD_SIZE_MB = 10; // Kích thước tối đa (MB)
    private const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    private const DEFAULT_JPG_WEBP_QUALITY = 20; // Chất lượng ảnh (0-100)
    private const DEFAULT_PNG_COMPRESSION = 9; // Mức nén PNG (0-9)

    // --- Validation Constants ---
    private const REGEX_USERNAME = '/^[a-zA-Z0-9_.-]{3,}$/';
    private const REGEX_PASSWORD_COMPLEX = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9\s]).{8,}$/';
    private const DEFAULT_PASSWORD_MIN_LENGTH = 6;
    private const REGEX_PHONE = '/^0[0-9]{9}$/';

    private static function optimizeAndSaveImage(string $sourcePath, string $destPath, string $mimeType): bool
    {
        if (!extension_loaded('gd')) {
            error_log('Utils Error: GD extension is not loaded.');
            return false;
        }
        $image = null;
        $success = false;
        try {
            switch ($mimeType) {
                case 'image/jpeg':
                    if (!(imagetypes() & IMG_JPG)) {
                        throw new \RuntimeException('JPEG support is not enabled in GD.');
                    }
                    $image = imagecreatefromjpeg($sourcePath);
                    if ($image === false) throw new \RuntimeException('Failed to create image resource from JPEG.');
                    $success = imagejpeg($image, $destPath, self::DEFAULT_JPG_WEBP_QUALITY);
                    break;
                case 'image/png':
                    if (!(imagetypes() & IMG_PNG)) {
                        throw new \RuntimeException('PNG support is not enabled in GD.');
                    }
                    $image = imagecreatefrompng($sourcePath);
                    if ($image === false) throw new \RuntimeException('Failed to create image resource from PNG.');
                    imagesavealpha($image, true);
                    $success = imagepng($image, $destPath, self::DEFAULT_PNG_COMPRESSION);
                    break;
                case 'image/webp':
                    if (!(imagetypes() & IMG_WEBP)) {
                        throw new \RuntimeException('WebP support is not enabled in GD.');
                    }
                    $image = imagecreatefromwebp($sourcePath);
                    if ($image === false) throw new \RuntimeException('Failed to create image resource from WebP.');
                    $success = imagewebp($image, $destPath, self::DEFAULT_JPG_WEBP_QUALITY);
                    break;
                default:
                    error_log("Utils Error: Unsupported image type '$mimeType' passed to optimizeAndSaveImage.");
                    return false;
            }
            if (!$success) {
                error_log("Utils Error: Failed to save optimized image to '$destPath' for type '$mimeType'.");
            }
            return $success;
        } catch (\Throwable $e) {
            error_log("Utils Error in optimizeAndSaveImage: " . $e->getMessage() . " | Source: $sourcePath, Dest: $destPath, Type: $mimeType");
            return false;
        } finally {
            if (is_resource($image) || $image instanceof \GdImage) {
                imagedestroy($image);
            }
        }
    }

    public static function uploadImage(array $file, string $filePrefix, ?string $customName = null): array
    {
        // Step 1: Check basic upload errors
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['success' => false, 'message' => 'Thông số upload không hợp lệ.'];
        }
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                return ['success' => false, 'message' => 'Không có file nào được upload.'];
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return ['success' => false, 'message' => 'Kích thước ảnh vượt quá giới hạn cho phép.'];
            default:
                return ['success' => false, 'message' => 'Lỗi không xác định khi upload file.'];
        }

        // Step 2: Validate uploaded file
        $tempPath = $file['tmp_name'];
        if (!is_uploaded_file($tempPath)) {
            error_log("Utils Security Alert: Possible file upload attack. Path: " . $tempPath);
            return ['success' => false, 'message' => 'File upload không hợp lệ.'];
        }

        // Step 3: Check MIME type and size
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tempPath);
        finfo_close($finfo);
        if (!in_array($mimeType, self::ALLOWED_IMAGE_TYPES, true)) {
            return ['success' => false, 'message' => 'Chỉ chấp nhận ảnh JPEG, PNG hoặc WEBP. Loại file phát hiện: ' . $mimeType];
        }
        if (filesize($tempPath) > self::MAX_UPLOAD_SIZE_MB * 1024 * 1024) {
            return ['success' => false, 'message' => 'Ảnh quá lớn (tối đa ' . self::MAX_UPLOAD_SIZE_MB . 'MB)'];
        }

        // Step 4: Generate filename and path
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $ext = $extensions[$mimeType] ?? pathinfo($file['name'], PATHINFO_EXTENSION);
        if ($customName !== null) {
            $customName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $customName);
            $customName = trim($customName, '_-');
            $customName = mb_substr($customName, 0, 100);
        }
        $baseName = !empty($customName) ? $customName : $filePrefix . '_' . time() . '_' . bin2hex(random_bytes(4));
        $fileName = $baseName . '.' . $ext;

        // Step 5: Ensure directory exists and is writable
        $absoluteDir = rtrim(self::UPLOAD_BASE_PATH, '/\\');
        if (!is_dir($absoluteDir)) {
            if (!mkdir($absoluteDir, self::UPLOAD_DIR_PERMISSIONS, true) && !is_dir($absoluteDir)) {
                error_log("Utils Error: Failed to create upload directory: " . $absoluteDir);
                return ['success' => false, 'message' => 'Không thể tạo thư mục lưu ảnh.'];
            }
        }
        if (!is_writable($absoluteDir)) {
            error_log("Utils Error: Upload directory is not writable: " . $absoluteDir);
            return ['success' => false, 'message' => 'Không có quyền ghi vào thư mục lưu ảnh.'];
        }
        $finalPath = $absoluteDir . '/' . $fileName;

        // Step 6: Optimize and save
        if (!self::optimizeAndSaveImage($tempPath, $finalPath, $mimeType)) {
            return ['success' => false, 'message' => 'Không thể xử lý hoặc lưu ảnh đã tối ưu.'];
        }

        // Step 7: Generate relative path and URL
        $uploadDirName = basename(self::UPLOAD_BASE_PATH);
        $relativePath = $uploadDirName . '/' . $fileName;
        return [
            'success' => true,
            'message' => 'Upload và tối ưu ảnh thành công',
            'file_name' => $fileName,
            'path' => $relativePath,
            'absolute_path' => $finalPath,
            'url' => self::buildAbsoluteUrl($relativePath)
        ];
    }

    public static function buildAbsoluteUrl(string $relativePath): string
    {
        if (defined('APP_BASE_URL')) {
            return rtrim(APP_BASE_URL, '/') . '/' . ltrim($relativePath, '/');
        }
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host . '/' . ltrim($relativePath, '/');
    }

    public static function validateBasicInput(?array $data, array $rules): array
    {
        $errors = [];
        if ($data === null) {
            return ['_error' => "Dữ liệu đầu vào không hợp lệ (null)."];
        }

        foreach ($rules as $key => $message) {
            if (!isset($data[$key]) || trim((string)$data[$key]) === '') {
                $errors[$key] = $message;
            }
        }

        return $errors;
    }

    // --- CÁC HÀM VALIDATE ---
    public static function validateUsernameFormat(string $username): bool
    {
        return preg_match(self::REGEX_USERNAME, $username) === 1;
    }

    public static function validatePhoneNumberVN(string $phone): bool
    {
        return preg_match(self::REGEX_PHONE, $phone) === 1;
    }

    public static function validatePasswordMinLength(string $password, int $minLength = self::DEFAULT_PASSWORD_MIN_LENGTH): bool
    {
        return mb_strlen($password, 'UTF-8') >= $minLength;
    }

    public static function validatePasswordComplexity(string $password): bool
    {
        return preg_match(self::REGEX_PASSWORD_COMPLEX, $password) === 1;
    }

    public static function validateEmailFormat(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }


    // --- CÁC HÀM RESPONSE ---
    public static function respond(mixed $data, int $status = 200): void
    {
        if (headers_sent($file, $line)) {
            error_log("Utils Error: Headers already sent in $file on line $line.");
            echo '{"success": false, "message": "Server configuration error."}';
            die();
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($jsonData === false) {
            $jsonError = json_last_error_msg();
            error_log("Utils Error: Failed to encode JSON. Error: " . $jsonError);
            http_response_code(500);
            echo '{"success": false, "message": "Server error processing response data.", "json_error": "' . $jsonError . '"}';
        } else {
            echo $jsonData;
        }
        die();
    }

    public static function buildPaginatedResponse(bool $success, string $message, array $data = [], ?int $page = null, ?int $limit = null, ?int $totalItems = null, array $filters = []): array
    {
        $response = ['success' => $success, 'message' => $message, 'filters' => $filters];
        if ($success && $page !== null && $limit !== null && $totalItems !== null && $limit > 0) {
            $response['pagination'] = ['current_page' => $page, 'limit' => $limit, 'total_items' => $totalItems, 'total_pages' => (int)ceil($totalItems / $limit)];
        } else {
            $response['pagination'] = null;
        }
        $response['data'] = $success ? $data : [];
        return $response;
    }
}
