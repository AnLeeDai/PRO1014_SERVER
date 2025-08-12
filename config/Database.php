<?php

class Database
{
    public function getConnection()
    {
    // Read DB config from environment variables with sensible defaults
    $host = getenv('DB_HOST') ?: 'localhost';
    $port = getenv('DB_PORT') ?: '3306';
    $db_name = getenv('DB_NAME') ?: 'pro1014_schema';
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';

        $conn = null;

        try {
            $dsn = "mysql:host=$host;port=$port;dbname=$db_name;charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_PERSISTENT => true,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $conn = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $exception) {
            http_response_code(500);
            echo json_encode(["error" => "Không thể kết nối đến cơ sở dữ liệu."]);
            die();
        }

        return $conn;
    }
}
