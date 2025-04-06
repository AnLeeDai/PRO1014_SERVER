<?php

class CartModel
{
    private ?PDO $conn;
    private string $cartsTable = "carts";
    private string $cartItemsTable = "cart_items";
    private string $productsTable = "products";

    public function __construct()
    {
        $this->conn = (new Database())->getConnection();
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
                d.percent_value,
                d.start_date,
                d.end_date,
                d.quantity,
                d.total_quantity,
                (
                    IF(d.id IS NOT NULL 
                             AND d.start_date <= NOW()
                             AND d.end_date >= NOW()
                             AND d.quantity < d.total_quantity, ROUND(ci.price * (1 - d.percent_value / 100), 2), ci.price)
                ) AS final_price,
                c.id AS cart_id,
                c.status
            FROM carts c
            INNER JOIN cart_items ci ON c.id = ci.cart_id
            INNER JOIN products p ON ci.product_id = p.id
            LEFT JOIN discounts d ON ci.product_id = d.product_id
            WHERE c.user_id = :user_id AND c.status = 'pending'
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
            SELECT * FROM discounts 
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

    public function decreaseDiscountQuantity(int $discountId): void
    {
        try {
            $stmt = $this->conn->prepare("
            UPDATE discounts 
            SET quantity = quantity - 1, updated_at = NOW()
            WHERE id = :id AND quantity > 0
        ");
            $stmt->execute([':id' => $discountId]);
        } catch (PDOException $e) {
            error_log("DB Error decreaseDiscountQuantity: " . $e->getMessage());
        }
    }

    public function getPendingCartIdByUser(int $userId): int|false
    {
        try {
            $stmt = $this->conn->prepare("SELECT id FROM {$this->cartsTable} WHERE user_id = :user_id AND status = 'pending' LIMIT 1");
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
            $stmt = $this->conn->prepare("INSERT INTO {$this->cartsTable} (user_id, status, created_at, updated_at) VALUES (:user_id, 'pending', NOW(), NOW())");
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
            $stmt = $this->conn->prepare("SELECT price, in_stock FROM {$this->productsTable} WHERE id = :product_id AND is_active = 1");
            $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function getQuantityInCart(int $cartId, int $productId): int
    {
        try {
            $stmt = $this->conn->prepare("SELECT quantity FROM {$this->cartItemsTable} WHERE cart_id = :cart_id AND product_id = :product_id");
            $stmt->execute([
                ':cart_id' => $cartId,
                ':product_id' => $productId
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (int)$row['quantity'] : 0;
        } catch (PDOException $e) {
            return 0;
        }
    }

    public function addOrUpdateCartItem(int $cartId, int $productId, int $quantity, float $price): bool
    {
        try {
            $existingQty = $this->getQuantityInCart($cartId, $productId);

            if ($existingQty > 0) {
                $stmt = $this->conn->prepare("UPDATE {$this->cartItemsTable} SET quantity = quantity + :quantity, price = :price, created_at = NOW() WHERE cart_id = :cart_id AND product_id = :product_id");
            } else {
                $stmt = $this->conn->prepare("INSERT INTO {$this->cartItemsTable} (cart_id, product_id, quantity, price, created_at) VALUES (:cart_id, :product_id, :quantity, :price, NOW())");
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
