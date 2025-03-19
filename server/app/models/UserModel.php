<?php

require_once __DIR__ . "/../controllers/AuthController.php";
require_once __DIR__ . "/../../helper/middleware.php";

class UserModel
{
    private ?PDO $conn;
    private static $table_name = "users";
    private $isAdmin;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
        $this->isAdmin = new Middleware();
    }

    // get all user model
    public function getAllUser(): array
    {
        try {
            // check admin role
            $this->isAdmin->IsAdmin();

            $query = "SELECT * FROM " . self::$table_name;
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // remove password field
            foreach ($users as $key => $user) {
                unset($users[$key]['password']);
            }

            // remove admin user
            $users = array_filter($users, function ($user) {
                return $user['role'] !== 'admin';
            });

            return [
                "success" => true,
                "message" => "Get all user successfully",
                "data" => $users
            ];
        } catch (PDOException $e) {
            return [
                "success" => false,
                "message" => "Error: " . $e->getMessage(),
                "data" => []
            ];
        }
    }
}
