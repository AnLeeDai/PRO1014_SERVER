<?php

require_once __DIR__ . "/../models/UserModel.php";
require_once __DIR__ . "/../../helper/utils.php";

class UserController
{
    private UserModel $userModel;
    private Utils $utils;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->utils = new Utils();
    }

    // Xử lý lấy danh sách user
    public function handleGetAllUser(): void
    {
        // Lấy dữ liệu từ query params
        $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
        $limitPerPage = filter_input(INPUT_GET, 'limitPerPage', FILTER_VALIDATE_INT) ?: 10;


        // Lấy dữ liệu từ query params
        $search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
        $sort_by = strtolower($_GET['sort_by'] ?? 'desc');

        // Đảm bảo sort_by chỉ nhận giá trị hợp lệ
        if (!in_array($sort_by, ['asc', 'desc'])) {
            $sort_by = 'desc';
        }

        // Gọi model để lấy danh sách user
        $users = $this->userModel->getAllUser($page, $limitPerPage, $sort_by, $search);

        $this->utils->respond($users, $users['success'] ? 200 : 400);

        echo json_encode($users);
    }

    // Lấy thông tin user theo id
    public function handleGetUserById(): void
    {
        if (!isset($_SESSION['user'])) {
            $this->utils->respond(["success" => false, "message" => "Bạn chưa đăng nhập"], 401);
            return;
        }

        // lấy id từ session
        $id = $_SESSION['user']['user_id'] ?? null;

        // Gọi model để lấy thông tin user
        $user = $this->userModel->getUserById($id);

        echo json_encode($user);
    }

    // Chỉnh sửa thông tin
    function handlerEditProfile(): void
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!isset($_SESSION['user'])) {
            $this->utils->respond(["success" => false, "message" => "Bạn chưa đăng nhập"], 401);
            return;
        }

        if (!$data) {
            $this->utils->respond(["success" => false, "message" => "Dữ liệu không hợp lệ"], 400);
            return;
        }

        // Validate các trường bắt buộc
        $this->utils->validateInput($data, [
            'full_name' => 'Họ tên không được để trống',
            'email' => 'Email không được để trống',
            'phone_number' => 'Số điện thoại không được để trống',
            'address' => 'Địa chỉ không được để trống',
        ]);

        // Validate định dạng
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $this->utils->respond(["success" => false, "message" => "Email không hợp lệ"], 400);
            return;
        }

        if (!preg_match('/^0[0-9]{9,10}$/', $data['phone_number']) || strlen($data['phone_number']) < 10) {
            $this->utils->respond(["success" => false, "message" => "Số điện thoại không hợp lệ"], 400);
            return;
        }

        // Gọi model update
        $result = $this->userModel->updateUser(
            $_SESSION['user']['user_id'],
            $data['full_name'],
            $data['email'],
            $data['phone_number'],
            $data['address']
        );

        // Nếu thành công thì cập nhật lại session
        if ($result['success']) {
            $_SESSION['user']['full_name'] = $data['full_name'];
            $_SESSION['user']['email'] = $data['email'];
            $_SESSION['user']['phone_number'] = $data['phone_number'];
            $_SESSION['user']['address'] = $data['address'];
        }

        $this->utils->respond($result, $result['success'] ? 200 : 400);
    }

    function handlerUpdateAvatar(): void
    {
        if (!isset($_SESSION['user'])) {
            $this->utils->respond($this->utils->buildResponse(false, "Bạn chưa đăng nhập"), 401);
            return;
        }

        if (!isset($_FILES['avatar'])) {
            $this->utils->respond($this->utils->buildResponse(false, "Không tìm thấy file ảnh"), 400);
            return;
        }

        $username = $_SESSION['user']['username'] ?? 'user_' . $_SESSION['user']['user_id'];

        // XÓA ẢNH CŨ nếu tồn tại
        if (!empty($_SESSION['user']['avatar'])) {
            $oldUrl = $_SESSION['user']['avatar'];
            $parsedUrl = parse_url($oldUrl);
            $relativePath = ltrim($parsedUrl['path'] ?? '', '/');
            $oldFilePath = "C:/laragon/www/" . $relativePath;

            if (file_exists($oldFilePath)) {
                unlink($oldFilePath);
            }
        }

        // Upload ảnh mới với tên = username
        $uploadResult = Utils::uploadImage($_FILES['avatar'], $username, $username);

        if (!$uploadResult['success']) {
            $this->utils->respond($this->utils->buildResponse(false, $uploadResult['message']), 400);
            return;
        }

        // Cập nhật DB & session
        $this->userModel->updateAvatar($_SESSION['user']['user_id'], $uploadResult['url']);
        $_SESSION['user']['avatar'] = $uploadResult['url'];

        $response = $this->utils->buildResponse(true, "Cập nhật ảnh đại diện thành công", [
            "avatar_url" => $uploadResult['url']
        ]);

        $this->utils->respond($response);
    }
}
