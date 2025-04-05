<?php

class UserController
{
    private UserModel $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    public function handleUpdateAvatar(): void
    {
        AuthMiddleware::isUser();

        // Kiểm tra user_id
        $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        if (!$userId || $userId <= 0) {
            Utils::respond(['success' => false, 'message' => 'ID người dùng không hợp lệ.'], 400);
        }

        // Kiểm tra user có tồn tại
        $user = $this->userModel->getUserById($userId);
        if (!$user) {
            Utils::respond(['success' => false, 'message' => 'Không tìm thấy người dùng.'], 404);
        }

        // Kiểm tra file upload
        if (!isset($_FILES['avatar'])) {
            Utils::respond(['success' => false, 'message' => 'Vui lòng gửi ảnh avatar.'], 400);
        }

        $uploadResult = Utils::uploadImage($_FILES['avatar'], 'avatar', $user['username'] ?? null);

        if (!$uploadResult['success']) {
            Utils::respond(['success' => false, 'message' => 'Lỗi upload ảnh: ' . $uploadResult['message']], 400);
        }

        $newAvatarUrl = $uploadResult['url'];

        $updated = $this->userModel->updateUserAvatar($userId, $newAvatarUrl);

        if ($updated) {
            $updatedUser = $this->userModel->getUserById($userId);
            Utils::respond([
                'success' => true,
                'message' => 'Cập nhật ảnh đại diện thành công.',
                'user' => $updatedUser
            ], 200);
        } else {
            Utils::respond(['success' => false, 'message' => 'Không thể cập nhật ảnh đại diện.'], 500);
        }
    }

    public function handleUpdateUserProfile(): void
    {
        AuthMiddleware::isUser();

        $data = json_decode(file_get_contents("php://input"), true);

        $basicRules = [
            'user_id' => 'ID người dùng không được để trống',
            'full_name' => 'Họ tên không được để trống',
            'email' => 'Email không được để trống',
        ];
        $validationErrors = Utils::validateBasicInput($data, $basicRules);
        if (!empty($validationErrors)) {
            Utils::respond([
                'success' => false,
                'message' => 'Thiếu thông tin bắt buộc.',
                'errors' => $validationErrors
            ], 400);
        }

        // Chuẩn hóa dữ liệu đầu vào
        $userId = filter_var($data['user_id'], FILTER_VALIDATE_INT);
        $fullName = trim($data['full_name']);
        $email = trim($data['email']);
        $phoneNumber = isset($data['phone_number']) ? trim($data['phone_number']) : null;
        $address = isset($data['address']) ? trim($data['address']) : null;

        $formatErrors = [];

        // Validate định dạng
        if (!Utils::validateEmailFormat($email)) {
            $formatErrors['email'] = 'Định dạng email không hợp lệ.';
        }

        if (!empty($phoneNumber) && !Utils::validatePhoneNumberVN($phoneNumber)) {
            $formatErrors['phone_number'] = 'Số điện thoại không hợp lệ.';
        }

        if (!empty($formatErrors)) {
            Utils::respond([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ.',
                'errors' => $formatErrors
            ], 400);
        }

        // Kiểm tra user hiện tại
        $existingUser = $this->userModel->getUserById($userId);
        if (!$existingUser) {
            Utils::respond([
                'success' => false,
                'message' => 'Không tìm thấy người dùng.'
            ], 404);
        }

        // Nếu email thay đổi → kiểm tra trùng
        if ($email !== $existingUser['email']) {
            if ($this->userModel->findUserByEmail($email)) {
                Utils::respond([
                    'success' => false,
                    'message' => 'Email này đã được sử dụng bởi người khác.',
                    'errors' => ['email' => 'Email đã tồn tại.']
                ], 409);
            }
        }

        // Cập nhật
        $success = $this->userModel->updateUserProfile($userId, $fullName, $email, $phoneNumber ?? '', $address ?? '');

        if ($success) {
            $updatedUser = $this->userModel->getUserById($userId);
            Utils::respond([
                'success' => true,
                'message' => 'Cập nhật thông tin thành công.',
                'user' => $updatedUser
            ], 200);
        } else {
            Utils::respond([
                'success' => false,
                'message' => 'Cập nhật thất bại hoặc không có thay đổi nào.'
            ], 500);
        }
    }

    public function handleGetUserById(): void
    {
        AuthMiddleware::isUser();

        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if ($id === false || $id === null || $id <= 0) {
            Utils::respond([
                'success' => false,
                'message' => 'ID không hợp lệ.'
            ], 400);
            return;
        }

        $user = $this->userModel->getUserById($id);

        if (!$user) {
            Utils::respond([
                'success' => false,
                'message' => "Không tìm thấy người dùng với ID {$id}."
            ], 404);
            return;
        }

        Utils::respond([
            'success' => true,
            'message' => 'Lấy thông tin người dùng thành công.',
            'user' => $user
        ], 200);
    }


    public function handleListUsers(): void
    {
        AuthMiddleware::isAdmin();

        $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
        $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT, ['options' => ['default' => 10, 'min_range' => 1, 'max_range' => 100]]);
        $sortBy = filter_input(INPUT_GET, 'sort_by', FILTER_SANITIZE_SPECIAL_CHARS) ?: 'created_at';
        $search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS) ?: '';

        $result = $this->userModel->getUsersPaginated($page, $limit, $sortBy, $search);

        $filters = [
            'sort_by' => $sortBy,
            'search' => $search
        ];

        Utils::respond(Utils::buildPaginatedResponse(
            true,
            "Lấy danh sách người dùng thành công.",
            $result['users'] ?? [],
            $page,
            $limit,
            $result['total'] ?? 0,
            $filters
        ), 200);
    }
}
