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
    public function getAllUser(int $page = 1, int $limit = 10): array
    {
        try {
            // check admin role
            $this->isAdmin->IsAdmin();

            // set limit and offset
            $offset = ($page - 1) * $limit;

            // count total user in database
            $countSql = "SELECT COUNT(*) FROM " . self::$table_name . " WHERE role != 'admin'";
            $stmtCount = $this->conn->prepare($countSql);
            $stmtCount->execute();
            $totalItems = $stmtCount->fetchColumn();

            // get all user, limit by page
            $query = "SELECT * FROM " . self::$table_name . " 
                      WHERE role != 'admin'
                      LIMIT :limit OFFSET :offset";
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            //    remove password field
            foreach ($users as $key => $user) {
                unset($users[$key]['password']);
            }

            return [
                "success" => true,
                "message" => "Get all user successfully",
                "pagination" => [
                    "current_page" => $page,
                    "limit" => $limit,
                    "total_items" => (int) $totalItems,
                    "total_pages" => (int) ceil($totalItems / $limit)
                ],
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