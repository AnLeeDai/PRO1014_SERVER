<?php
require_once __DIR__ . "/../../config/database.php";

class AuthModel
{
  private ?PDO $conn;
  private string $table_name = "users";

  public function __construct()
  {
    $database = new Database();
    $this->conn = $database->getConnection();
  }

  // Đăng ký người dùng
  public function register(
    string $username,
    string $password,
    string $full_name,
    string $email,
    string $phone_number,
    string $address,
    string $avatar_url,
    string $role
  ): array {
    try {
      // Kiểm tra email hoặc tài khoản admin đã tồn tại chưa
      $queryCheck = "SELECT email, role FROM {$this->table_name} WHERE email = :email OR role = 'admin' LIMIT 1";
      $stmtCheck = $this->conn->prepare($queryCheck);
      $stmtCheck->bindParam(':email', $email, PDO::PARAM_STR);
      $stmtCheck->execute();
      $existingUser = $stmtCheck->fetch(PDO::FETCH_ASSOC);

      if ($existingUser) {
        if ($existingUser['email'] === $email) {
          return ["success" => false, "message" => "Email đã được sử dụng"];
        }
        if ($role === 'admin' && $existingUser['role'] === 'admin') {
          return ["success" => false, "message" => "Tài khoản admin đã tồn tại"];
        }
      }

      // Chèn dữ liệu mới vào database
      $queryInsert = "INSERT INTO {$this->table_name} 
                      (username, password, full_name, email, phone_number, address, avatar_url, role) 
                      VALUES (:username, :password, :full_name, :email, :phone_number, :address, :avatar_url, :role)";
      $stmtInsert = $this->conn->prepare($queryInsert);
      $stmtInsert->execute([
        'username' => $username,
        'password' => $password,
        'full_name' => $full_name,
        'email' => $email,
        'phone_number' => $phone_number,
        'address' => $address,
        'avatar_url' => $avatar_url,
        'role' => $role
      ]);

      return ["success" => true, "message" => "Đăng ký tài khoản thành công"];
    } catch (PDOException $e) {
      return ["success" => false, "message" => "Database error: " . $e->getMessage()];
    }
  }

  // Đăng nhập người dùng
  public function login(string $email, string $password): array
  {
    try {
      $query = "SELECT * FROM {$this->table_name} WHERE email = :email LIMIT 1";
      $stmt = $this->conn->prepare($query);
      $stmt->bindParam(':email', $email, PDO::PARAM_STR);
      $stmt->execute();
      $user = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user'] = $user;
        unset($user['password'], $user['user_id'], $user['role']);

        return ["success" => true, "message" => "Đăng nhập thành công", "data" => $user];
      }

      return ["success" => false, "message" => $user ? "Mật khẩu không chính xác" : "Email không tồn tại trên hệ thống"];
    } catch (PDOException $e) {
      return ["success" => false, "message" => "Database error: " . $e->getMessage()];
    }
  }

  // Đổi mật khẩu
  public function changePassword(string $email, string $old_password, string $new_password): array
  {
    try {
      $query = "SELECT password FROM {$this->table_name} WHERE email = :email LIMIT 1";
      $stmt = $this->conn->prepare($query);
      $stmt->bindParam(':email', $email, PDO::PARAM_STR);
      $stmt->execute();
      $user = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$user) {
        return ["success" => false, "message" => "Email không tồn tại trên hệ thống"];
      }

      if (!password_verify($old_password, $user['password'])) {
        return ["success" => false, "message" => "Mật khẩu cũ không chính xác"];
      }

      // Cập nhật mật khẩu mới
      $queryUpdate = "UPDATE {$this->table_name} SET password = :password WHERE email = :email";
      $stmt = $this->conn->prepare($queryUpdate);
      $stmt->bindParam(':password', $new_password, PDO::PARAM_STR);
      $stmt->bindParam(':email', $email, PDO::PARAM_STR);
      $stmt->execute();

      return ["success" => true, "message" => "Đổi mật khẩu thành công"];
    } catch (PDOException $e) {
      return ["success" => false, "message" => "Database error: " . $e->getMessage()];
    }
  }

  // Quên mật khẩu
  public function forgotPassword(string $email, string $new_password): array
  {
    try {
      $query = "SELECT email FROM {$this->table_name} WHERE email = :email LIMIT 1";
      $stmt = $this->conn->prepare($query);
      $stmt->bindParam(':email', $email, PDO::PARAM_STR);
      $stmt->execute();
      $user = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$user) {
        return ["success" => false, "message" => "Email không tồn tại trên hệ thống"];
      }

      // Cập nhật mật khẩu
      $queryUpdate = "UPDATE {$this->table_name} SET password = :password WHERE email = :email";
      $stmt = $this->conn->prepare($queryUpdate);
      $stmt->bindParam(':password', $new_password, PDO::PARAM_STR);
      $stmt->bindParam(':email', $email, PDO::PARAM_STR);
      $stmt->execute();

      return ["success" => true, "message" => "Đổi mật khẩu thành công"];
    } catch (PDOException $e) {
      return ["success" => false, "message" => "Database error: " . $e->getMessage()];
    }
  }
}
