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

    // register function model
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
            $query = "INSERT INTO {$this->table_name} 
                (username, password, full_name, email, phone_number, address, avatar_url, role) 
                VALUES (:username, :password, :full_name, :email, :phone_number, :address, :avatar_url, :role)";

            $stmt = $this->conn->prepare($query);
            $stmt->execute(compact('username', 'password', 'full_name', 'email', 'phone_number', 'address', 'avatar_url', 'role'));

            // check only create one admin
            if ($role === 'admin') {
                $query = "SELECT COUNT(*) FROM {$this->table_name} WHERE role = 'admin'";
                $stmt = $this->conn->prepare($query);
                $stmt->execute();
                $adminCount = $stmt->fetchColumn();

                if ($adminCount > 1) {
                    return [
                        "success" => false,
                        "message" => "Cannot create multiple admin users"
                    ];
                }
            }

            return [
                "success" => true,
                "message" => "User registered successfully"
            ];
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                return [
                    "success" => false,
                    "message" => "User already registered"
                ];
            }

            return [
                "success" => false,
                "message" => "Database error: " . $e->getMessage()
            ];
        }
    }

    // login function model
    public function login(string $email, string $password): array
    {
        try {
            $query = "SELECT * FROM {$this->table_name} WHERE email = :email";
            $stmt = $this->conn->prepare($query);
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // check if user exist and password match
            if ($user && password_verify($password, $user['password'])) {
                // remove password from user data
                unset($user['password']);

                // save user data to session
                $_SESSION['user'] = $user;

                return [
                    "success" => true,
                    "message" => "Login successfully",
                    "data" => $user
                ];
            }

            return [
                "success" => false,
                "message" => $user ? "Password does not match" : "Email not found"
            ];
        } catch (PDOException $e) {
            return [
                "success" => false,
                "message" => "Database error: " . $e->getMessage()
            ];
        }
    }

    // change password function model
    public function changePassword(string $email, string $old_password, string $hashed_password): array
    {
        try {
            $query = "SELECT password FROM {$this->table_name} WHERE email = :email";
            $stmt = $this->conn->prepare($query);
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // check if user exist and old password match
            if ($user && password_verify($old_password, $user['password'])) {
                $query = "UPDATE {$this->table_name} SET password = :password WHERE email = :email";
                $stmt = $this->conn->prepare($query);
                $stmt->execute(['password' => $hashed_password, 'email' => $email]);

                return [
                    "success" => true,
                    "message" => "Password changed successfully"
                ];
            }

            // check if email not found
            if (!$user) {
                return [
                    "success" => false,
                    "message" => "Email not found"
                ];
            }

            return [
                "success" => false,
                "message" => "Old password is incorrect"
            ];
        } catch (PDOException $e) {
            return [
                "success" => false,
                "message" => "Database error: " . $e->getMessage()
            ];
        }
    }

    // forgot password function model
    public function forgotPassword(string $email, string $hashed_password): array
    {
        try {
            // get user data by email
            $query = "SELECT username, email FROM {$this->table_name} WHERE email = :email";
            $stmt = $this->conn->prepare($query);
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // check if user exist
            if ($user && $email === $user['email']) {
                // Update new password to database
                $query = "UPDATE {$this->table_name} SET password = :password WHERE email = :email";
                $stmt = $this->conn->prepare($query);
                $stmt->execute(['password' => $hashed_password, 'email' => $email]);

                return [
                    "success" => true,
                    "message" => "Password changed successfully"
                ];
            }

            return [
                "success" => false,
                "message" => "Email not found"
            ];
        } catch (PDOException $e) {
            return [
                "success" => false,
                "message" => "Database error: " . $e->getMessage()
            ];
        }
    }
}
