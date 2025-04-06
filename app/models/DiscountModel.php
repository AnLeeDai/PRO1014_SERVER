<?php

class DiscountModel
{
    private ?PDO $conn;
    private string $table_name = "discounts";

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
        if ($this->conn === null) {
            error_log("DiscountModel Error: Failed to get DB connection.");
        }
    }

    public function getDiscountById(int $id): array|false
    {
        if ($this->conn === null) return false;

        try {
            $query = "SELECT * FROM {$this->table_name} WHERE id = :id LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            if ($stmt->execute()) {
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
            return false;
        } catch (PDOException $e) {
            error_log("DiscountModel Error getDiscountById: " . $e->getMessage());
            return false;
        }
    }

    public function deleteDiscount(int $discountId): bool
    {
        if ($this->conn === null) return false;

        try {
            $query = "DELETE FROM {$this->table_name} WHERE id = :discount_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':discount_id', $discountId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("DiscountModel Error deleting discount {$discountId}: " . $e->getMessage());
            return false;
        }
    }

    public function getDiscountsPaginated(
        int    $page = 1,
        int    $limit = 10,
        string $sortBy = 'created_at',
        string $search = ''
    ): array
    {
        $result = ['total' => 0, 'discounts' => []];
        if ($this->conn === null) return $result;

        $allowedSortColumns = ['id', 'discount_code', 'percent_value', 'created_at', 'start_date', 'end_date'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'created_at';
        }

        $sortDirection = 'DESC';
        $offset = ($page - 1) * $limit;

        $whereConditions = [];
        $params = [];

        if (!empty($search)) {
            $whereConditions[] = "discount_code LIKE :search";
            $params[':search'] = '%' . $search . '%';
        }

        $whereSql = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

        try {
            $countQuery = "SELECT COUNT(*) FROM {$this->table_name} {$whereSql}";
            $stmtCount = $this->conn->prepare($countQuery);
            $stmtCount->execute($params);
            $totalItems = (int)$stmtCount->fetchColumn();
            $result['total'] = $totalItems;

            if ($totalItems === 0) return $result;

            $dataQuery = "SELECT id, discount_code, percent_value, product_id, quantity, total_quantity,
                             start_date, end_date, created_at, updated_at
                      FROM {$this->table_name} {$whereSql}
                      ORDER BY {$sortBy} {$sortDirection}
                      LIMIT :limit OFFSET :offset";

            $stmtData = $this->conn->prepare($dataQuery);
            foreach ($params as $key => $val) {
                $stmtData->bindValue($key, $val);
            }
            $stmtData->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmtData->execute();

            $result['discounts'] = $stmtData->fetchAll(PDO::FETCH_ASSOC);
            return $result;
        } catch (PDOException $e) {
            error_log("DiscountModel Error (getDiscountsPaginated): " . $e->getMessage());
            return $result;
        }
    }

    public function createDiscount(array $data): int|false
    {
        if ($this->conn === null) return false;

        try {
            $query = "INSERT INTO {$this->table_name}
                      (discount_code, percent_value, product_id, quantity, total_quantity, start_date, end_date, created_at, updated_at)
                      VALUES (:discount_code, :percent_value, :product_id, :quantity, :total_quantity, :start_date, :end_date, NOW(), NOW())";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':discount_code', $data['discount_code']);
            $stmt->bindParam(':percent_value', $data['percent_value']);
            $stmt->bindParam(':product_id', $data['product_id'], PDO::PARAM_INT);
            $stmt->bindParam(':quantity', $data['quantity'], PDO::PARAM_INT);
            $stmt->bindParam(':total_quantity', $data['total_quantity'], PDO::PARAM_INT);
            $stmt->bindParam(':start_date', $data['start_date']);
            $stmt->bindParam(':end_date', $data['end_date']);

            if (!$stmt->execute()) {
                error_log("DiscountModel Error: createDiscount failed.");
                return false;
            }

            return (int)$this->conn->lastInsertId();
        } catch (PDOException $e) {
            error_log("DiscountModel Error: " . $e->getMessage());
            return false;
        }
    }
}
