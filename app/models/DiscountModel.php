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

    /**
     * Kiểm tra user đã dùng discount cho product chưa
     */
    public function hasUsedDiscount(int $userId, int $discountId, int $productId): bool
    {
        if ($this->conn === null) return true; // Lỗi DB => chặn
        try {
            $query = "SELECT COUNT(*) FROM discount_usages
                      WHERE user_id = :user_id AND discount_id = :discount_id AND product_id = :product_id";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                ':user_id' => $userId,
                ':discount_id' => $discountId,
                ':product_id' => $productId
            ]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("DiscountModel hasUsedDiscount Error: " . $e->getMessage());
            return true; // fallback => chặn
        }
    }

    /**
     * Ghi nhận việc sử dụng mã giảm giá
     */
    public function recordUsage(int $userId, int $discountId, int $productId): bool
    {
        if ($this->conn === null) return false;

        $query = "INSERT INTO discount_usages (user_id, discount_id, product_id) 
                  VALUES (:user_id, :discount_id, :product_id)";
        $stmt = $this->conn->prepare($query);
        try {
            return $stmt->execute([
                ':user_id' => $userId,
                ':discount_id' => $discountId,
                ':product_id' => $productId
            ]);
        } catch (PDOException $e) {
            error_log("DiscountUsageModel recordUsage Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Xóa usage (nếu đang track). Dùng khi user hủy discount
     */
    public function removeDiscountUsage(int $userId, int $discountId, int $productId): bool
    {
        if ($this->conn === null) return false;
        try {
            $stmt = $this->conn->prepare("
                DELETE FROM discount_usages
                WHERE user_id = :user_id
                  AND discount_id = :discount_id
                  AND product_id = :product_id
            ");
            return $stmt->execute([
                ':user_id' => $userId,
                ':discount_id' => $discountId,
                ':product_id' => $productId
            ]);
        } catch (PDOException $e) {
            error_log("DiscountModel removeDiscountUsage Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Lấy discount theo ID
     */
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

    /**
     * Lấy discount theo product_id + discount_code
     */
    public function getDiscountByCodeAndProduct(string $code, int $productId): array|false
    {
        if ($this->conn === null) return false;
        try {
            $stmt = $this->conn->prepare("SELECT * FROM {$this->table_name}
                WHERE discount_code = :code
                  AND product_id = :product_id
                LIMIT 1
            ");
            $stmt->execute([
                ':code' => $code,
                ':product_id' => $productId
            ]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("DiscountModel getDiscountByCodeAndProduct Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Xóa discount (admin)
     */
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

    /**
     * Lấy danh sách discount (có phân trang) cho admin
     */
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

        $whereSql = !empty($whereConditions)
            ? "WHERE " . implode(" AND ", $whereConditions)
            : "";

        try {
            // Đếm total
            $countQuery = "SELECT COUNT(*) FROM {$this->table_name} {$whereSql}";
            $stmtCount = $this->conn->prepare($countQuery);
            $stmtCount->execute($params);
            $totalItems = (int)$stmtCount->fetchColumn();
            $result['total'] = $totalItems;

            if ($totalItems === 0) {
                return $result;
            }

            // Lấy data
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

    /**
     * Tạo mã giảm giá (admin)
     */
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

    /**
     * Lấy danh sách discount còn quantity, còn hạn cho 1 product
     */
    public function getAvailableDiscountsForProduct(int $productId): array
    {
        if ($this->conn === null) return [];
        try {
            $stmt = $this->conn->prepare("
                SELECT id, discount_code, product_id, percent_value, quantity, start_date, end_date
                FROM discounts
                WHERE product_id = :product_id
                  AND quantity > 0
                  AND start_date <= NOW()
                  AND end_date >= NOW()
                ORDER BY created_at DESC
            ");
            $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("DiscountModel Error getAvailableDiscountsForProduct: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Lấy cart_id đang pending (cũng có thể đặt ở CartModel, tùy bạn)
     */
    public function getPendingCartIdByUser(int $userId): int|false
    {
        if ($this->conn === null) return false;
        try {
            $stmt = $this->conn->prepare("SELECT id FROM carts WHERE user_id = :user_id AND status = 'pending' LIMIT 1");
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (int)$row['id'] : false;
        } catch (PDOException $e) {
            error_log("DiscountModel Error getPendingCartIdByUser: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Xóa discount_code khỏi cart_items
     */
    public function removeDiscountFromCartItem(int $cartId, int $productId, string $discountCode): bool
    {
        if ($this->conn === null) return false;
        try {
            $stmt = $this->conn->prepare("
                UPDATE cart_items
                SET discount_code = NULL
                WHERE cart_id = :cart_id
                  AND product_id = :product_id
                  AND discount_code = :discount_code
            ");
            return $stmt->execute([
                ':cart_id' => $cartId,
                ':product_id' => $productId,
                ':discount_code' => $discountCode
            ]);
        } catch (PDOException $e) {
            error_log("DiscountModel Error removeDiscountFromCartItem: " . $e->getMessage());
            return false;
        }
    }
}
