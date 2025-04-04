<?php

require_once __DIR__ . "/../models/AuthModel.php";
require_once __DIR__ . "/../../helper/utils.php";
require_once __DIR__ . "/../../helper/cors.php";
require_once __DIR__ . "/../../helper/jwt_helper.php";

class AuthController
{
    private AuthModel $authModel;
    private JwtHelper $jwtHelper;

    public function __construct()
    {
        $this->authModel = new AuthModel();
        $this->jwtHelper = new JwtHelper();
    }

    public function handleRegister(): void
    {
        $data = json_decode(file_get_contents("php://input"), true);

        $basicRules = [
            'username' => 'Tên đăng nhập không được để trống',
            'password' => 'Mật khẩu không được để trống',
            'password_confirm' => 'Xác nhận mật khẩu không được để trống',
            'full_name' => 'Họ tên không được để trống',
            'email' => 'Email không được để trống',
        ];
        $validationErrors = Utils::validateBasicInput($data, $basicRules);
        if (!empty($validationErrors)) {
            Utils::respond(["success" => false, "message" => "Thiếu thông tin bắt buộc.", "errors" => $validationErrors], 400);
        }

        $formatErrors = [];

        // Lấy và trim dữ liệu
        $username = trim($data['username']);
        $password = trim($data['password']);
        $password_confirm = trim($data['password_confirm']);
        $full_name = trim($data['full_name']);
        $email = trim($data['email']);
        $phone_number = isset($data['phone_number']) ? trim($data['phone_number']) : null;
        $address = isset($data['address']) ? trim($data['address']) : null;
        $role = isset($data['role']) ? trim($data['role']) : 'user';

        // Validate Format
        if (!Utils::validateUsernameFormat($username)) {
            $formatErrors['username'] = 'Tên đăng nhập không hợp lệ (ít nhất 3 ký tự, chỉ chứa a-z, A-Z, 0-9, _, ., -).';
        }

        if (!Utils::validateEmailFormat($email)) {
            $formatErrors['email'] = 'Định dạng email không hợp lệ.';
        }

        if (!Utils::validatePasswordComplexity($password)) {
            $formatErrors['password'] = 'Mật khẩu phải chứa ít nhất một ký tự thường (a-z), một ký tự hoa (A-Z), một chữ số (0-9), một ký tự đặc biệt và có độ dài tối thiểu 8 ký tự.';
        }

        if ($phone_number !== null && $phone_number !== '' && !Utils::validatePhoneNumberVN($phone_number)) {
            $formatErrors['phone_number'] = 'Số điện thoại không hợp lệ (phải có 10 chữ số, bắt đầu bằng 0).';
        }

        if ($password !== $password_confirm) {
            $formatErrors['password_confirm'] = 'Xác nhận mật khẩu không khớp.';
        }

        // Chỉ cho phép đăng ký role 'user' qua API này
        if ($role !== 'user') {
            $role = 'user';
        }

        // Kiểm tra lỗi định dạng/logic
        if (!empty($formatErrors)) {
            Utils::respond(["success" => false, "message" => "Dữ liệu không hợp lệ.", "errors" => $formatErrors], 400);
        }

        $conflictErrors = [];
        if ($this->authModel->findUserByUsername($username)) {
            $conflictErrors['username'] = 'Tên đăng nhập này đã được sử dụng.';
        }
        if ($this->authModel->findUserByEmail($email)) {
            $conflictErrors['email'] = 'Email này đã được sử dụng.';
        }

        if (!empty($conflictErrors)) {
            Utils::respond(["success" => false, "message" => "Thông tin đăng ký đã tồn tại.", "errors" => $conflictErrors], 409);
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
        if ($hashedPassword === false) {
            error_log("Password hashing failed for user: " . $username);
            Utils::respond(["success" => false, "message" => "Lỗi hệ thống, không thể xử lý mật khẩu."], 500);
        }

        $avatar_url = "https://avatar.iran.liara.run/public";

        $userDataToInsert = [
            'username' => $username,
            'password' => $hashedPassword,
            'full_name' => $full_name,
            'email' => $email,
            'phone_number' => $phone_number,
            'address' => $address,
            'avatar_url' => $avatar_url,
            'role' => $role,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $newUserId = $this->authModel->createUser($userDataToInsert);

        if ($newUserId !== false) {
            // Không trả về password hash
            unset($userDataToInsert['password']);
            unset($userDataToInsert['user_id']);

            $userDataToInsert['user_id'] = $newUserId;

            Utils::respond([
                "success" => true,
                "message" => "Đăng ký tài khoản thành công!",
                "user" => $userDataToInsert
            ], 201);
        } else {
            Utils::respond(["success" => false, "message" => "Đã xảy ra lỗi khi tạo tài khoản. Vui lòng thử lại."], 500); // 500 Internal Server Error
        }
    }

    public function handleLogin(): void
    {
        $data = json_decode(file_get_contents("php://input"), true);

        $rules = [
            'username' => 'Tên đăng nhập không được để trống',
            'password' => 'Mật khẩu không được để trống'
        ];

        $validationErrors = Utils::validateBasicInput($data, $rules);

        if (!empty($validationErrors)) {
            Utils::respond([
                "success" => false,
                "message" => "Dữ liệu đầu vào không hợp lệ.",
                "errors" => $validationErrors
            ], 400);
        }

        $username = trim($data['username']);
        $password = trim($data['password']);

        $formatErrors = [];

        if (!Utils::validateUsernameFormat($username)) {
            $formatErrors['username'] = 'Tên đăng nhập không hợp lệ (ít nhất 3 ký tự, chỉ chứa a-z, A-Z, 0-9, _, ., -).';
        }

        if (!Utils::validatePasswordComplexity($password)) {
            $formatErrors['password'] = 'Mật khẩu phải chứa ít nhất một ký tự thường (a-z), một ký tự hoa (A-Z), một chữ số (0-9), một ký tự đặc biệt và có độ dài tối thiểu 8 ký tự.';
        }

        if (!empty($formatErrors)) {
            Utils::respond([
                "success" => false,
                "message" => "Dữ liệu đầu vào không đúng định dạng.",
                "errors" => $formatErrors
            ], 400);
        }

        $userDataFromDb = $this->authModel->getUserLoginDataByUsername($username);

        $isAuthenticated = false;
        $loginMessage = '';

        if ($userDataFromDb === false) {
            $loginMessage = 'Tên đăng nhập không tồn tại trên hệ thống.';
        } else {
            if (password_verify($password, $userDataFromDb['password'])) {
                $isAuthenticated = true;
            } else {
                $loginMessage = 'Mật khẩu không chính xác.';
            }
        }

        if ($isAuthenticated) {
            // Tạo JWT
            $payload = [
                'user_id' => $userDataFromDb['user_id'],
                'username' => $userDataFromDb['username'],
                'role' => $userDataFromDb['role'],
            ];
            $token_lifetime = $_ENV['JWT_LIFETIME_SECONDS'] ?? 3600;
            $token = $this->jwtHelper->generateToken($payload, (int)$token_lifetime);

            $response_data = [
                "success" => true,
                "message" => "Đăng nhập thành công",
                "token" => $token,
                "user" => [
                    'user_id' => $userDataFromDb['user_id'],
                    'username' => $userDataFromDb['username'],
                    'full_name' => $userDataFromDb['full_name'] ?? null,
                    'email' => $userDataFromDb['email'] ?? null,
                    'role' => $userDataFromDb['role'],
                    'avatar_url' => $userDataFromDb['avatar_url'] ?? null
                ],
                "expires_in" => (int)$token_lifetime
            ];
            Utils::respond($response_data, 200);
        } else {
            Utils::respond(["success" => false, "message" => $loginMessage], 401);
        }
    }
}