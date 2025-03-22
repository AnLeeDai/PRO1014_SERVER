<?php
require_once __DIR__ . "/../models/AuthModel.php";
require_once __DIR__ . "/../../helper/utils.php";

class AuthController
{
  private AuthModel $authModel;
  private Utils $utils;

  public function __construct()
  {
    $this->authModel = new AuthModel();
    $this->utils = new Utils();
  }

  // Xử lý đăng ký tài khoản
  public function handleRegister(): void
  {
    // Nhận dữ liệu từ request
    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data) {
      $this->utils->respond(["success" => false, "message" => "Dữ liệu không hợp lệ"], 400);
    }

    // Validate dữ liệu đầu vào
    $this->utils->validateInput($data, [
      'username' => 'Tên đăng nhập không được để trống',
      'password' => 'Mật khẩu không được để trống',
      'full_name' => 'Họ tên không được để trống',
      'password_confirm' => 'Xác nhận mật khẩu không được để trống',
      'email' => 'Email không được để trống'
    ]);

    // Lấy dữ liệu từ request
    $username = trim($data['username']);
    $password = trim($data['password']);
    $password_confirm = trim($data['password_confirm']);
    $email = trim($data['email']);
    $phone_number = trim($data['phone_number'] ?? '');
    $address = trim($data['address'] ?? '');
    $role = trim($data['role'] ?? 'user');

    // Kiểm tra định dạng email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $this->utils->respond(["success" => false, "message" => "Email không hợp lệ"], 400);
    }

    // Kiểm tra role hợp lệ
    if (!in_array($role, ['user', 'admin'])) {
      $this->utils->respond(["success" => false, "message" => "Role không hợp lệ"], 400);
    }

    // Kiểm tra định dạng username
    if (!preg_match('/^[a-zA-Z0-9]{6,}$/', $username)) {
      $this->utils->respond(["success" => false, "message" => "Tên đăng nhập phải có ít nhất 6 ký tự, chỉ chứa chữ cái và số"], 400);
    }

    // Kiểm tra định dạng password
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9]).{6,}$/', $password)) {
      $this->utils->respond(["success" => false, "message" => "Mật khẩu phải có ít nhất 6 ký tự, gồm chữ hoa, chữ thường, số và ký tự đặc biệt"], 400);
    }

    // Kiểm tra số điện thoại hợp lệ
    if (!empty($phone_number) && (!preg_match('/^0[0-9]{9,10}$/', $phone_number) || strlen($phone_number) < 10)) {
      $this->utils->respond(["success" => false, "message" => "Số điện thoại không hợp lệ"], 400);
    }

    // Kiểm tra xác nhận mật khẩu
    if ($password !== $password_confirm) {
      $this->utils->respond(["success" => false, "message" => "Mật khẩu không khớp"], 400);
    }

    // Mã hóa mật khẩu với cost=12
    $hashed_password = password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);

    // Avatar mặc định
    $avatar_url = "https://picsum.photos/seed/picsum/200/300";

    // Gọi model để xử lý đăng ký
    $result = $this->authModel->register(
      $username,
      $hashed_password,
      trim($data['full_name']),
      $email,
      $phone_number,
      $address,
      $avatar_url,
      $role
    );

    // Trả về phản hồi
    $this->utils->respond($result, $result['success'] ? 200 : 400);
  }

  // Xử lý đăng nhập
  public function handleLogin(): void
  {
    $data = json_decode(file_get_contents("php://input"), true);
    $this->utils->validateInput($data, [
      'email' => 'Email không được để trống',
      'password' => 'Mật khẩu không được để trống'
    ]);

    $result = $this->authModel->login(trim($data['email']), trim($data['password']));
    $this->utils->respond($result, $result['success'] ? 200 : 400);
  }

  // Xử lý đổi mật khẩu
  public function handleChangePassword(): void
  {
    $data = json_decode(file_get_contents("php://input"), true);
    $this->utils->validateInput($data, [
      'email' => 'Email không được để trống',
      'old_password' => 'Mật khẩu cũ không được để trống',
      'new_password' => 'Mật khẩu mới không được để trống'
    ]);

    $new_password = trim($data['new_password']);
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9]).{6,}$/', $new_password)) {
      $this->utils->respond(["success" => false, "message" => "Mật khẩu không đúng định dạng"], 400);
    }

    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT, ['cost' => 12]);

    $result = $this->authModel->changePassword(
      trim($data['email']),
      trim($data['old_password']),
      $hashed_password
    );

    $this->utils->respond($result, $result['success'] ? 200 : 400);
  }

  // Xử lý quên mật khẩu
  public function handleForgotPassword(): void
  {
    $data = json_decode(file_get_contents("php://input"), true);
    $this->utils->validateInput($data, [
      'email' => 'Email không được để trống',
      'new_password' => 'Mật khẩu mới không được để trống'
    ]);

    $new_password = trim($data['new_password']);
    if (!filter_var(trim($data['email']), FILTER_VALIDATE_EMAIL)) {
      $this->utils->respond(["success" => false, "message" => "Email không hợp lệ"], 400);
    }

    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT, ['cost' => 12]);

    $result = $this->authModel->forgotPassword(trim($data['email']), $hashed_password);
    $this->utils->respond($result, $result['success'] ? 200 : 400);
  }

  // Xử lý đăng xuất
  public function handleLogout(): void
  {
    if (!isset($_SESSION) || !isset($_SESSION['user'])) {
      $this->utils->respond(["success" => false, "message" => "Bạn chưa đăng nhập"], 400);
    }

    session_unset();
    session_destroy();
    $this->utils->respond(["success" => true, "message" => "Đăng xuất thành công"], 200);
  }
}
