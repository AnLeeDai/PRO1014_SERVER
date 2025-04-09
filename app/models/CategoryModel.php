<?php

class CategoryModel
{
    private ?PDO $conn;
    private string $categories_table = "categories";

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
        if ($this->conn === null) {
            error_log("CategoryModel Error: Failed to get DB connection.");
        }
    }

    public function findCategoryByName(string $name): array|false
    {
        if ($this->conn === null) return false;
        try {
            $query = "SELECT category_id FROM {$this->categories_table}
                      WHERE category_name = :name AND is_active = 1
                      LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("DB Error finding active category by name '{$name}': " . $e->getMessage());
            return false;
        }
    }

    public function findCategoryByNameAndNotId(string $name, int $excludeId): array|false
    {
        if ($this->conn === null) return false;
        try {
            $query = "SELECT category_id FROM {$this->categories_table}
                      WHERE category_name = :name AND category_id != :exclude_id AND is_active = 1
                      LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':exclude_id', $excludeId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("DB Error finding active category by name '{$name}' excluding ID {$excludeId}: " . $e->getMessage());
            return false;
        }
    }

    public function getCategoryById(int $id, bool $includeHidden = false): array|false
    {
        if ($this->conn === null) return false;
        try {
            $sqlActiveCondition = $includeHidden ? "" : " AND is_active = 1";
            $query = "SELECT category_id, category_name, description, created_at, updated_at, is_active
                      FROM {$this->categories_table}
                      WHERE category_id = :id {$sqlActiveCondition}
                      LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("DB Error getting category by ID {$id}: " . $e->getMessage());
            return false;
        }
    }

    public function getCategoriesPaginated(
        int    $page = 1,
        int    $limit = 10,
        string $sortBy = 'category_name',
        string $search = '',
        bool   $includeHidden = false
    ): array
    {
        $result = ['total' => 0, 'categories' => []];
        if ($this->conn === null) return $result;

        $allowedSortColumns = ['category_id', 'category_name', 'created_at', 'is_active'];
        if (!in_array($sortBy, $allowedSortColumns)) { $sortBy = 'category_name'; }
        $sortDirection = 'ASC';

        $offset = ($limit === PHP_INT_MAX) ? 0 : (($page - 1) * $limit);

        $whereConditions = [];
        $params = [];

        if (!$includeHidden) {
            $whereConditions[] = "is_active = 1";
        }

        if (!empty($search)) {
            $whereConditions[] = "category_name LIKE :search";
            $params[':search'] = '%' . $search . '%';
        }

        $whereSql = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

        try {
            $countQuery = "SELECT COUNT(*) FROM {$this->categories_table} {$whereSql}";
            $stmtCount = $this->conn->prepare($countQuery);
            $stmtCount->execute($params);
            $totalItems = (int)$stmtCount->fetchColumn();
            $result['total'] = $totalItems;

            if ($totalItems === 0) { return $result; }

            $dataQuery = "SELECT category_id, category_name, description, created_at, updated_at, is_active
                          FROM {$this->categories_table} {$whereSql}
                          ORDER BY {$sortBy} {$sortDirection}
                          LIMIT :limit OFFSET :offset";

            $stmtData = $this->conn->prepare($dataQuery);
            foreach ($params as $key => $value) { $stmtData->bindValue($key, $value); }
            $stmtData->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmtData->execute();
            $result['categories'] = $stmtData->fetchAll(PDO::FETCH_ASSOC);

            return $result;
        } catch (PDOException $e) {
            error_log("DB Error getting paginated categories (search='{$search}', includeHidden=" . ($includeHidden ? 'true':'false') . "): " . $e->getMessage());
            return ['total' => 0, 'categories' => []];
        }
    }

    public function createCategory(string $name, ?string $description): int|false
    {
        if ($this->conn === null) return false;
        $query = "INSERT INTO {$this->categories_table} (category_name, description) VALUES (:name, :description)";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            if ($description === null) { $stmt->bindValue(':description', null, PDO::PARAM_NULL); }
            else { $stmt->bindParam(':description', $description, PDO::PARAM_STR); }
            $success = $stmt->execute();
            if ($success) { return (int)$this->conn->lastInsertId(); }
            else { return false; }
        } catch (PDOException $e) { return false; }
    }

    public function updateCategory(int $id, string $name, ?string $description): bool
    {
        if ($this->conn === null) return false;
        $query = "UPDATE {$this->categories_table} SET category_name = :name, description = :description WHERE category_id = :id";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            if ($description === null) { $stmt->bindValue(':description', null, PDO::PARAM_NULL); }
            else { $stmt->bindParam(':description', $description, PDO::PARAM_STR); }
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $success = $stmt->execute();
            return $success && ($stmt->rowCount() > 0);
        } catch (PDOException $e) { return false; }
    }

    public function hideCategoryById(int $id): bool
    {
        if ($this->conn === null) return false;
        $query = "UPDATE {$this->categories_table}
                  SET is_active = 0
                  WHERE category_id = :id AND is_active = 1";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $success = $stmt->execute();
            return $success && ($stmt->rowCount() > 0);
        } catch (PDOException $e) {
            error_log("DB Error hiding category ID {$id}: " . $e->getMessage());
            return false;
        }
    }

    public function unhideCategoryById(int $id): bool
    {
        if ($this->conn === null) return false;
        $query = "UPDATE {$this->categories_table}
                  SET is_active = 1
                  WHERE category_id = :id AND is_active = 0";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $success = $stmt->execute();
            return $success && ($stmt->rowCount() > 0);
        } catch (PDOException $e) {
            error_log("DB Error unhiding category ID {$id}: " . $e->getMessage());
            return false;
        }
    }
}
