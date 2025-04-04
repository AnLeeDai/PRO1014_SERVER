<?php

class AuthMiddleware
{
    /**
     * Xác thực JWT, kiểm tra quyền admin, và kiểm tra token hợp lệ sau khi đổi mật khẩu.
     * Tự động gửi lỗi 401/403 và dừng script nếu thất bại.
     * @return array|false Payload admin nếu thành công, false nếu thất bại (ít khi trả về false do die()).
     */
    public static function isAdmin(): array|false
    {
        try {
            $jwtHelper = new JwtHelper();
            $authModel = new AuthModel();
        } catch (\Throwable $e) {
            error_log("Middleware isAdmin Error: Failed to initialize dependencies: " . $e->getMessage());
            Utils::respond(["success" => false, "message" => "Lỗi cấu hình hệ thống."], 500);
            // Unreachable if respond dies
        }

        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        if (!$authHeader || !preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            Utils::respond(["success" => false, "message" => "Yêu cầu thiếu hoặc sai định dạng token."], 401);

        }
        $token = trim($matches[1]);

        $payload = $jwtHelper->verifyToken($token);
        if (!$payload) {
            Utils::respond(["success" => false, "message" => "Token không hợp lệ hoặc đã hết hạn."], 401);

        }

        // --- KIỂM TRA TIMESTAMP ĐỔI MẬT KHẨU ---
        if (isset($payload['user_id']) && isset($payload['iat'])) {
            $userId = (int)$payload['user_id'];
            $tokenIssuedAt = (int)$payload['iat'];
            $authData = $authModel->getUserAuthVerificationData($userId);
            $passwordChangedAt = $authData['password_changed_at'] ?? null;
            if ($passwordChangedAt !== null) {
                $passwordChangedTimestamp = strtotime($passwordChangedAt);
                if ($passwordChangedTimestamp !== false && $tokenIssuedAt < $passwordChangedTimestamp) {
                    Utils::respond(["success" => false, "message" => "Phiên đăng nhập không hợp lệ. Vui lòng đăng nhập lại."], 401);

                }
            }
        } else {
            error_log("Middleware isAdmin Error: Invalid payload structure.");
            Utils::respond(["success" => false, "message" => "Token không hợp lệ."], 401);

        }

        // Kiểm tra vai trò admin
        if (!isset($payload['role']) || $payload['role'] !== 'admin') {
            Utils::respond(["success" => false, "message" => "Không có quyền truy cập."], 403);

        }

        return $payload;
    }

    /**
     * Xác thực JWT cho người dùng bất kỳ, và kiểm tra token hợp lệ sau khi đổi mật khẩu.
     * Tự động gửi lỗi 401 và dừng script nếu thất bại.
     * @return array|false Payload user nếu thành công, false nếu thất bại (ít khi trả về false do die()).
     */
    public static function isUser(): array|false
    {
        try {
            $jwtHelper = new JwtHelper();
            $authModel = new AuthModel();
        } catch (\Throwable $e) {
            error_log("Middleware isUser Error: Failed to initialize dependencies: " . $e->getMessage());
            Utils::respond(["success" => false, "message" => "Lỗi cấu hình hệ thống."], 500);

        }

        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        if (!$authHeader || !preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            Utils::respond(["success" => false, "message" => "Yêu cầu thiếu hoặc sai định dạng token."], 401);

        }
        $token = trim($matches[1]);

        $payload = $jwtHelper->verifyToken($token);
        if (!$payload) {
            Utils::respond(["success" => false, "message" => "Token không hợp lệ hoặc đã hết hạn."], 401);

        }

        // --- KIỂM TRA TIMESTAMP ĐỔI MẬT KHẨU ---
        if (isset($payload['user_id']) && isset($payload['iat'])) {
            $userId = (int)$payload['user_id'];
            $tokenIssuedAt = (int)$payload['iat'];
            $authData = $authModel->getUserAuthVerificationData($userId);
            $passwordChangedAt = $authData['password_changed_at'] ?? null;
            if ($passwordChangedAt !== null) {
                $passwordChangedTimestamp = strtotime($passwordChangedAt);
                if ($passwordChangedTimestamp !== false && $tokenIssuedAt < $passwordChangedTimestamp) {
                    Utils::respond(["success" => false, "message" => "Phiên đăng nhập không hợp lệ. Vui lòng đăng nhập lại."], 401);
                }
            }
        } else {
            error_log("Middleware isUser Error: Invalid payload structure.");
            Utils::respond(["success" => false, "message" => "Token không hợp lệ."], 401);

        }
        return $payload;
    }

}