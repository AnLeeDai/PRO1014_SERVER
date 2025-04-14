<?php

class CartModel
{
    public ?PDO $conn = null;
    private string $cartsTable = "carts";
    private string $cartItemsTable = "cart_items";
    private string $productsTable = "products";

    public function __construct()
    {
        $this->conn = (new Database())->getConnection();
    }

    public function deleteCartItem(int $cartId, int $productId): int
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as total
                FROM {$this->cartItemsTable}
                WHERE cart_id = :cart_id
                  AND product_id = :product_id
            ");
            $stmt->execute([
                ':cart_id' => $cartId,
                ':product_id' => $productId
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result && (int)$result['total'] === 0) {
                return 0;
            }

            $deleteStmt = $this->conn->prepare("
                DELETE FROM {$this->cartItemsTable}
                WHERE cart_id = :cart_id
                  AND product_id = :product_id
            ");
            $deleteStmt->execute([
                ':cart_id' => $cartId,
                ':product_id' => $productId
            ]);

            return 1;
        } catch (PDOException $e) {
            error_log("DB Error deleteCartItem: " . $e->getMessage());
            return -1;
        }
    }

    /**
     * Lấy 1 dòng cart_item theo cart_id + product_id
     */
    public function getCartItem(int $cartId, int $productId): array
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT discount_code, quantity
                FROM {$this->cartItemsTable}
                WHERE cart_id = :cart_id
                  AND product_id = :product_id
                LIMIT 1
            ");
            $stmt->execute([
                ':cart_id' => $cartId,
                ':product_id' => $productId
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row : [];
        } catch (PDOException $e) {
            error_log("DB Error getCartItem: " . $e->getMessage());
            return [];
        }
    }

    public function updateCartItemQuantity(int $cartId, int $productId, int $quantity, float $price, ?string $discountCode = null): bool
    {
        try {
            $stmt = $this->conn->prepare("
                UPDATE {$this->cartItemsTable}
                SET quantity = :quantity,
                    price = :price,
                    discount_code = :discount_code,
                    created_at = NOW()
                WHERE cart_id = :cart_id
                  AND product_id = :product_id
            ");
            return $stmt->execute([
                ':quantity' => $quantity,
                ':price' => $price,
                ':discount_code' => $discountCode,
                ':cart_id' => $cartId,
                ':product_id' => $productId
            ]);
        } catch (PDOException $e) {
            error_log("DB Error updateCartItemQuantity: " . $e->getMessage());
            return false;
        }
    }

    public function getCartItemsByUser(int $userId): array
    {
        if ($this->conn === null) return [];

        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    ci.id AS cart_item_id,
                    ci.product_id,
                    ci.quantity,
                    ci.price AS original_price,
                    ci.discount_code,
                    p.product_name,
                    p.thumbnail,
                    p.in_stock,
                    d.percent_value,
                    (
                        IF(
                            d.id IS NOT NULL
                            AND d.start_date <= NOW()
                            AND d.end_date >= NOW()
                            AND d.quantity < d.total_quantity,
                            ROUND(ci.price * (1 - d.percent_value / 100), 2),
                            ci.price
                        )
                    ) AS final_price,
                    c.id AS cart_id,
                    c.status
                FROM carts c
                INNER JOIN cart_items ci ON c.id = ci.cart_id
                INNER JOIN products p ON ci.product_id = p.id
                LEFT JOIN discounts d
                    ON ci.discount_code = d.discount_code
                    AND ci.product_id = d.product_id
                WHERE c.user_id = :user_id
                  AND c.status = 'pending'
            ");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("DB Error getCartItemsByUser: " . $e->getMessage());
            return [];
        }
    }

    public function getValidDiscount(int $productId, string $code): array|false
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT *
                FROM discounts
                WHERE product_id = :product_id
                  AND discount_code = :code
                  AND quantity > 0
                  AND start_date <= NOW()
                  AND end_date >= NOW()
                LIMIT 1
            ");
            $stmt->execute([
                ':product_id' => $productId,
                ':code' => $code
            ]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("DB Error getValidDiscount: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Tìm discount theo code + product_id (để lấy id => increaseDiscountQuantity)
     */
    public function getDiscountByCode(string $code, int $productId): array|false
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT *
                FROM discounts
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
            error_log("DB Error getDiscountByCode: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Giảm số lượng discount
     */
    public function decreaseDiscountQuantity(int $discountId, int $usedQty = 1): void
    {
        try {
            $stmt = $this->conn->prepare("
                UPDATE discounts
                SET quantity = quantity - :usedQty,
                    updated_at = NOW()
                WHERE id = :id
                  AND quantity >= :usedQty
            ");
            $stmt->execute([
                ':id' => $discountId,
                ':usedQty' => $usedQty
            ]);
        } catch (PDOException $e) {
            error_log("DB Error decreaseDiscountQuantity: " . $e->getMessage());
        }
    }

    /**
     * Tăng số lượng discount (nếu user bỏ discount hoặc giảm quantity)
     */
    public function increaseDiscountQuantity(int $discountId, int $qty = 1): void
    {
        try {
            $stmt = $this->conn->prepare("
                UPDATE discounts
                SET quantity = quantity + :qty,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':id' => $discountId,
                ':qty' => $qty
            ]);
        } catch (PDOException $e) {
            error_log("DB Error increaseDiscountQuantity: " . $e->getMessage());
        }
    }

    public function getPendingCartIdByUser(int $userId): int|false
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT id
                FROM {$this->cartsTable}
                WHERE user_id = :user_id
                  AND status = 'pending'
                LIMIT 1
            ");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (int)$row['id'] : false;
        } catch (PDOException $e) {
            error_log("DB Error getPendingCartIdByUser: " . $e->getMessage());
            return false;
        }
    }

    public function createCartForUser(int $userId): int|false
    {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO {$this->cartsTable}
                (user_id, status, created_at, updated_at)
                VALUES
                (:user_id, 'pending', NOW(), NOW())
            ");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            return (int)$this->conn->lastInsertId();
        } catch (PDOException $e) {
            error_log("DB Error createCartForUser: " . $e->getMessage());
            return false;
        }
    }

    public function getProductStockAndPrice(int $productId): array|false
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT price, in_stock
                FROM {$this->productsTable}
                WHERE id = :product_id
                  AND is_active = 1
            ");
            $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException) {
            return false;
        }
    }

    public function getQuantityInCart(int $cartId, int $productId): int
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT quantity
                FROM {$this->cartItemsTable}
                WHERE cart_id = :cart_id
                  AND product_id = :product_id
            ");
            $stmt->execute([
                ':cart_id' => $cartId,
                ':product_id' => $productId
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (int)$row['quantity'] : 0;
        } catch (PDOException) {
            return 0;
        }
    }

    /**
     * Thêm hoặc cập nhật cart item.
     * Nếu đã có product_id => quantity += ?; ngược lại => insert mới.
     */
    public function addOrUpdateCartItem(
        int     $cartId,
        int     $productId,
        int     $quantity,
        float   $price,
        ?string $discountCode = null
    ): bool
    {
        try {
            $existingQty = $this->getQuantityInCart($cartId, $productId);

            if ($existingQty > 0) {
                // Đã có => update
                $stmt = $this->conn->prepare("
                    UPDATE {$this->cartItemsTable}
                    SET quantity = quantity + :quantity,
                        price = :price,
                        discount_code = :discount_code,
                        created_at = NOW()
                    WHERE cart_id = :cart_id
                      AND product_id = :product_id
                ");
            } else {
                // Chưa có => insert
                $stmt = $this->conn->prepare("
                    INSERT INTO {$this->cartItemsTable}
                    (cart_id, product_id, quantity, price, discount_code, created_at)
                    VALUES
                    (:cart_id, :product_id, :quantity, :price, :discount_code, NOW())
                ");
            }

            return $stmt->execute([
                ':cart_id' => $cartId,
                ':product_id' => $productId,
                ':quantity' => $quantity,
                ':price' => $price,
                ':discount_code' => $discountCode
            ]);
        } catch (PDOException $e) {
            error_log("DB Error addOrUpdateCartItem: " . $e->getMessage());
            return false;
        }
    }
}
