<?php

class ProductModel
{
    private ?PDO $conn;
    private string $products_table = "products";

    public function __construct()
    {
        $this->conn = (new Database())->getConnection();
    }

    public function uploadGalleryImages(int $productId, array $galleryFiles, string $productName = ''): void
    {
        if ($this->conn === null) {
            error_log("DB Connection is null in uploadGalleryImages");
            return;
        }

        foreach ($galleryFiles['tmp_name'] as $index => $tmpName) {
            if ($galleryFiles['error'][$index] === UPLOAD_ERR_OK) {
                $singleFile = [
                    'name' => $galleryFiles['name'][$index],
                    'type' => $galleryFiles['type'][$index],
                    'tmp_name' => $tmpName,
                    'error' => $galleryFiles['error'][$index],
                    'size' => $galleryFiles['size'][$index]
                ];

                $uploadResult = Utils::uploadImage($singleFile, 'product_gallery', $productName . "_$index");

                if ($uploadResult['success']) {
                    try {
                        $stmt = $this->conn->prepare("
                            INSERT INTO product_images (product_id, image_url, created_at)
                            VALUES (:product_id, :image_url, NOW())
                        ");
                        $stmt->execute([
                            ':product_id' => $productId,
                            ':image_url' => $uploadResult['url']
                        ]);
                    } catch (PDOException $e) {
                        error_log("DB Error uploadGalleryImages: " . $e->getMessage());
                    }
                } else {
                    error_log("Upload Gallery Error: " . $uploadResult['message']);
                }
            } else {
                error_log("File upload error at index $index: code " . $galleryFiles['error'][$index]);
            }
        }
    }

    public function getGalleryByProductId(int $productId): array
    {
        if ($this->conn === null) return [];

        try {
            $stmt = $this->conn->prepare("SELECT image_url FROM product_images WHERE product_id = :product_id");
            $stmt->execute([':product_id' => $productId]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log("DB Error getGalleryByProductId: " . $e->getMessage());
            return [];
        }
    }

    public function getProductById(int $id, bool $includeHidden = false): array|false
    {
        if ($this->conn === null) return false;

        try {
            $where = $includeHidden ? "" : " AND p.is_active = 1";
            $stmt = $this->conn->prepare("
            SELECT p.*, c.category_name 
            FROM {$this->products_table} p
            LEFT JOIN categories c ON p.category_id = c.category_id
            WHERE p.id = :id {$where}
            LIMIT 1
        ");
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($product) {
                $product['gallery'] = $this->getGalleryByProductId((int)$product['id']);
                unset($product['category_id']);
            }

            return $product;
        } catch (PDOException $e) {
            error_log("DB Error getProductById: " . $e->getMessage());
            return false;
        }
    }

    public function findProductByName(string $name): array|false
    {
        if ($this->conn === null) return false;

        try {
            $stmt = $this->conn->prepare("SELECT id FROM {$this->products_table} WHERE product_name = :name AND is_active = 1 LIMIT 1");
            $stmt->bindParam(':name', $name);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function createProduct(array $data): int|false
    {
        if ($this->conn === null) return false;

        $query = "INSERT INTO {$this->products_table} (
            product_name, price, thumbnail, short_description, full_description, in_stock,
            extra_info, brand, category_id, is_active, created_at, updated_at
        ) VALUES (
            :product_name, :price, :thumbnail, :short_description, :full_description, :in_stock,
            :extra_info, :brand, :category_id, 1, NOW(), NOW()
        )";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                ':product_name' => $data['product_name'],
                ':price' => $data['price'],
                ':thumbnail' => $data['thumbnail'],
                ':short_description' => $data['short_description'],
                ':full_description' => $data['full_description'],
                ':extra_info' => $data['extra_info'],
                ':brand' => $data['brand'] ?? null,
                ':category_id' => $data['category_id'] ?? null,
                ':in_stock' => $data['in_stock'],
            ]);
            return (int)$this->conn->lastInsertId();
        } catch (PDOException $e) {
            error_log("DB Error createProduct: " . $e->getMessage());
            return false;
        }
    }

    public function updateProduct(int $id, array $data): bool
    {
        if ($this->conn === null) return false;

        try {
            $setParts = [
                "product_name = :product_name",
                "price = :price",
                "short_description = :short_description",
                "full_description = :full_description",
                "extra_info = :extra_info",
                "brand = :brand",
                "category_id = :category_id",
                "updated_at = NOW()",
                "in_stock = :in_stock"
            ];

            if (!empty($data['thumbnail'])) {
                $setParts[] = "thumbnail = :thumbnail";
            }

            $setSql = implode(", ", $setParts);
            $query = "UPDATE {$this->products_table} SET {$setSql} WHERE id = :id";

            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':product_name', $data['product_name']);
            $stmt->bindValue(':price', $data['price']);
            $stmt->bindValue(':short_description', $data['short_description']);
            $stmt->bindValue(':full_description', $data['full_description']);
            $stmt->bindValue(':extra_info', $data['extra_info']);
            $stmt->bindValue(':brand', $data['brand']);
            $stmt->bindValue(':category_id', $data['category_id']);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':in_stock', (int) $data['in_stock'], PDO::PARAM_INT);

            if (!empty($data['thumbnail'])) {
                $stmt->bindValue(':thumbnail', $data['thumbnail']);
            }

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("DB Error updateProduct: " . $e->getMessage());
            return false;
        }
    }

    public function hideProductById(int $id): bool
    {
        if ($this->conn === null) return false;

        try {
            $stmt = $this->conn->prepare("UPDATE {$this->products_table} SET is_active = 0 WHERE id = :id AND is_active = 1");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute() && $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function unhideProductById(int $id): bool
    {
        if ($this->conn === null) return false;

        try {
            $stmt = $this->conn->prepare("UPDATE {$this->products_table} SET is_active = 1 WHERE id = :id AND is_active = 0");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute() && $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function getProductsPaginated(
        int    $page = 1,
        int    $limit = 10,
        string $sortBy = 'created_at',
        string $search = '',
        bool   $includeHidden = false,
        ?int   $categoryId = null
    ): array
    {
        $result = ['total' => 0, 'products' => []];
        if ($this->conn === null) return $result;

        // Cho phép sắp xếp theo các cột hợp lệ
        $allowedSortColumns = ['id', 'product_name', 'price', 'created_at', 'is_active'];
        if (!in_array($sortBy, $allowedSortColumns)) $sortBy = 'created_at';

        $offset = ($page - 1) * $limit;
        $params = [];
        $where = [];

        if (!$includeHidden) {
            $where[] = "p.is_active = 1";
        }

        if (!empty($search)) {
            $where[] = "p.product_name LIKE :search";
            $params[':search'] = "%{$search}%";
        }

        if ($categoryId !== null) {
            $where[] = "p.category_id = :category_id";
            $params[':category_id'] = $categoryId;
        }

        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        try {
            // Đếm tổng số bản ghi
            $countQuery = "
            SELECT COUNT(*) 
            FROM {$this->products_table} p 
            LEFT JOIN categories c ON p.category_id = c.category_id 
            {$whereClause}
        ";
            $stmt = $this->conn->prepare($countQuery);
            $stmt->execute($params);
            $result['total'] = (int)$stmt->fetchColumn();

            if ($result['total'] === 0) return $result;

            // Truy vấn danh sách sản phẩm có phân trang
            $dataQuery = "
            SELECT p.*, c.category_name 
            FROM {$this->products_table} p 
            LEFT JOIN categories c ON p.category_id = c.category_id 
            {$whereClause}
            ORDER BY p.{$sortBy} DESC 
            LIMIT :limit OFFSET :offset
        ";

            $stmt = $this->conn->prepare($dataQuery);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Gắn thêm gallery ảnh cho mỗi sản phẩm
            foreach ($products as &$product) {
                $product['gallery'] = $this->getGalleryByProductId((int)$product['id']);
                unset($product['category_id']); // Không cần trả về category_id nếu đã có category_name
            }

            $result['products'] = $products;
            return $result;
        } catch (PDOException $e) {
            error_log("DB Error getProductsPaginated: " . $e->getMessage());
            return $result;
        }
    }
}
