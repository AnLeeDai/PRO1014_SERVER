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

    public function getCartItem(int $cartId, int $productId): array
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT quantity
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

    public function updateCartItemQuantity(int $cartId, int $productId, int $quantity, float $price): bool
    {
        try {
            $stmt = $this->conn->prepare("
                UPDATE {$this->cartItemsTable}
                SET quantity = :quantity,
                    price = :price,
                    created_at = NOW()
                WHERE cart_id = :cart_id
                  AND product_id = :product_id
            ");
            return $stmt->execute([
                ':quantity' => $quantity,
                ':price' => $price,
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
                    p.product_name,
                    p.thumbnail,
                    p.in_stock,
                    c.id AS cart_id,
                    c.status
                FROM carts c
                INNER JOIN cart_items ci ON c.id = ci.cart_id
                INNER JOIN products p ON ci.product_id = p.id
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
            SELECT id, product_name, price, in_stock
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

    public function addOrUpdateCartItem(
        int   $cartId,
        int   $productId,
        int   $quantity,
        float $price
    ): bool {
        try {
            $existingQty = $this->getQuantityInCart($cartId, $productId);

            if ($existingQty > 0) {
                $stmt = $this->conn->prepare("
                    UPDATE {$this->cartItemsTable}
                    SET quantity = quantity + :quantity,
                        price = :price,
                        created_at = NOW()
                    WHERE cart_id = :cart_id
                      AND product_id = :product_id
                ");
            } else {
                $stmt = $this->conn->prepare("
                    INSERT INTO {$this->cartItemsTable}
                    (cart_id, product_id, quantity, price, created_at)
                    VALUES
                    (:cart_id, :product_id, :quantity, :price, NOW())
                ");
            }

            return $stmt->execute([
                ':cart_id' => $cartId,
                ':product_id' => $productId,
                ':quantity' => $quantity,
                ':price' => $price
            ]);
        } catch (PDOException $e) {
            error_log("DB Error addOrUpdateCartItem: " . $e->getMessage());
            return false;
        }
    }
}
