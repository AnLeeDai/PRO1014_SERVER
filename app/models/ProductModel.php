<?php

class ProductModel
{
    private ?PDO $conn;
    private string $products_table = "products";

    public function __construct()
    {
        $this->conn = (new Database())->getConnection();
    }

    /* ---------- GALLERY: ADD ---------- */
    public function uploadGalleryImages(int $productId, array $galleryFiles, string $productName = ''): void
    {
        if ($this->conn === null) return;

        foreach ($galleryFiles['tmp_name'] as $idx => $tmp) {
            if ($galleryFiles['error'][$idx] !== UPLOAD_ERR_OK) {
                error_log("File upload error index $idx: code ".$galleryFiles['error'][$idx]);
                continue;
            }

            $single = [
                'name'     => $galleryFiles['name'][$idx],
                'type'     => $galleryFiles['type'][$idx],
                'tmp_name' => $tmp,
                'error'    => $galleryFiles['error'][$idx],
                'size'     => $galleryFiles['size'][$idx],
            ];

            $up = Utils::uploadImage($single, 'product_gallery', $productName."_".$idx);
            if (!$up['success']) {
                error_log("Upload gallery error: ".$up['message']);
                continue;
            }

            try {
                $stmt = $this->conn->prepare("
                    INSERT INTO product_images (product_id, image_url, created_at)
                    VALUES (:pid, :url, NOW())
                ");
                $stmt->execute([
                    ':pid' => $productId,
                    ':url' => $up['url'],
                ]);
            } catch (PDOException $e) {
                error_log("DB error uploadGalleryImages: ".$e->getMessage());
            }
        }
    }

    /* ---------- GALLERY: REPLACE ---------- */
    public function replaceGalleryImages(int $productId, array $galleryFiles, string $productName = ''): void
    {
        if ($this->conn === null) return;

        try {
            $this->conn->beginTransaction();

            /* Xoá bản ghi + file cũ */
            $old = $this->getGalleryByProductId($productId);
            foreach ($old as $url) {
                Utils::deletePhysicalImage($url); // tự cài hàm xoá file
            }
            $this->conn->prepare("DELETE FROM product_images WHERE product_id = :pid")
                       ->execute([':pid' => $productId]);

            /* Thêm mới */
            $this->uploadGalleryImages($productId, $galleryFiles, $productName);

            $this->conn->commit();
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("DB error replaceGalleryImages: ".$e->getMessage());
        }
    }

    /* ---------- GALLERY: GET BY PRODUCT ---------- */
    public function getGalleryByProductId(int $productId): array
    {
        if ($this->conn === null) return [];

        try {
            $stmt = $this->conn->prepare("SELECT image_url FROM product_images WHERE product_id = :pid");
            $stmt->execute([':pid' => $productId]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log("DB error getGalleryByProductId: ".$e->getMessage());
            return [];
        }
    }

    /* ---------- PRODUCT: GET ONE ---------- */
    public function getProductById(int $id, bool $includeHidden = false): array|false
    {
        if ($this->conn === null) return false;

        try {
            $where = $includeHidden ? "" : " AND p.is_active = 1";
            $stmt  = $this->conn->prepare("
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
            error_log("DB error getProductById: ".$e->getMessage());
            return false;
        }
    }

    /* ---------- PRODUCT: FIND BY NAME ---------- */
    public function findProductByName(string $name): array|false
    {
        if ($this->conn === null) return false;

        try {
            $stmt = $this->conn->prepare("
                SELECT id FROM {$this->products_table}
                WHERE product_name = :name AND is_active = 1
                LIMIT 1
            ");
            $stmt->bindParam(':name', $name);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return false;
        }
    }

    /* ---------- PRODUCT: CREATE ---------- */
    public function createProduct(array $data): int|false
    {
        if ($this->conn === null) return false;

        $sql = "
            INSERT INTO {$this->products_table} (
                product_name, price, thumbnail, short_description,
                full_description, in_stock, extra_info, brand,
                category_id, is_active, created_at, updated_at
            )
            VALUES (
                :product_name, :price, :thumbnail, :short_description,
                :full_description, :in_stock, :extra_info, :brand,
                :category_id, 1, NOW(), NOW()
            )
        ";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':product_name'      => $data['product_name'],
                ':price'             => $data['price'],
                ':thumbnail'         => $data['thumbnail'],
                ':short_description' => $data['short_description'],
                ':full_description'  => $data['full_description'],
                ':extra_info'        => $data['extra_info'],
                ':brand'             => $data['brand'] ?? null,
                ':category_id'       => $data['category_id'] ?? null,
                ':in_stock'          => (int)$data['in_stock'],
            ]);
            return (int)$this->conn->lastInsertId();
        } catch (PDOException $e) {
            error_log("DB error createProduct: ".$e->getMessage());
            return false;
        }
    }

    /* ---------- PRODUCT: UPDATE ---------- */
    public function updateProduct(int $id, array $data): bool
    {
        if ($this->conn === null) return false;

        try {
            $set = [
                "product_name      = :product_name",
                "price             = :price",
                "short_description = :short_description",
                "full_description  = :full_description",
                "extra_info        = :extra_info",
                "brand             = :brand",
                "category_id       = :category_id",
                "in_stock          = :in_stock",
                "updated_at        = NOW()",
            ];
            if (!empty($data['thumbnail'])) {
                $set[] = "thumbnail = :thumbnail";
            }
            $sql = "UPDATE {$this->products_table} SET ".implode(", ", $set)." WHERE id = :id";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':product_name',      $data['product_name']);
            $stmt->bindValue(':price',             $data['price']);
            $stmt->bindValue(':short_description', $data['short_description']);
            $stmt->bindValue(':full_description',  $data['full_description']);
            $stmt->bindValue(':extra_info',        $data['extra_info']);
            $stmt->bindValue(':brand',             $data['brand']);
            $stmt->bindValue(':category_id',       $data['category_id']);
            $stmt->bindValue(':in_stock',          (int)$data['in_stock'], PDO::PARAM_INT);
            $stmt->bindValue(':id',                $id, PDO::PARAM_INT);
            if (!empty($data['thumbnail'])) {
                $stmt->bindValue(':thumbnail', $data['thumbnail']);
            }
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("DB error updateProduct: ".$e->getMessage());
            return false;
        }
    }

    /* ---------- PRODUCT: HIDE / UNHIDE ---------- */
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

    /* ---------- PRODUCTS: PAGINATED LIST ---------- */
    public function getProductsPaginated(
        int    $page = 1,
        int    $limit = 10,
        string $sortBy = 'created_at',
        string $search = '',
        bool   $includeHidden = false,
        ?int   $categoryId = null,
        ?float $minPrice = null,
        ?float $maxPrice = null,
        ?string $brand = null
    ): array {
        $result = ['total' => 0, 'products' => []];
        if ($this->conn === null) return $result;

        /* Chỉ cho phép sort theo vài cột an toàn */
        $allowedSort = ['id', 'product_name', 'price', 'created_at', 'is_active'];
        if (!in_array($sortBy, $allowedSort, true)) $sortBy = 'created_at';

        $offset = ($page - 1) * $limit;
        $params = [];
        $where  = [];

        if (!$includeHidden)         $where[] = "p.is_active = 1";
        if ($search !== '') {
            $where[]          = "p.product_name LIKE :search";
            $params[':search'] = "%$search%";
        }
        if ($categoryId) {
            $where[]                = "p.category_id = :category_id";
            $params[':category_id'] = $categoryId;
        }
        if ($minPrice !== null) {
            $where[]              = "p.price >= :min_price";
            $params[':min_price'] = $minPrice;
        }
        if ($maxPrice !== null) {
            $where[]              = "p.price <= :max_price";
            $params[':max_price'] = $maxPrice;
        }
        if ($brand !== null && $brand !== '') {
            $where[]         = "p.brand = :brand";
            $params[':brand'] = $brand;
        }

        $whereSql = $where ? 'WHERE '.implode(' AND ', $where) : '';

        try {
            /* Tổng bản ghi */
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) 
                FROM {$this->products_table} p
                LEFT JOIN categories c ON p.category_id = c.category_id
                $whereSql
            ");
            $stmt->execute($params);
            $result['total'] = (int)$stmt->fetchColumn();
            if ($result['total'] === 0) return $result;

            /* Data */
            $stmt = $this->conn->prepare("
                SELECT p.*, c.category_name
                FROM {$this->products_table} p
                LEFT JOIN categories c ON p.category_id = c.category_id
                $whereSql
                ORDER BY p.$sortBy DESC
                LIMIT :limit OFFSET :offset
            ");
            foreach ($params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            /* Gắn gallery cho từng sản phẩm */
            foreach ($products as &$p) {
                $p['gallery'] = $this->getGalleryByProductId((int)$p['id']);
            }
            unset($p);

            $result['products'] = $products;
            return $result;
        } catch (PDOException $e) {
            error_log("DB error getProductsPaginated: ".$e->getMessage());
            return $result;
        }
    }
}