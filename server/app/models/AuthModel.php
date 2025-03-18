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

    // register user model
    public function register(
        $username,
        $password,
        $full_name,
        $email,
        $phone_number,
        $address,
        $avatar_url
    ): array {
        try {
            $query = "INSERT INTO " . $this->table_name .
                " (username, password, full_name, email, phone_number, address, avatar_url) 
            VALUES (:username, :password, :full_name, :email, :phone_number, :address, :avatar_url)";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':password', $password);
            $stmt->bindParam(':full_name', $full_name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone_number', $phone_number);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':avatar_url', $avatar_url);
            $stmt->execute();

            return [
                "success" => true,
                "message" => "User registered successfully"
            ];
        } catch (Exception $e) {
            // check if username already exists
            if ($e->getCode() == 23000) {
                return [
                    "success" => false,
                    "message" => "Username already exists"
                ];
            }

            return [
                "success" => false,
                "message" => "Database error: " . $e->getMessage()
            ];
        }
    }

    // login user model
    public function login($username, $password): array
    {
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE username = :username";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // remove password from user data
                unset($user['password']);

                return [
                    "success" => true,
                    "message" => "Login successfully",
                    "data" => $user
                ];
            }

            // check username in db
            if (!$user) {
                return [
                    "success" => false,
                    "message" => "Username not found"
                ];
            }

            return [
                "success" => false,
                "message" => "Password not match"
            ];
        } catch (Exception $e) {
            return [
                "success" => false,
                "message" => "Database error: " . $e->getMessage()
            ];
        }
    }
}