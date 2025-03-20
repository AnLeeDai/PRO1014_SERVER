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

  // handle register user controller
  public function handleRegister(): void
  {
    // get request body
    $data = json_decode(file_get_contents("php://input"), true);

    // validate input
    $this->utils->validateInput($data, [
      'username' => 'Tên đăng nhập không được để trống',
      'password' => 'Mật khẩu không được để trống',
      'full_name' => 'Họ tên không được để trống',
    ]);

    // get data from request body
    $username = trim($data['username']) ?? '';
    $password = trim($data['password']) ?? '';
    $password_confirm = trim($data['password_confirm'] ?? '');
    $email = trim($data['email'] ?? '');
    $phone_number = trim($data['phone_number'] ?? '');
    $role = trim($data['role'] ?? 'user');

    // validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $this->utils->respond(["success" => false, "message" => "Email không đúng định dạng"], 400);
    }

    // validate role
    if (!in_array($role, ['user', 'admin'])) {
      $this->utils->respond(["success" => false, "message" => "Role không đúng"], 400);
    }

    // validate username
    if (!preg_match('/^[a-zA-Z0-9]{6,}$/', $username)) {
      $this->utils->respond(["success" => false, "message" => "Tên đăng nhập phải có ít nhất 6 ký tự, chỉ chứa chữ cái và số"], 400);
    }

    // validate password
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9]).{6,}$/', $password)) {
      $this->utils->respond(["success" => false, "message" => "Mật khẩu phải có ít nhất 6 ký tự, gồm chữ hoa, chữ thường, số và ký tự đặc biệt"], 400);
    }

    // validate phone number
    if (!empty($phone_number) && (!preg_match('/^0[0-9]{9,10}$/', $phone_number) || strlen($phone_number) < 10)) {
      $this->utils->respond(["success" => false, "message" => "Số điện thoại không đúng định dạng"], 400);
    }

    // validate password confirm
    if ($password !== $password_confirm) {
      $this->utils->respond(["success" => false, "message" => "Mật khẩu không khớp"], 400);
    }

    // hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT, ['cost' => 10]);

    // set default avatar for user
    $avatar_url = "https://picsum.photos/seed/picsum/200/300";

    // call register function from model
    $result = $this->authModel->register(
      $username,
      $hashed_password,
      trim($data['full_name']),
      $email,
      $phone_number,
      trim($data['address'] ?? ''),
      $avatar_url,
      $role
    );

    // return response
    $this->utils->respond($result, $result['success'] ? 200 : 400);
  }

  // handle login controller
  public function handleLogin(): void
  {
    // get request body
    $data = json_decode(file_get_contents("php://input"), true);

    // validate input
    $this->utils->validateInput($data, [
      'email' => 'Email không được để trống',
      'password' => 'Mật khẩu không được để trống'
    ]);

    // call login function from model
    $result = $this->authModel->login(trim($data['email']), trim($data['password']));

    // return response
    $this->utils->respond($result, $result['success'] ? 200 : 400);
  }

  // handle change password controller
  public function handleChangePassword(): void
  {
    // get request body
    $data = json_decode(file_get_contents("php://input"), true);

    // validate input
    $this->utils->validateInput($data, [
      'email' => 'Email không được để trống',
      'old_password' => 'Mât khẩu cũ không được để trống',
      'new_password' => 'Mật khẩu mới không được để trống'
    ]);

    // validate new password
    $new_password = trim($data['new_password']);
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9]).{6,}$/', $new_password)) {
      $this->utils->respond(["success" => false, "message" => "Mật khẩu phải có ít nhất 6 ký tự, gồm chữ hoa, chữ thường, số và ký tự đặc biệt"], 400);
    }

    // hash new password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT, ['cost' => 10]);

    // call change password function from model
    $result = $this->authModel->changePassword(
      trim($data['email']),
      trim($data['old_password']),
      $hashed_password
    );

    // return response
    $this->utils->respond($result, $result['success'] ? 200 : 400);
  }

  // handle forgot password controller
  public function handleForgotPassword(): void
  {
    // get request body
    $data = json_decode(file_get_contents("php://input"), true);

    // validate input
    $this->utils->validateInput($data, [
      'email' => 'Email không được để trống',
      'new_password' => 'Mật khẩu mới không được để trống'
    ]);

    $new_password = trim($data['new_password']) ?? '';

    // validate email
    if (!filter_var(trim($data['email']), FILTER_VALIDATE_EMAIL)) {
      $this->utils->respond(["success" => false, "message" => "Không tìm thấy Email trên hệ thống"], 400);
    }

    // validate new password
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9]).{6,}$/', $new_password)) {
      $this->utils->respond(["success" => false, "message" => "Mật khẩu phải có ít nhất 6 ký tự, gồm chữ hoa, chữ thường, số và ký tự đặc biệt"], 400);
    }

    // hash new password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT, ['cost' => 10]);

    // call forgot password function from model
    $result = $this->authModel->forgotPassword(
      trim($data['email']),
      $hashed_password
    );

    // return response
    $this->utils->respond($result, $result['success'] ? 200 : 400);
  }

  // logout controller
  public function handleLogout(): void
  {
    // check if user not logged in
    if (!isset($_SESSION['user'])) {
      $this->utils->respond(["success" => false, "message" => "Bạn chưa đăng nhập"], 400);
    }

    // clear session
    session_unset();
    session_destroy();
    // return response
    $this->utils->respond(["success" => true, "message" => "Đăng xuất thành công"], 200);
  }
}
