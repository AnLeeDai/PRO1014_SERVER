<?php
require_once __DIR__ . "/../../config/database.php";

class UserModel
{
    private $conn;
    private $table_name = "users";

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function getUsers()
    {
        $query = "SELECT id, name, email FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createUser($name, $email)
    {
        try {
            $query = "INSERT INTO " . $this->table_name . " (name, email) VALUES (:name, :email)";
            $stmt = $this->conn->prepare($query);

            $stmt->bindParam(":name", $name);
            $stmt->bindParam(":email", $email);

            if ($stmt->execute()) {
                return ["success" => true, "message" => "User created successfully"];
            } else {
                return ["success" => false, "message" => "Failed to create user"];
            }
        } catch (PDOException $e) {
            return ["success" => false, "message" => "Database error: " . $e->getMessage()];
        }
    }

    public function updateUser($id, $name, $email)
    {
        try {
            $query = "UPDATE " . $this->table_name . " SET name = :name, email = :email WHERE id = :id";
            $stmt = $this->conn->prepare($query);

            $stmt->bindParam(":id", $id);
            $stmt->bindParam(":name", $name);
            $stmt->bindParam(":email", $email);

            if ($stmt->execute()) {
                return ["success" => true, "message" => "User updated successfully"];
            } else {
                return ["success" => false, "message" => "Failed to update user"];
            }
        } catch (PDOException $e) {
            return ["success" => false, "message" => "Database error: " . $e->getMessage()];
        }
    }

    public function deleteUser($id)
    {
        try {
            $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $id);

            if ($stmt->execute()) {
                return ["success" => true, "message" => "User deleted successfully"];
            } else {
                return ["success" => false, "message" => "Failed to delete user"];
            }
        } catch (PDOException $e) {
            return ["success" => false, "message" => "Database error: " . $e->getMessage()];
        }
    }
}
