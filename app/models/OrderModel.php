<?php

class OrderModel
{
    public function createOrder(int $userId, float $total, array $items): int|false
    {
        $conn = (new Database())->getConnection();
        try {
            $conn->beginTransaction();

            $stmt = $conn->prepare("INSERT INTO orders (user_id, total_price, status, created_at, updated_at) VALUES (:user_id, :total, 'pending', NOW(), NOW())");
            $stmt->execute([
                ':user_id' => $userId,
                ':total' => $total
            ]);
            $orderId = $conn->lastInsertId();

            $stmtItem = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, discount_code) VALUES (:order_id, :product_id, :quantity, :price, :discount_code)");
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
}
