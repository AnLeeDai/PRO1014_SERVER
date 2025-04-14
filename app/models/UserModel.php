<?php

class UserModel
{
    private ?PDO $conn;
    private string $users_table = "users";

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
        if ($this->conn === null) {
            error_log("UserModel Error: Failed to get DB connection.");
        }
    }

    public function reactivateUserById(int $userId): bool
    {
        if ($this->conn === null) return false;

        try {
            $query = "UPDATE {$this->users_table}
                  SET is_active = 1
                  WHERE user_id = :id AND role = 'user' AND is_active = 0";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("DB Error reactivating user ID {$userId}: " . $e->getMessage());
            return false;
        }
    }

    public function deactivateUserById(int $userId): bool
    {
        if ($this->conn === null) return false;

        try {
            $query = "UPDATE {$this->users_table}
                  SET is_active = 0
                  WHERE user_id = :id AND role = 'user' AND is_active = 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("DB Error deactivating user ID {$userId}: " . $e->getMessage());
            return false;
        }
    }

    public function updateUserAvatar(int $userId, string $avatarUrl): bool
    {
        if ($this->conn === null) return false;

        try {
            $query = "UPDATE {$this->users_table}
                  SET avatar_url = :avatar_url
                  WHERE user_id = :user_id AND role = 'user'";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':avatar_url', $avatarUrl, PDO::PARAM_STR);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("UserModel Error updating avatar for user {$userId}: " . $e->getMessage());
            return false;
        }
    }

    public function findUserByEmail(string $email): array|false
    {
        if ($this->conn === null) return false;
        try {
            $query = "SELECT user_id FROM {$this->users_table} WHERE email = :email LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("DB Error finding user by email '{$email}': " . $e->getMessage());
            return false;
        }
    }

    public function updateUserProfile(
        int    $userId,
        string $fullName,
        string $email,
        string $phoneNumber,
        string $address
    ): bool {
        if ($this->conn === null) return false;

        try {
            $query = "UPDATE {$this->users_table}
                  SET full_name = :full_name,
                      email = :email,
                      phone_number = :phone_number,
                      address = :address
                  WHERE user_id = :user_id AND role = 'user'";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':full_name', $fullName, PDO::PARAM_STR);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':phone_number', $phoneNumber, PDO::PARAM_STR);
            $stmt->bindParam(':address', $address, PDO::PARAM_STR);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("UserModel Error updating profile for user {$userId}: " . $e->getMessage());
            return false;
        }
    }

    public function getUserById(int $id): array|false
    {
        if ($this->conn === null) return false;

        try {
            $query = "SELECT user_id, username, full_name, email, phone_number, address, avatar_url, password_changed_at, created_at, role
                  FROM {$this->users_table}
                  WHERE user_id = :id AND role = 'user'
                  LIMIT 1";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("DB Error getting user by ID {$id}: " . $e->getMessage());
            return false;
        }
    }

    public function getUsersPaginated(
        int     $page = 1,
        int     $limit = 10,
        string  $sortBy = 'created_at',
        string  $search = '',
        ?string $status = null
    ): array {
        $result = ['total' => 0, 'users' => []];
        if ($this->conn === null) return $result;

        $allowedSortColumns = ['created_at', 'username', 'email', 'full_name'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'created_at';
        }

        $offset = ($page - 1) * $limit;
        $whereConditions = ["role = 'user'"];
        $params = [];

        if (!empty($search)) {
            $whereConditions[] = "LOWER(username) LIKE :search";
            $params[':search'] = '%' . strtolower($search) . '%';
        }

        // 👇 Thêm điều kiện lọc theo trạng thái
        if ($status === 'active') {
            $whereConditions[] = "is_active = 1";
        } elseif ($status === 'inactive') {
            $whereConditions[] = "is_active = 0";
        }

        $whereSql = 'WHERE ' . implode(' AND ', $whereConditions);

        try {
            $countQuery = "SELECT COUNT(*) FROM {$this->users_table} {$whereSql}";
            $stmtCount = $this->conn->prepare($countQuery);
            foreach ($params as $key => $value) {
                $stmtCount->bindValue($key, $value);
            }
            $stmtCount->execute();
            $result['total'] = (int)$stmtCount->fetchColumn();

            if ($result['total'] === 0) return $result;

            $dataQuery = "SELECT user_id, username, full_name, email, phone_number, address, avatar_url, password_changed_at, created_at, role, is_active
                      FROM {$this->users_table}
                      {$whereSql}
                      ORDER BY {$sortBy} DESC
                      LIMIT :limit OFFSET :offset";
            $stmtData = $this->conn->prepare($dataQuery);
            foreach ($params as $key => $value) {
                $stmtData->bindValue($key, $value);
            }
            $stmtData->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmtData->execute();
            $result['users'] = $stmtData->fetchAll(PDO::FETCH_ASSOC);

            return $result;
        } catch (PDOException $e) {
            error_log("DB Error getting paginated users: " . $e->getMessage());
            return ['total' => 0, 'users' => []];
        }
    }
}
