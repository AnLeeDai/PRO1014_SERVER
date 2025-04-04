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
        if ($this->conn === null) {
            error_log("AuthModel Error: Failed to get DB connection.");
        }
    }

    public function hasAdminAccount(): bool
    {
        if ($this->conn === null) {
            error_log("AuthModel::hasAdminAccount Error: Database connection is not available.");
            return false;
        }
        try {
            $query = "SELECT 1 FROM {$this->table_name} WHERE role = 'admin' LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();

            return $stmt->fetchColumn() !== false;

        } catch (PDOException $e) {
            error_log("Database error checking for admin account: " . $e->getMessage());
            return false;
        }
    }

    public function findUserByUsername(string $username): array|false
    {
        if ($this->conn === null) return false;

        try {
            $query = "SELECT user_id, username FROM {$this->table_name} WHERE username = :username LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("DB Error finding user by username '{$username}': " . $e->getMessage());
            return false;
        }
    }

    public function findUserByEmail(string $email): array|false
    {
        if ($this->conn === null) return false;

        try {
            $query = "SELECT user_id, email FROM {$this->table_name} WHERE email = :email LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("DB Error finding user by email '{$email}': " . $e->getMessage());
            return false;
        }
    }

    public function createUser(array $userData): int|false
    {
        if ($this->conn === null) return false;

        $fields = implode(', ', array_keys($userData));
        $placeholders = ':' . implode(', :', array_keys($userData));
        $query = "INSERT INTO {$this->table_name} ({$fields}) VALUES ({$placeholders})";

        try {
            $stmt = $this->conn->prepare($query);

            foreach ($userData as $key => &$value) {
                $paramType = PDO::PARAM_STR;
                if (is_int($value)) {
                    $paramType = PDO::PARAM_INT;
                } elseif (is_bool($value)) {
                    $paramType = PDO::PARAM_BOOL;
                } elseif (is_null($value)) {
                    $paramType = PDO::PARAM_NULL;
                }
                $stmt->bindParam(":$key", $value, $paramType);
            }

            unset($value);

            $success = $stmt->execute();

            if ($success) {
                return (int)$this->conn->lastInsertId();
            } else {
                error_log("DB Error creating user: Failed to execute statement.");
                error_log(print_r($stmt->errorInfo(), true));
                return false;
            }
        } catch (PDOException $e) {
            error_log("DB Error creating user: " . $e->getMessage());
            return false;
        }
    }

    public function getUserLoginDataByUsername(string $username): array|false
    {
        if ($this->conn === null) return false;

        try {
            $query = "SELECT user_id, username, password, full_name, email, role, avatar_url FROM {$this->table_name} WHERE username = :username LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database error in AuthModel::getUserLoginDataByUsername for user '{$username}': " . $e->getMessage());
            return false;
        }
    }
}