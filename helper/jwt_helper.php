<?php

// Nếu sử dụng file .env, hãy đảm bảo bạn đã cài đặt và load thư viện như phpdotenv
// Ví dụ:
// require_once __DIR__ . '/../../vendor/autoload.php'; // Nếu dùng Composer
// try {
//     $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..'); // Chỉ đường dẫn tới thư mục chứa file .env
//     $dotenv->load();
//     $dotenv->required('JWT_SECRET'); // Đảm bảo biến này tồn tại
// } catch (\Throwable $th) {
//     error_log("Could not load .env file or JWT_SECRET is missing: " . $th->getMessage());
//     // Có thể đặt một secret mặc định yếu ở đây CHỈ cho dev, hoặc throw lỗi
// }


class JwtHelper
{
    private string $secret;
    private string $alg = 'HS256'; // Thuật toán mã hóa

    /**
     * @throws Exception
     */
    public function __construct() {
        $this->secret = $_ENV['JWT_SECRET'] ?? 'your-default-fallback-secret-only-for-dev';

        if ($this->secret === 'your-default-fallback-secret-only-for-dev') {
            // Ghi log cảnh báo nếu đang dùng secret mặc định yếu
            error_log("SECURITY WARNING: Using default JWT secret. Set the JWT_SECRET environment variable for production!");
        }
        if (empty($this->secret)) {
            throw new \Exception("JWT Secret Key is not configured. Set the JWT_SECRET environment variable.");
        }
    }

    /**
     * Tạo một JWT mới.
     *
     * @param array $payload Dữ liệu muốn đưa vào token (không chứa thông tin nhạy cảm).
     * @param int $expireInSeconds Thời gian token hết hạn (tính bằng giây). Mặc định là 1 giờ.
     * @return string JWT đã được tạo.
     */
    public function generateToken(array $payload, int $expireInSeconds = 3600): string
    {
        $header = json_encode(['alg' => $this->alg, 'typ' => 'JWT']);

        // Thêm thời gian phát hành (iat) và thời gian hết hạn (exp) vào payload
        $payload['iat'] = time();
        $payload['exp'] = time() + $expireInSeconds;

        // Encode Base64 URL-safe
        $base64UrlHeader = $this->base64UrlEncode($header);
        $base64UrlPayload = $this->base64UrlEncode(json_encode($payload));

        // Tạo chữ ký
        $signature = hash_hmac('sha256', "$base64UrlHeader.$base64UrlPayload", $this->secret, true);
        $base64UrlSignature = $this->base64UrlEncode($signature);

        // Ghép các phần lại thành JWT
        return "$base64UrlHeader.$base64UrlPayload.$base64UrlSignature";
    }

    /**
     * Xác thực một JWT và trả về payload nếu hợp lệ.
     *
     * @param string $jwt Chuỗi JWT cần xác thực.
     * @return array|null Payload của token nếu hợp lệ và chưa hết hạn, ngược lại trả về null.
     */
    public function verifyToken(string $jwt): ?array
    {
        // Tách JWT thành 3 phần: header, payload, signature
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            error_log("JWT Verify Error: Invalid token structure (not 3 parts)");
            return null;
        }

        [$base64UrlHeader, $base64UrlPayload, $base64UrlSignature] = $parts;

        // Decode header và payload (chưa cần kiểm tra nội dung header lúc này)
        $payloadJson = $this->base64UrlDecode($base64UrlPayload);
        $payload = json_decode($payloadJson, true);

        if ($payload === null) {
            error_log("JWT Verify Error: Invalid payload encoding");
            return null; // Payload không phải JSON hợp lệ
        }

        // Kiểm tra chữ ký
        $signatureToCheck = hash_hmac('sha256', "$base64UrlHeader.$base64UrlPayload", $this->secret, true);
        $base64UrlSignatureToCheck = $this->base64UrlEncode($signatureToCheck);

        // So sánh chữ ký an toàn (chống timing attack)
        if (!hash_equals($base64UrlSignature, $base64UrlSignatureToCheck)) {
            error_log("JWT Verify Error: Signature verification failed");
            return null; // Chữ ký không khớp
        }

        // Kiểm tra thời gian hết hạn (exp claim)
        if (!isset($payload['exp']) || $payload['exp'] < time()) {
            error_log("JWT Verify Error: Token has expired (exp=" . ($payload['exp'] ?? 'null') . ")");
            return null; // Token đã hết hạn
        }

        // Kiểm tra thời gian phát hành (iat claim) - nếu cần, ví dụ không chấp nhận token từ tương lai
        if (isset($payload['iat']) && $payload['iat'] > time() + 60) { // Cho phép sai lệch 60s
            error_log("JWT Verify Error: Invalid issue time (iat=" . $payload['iat'] . ")");
            return null; // Token được phát hành trong tương lai (không hợp lệ)
        }

        // Kiểm tra 'not before' (nbf claim) - nếu có
        if (isset($payload['nbf']) && $payload['nbf'] > time()) {
            error_log("JWT Verify Error: Token not valid yet (nbf=" . $payload['nbf'] . ")");
            return null; // Token chưa tới thời gian được phép sử dụng
        }


        // Mọi thứ đều ổn, trả về payload
        return $payload;
    }

    /**
     * Mã hóa Base64 URL-safe.
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Giải mã Base64 URL-safe.
     */
    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}