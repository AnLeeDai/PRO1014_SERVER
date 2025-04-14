<?php
require_once __DIR__ . "/../../config/Database.php";

class Authmodel
{
    private ?PDO $conn;
    private string $users_table = "users";
    private string $password_requests_table = "password_requests";

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
        if ($this->conn === null) {
            error_log("Authmodel Error: Failed to get DB connection.");
        }
    }

    public function getUserAuthVerificationData(int $userId): array|false
    {
        if ($this->conn === null) return false;

        try {
            $query = "SELECT password, password_changed_at FROM {$this->users_table} WHERE user_id = :user_id LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("DB Error getting auth verification data for user ID {$userId}: " . $e->getMessage());
            return false;
        }
    }

    public function getCurrentPasswordHash(int $userId): string|false
    {
        if ($this->conn === null) return false;

        try {
            $query = "SELECT password FROM {$this->users_table} WHERE user_id = :user_id LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("DB Error getting current password hash for user ID {$userId}: " . $e->getMessage());
            return false;
        }
    }

    public function updateUserPassword(int $userId, string $newHashedPassword, string $changedAt): bool
    {
        if ($this->conn === null) return false;

        $query = "UPDATE {$this->users_table}
              SET password = :password, password_changed_at = :changed_at
              WHERE user_id = :user_id";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':password', $newHashedPassword, PDO::PARAM_STR);
            $stmt->bindParam(':changed_at', $changedAt, PDO::PARAM_STR);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("DB Error updating password for user ID {$userId}: " . $e->getMessage());
            return false;
        }
    }

    public function hasAdminAccount(): bool
    {
        if ($this->conn === null) {
            error_log("authmodel::hasAdminAccount Error: Database connection is not available.");
            return false;
        }
        try {
            $query = "SELECT 1 FROM {$this->users_table} WHERE role = 'admin' LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();

            return $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            error_log("Database error checking for admin account: " . $e->getMessage());
            return false;
        }
    }

    public function createUser(array $userData): int|false
    {
        if ($this->conn === null) return false;

        $fields = implode(', ', array_keys($userData));
        $placeholders = ':' . implode(', :', array_keys($userData));
        $query = "INSERT INTO {$this->users_table} ({$fields}) VALUES ({$placeholders})";

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

    public function hasPendingPasswordRequest(string $username): bool
    {
        if ($this->conn === null) {
            error_log("AuthModel::hasPendingPasswordRequest Error: DB connection unavailable.");
            return false;
        }
        try {
            $query = "SELECT 1 FROM {$this->password_requests_table}
                      WHERE username = :username AND status = 'pending'
                      LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->execute();

            return $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            error_log("DB Error checking for pending password request for '{$username}': " . $e->getMessage());
            return false;
        }
    }

    public function createPasswordResetRequest(string $username, string $email, string $hashedNewPassword): bool
    {
        if ($this->conn === null) return false;

        $status = 'pending';

        $query = "INSERT INTO {$this->password_requests_table} (username, email, new_password, status)
                  VALUES (:username, :email, :new_password, :status)";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':new_password', $hashedNewPassword, PDO::PARAM_STR);
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("DB Error creating password reset request for '{$username}': " . $e->getMessage());
            return false;
        }
    }

    public function getPasswordRequests(
        int    $page = 1,
        int    $limit = 10,
        string $sortBy = 'created_at',
        string $search = '',
        string $status = 'pending'
    ): array {
        $result = ['total' => 0, 'requests' => []];
        if ($this->conn === null) return $result;

        $allowedSortColumns = ['id', 'username', 'email', 'created_at', 'status'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'created_at';
        }

        $sortDirection = 'DESC';

        $offset = ($page - 1) * $limit;

        $whereSql = "";
        $params = [];
        $allowedStatus = ['pending', 'done', 'rejected'];
        if (!empty($status) && in_array($status, $allowedStatus)) {
            $whereSql .= (empty($whereSql) ? "WHERE " : " AND ") . "status = :status";
            $params[':status'] = $status;
        }
        if (!empty($search)) {
            $whereSql .= (empty($whereSql) ? "WHERE " : " AND ") . "(username LIKE :search OR email LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        try {
            $countQuery = "SELECT COUNT(*) FROM {$this->password_requests_table} {$whereSql}";
            $stmtCount = $this->conn->prepare($countQuery);
            $stmtCount->execute($params);
            $totalItems = (int)$stmtCount->fetchColumn();
            $result['total'] = $totalItems;

            if ($totalItems === 0) {
                return $result;
            }

            $dataQuery = "SELECT id, username, email, created_at, status
                           FROM {$this->password_requests_table} {$whereSql}
                           ORDER BY {$sortBy} {$sortDirection} 
                           LIMIT :limit OFFSET :offset";

            $stmtData = $this->conn->prepare($dataQuery);
            foreach ($params as $key => $value) {
                $stmtData->bindValue($key, $value);
            }
            $stmtData->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmtData->execute();
            $result['requests'] = $stmtData->fetchAll(PDO::FETCH_ASSOC);

            return $result;
        } catch (PDOException $e) {
            error_log("DB Error getting password requests: " . $e->getMessage());
            return ['total' => 0, 'requests' => []];
        }
    }

    public function getPendingPasswordRequestById(int $requestId): array|false
    {
        if ($this->conn === null) return false;

        try {
            $query = "SELECT id, username, email, new_password
                      FROM {$this->password_requests_table}
                      WHERE id = :id AND status = 'pending'
                      LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $requestId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("DB Error getting pending password request by ID {$requestId}: " . $e->getMessage());
            return false;
        }
    }

    public function getUserLoginDataByUsername(string $username): array|false
    {
        if ($this->conn === null) return false;

        try {
            $query = "SELECT user_id, username, password, full_name, email, role, avatar_url, is_active
                  FROM {$this->users_table}
                  WHERE username = :username
                  LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database error in authmodel::getUserLoginDataByUsername for user '{$username}': " . $e->getMessage());
            return false;
        }
    }

    public function updateUserPasswordByUsername(string $username, string $hashedNewPassword): bool
    {
        if ($this->conn === null) return false;

        $query = "UPDATE {$this->users_table} SET password = :password WHERE username = :username";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':password', $hashedNewPassword, PDO::PARAM_STR);
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("DB Error updating password for user '{$username}': " . $e->getMessage());
            return false;
        }
    }

    public function updatePasswordRequestStatus(int $requestId, string $newStatus): bool
    {
        if ($this->conn === null) return false;

        if (!in_array($newStatus, ['done', 'rejected'])) {
            error_log("Invalid status '{$newStatus}' provided for password request update.");
            return false;
        }

        $query = "UPDATE {$this->password_requests_table} SET status = :status WHERE id = :id";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':status', $newStatus, PDO::PARAM_STR);
            $stmt->bindParam(':id', $requestId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("DB Error updating status for password request ID {$requestId}: " . $e->getMessage());
            return false;
        }
    }

    public function findUserByUsernameAndEmail(string $username, string $email): array|false
    {
        if ($this->conn === null) return false;
        try {
            $query = "SELECT user_id FROM {$this->users_table}
                      WHERE username = :username AND email = :email
                      LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("DB Error finding user by username AND email ('{$username}', '{$email}'): " . $e->getMessage());
            return false;
        }
    }

    public function findUserByUsername(string $username): array|false
    {
        if ($this->conn === null) return false;

        try {
            $query = "SELECT user_id, username FROM {$this->users_table} WHERE username = :username LIMIT 1";
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
            $query = "SELECT user_id, email FROM {$this->users_table} WHERE email = :email LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("DB Error finding user by email '{$email}': " . $e->getMessage());
            return false;
        }
    }
}
