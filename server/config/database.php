<?php

class Database
{
  private $conn;

  public function getConnection()
  {
    $host = getenv('DB_HOST') ?: 'localhost';
    $db_name = getenv('DB_NAME') ?: 'pro1014_schema';
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASS') ?: '';

    $this->conn = null;
    try {
      // Thêm charset=utf8mb4 để hỗ trợ Unicode tốt hơn
      $dsn = "mysql:host=$host;dbname=$db_name;charset=utf8mb4";
      $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_PERSISTENT => true, // Giữ kết nối lâu dài để tối ưu hiệu suất
        PDO::ATTR_EMULATE_PREPARES => false, // Tắt mô phỏng prepared statements để tăng bảo mật
      ];

      $this->conn = new PDO($dsn, $username, $password, $options);
    } catch (PDOException $exception) {
      http_response_code(500);
      echo json_encode(["error" => "Không thể kết nối đến cơ sở dữ liệu. Vui lòng thử lại sau."]);
      die();
    }

    return $this->conn;
  }
}
