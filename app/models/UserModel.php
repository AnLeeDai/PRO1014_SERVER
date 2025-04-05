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

    public function getUsersPaginated(
        int    $page = 1,
        int    $limit = 10,
        string $search = '',
        bool   $includeHidden = false
    ): array
    {
        $result = ['total' => 0, 'users' => []];
        if ($this->conn === null) return $result;

        $offset = ($page - 1) * $limit;
        $whereConditions = [];
        $params = [];

        if (!$includeHidden) {
            $whereConditions[] = "is_active = 1";
        }

        if (!empty($search)) {
            $whereConditions[] = "username LIKE :search";
            $params[':search'] = '%' . $search . '%';
        }

        $whereSql = !empty($whereConditions)
            ? "WHERE " . implode(" AND ", $whereConditions)
            : "";

        try {
            $countQuery = "SELECT COUNT(*) FROM {$this->users_table} {$whereSql}";
            $stmtCount = $this->conn->prepare($countQuery);
            $stmtCount->execute($params);
            $totalItems = (int)$stmtCount->fetchColumn();
            $result['total'] = $totalItems;

            if ($totalItems === 0) {
                return $result;
            }

            $dataQuery = "SELECT user_id, username, email, created_at, updated_at, is_active
                          FROM {$this->users_table}
                          {$whereSql}
                          ORDER BY user_id
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
            error_log("DB Error getUsersPaginated: " . $e->getMessage());
            return $result;
        }
    }
}
