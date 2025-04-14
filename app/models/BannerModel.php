<?php

class BannerModel
{
    private ?PDO $conn;
    private string $table_name = "banners";

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
        if ($this->conn === null) {
            error_log("BannerModel Error: Failed to get DB connection.");
        }
    }

    public function deleteBanner(int $bannerId): bool
    {
        if ($this->conn === null) return false;

        try {
            $query = "DELETE FROM {$this->table_name} WHERE id = :banner_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':banner_id', $bannerId, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("BannerModel Error deleting banner {$bannerId}: " . $e->getMessage());
            return false;
        }
    }

    public function getBannersPaginated(
        int    $page = 1,
        int    $limit = 10,
        string $sortBy = 'created_at',
        string $search = ''
    ): array {
        // Kết quả trả về với tổng số banner và danh sách banner
        $result = ['total' => 0, 'banners' => []];
        if ($this->conn === null) return $result;

        // Cho phép sắp xếp theo các cột sau
        $allowedSortColumns = ['id', 'title', 'created_at', 'updated_at'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'created_at';
        }
        // Sắp xếp theo thứ tự giảm dần để banner mới hiển thị đầu tiên
        $sortDirection = 'DESC';

        $offset = ($page - 1) * $limit;

        // Xây dựng điều kiện WHERE nếu có tìm kiếm theo title
        $whereConditions = [];
        $params = [];

        if (!empty($search)) {
            $whereConditions[] = "title LIKE :search";
            $params[':search'] = '%' . $search . '%';
        }

        $whereSql = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

        try {
            // Đếm tổng số banner
            $countQuery = "SELECT COUNT(*) FROM {$this->table_name} {$whereSql}";
            $stmtCount = $this->conn->prepare($countQuery);
            $stmtCount->execute($params);
            $totalItems = (int)$stmtCount->fetchColumn();
            $result['total'] = $totalItems;

            if ($totalItems === 0) {
                return $result;
            }

            // Truy vấn dữ liệu banner theo phân trang
            $dataQuery = "SELECT id, title, image_url, created_at, updated_at
                      FROM {$this->table_name} {$whereSql}
                      ORDER BY {$sortBy} {$sortDirection}
                      LIMIT :limit OFFSET :offset";

            $stmtData = $this->conn->prepare($dataQuery);
            foreach ($params as $key => $value) {
                $stmtData->bindValue($key, $value);
            }
            $stmtData->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmtData->execute();
            $result['banners'] = $stmtData->fetchAll(PDO::FETCH_ASSOC);

            return $result;
        } catch (PDOException $e) {
            error_log("DB Error getting paginated banners (search='{$search}'): " . $e->getMessage());
            return ['total' => 0, 'banners' => []];
        }
    }

    public function getBannerById(int $bannerId): array|false
    {
        if ($this->conn === null) return false;

        try {
            $query = "SELECT * FROM {$this->table_name} WHERE id = :banner_id LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':banner_id', $bannerId, PDO::PARAM_INT);
            if ($stmt->execute()) {
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
            return false;
        } catch (PDOException $e) {
            error_log("BannerModel Error getting banner by id {$bannerId}: " . $e->getMessage());
            return false;
        }
    }

    public function updateBanner(int $bannerId, string $title, ?string $imageUrl = null): bool
    {
        if ($this->conn === null) return false;

        try {
            if ($imageUrl !== null) {
                $query = "UPDATE {$this->table_name}
                          SET title = :title, image_url = :image_url, updated_at = NOW()
                          WHERE id = :banner_id";
            } else {
                $query = "UPDATE {$this->table_name}
                          SET title = :title, updated_at = NOW()
                          WHERE id = :banner_id";
            }
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':title', $title, PDO::PARAM_STR);
            $stmt->bindParam(':banner_id', $bannerId, PDO::PARAM_INT);

            if ($imageUrl !== null) {
                $stmt->bindParam(':image_url', $imageUrl, PDO::PARAM_STR);
            }

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("BannerModel Error updating banner {$bannerId}: " . $e->getMessage());
            return false;
        }
    }

    public function createBanner(string $title, string $imageUrl): int|false
    {
        if ($this->conn === null) return false;

        try {
            $query = "INSERT INTO {$this->table_name} (title, image_url, created_at, updated_at)
                      VALUES (:title, :image_url, NOW(), NOW())";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':title', $title, PDO::PARAM_STR);
            $stmt->bindParam(':image_url', $imageUrl, PDO::PARAM_STR);

            if (!$stmt->execute()) {
                error_log("BannerModel Error: createBanner failed to execute.");
                return false;
            }

            return (int)$this->conn->lastInsertId();
        } catch (PDOException $e) {
            error_log("BannerModel Error: " . $e->getMessage());
            return false;
        }
    }
}
