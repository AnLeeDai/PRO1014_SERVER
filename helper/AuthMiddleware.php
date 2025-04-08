<?php

class AuthMiddleware
{
    public static function isAdmin(): array|false
    {
        try {
            $jwtHelper = new JwtHelper();
            $authModel = new AuthModel();
        } catch (\Throwable $e) {
            error_log("Middleware isAdmin Error: Failed to initialize dependencies: " . $e->getMessage());
            Utils::respond([
                "success" => false,
                "message" => "Lỗi hệ thống: Không thể khởi tạo middleware.",
                "code" => "INTERNAL_ERROR"
            ], 500);
        }

        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        if (!$authHeader) {
            Utils::respond([
                "success" => false,
                "message" => "Thiếu thông tin xác thực (Authorization header).",
                "code" => "MISSING_AUTH_HEADER"
            ], 440);
        }

        if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            Utils::respond([
                "success" => false,
                "message" => "Token không đúng định dạng yêu cầu.",
                "code" => "INVALID_TOKEN_FORMAT"
            ], 440);
        }

        $token = trim($matches[1]);

        $payload = $jwtHelper->verifyToken($token);
        if (!$payload) {
            Utils::respond([
                "success" => false,
                "message" => "Token không hợp lệ hoặc đã hết hạn.",
                "code" => "INVALID_TOKEN"
            ], 440);
        }

        // Kiểm tra thời gian đổi mật khẩu
        if (isset($payload['user_id']) && isset($payload['iat'])) {
            $userId = (int)$payload['user_id'];
            $tokenIssuedAt = (int)$payload['iat'];
            $authData = $authModel->getUserAuthVerificationData($userId);
            $passwordChangedAt = $authData['password_changed_at'] ?? null;

            if ($passwordChangedAt !== null) {
                $passwordChangedTimestamp = strtotime($passwordChangedAt);
                $allowedSkew = 5;

                if ($passwordChangedTimestamp !== false && $tokenIssuedAt < ($passwordChangedTimestamp - $allowedSkew)) {
                    Utils::respond([
                        "success" => false,
                        "message" => "Phiên đăng nhập không còn hiệu lực. Vui lòng đăng nhập lại.",
                        "code" => "TOKEN_EXPIRED"
                    ], 440);
                }
            }
        } else {
            error_log("Middleware isAdmin Error: Invalid payload structure.");
            Utils::respond([
                "success" => false,
                "message" => "Token không hợp lệ (thiếu thông tin xác thực).",
                "code" => "INVALID_TOKEN_PAYLOAD"
            ], 440);
        }

        // Kiểm tra vai trò
        if (!isset($payload['role']) || $payload['role'] !== 'admin') {
            Utils::respond([
                "success" => false,
                "message" => "Bạn không có quyền truy cập chức năng này.",
                "code" => "FORBIDDEN"
            ], 403);
        }

        return $payload;
    }

    public static function isUser(): array|false
    {
        try {
            $jwtHelper = new JwtHelper();
            $authModel = new AuthModel();
        } catch (\Throwable $e) {
            error_log("Middleware isUser Error: Failed to initialize dependencies: " . $e->getMessage());
            Utils::respond([
                "success" => false,
                "message" => "Lỗi hệ thống: Không thể khởi tạo middleware.",
                "code" => "INTERNAL_ERROR"
            ], 500);
        }

        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        if (!$authHeader) {
            Utils::respond([
                "success" => false,
                "message" => "Thiếu thông tin xác thực (Authorization header).",
                "code" => "MISSING_AUTH_HEADER"
            ], 440);
        }

        if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            Utils::respond([
                "success" => false,
                "message" => "Token không đúng định dạng yêu cầu.",
                "code" => "INVALID_TOKEN_FORMAT"
            ], 440);
        }

        $token = trim($matches[1]);

        $payload = $jwtHelper->verifyToken($token);
        if (!$payload) {
            Utils::respond([
                "success" => false,
                "message" => "Token không hợp lệ hoặc đã hết hạn.",
                "code" => "INVALID_TOKEN"
            ], 440);
        }

        // Kiểm tra thời gian đổi mật khẩu
        if (isset($payload['user_id']) && isset($payload['iat'])) {
            $userId = (int)$payload['user_id'];
            $tokenIssuedAt = (int)$payload['iat'];
            $authData = $authModel->getUserAuthVerificationData($userId);
            $passwordChangedAt = $authData['password_changed_at'] ?? null;

            if ($passwordChangedAt !== null) {
                $passwordChangedTimestamp = strtotime($passwordChangedAt);
                $allowedSkew = 5;

                if ($passwordChangedTimestamp !== false && $tokenIssuedAt < ($passwordChangedTimestamp - $allowedSkew)) {
                    Utils::respond([
                        "success" => false,
                        "message" => "Phiên đăng nhập không còn hiệu lực. Vui lòng đăng nhập lại.",
                        "code" => "TOKEN_EXPIRED"
                    ], 440);
                }
            }
        } else {
            error_log("Middleware isUser Error: Invalid payload structure.");
            Utils::respond([
                "success" => false,
                "message" => "Token không hợp lệ (thiếu thông tin xác thực).",
                "code" => "INVALID_TOKEN_PAYLOAD"
            ], 440);
        }

        return $payload;
    }
}
