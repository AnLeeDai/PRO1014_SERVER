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
            $conn->beginTransaction();

            // Cập nhật trạng thái
            $stmt = $conn->prepare("UPDATE orders SET status = :status, updated_at = NOW() WHERE id = :order_id");
            $success = $stmt->execute([
                ':status' => $status,
                ':order_id' => $orderId
            ]);

            if (!$success) {
                $conn->rollBack();
                return false;
            }

            // Nếu trạng thái là delivered hoặc completed thì xử lý thêm
            if (in_array($status, ['delivered', 'completed'])) {
                // Lấy danh sách sản phẩm trong đơn
                $stmtItems = $conn->prepare("SELECT * FROM order_items WHERE order_id = :order_id");
                $stmtItems->execute([':order_id' => $orderId]);
                $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

                foreach ($items as $item) {
                    // Trừ tồn kho sản phẩm
                    $stmtProduct = $conn->prepare("UPDATE products SET in_stock = in_stock - :qty WHERE id = :product_id");
                    $stmtProduct->execute([
                        ':qty' => $item['quantity'],
                        ':product_id' => $item['product_id']
                    ]);

                    // Nếu có mã giảm giá thì trừ số lượng
                    if (!empty($item['discount_code'])) {
                        $stmtDiscount = $conn->prepare("UPDATE discounts SET quantity = quantity - :qty 
                                                    WHERE product_id = :product_id AND discount_code = :code");
                        $stmtDiscount->execute([
                            ':qty' => $item['quantity'],
                            ':product_id' => $item['product_id'],
                            ':code' => $item['discount_code']
                        ]);
                    }
                }

                // Xóa giỏ hàng pending của user
                $stmtUser = $conn->prepare("SELECT user_id FROM orders WHERE id = :order_id");
                $stmtUser->execute([':order_id' => $orderId]);
                $userId = $stmtUser->fetchColumn();

                // Lấy cart ID
                $stmtCart = $conn->prepare("SELECT id FROM carts WHERE user_id = :user_id AND status = 'pending'");
                $stmtCart->execute([':user_id' => $userId]);
                $cartIds = $stmtCart->fetchAll(PDO::FETCH_COLUMN);

                foreach ($cartIds as $cartId) {
                    // Xóa item trong cart
                    $stmtDeleteItems = $conn->prepare("DELETE FROM cart_items WHERE cart_id = :cart_id");
                    $stmtDeleteItems->execute([':cart_id' => $cartId]);

                    // Xóa cart
                    $stmtDeleteCart = $conn->prepare("DELETE FROM carts WHERE id = :cart_id");
                    $stmtDeleteCart->execute([':cart_id' => $cartId]);
                }
            }

            $conn->commit();
            return true;

        } catch (PDOException $e) {
            $conn->rollBack();
            error_log("DB Error updateOrderStatus: " . $e->getMessage());
            return false;
        }
    }

    public function getAllOrders(): array
    {
        $conn = (new Database())->getConnection();

        try {
            $query = "
            SELECT 
                o.id AS id,
                o.user_id,
                o.total_price,
                o.status,
                o.created_at,
                o.updated_at,
                u.full_name,
                u.email,
                
                GROUP_CONCAT(
                    JSON_OBJECT(
                        'id', oi.id,
                        'order_id', oi.order_id,
                        'product_id', oi.product_id,
                        'quantity', oi.quantity,
                        'price', oi.price,
                        'discount_code', oi.discount_code,
                        'product_name', p.product_name,
                        'thumbnail', p.thumbnail
                    )
                    SEPARATOR '||'
                ) AS items

            FROM orders o
            INNER JOIN users u ON o.user_id = u.user_id
            LEFT JOIN order_items oi ON o.id = oi.order_id
            LEFT JOIN products p ON oi.product_id = p.id

            GROUP BY 
                o.id, o.user_id, o.total_price, o.status, o.created_at, o.updated_at,
                u.full_name, u.email

            ORDER BY o.created_at DESC
        ";

            $stmt = $conn->prepare($query);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Parse items JSON sau khi lấy về
            foreach ($rows as &$row) {
                if (!empty($row['items'])) {
                    $itemStrings = explode('||', $row['items']);
                    $row['items'] = array_map(fn($item) => json_decode($item, true), $itemStrings);
                } else {
                    $row['items'] = [];
                }
            }

            return $rows;

        } catch (PDOException $e) {
            error_log("DB Error getAllOrders (refactor): " . $e->getMessage());
            return [];
        }
    }
}
