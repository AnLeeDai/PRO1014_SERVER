<?php

class ProductModel
{
    private ?PDO $conn;
    private string $table_name = "products";

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
        if ($this->conn === null) {
            error_log("ProductModel Error: Failed to get DB connection.");
        }
    }

    public function restoreProduct(int $productId): bool
    {
        if ($this->conn === null) return false;

        try {
            $query = "UPDATE {$this->table_name}
                  SET is_active = 1, updated_at = NOW()
                  WHERE id = :id";

            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':id', $productId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("ProductModel Error (restoreProduct): " . $e->getMessage());
            return false;
        }
    }

    public function hideProduct(int $productId): bool
    {
        if ($this->conn === null) return false;

        try {
            $query = "UPDATE {$this->table_name}
                  SET is_active = 0, updated_at = NOW()
                  WHERE id = :id";

            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':id', $productId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("ProductModel Error (hideProduct): " . $e->getMessage());
            return false;
        }
    }

    public function updateProduct(int $productId, array $data): bool
    {
        if ($this->conn === null) return false;

        try {
            // Phần SET chính
            $setParts = [
                "product_name = :product_name",
                "price = :price",
                "short_description = :short_description",
                "full_description = :full_description",
                "extra_info = :extra_info",
                "brand = :brand",
                "updated_at = NOW()"
            ];

            // Nếu có thumbnail mới, thêm dòng update
            if (!empty($data['thumbnail'])) {
                $setParts[] = "thumbnail = :thumbnail";
            }

            $setSql = implode(", ", $setParts);
            $query = "UPDATE {$this->table_name} SET {$setSql} WHERE id = :id";

            $stmt = $this->conn->prepare($query);

            // Bind giá trị
            $stmt->bindValue(':product_name', $data['product_name']);
            $stmt->bindValue(':price', $data['price']);
            $stmt->bindValue(':short_description', $data['short_description']);
            $stmt->bindValue(':full_description', $data['full_description']);
            $stmt->bindValue(':extra_info', $data['extra_info']);
            $stmt->bindValue(':brand', $data['brand']);
            $stmt->bindValue(':id', $productId, PDO::PARAM_INT);

            if (!empty($data['thumbnail'])) {
                $stmt->bindValue(':thumbnail', $data['thumbnail']);
            }

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("ProductModel Error (updateProduct): " . $e->getMessage());
            return false;
        }
    }

    public function getProductById(int $id): array|false
    {
        if ($this->conn === null) return false;

        try {
            // Lấy thông tin sản phẩm
            $query = "SELECT * FROM {$this->table_name} WHERE id = :id LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) return false;

            // Lấy gallery ảnh phụ
            $imgQuery = "SELECT image_url FROM product_images WHERE product_id = :id";
            $stmtImg = $this->conn->prepare($imgQuery);
            $stmtImg->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtImg->execute();
            $images = $stmtImg->fetchAll(PDO::FETCH_COLUMN);

            $product['gallery'] = $images;

            return $product;
        } catch (PDOException $e) {
            error_log("ProductModel Error (getProductById): " . $e->getMessage());
            return false;
        }
    }

    public function getProductsPaginatedWithGallery(
        int    $page = 1,
        int    $limit = 10,
        string $sortBy = 'created_at',
        string $search = '',
        string $brand = '',
        string $priceRange = ''
    ): array
    {
        $result = ['total' => 0, 'products' => []];
        if ($this->conn === null) return $result;

        $allowedSortColumns = ['id', 'product_name', 'price', 'created_at', 'updated_at'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'created_at';
        }

        $sortDirection = 'DESC';
        $offset = ($page - 1) * $limit;

        $whereConditions = [];
        $params = [];

        if (!empty($search)) {
            $whereConditions[] = "product_name LIKE :search";
            $params[':search'] = '%' . $search . '%';
        }

        if (!empty($brand)) {
            $whereConditions[] = "brand = :brand";
            $params[':brand'] = $brand;
        }

        if (!empty($priceRange)) {
            switch ($priceRange) {
                case 'lt5':
                    $whereConditions[] = "price < 5000000";
                    break;
                case '5to10':
                    $whereConditions[] = "price BETWEEN 5000000 AND 10000000";
                    break;
                case '10to20':
                    $whereConditions[] = "price BETWEEN 10000000 AND 20000000";
                    break;
                case 'gt20':
                    $whereConditions[] = "price > 20000000";
                    break;
            }
        }

        $whereSql = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        try {
            $countQuery = "SELECT COUNT(*) FROM {$this->table_name} {$whereSql}";
            $stmtCount = $this->conn->prepare($countQuery);
            $stmtCount->execute($params);
            $totalItems = (int)$stmtCount->fetchColumn();
            $result['total'] = $totalItems;

            if ($totalItems === 0) return $result;

            $dataQuery = "SELECT * FROM {$this->table_name}
                          {$whereSql}
                          ORDER BY {$sortBy} {$sortDirection}
                          LIMIT :limit OFFSET :offset";

            $stmtData = $this->conn->prepare($dataQuery);
            foreach ($params as $key => $val) {
                $stmtData->bindValue($key, $val);
            }
            $stmtData->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmtData->execute();

            $products = $stmtData->fetchAll(PDO::FETCH_ASSOC);

            $imageQuery = "SELECT product_id, image_url FROM product_images";
            $imageStmt = $this->conn->query($imageQuery);
            $images = $imageStmt->fetchAll(PDO::FETCH_ASSOC);

            $imageMap = [];
            foreach ($images as $img) {
                $imageMap[$img['product_id']][] = $img['image_url'];
            }

            foreach ($products as &$product) {
                $product['gallery'] = $imageMap[$product['id']] ?? [];
            }

            $result['products'] = $products;
            return $result;
        } catch (PDOException $e) {
            error_log("ProductModel Error (getProductsPaginatedWithGallery): " . $e->getMessage());
            return ['total' => 0, 'products' => []];
        }
    }

    public function createProduct(array $data): int|false
    {
        if ($this->conn === null) return false;

        try {
            $query = "INSERT INTO {$this->table_name}
                      (product_name, price, thumbnail, short_description, full_description, extra_info, in_stock, brand, created_at, updated_at)
                      VALUES (:product_name, :price, :thumbnail, :short_description, :full_description, :extra_info, 1, :brand, NOW(), NOW())";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                ':product_name' => $data['product_name'],
                ':price' => $data['price'],
                ':thumbnail' => $data['thumbnail'],
                ':short_description' => $data['short_description'],
                ':full_description' => $data['full_description'],
                ':extra_info' => $data['extra_info'],
                ':brand' => $data['brand'] ?? null
            ]);

            return (int)$this->conn->lastInsertId();
        } catch (PDOException $e) {
            error_log("ProductModel Error (createProduct): " . $e->getMessage());
            return false;
        }
    }

    public function insertProductImage(int $productId, string $imageUrl): bool
    {
        if ($this->conn === null) return false;

        try {
            $query = "INSERT INTO product_images (product_id, image_url, created_at) VALUES (:product_id, :image_url, NOW())";
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([
                ':product_id' => $productId,
                ':image_url' => $imageUrl
            ]);
        } catch (PDOException $e) {
            error_log("ProductModel Error (insertProductImage): " . $e->getMessage());
            return false;
        }
    }
}
