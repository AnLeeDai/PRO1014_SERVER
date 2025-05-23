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
            return false;
        }
    }

    public function createOrder(
        int    $userId,
        float  $total,
        array  $items,
        string $shippingAddress,
        string $paymentMethod
    ): int|false {
        $conn = (new Database())->getConnection();
        try {
            $conn->beginTransaction();

            foreach ($items as $item) {
                $stmtCheck = $conn->prepare("SELECT product_name, in_stock FROM products WHERE id = :product_id LIMIT 1");
                $stmtCheck->execute([':product_id' => $item['product_id']]);
                $product = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                if (!$product) {
                    $conn->rollBack();
                    return false;
                }

                if ($item['quantity'] > (int)$product['in_stock']) {
                    $conn->rollBack();
                    return false;
                }
            }

            $stmt = $conn->prepare("
                INSERT INTO orders 
                    (user_id, total_price, status, shipping_address, payment_method, created_at, updated_at) 
                VALUES 
                    (:user_id, :total_price, 'pending', :shipping_address, :payment_method, NOW(), NOW())
            ");
            $stmt->execute([
                ':user_id' => $userId,
                ':total_price' => $total,
                ':shipping_address' => $shippingAddress,
                ':payment_method' => $paymentMethod,
            ]);

            $orderId = $conn->lastInsertId();

            $stmtItem = $conn->prepare("
                INSERT INTO order_items 
                    (order_id, product_id, quantity, price) 
                VALUES 
                    (:order_id, :product_id, :quantity, :price)
            ");

            foreach ($items as $item) {
                $stmtItem->execute([
                    ':order_id' => $orderId,
                    ':product_id' => $item['product_id'],
                    ':quantity' => $item['quantity'],
                    ':price' => $item['price']
                ]);
            }

            $conn->commit();
            return $orderId;
        } catch (Exception $e) {
            $conn->rollBack();
            error_log("❌ Exception Order: " . $e->getMessage());
            return false;
        } catch (PDOException $e) {
            $conn->rollBack();
            error_log("❌ DB Error createOrder: " . $e->getMessage());
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
            $stmt = $conn->prepare("
                SELECT oi.*, p.product_name, p.thumbnail
                FROM order_items oi
                INNER JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = :order_id
            ");
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
                $stmtItems = $conn->prepare("SELECT * FROM order_items WHERE order_id = :order_id");
                $stmtItems->execute([':order_id' => $orderId]);
                $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

                foreach ($items as $item) {
                    $stmtProduct = $conn->prepare("
                        UPDATE products 
                        SET in_stock = in_stock - :qty 
                        WHERE id = :product_id
                    ");
                    $stmtProduct->execute([
                        ':qty' => $item['quantity'],
                        ':product_id' => $item['product_id']
                    ]);
                }

                // Xóa giỏ hàng pending của user
                $stmtUser = $conn->prepare("SELECT user_id FROM orders WHERE id = :order_id");
                $stmtUser->execute([':order_id' => $orderId]);
                $userId = $stmtUser->fetchColumn();

                $stmtCart = $conn->prepare("SELECT id FROM carts WHERE user_id = :user_id AND status = 'pending'");
                $stmtCart->execute([':user_id' => $userId]);
                $cartIds = $stmtCart->fetchAll(PDO::FETCH_COLUMN);

                foreach ($cartIds as $cartId) {
                    $stmtDeleteItems = $conn->prepare("DELETE FROM cart_items WHERE cart_id = :cart_id");
                    $stmtDeleteItems->execute([':cart_id' => $cartId]);

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

    public function getOrdersPaginated(
        int    $page = 1,
        int    $limit = 10,
        string $sortBy = 'created_at',
        string $statusFilter = '',
        string $search = ''
    ): array {
        $result = ['total' => 0, 'orders' => []];
        $conn   = (new Database())->getConnection();

        /* sort an toàn */
        $allowedSort = ['created_at', 'updated_at', 'status', 'total_price'];
        if (!in_array($sortBy, $allowedSort)) $sortBy = 'created_at';

        $offset = ($page - 1) * $limit;
        $params = [];
        $where  = [];

        /* ---- lọc theo trạng thái ---- */
        if ($statusFilter !== '') {
            $where[]           = 'o.status = :status';
            $params[':status'] = $statusFilter;
        }

        /* ---- lọc theo username ---- */
        if ($search !== '') {
            $where[]       = 'u.username LIKE :kw';
            $params[':kw'] = '%' . $search . '%';
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        try {
            /* --------- đếm tổng --------- */
            $countSql = "
                SELECT COUNT(DISTINCT o.id)
                FROM orders o
                INNER JOIN users u ON o.user_id = u.user_id
                $whereSql
            ";
            $stmt = $conn->prepare($countSql);
            $stmt->execute($params);
            $result['total'] = (int)$stmt->fetchColumn();
            if ($result['total'] === 0) return $result;

            /* --------- lấy dữ liệu --------- */
            $dataSql = "
                SELECT 
                    o.id,
                    o.user_id,
                    o.total_price,
                    o.status,
                    o.created_at,
                    o.updated_at,
                    o.payment_method,
                    o.shipping_address,
                    u.username,
                    u.full_name,
                    GROUP_CONCAT(
                        JSON_OBJECT(
                            'id',           oi.id,
                            'order_id',     oi.order_id,
                            'product_id',   oi.product_id,
                            'quantity',     oi.quantity,
                            'price',        oi.price,
                            'product_name', p.product_name,
                            'thumbnail',    p.thumbnail
                        ) SEPARATOR '||'
                    ) AS items
                FROM orders o
                INNER JOIN users u        ON o.user_id = u.user_id
                LEFT  JOIN order_items oi ON o.id      = oi.order_id
                LEFT  JOIN products p     ON oi.product_id = p.id
                $whereSql
                GROUP BY 
                    o.id, o.user_id, o.total_price, o.status,
                    o.created_at, o.updated_at, o.payment_method,
                    o.shipping_address, u.username, u.full_name
                ORDER BY o.$sortBy DESC
                LIMIT  :limit OFFSET :offset
            ";

            $stmt = $conn->prepare($dataSql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v, PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            /* tách items JSON */
            foreach ($rows as &$row) {
                $row['items'] = $row['items']
                    ? array_map(fn($j) => json_decode($j, true), explode('||', $row['items']))
                    : [];
            }

            $result['orders'] = $rows;
            return $result;
        } catch (PDOException $e) {
            error_log('OrderModel::getOrdersPaginated -> ' . $e->getMessage());
            return $result;
        }
    }
}
