<?php

class Database
{
  private $conn;

  public function getConnection()
  {
    $host = 'localhost';
    $db_name = 'pro1014_schema';
    $username = 'root';
    $password = '';

//    $host = 'sql304.infinityfree.com';
//    $db_name = 'if0_38617874_pro1014_schema';
//    $username =  'if0_38617874';
//    $password =  'sql304.infinityfree.com';

    $this->conn = null;

    try {
      $dsn = "mysql:host=$host;dbname=$db_name;charset=utf8mb4";
      $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_PERSISTENT => true,
        PDO::ATTR_EMULATE_PREPARES => false,
      ];

      $this->conn = new PDO($dsn, $username, $password, $options);
    } catch (PDOException $exception) {
      http_response_code(500);
      echo json_encode(["error" => "Không thể kết nối đến cơ sở dữ liệu."]);
      die();
    }

    return $this->conn;
  }
}
