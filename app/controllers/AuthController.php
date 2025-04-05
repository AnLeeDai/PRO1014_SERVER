<?php

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
        $username = trim($data['username']);
        $password = $data['password'];
        $password_confirm = $data['password_confirm'];
        $full_name = trim($data['full_name']);
        $email = trim($data['email']);
        $phone_number = isset($data['phone_number']) ? trim($data['phone_number']) : null;
        $address = isset($data['address']) ? trim($data['address']) : null;
        $role = 'user';

        if (!Utils::validateUsernameFormat($username)) {
            $formatErrors['username'] = 'Tên đăng nhập không hợp lệ.';
        }
        if (!Utils::validateEmailFormat($email)) {
            $formatErrors['email'] = 'Định dạng email không hợp lệ.';
        }
        if (!Utils::validatePasswordComplexity($password)) {
            $formatErrors['password'] = 'Mật khẩu yếu.';
        }
        if ($phone_number !== null && $phone_number !== '' && !Utils::validatePhoneNumberVN($phone_number)) {
            $formatErrors['phone_number'] = 'Số điện thoại không hợp lệ.';
        }
        if ($password !== $password_confirm) {
            $formatErrors['password_confirm'] = 'Xác nhận mật khẩu không khớp.';
        }

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

        $avatar_url = 'https://picsum.photos/id/' . rand(1, 1000) . '/300';
        $userDataToInsert = [
            'username' => $username,
            'password' => $hashedPassword,
            'full_name' => $full_name,
            'email' => $email,
            'phone_number' => $phone_number,
            'address' => $address,
            'avatar_url' => $avatar_url,
            'role' => $role,
        ];

        $newUserId = $this->authModel->createUser($userDataToInsert);

        if ($newUserId !== false) {
            unset($userDataToInsert['password']);
            $userDataToInsert['user_id'] = $newUserId;
            Utils::respond(["success" => true, "message" => "Đăng ký tài khoản thành công!", "user" => $userDataToInsert], 201);
        } else {
            Utils::respond(["success" => false, "message" => "Đã xảy ra lỗi khi tạo tài khoản."], 500);
        }
    }

    public function handleLogin(): void
    {
        $data = json_decode(file_get_contents("php://input"), true);

        $rules = ['username' => 'Tên đăng nhập...', 'password' => 'Mật khẩu...'];
        $validationErrors = Utils::validateBasicInput($data, $rules);
        if (!empty($validationErrors)) {
            Utils::respond(["success" => false, "message" => "Dữ liệu đầu vào không hợp lệ.", "errors" => $validationErrors], 400);
        }

        $username = trim($data['username']);
        $password = $data['password'];

        $formatErrors = [];
        if (!Utils::validateUsernameFormat($username)) {
            $formatErrors['username'] = 'Tên đăng nhập không hợp lệ.';
        }
        if (!empty($formatErrors)) {
            Utils::respond(["success" => false, "message" => "Dữ liệu không đúng định dạng.", "errors" => $formatErrors], 400);
        }

        $userDataFromDb = $this->authModel->getUserLoginDataByUsername($username);

        $isAuthenticated = false;
        $loginMessage = '';
        if ($userDataFromDb === false) {
            $loginMessage = 'Tên đăng nhập không tồn tại.';
        } else {
            // 👉 Kiểm tra nếu tài khoản bị khóa
            if (isset($userDataFromDb['is_active']) && (int)$userDataFromDb['is_active'] === 0) {
                Utils::respond([
                    "success" => false,
                    "message" => "Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên để biết thêm chi tiết."
                ], 403);
            }

            if (password_verify($password, $userDataFromDb['password'])) {
                $isAuthenticated = true;
            } else {
                $loginMessage = 'Mật khẩu không chính xác.';
            }
        }

        if ($isAuthenticated) {
            $payload = ['user_id' => $userDataFromDb['user_id'], 'username' => $userDataFromDb['username'], 'role' => $userDataFromDb['role']];
            $token_lifetime = getenv('JWT_LIFETIME_SECONDS') ?: 3600;
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

    public function handleForgotPassword(): void
    {
        $data = json_decode(file_get_contents("php://input"), true);

        $basicRules = [
            'username' => 'Tên đăng nhập không được để trống',
            'email' => 'Email không được để trống',
            'new_password' => 'Mật khẩu mới không được để trống',
            'password_confirm' => 'Xác nhận mật khẩu mới không được để trống',
        ];
        $basicErrors = Utils::validateBasicInput($data, $basicRules);
        if (!empty($basicErrors)) {
            Utils::respond(["success" => false, "message" => "Thiếu thông tin bắt buộc.", "errors" => $basicErrors], 400);
        }

        $formatErrors = [];
        $username = trim($data['username']);
        $email = trim($data['email']);
        $new_password = $data['new_password'];
        $password_confirm = $data['password_confirm'];

        if (!Utils::validateUsernameFormat($username)) {
            $formatErrors['username'] = 'Tên đăng nhập không hợp lệ.';
        }
        if (!Utils::validateEmailFormat($email)) {
            $formatErrors['email'] = 'Định dạng email không hợp lệ.';
        }
        if (!Utils::validatePasswordComplexity($new_password)) {
            $formatErrors['new_password'] = 'Mật khẩu mới yếu.';
        }
        if ($new_password !== $password_confirm) {
            $formatErrors['password_confirm'] = 'Xác nhận mật khẩu mới không khớp.';
        }
        if (!empty($formatErrors)) {
            Utils::respond(["success" => false, "message" => "Dữ liệu không hợp lệ.", "errors" => $formatErrors], 400);
        }

        $userExists = $this->authModel->findUserByUsernameAndEmail($username, $email);
        if ($userExists === false) {
            Utils::respond(["success" => false, "message" => "Tên đăng nhập hoặc Email không đúng hoặc không thuộc cùng một tài khoản."], 404);
        }

        $userDetails = $this->authModel->getUserLoginDataByUsername($username);
        if ($userDetails && isset($userDetails['role']) && $userDetails['role'] === 'admin') {
            Utils::respond([
                "success" => false,
                "message" => "Tài khoản quản trị viên không thể sử dụng chức năng quên mật khẩu này."
            ], 403);
        }

        $hasPending = $this->authModel->hasPendingPasswordRequest($username);
        if ($hasPending) {
            Utils::respond(["success" => false, "message" => "Bạn đã có yêu cầu đặt lại mật khẩu đang chờ phê duyệt."], 409);
        }

        $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT, ['cost' => 12]);
        if ($hashedPassword === false) {
            error_log("Password hashing failed for forgot password request: " . $username);
            Utils::respond(["success" => false, "message" => "Lỗi hệ thống khi xử lý mật khẩu."], 500);
        }

        $success = $this->authModel->createPasswordResetRequest($username, $email, $hashedPassword);
        if ($success) {
            Utils::respond(["success" => true, "message" => "Yêu cầu đặt lại mật khẩu của bạn đã được gửi. Vui lòng chờ quản trị viên phê duyệt."], 202);
        } else {
            Utils::respond(["success" => false, "message" => "Đã xảy ra lỗi khi gửi yêu cầu. Vui lòng thử lại."], 500);
        }
    }

    public function handleChangePassword(): void
    {
        $userData = AuthMiddleware::isUser();
        $userId = $userData['user_id'];

        $data = json_decode(file_get_contents("php://input"), true);
        $basicRules = [
            'old_password' => 'Mật khẩu cũ không được để trống',
            'new_password' => 'Mật khẩu mới không được để trống',
            'password_confirm' => 'Xác nhận mật khẩu mới không được để trống',
        ];
        $basicErrors = Utils::validateBasicInput($data, $basicRules);
        if (!empty($basicErrors)) {
            Utils::respond(["success" => false, "message" => "Thiếu thông tin.", "errors" => $basicErrors], 400);
        }

        $formatErrors = [];
        $old_password = $data['old_password']; // Không trim
        $new_password = $data['new_password']; // Không trim
        $password_confirm = $data['password_confirm']; // Không trim
        if (!Utils::validatePasswordComplexity($new_password)) {
            $formatErrors['new_password'] = 'Mật khẩu mới yếu.';
        }
        if ($new_password !== $password_confirm) {
            $formatErrors['password_confirm'] = 'Xác nhận mật khẩu mới không khớp.';
        }
        if ($old_password === $new_password) {
            $formatErrors['new_password'] = 'Mật khẩu mới không được trùng mật khẩu cũ.';
        }
        if (!empty($formatErrors)) {
            Utils::respond(["success" => false, "message" => "Dữ liệu không hợp lệ.", "errors" => $formatErrors], 400);
        }

        // Xác minh mật khẩu cũ
        $authVerificationData = $this->authModel->getUserAuthVerificationData($userId);
        if ($authVerificationData === false || !isset($authVerificationData['password'])) {
            Utils::respond(["success" => false, "message" => "Không thể xác thực người dùng."], 500);
        }
        $currentHashedPassword = $authVerificationData['password'];
        if (!password_verify($old_password, $currentHashedPassword)) {
            Utils::respond(["success" => false, "message" => "Mật khẩu cũ không chính xác."], 401);
        }

        // Hash mật khẩu mới
        $newHashedPassword = password_hash($new_password, PASSWORD_DEFAULT, ['cost' => 12]);
        if ($newHashedPassword === false) {
            Utils::respond(["success" => false, "message" => "Lỗi hệ thống khi xử lý mật khẩu mới."], 500);
        }

        // Cập nhật mật khẩu mới và password_changed_at
        $updateSuccess = $this->authModel->updateUserPassword($userId, $newHashedPassword);
        if ($updateSuccess) {
            Utils::respond(["success" => true, "message" => "Đổi mật khẩu thành công."], 200);
        } else {
            Utils::respond(["success" => false, "message" => "Lỗi khi cập nhật mật khẩu."], 500);
        }
    }

    public function listPendingPasswordRequests(): void
    {
        $adminData = AuthMiddleware::isAdmin();

        $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
        $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT, ['options' => ['default' => 10, 'min_range' => 1, 'max_range' => 100]]);
        $sortBy = filter_input(INPUT_GET, 'sort_by', FILTER_SANITIZE_SPECIAL_CHARS) ?: 'created_at';
        $search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS) ?: '';
        $status = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_SPECIAL_CHARS) ?: 'pending';

        $result = $this->authModel->getPasswordRequests($page, $limit, $sortBy, $search, $status);

        $filters = [
            'sort_by' => $sortBy,
            'search' => $search,
            'status' => $status
        ];

        Utils::respond(Utils::buildPaginatedResponse(
            true,
            "Lấy danh sách yêu cầu đổi mật khẩu thành công.",
            $result['requests'] ?? [],
            $page,
            $limit,
            $result['total'] ?? 0,
            $filters
        ), 200);
    }

    public function handleAdminPasswordRequestAction(): void
    {
        $adminData = AuthMiddleware::isAdmin();

        $data = json_decode(file_get_contents("php://input"), true);
        $basicRules = ['request_id' => 'ID yêu cầu...', 'action' => 'Hành động...'];
        $basicErrors = Utils::validateBasicInput($data, $basicRules);
        if (!empty($basicErrors)) {
            Utils::respond(["success" => false, "message" => "Thiếu thông tin.", "errors" => $basicErrors], 400);
        }

        $requestId = filter_var($data['request_id'], FILTER_VALIDATE_INT);
        $action = strtolower(trim($data['action']));
        if ($requestId === false || $requestId <= 0) {
            Utils::respond(["success" => false, "message" => "ID yêu cầu không hợp lệ."], 400);
        }
        if (!in_array($action, ['approve', 'reject'])) {
            Utils::respond(["success" => false, "message" => "Hành động không hợp lệ."], 400);
        }

        if ($action === 'approve') {
            $requestDetails = $this->authModel->getPendingPasswordRequestById($requestId);
            if (!$requestDetails) {
                Utils::respond(["success" => false, "message" => "Không tìm thấy yêu cầu [{$requestId}]."], 404);
            }

            $targetUserData = $this->authModel->getUserLoginDataByUsername($requestDetails['username']);
            if ($targetUserData && isset($targetUserData['role']) && $targetUserData['role'] === 'admin') {
                $this->authModel->updatePasswordRequestStatus($requestId, 'rejected');
                error_log(
                    "Admin '{$adminData['username']}' attempted to approve pwd reset for admin '{$requestDetails['username']}'. Req {$requestId} rejected."
                );
                Utils::respond(["success" => false, "message" => "Không được phép phê duyệt yêu cầu cho tài khoản quản trị viên."], 403);
            }

            $updateSuccess = $this->authModel->updateUserPasswordByUsername($requestDetails['username'], $requestDetails['new_password']);
            if (!$updateSuccess) {
                Utils::respond(["success" => false, "message" => "Lỗi cập nhật mật khẩu user."], 500);
            }

            $this->authModel->updatePasswordRequestStatus($requestId, 'done');
            Utils::respond(["success" => true, "message" => "Đã phê duyệt và cập nhật mật khẩu."], 200);

        } elseif ($action === 'reject') {
            $statusUpdateSuccess = $this->authModel->updatePasswordRequestStatus($requestId, 'rejected');
            if ($statusUpdateSuccess) {
                Utils::respond(["success" => true, "message" => "Đã từ chối yêu cầu."], 200);
            } else {
                Utils::respond(["success" => false, "message" => "Lỗi khi cập nhật trạng thái."], 500);
            }
        }
    }
}
