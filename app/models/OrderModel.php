<?php

class OrderModel
{

    public function orderExists(int $orderId): bool
    {
        $conn = (new Database())->getConnection();
        try {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM orders WHERE id = :order_id");
            $stmt->execute([':order_id' => $orderId]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("DB Error orderExists: " . $e->getMessage());
            return false;
        }
    }

    public function createOrder(int $userId, float $total, array $items): int|false
    {
        $conn = (new Database())->getConnection();
        try {
            $conn->beginTransaction();

            $stmt = $conn->prepare("INSERT INTO orders (user_id, total_price, status, created_at, updated_at) 
                                    VALUES (:user_id, :total, 'pending', NOW(), NOW())");
            $stmt->execute([
                ':user_id' => $userId,
                ':total' => $total
            ]);
            $orderId = $conn->lastInsertId();

            $stmtItem = $conn->prepare("INSERT INTO order_items 
                (order_id, product_id, quantity, price, discount_code) 
                VALUES (:order_id, :product_id, :quantity, :price, :discount_code)");

            foreach ($items as $item) {
                $stmtItem->execute([
                    ':order_id' => $orderId,
                    ':product_id' => $item['product_id'],
                    ':quantity' => $item['quantity'],
                    ':price' => $item['price'],
                    ':discount_code' => $item['discount_code']
                ]);
            }

            $conn->commit();
            return $orderId;
        } catch (PDOException $e) {
            $conn->rollBack();
            error_log("DB Error createOrder: " . $e->getMessage());
            return false;
        }
    }

    public function clearCart(int $cartId): void
    {
        $conn = (new Database())->getConnection();
        $stmt = $conn->prepare("DELETE FROM cart_items WHERE cart_id = :cart_id");
        $stmt->execute([':cart_id' => $cartId]);
    }

    public function getOrdersByUser(int $userId): array
    {
        $conn = (new Database())->getConnection();
        try {
            $stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = :user_id ORDER BY created_at DESC");
            $stmt->execute([':user_id' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("DB Error getOrdersByUser: " . $e->getMessage());
            return [];
        }
    }

    public function getOrderItems(int $orderId): array
    {
        $conn = (new Database())->getConnection();
        try {
            $stmt = $conn->prepare("SELECT oi.*, p.product_name, p.thumbnail
                FROM order_items oi
                INNER JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = :order_id");
            $stmt->execute([':order_id' => $orderId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("DB Error getOrderItems: " . $e->getMessage());
            return [];
        }
    }

    public function updateOrderStatus(int $orderId, string $status): bool
    {
        $conn = (new Database())->getConnection();
        try {
            $stmt = $conn->prepare("UPDATE orders SET status = :status, updated_at = NOW() WHERE id = :order_id");
            return $stmt->execute([
                ':status' => $status,
                ':order_id' => $orderId
            ]);
        } catch (PDOException $e) {
            error_log("DB Error updateOrderStatus: " . $e->getMessage());
            return false;
        }
    }

    public function getAllOrders(): array
    {
        $conn = (new Database())->getConnection();
        try {
            $stmt = $conn->prepare("SELECT o.*, u.full_name, u.email FROM orders o INNER JOIN users u ON o.user_id = u.user_id ORDER BY o.created_at DESC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("DB Error getAllOrders: " . $e->getMessage());
            return [];
        }
    }
}
