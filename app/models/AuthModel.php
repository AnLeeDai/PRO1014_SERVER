<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helper/middleware.php";
require_once __DIR__ . "/../../helper/utils.php";

class AuthModel
{
    private ?PDO $conn;
    private string $table_name = "users";
    private Middleware $isAdmin;
    private Utils $utils;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
        $this->isAdmin = new Middleware();
        $this->utils = new Utils();
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
    ): array
    {
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
    public function login(string $username, string $password): array
    {
        try {
            $query = "SELECT * FROM {$this->table_name} WHERE username = :username LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user'] = $user;
                unset($user['password'], $user['user_id']);

                return ["success" => true, "message" => "Đăng nhập thành công", "data" => $user];
            }

            return ["success" => false, "message" => $user ? "Mật khẩu không chính xác" : "Username không tồn tại trên hệ thống"];
        } catch (PDOException $e) {
            return ["success" => false, "message" => "Database error: " . $e->getMessage()];
        }
    }

    // Đổi mật khẩu
    public function changePassword(string $username, string $old_password, string $new_password): array
    {
        try {
            $query = "SELECT password FROM {$this->table_name} WHERE username = :username LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return ["success" => false, "message" => "Username không tồn tại trên hệ thống"];
            }

            if (!password_verify($old_password, $user['password'])) {
                return ["success" => false, "message" => "Mật khẩu cũ không chính xác"];
            }

            // Cập nhật mật khẩu mới
            $queryUpdate = "UPDATE {$this->table_name} SET password = :password WHERE username = :username";
            $stmt = $this->conn->prepare($queryUpdate);
            $stmt->bindParam(':password', $new_password, PDO::PARAM_STR);
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->execute();

            return ["success" => true, "message" => "Đổi mật khẩu thành công"];
        } catch (PDOException $e) {
            return ["success" => false, "message" => "Database error: " . $e->getMessage()];
        }
    }

    // Quên mật khẩu
    public function forgotPassword(string $username, string $email, string $new_password): array
    {
        try {
            // Kiểm tra username có tồn tại
            $queryUsername = "SELECT username FROM {$this->table_name} WHERE username = :username LIMIT 1";
            $stmtUsername = $this->conn->prepare($queryUsername);
            $stmtUsername->bindParam(':username', $username, PDO::PARAM_STR);
            $stmtUsername->execute();
            $userUsername = $stmtUsername->fetch(PDO::FETCH_ASSOC);

            if (!$userUsername) {
                return ["success" => false, "message" => "Username không tồn tại trên hệ thống"];
            }

            // Kiểm tra email có tồn tại
            $queryEmail = "SELECT email FROM {$this->table_name} WHERE email = :email LIMIT 1";
            $stmtEmail = $this->conn->prepare($queryEmail);
            $stmtEmail->bindParam(':email', $email, PDO::PARAM_STR);
            $stmtEmail->execute();
            $userEmail = $stmtEmail->fetch(PDO::FETCH_ASSOC);

            if (!$userEmail) {
                return ["success" => false, "message" => "Email không tồn tại trên hệ thống"];
            }

            // Thêm yêu cầu đổi mật khẩu vào bảng
            $queryInsert = "INSERT INTO password_requests (username, email, new_password) VALUES (:username, :email, :new_password)";
            $stmt = $this->conn->prepare($queryInsert);
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':new_password', $new_password, PDO::PARAM_STR);
            $stmt->execute();

            return ["success" => true, "message" => "Yêu cầu đổi mật khẩu đã được ghi nhận, admin sẽ xử lý sơm nhất có thể"];
        } catch (PDOException $e) {
            return ["success" => false, "message" => "Database error: " . $e->getMessage()];
        }
    }

    // Lấy danh sách yêu cầu đổi mật khẩu đang chờ xử lý
    public function getPasswordRequests(
        int    $page = 1,
        int    $limit = 10,
        string $sort_by = 'desc',
        string $search = '',
        string $status = 'pending'
    ): array
    {
        try {
            $this->isAdmin->IsAdmin();

            $offset = ($page - 1) * $limit;

            // Validate sort_by
            $sort_by = strtolower(trim($sort_by));
            $allowedSortValues = ['asc', 'desc'];
            if (!in_array($sort_by, $allowedSortValues)) {
                $sort_by = 'desc';
            }

            // Validate status
            $status = strtolower($status);
            $allowedStatuses = ['pending', 'done'];
            if (!in_array($status, $allowedStatuses)) {
                $status = 'pending';
            }

            // WHERE condition
            $whereConditions = ["status = :status"];
            $params = [':status' => $status];

            if (!empty($search)) {
                $whereConditions[] = "email LIKE :search";
                $params[':search'] = '%' . $search . '%';
            }

            $whereClause = " WHERE " . implode(" AND ", $whereConditions);

            // Count total items
            $countQuery = "SELECT COUNT(*) FROM password_requests" . $whereClause;
            $stmtCount = $this->conn->prepare($countQuery);
            foreach ($params as $key => $value) {
                $stmtCount->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmtCount->execute();
            $totalItems = $stmtCount->fetchColumn();

            // Get list
            $query = "SELECT id, email, created_at, status
                FROM password_requests" . $whereClause .
                " ORDER BY created_at " . strtoupper($sort_by) .
                " LIMIT :limit OFFSET :offset";

            $stmt = $this->conn->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->execute();
            $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->utils->buildResponse(
                true,
                "Lấy dữ liệu thành công",
                $requests,
                $page,
                $limit,
                (int)$totalItems,
                [
                    "search" => $search,
                    "sort_by" => $sort_by,
                    "status" => $status
                ]
            );
        } catch (PDOException $e) {
            return $this->utils->buildResponse(
                false,
                "Database error: " . $e->getMessage()
            );
        }
    }

    // Xử lý yêu cầu đổi mật khẩu của admin
    public function adminChangePassword(int $request_id): array
    {
        try {
            $this->isAdmin->IsAdmin();

            // Lấy request từ bảng
            $query = "SELECT email, new_password FROM password_requests WHERE id = :id AND status = 'pending'";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $request_id, PDO::PARAM_INT);
            $stmt->execute();
            $request = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$request) {
                return ["success" => false, "message" => "Yêu cầu không tồn tại hoặc đã được xử lý"];
            }

            // Cập nhật mật khẩu người dùng
            $queryUpdate = "UPDATE {$this->table_name} SET password = :password WHERE email = :email";
            $stmt = $this->conn->prepare($queryUpdate);
            $stmt->bindParam(':password', $request['new_password'], PDO::PARAM_STR);
            $stmt->bindParam(':email', $request['email'], PDO::PARAM_STR);
            $stmt->execute();

            // Cập nhật trạng thái yêu cầu
            $queryDone = "UPDATE password_requests SET status = 'done' WHERE id = :id";
            $stmt = $this->conn->prepare($queryDone);
            $stmt->bindParam(':id', $request_id, PDO::PARAM_INT);
            $stmt->execute();

            return ["success" => true, "message" => "Đã cập nhật mật khẩu thành công và hoàn tất yêu cầu"];
        } catch (PDOException $e) {
            return ["success" => false, "message" => "Database error: " . $e->getMessage()];
        }
    }
}
