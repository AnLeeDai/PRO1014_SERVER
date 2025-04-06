<?php

class OrderController
{
    private OrderModel $orderModel;
    private CartModel $cartModel;

    public function __construct()
    {
        $this->orderModel = new OrderModel();
        $this->cartModel = new CartModel();
    }

    public function handleCheckout(): void
    {
        $user = AuthMiddleware::isUser();
        $userId = $user['user_id'];

        $data = json_decode(file_get_contents("php://input"), true);
        $type = $data['type'] ?? '';

        if (!in_array($type, ['buy_now', 'from_cart'])) {
            Utils::respond(["success" => false, "message" => "Loại thanh toán không hợp lệ."], 400);
        }

        $orderItems = [];
        $total = 0;

        if ($type === 'buy_now') {
            $productId = (int)($data['product_id'] ?? 0);
            $quantity = (int)($data['quantity'] ?? 0);
            $discountCode = trim($data['discount_code'] ?? '');

            if ($productId <= 0 || $quantity <= 0) {
                Utils::respond(["success" => false, "message" => "Thông tin sản phẩm không hợp lệ."], 400);
            }

            $product = $this->cartModel->getProductStockAndPrice($productId);
            if (!$product || $quantity > (int)$product['in_stock']) {
                Utils::respond(["success" => false, "message" => "Sản phẩm không tồn tại hoặc vượt quá tồn kho."], 400);
            }

            $price = (float)$product['price'];
            $finalPrice = $price;

            if ($discountCode !== '') {
                $discount = $this->cartModel->getValidDiscount($productId, $discountCode);
                if (!$discount) {
                    Utils::respond(["success" => false, "message" => "Mã giảm giá không hợp lệ."], 400);
                }
                $finalPrice = round($price * (1 - $discount['percent_value'] / 100), 2);
                $this->cartModel->decreaseDiscountQuantity($discount['id']);
            }

            $orderItems[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'price' => $finalPrice,
                'discount_code' => $discountCode
            ];
            $total += $finalPrice * $quantity;

        } else if ($type === 'from_cart') {
            $items = $this->cartModel->getCartItemsByUser($userId);
            if (empty($items)) {
                Utils::respond(["success" => false, "message" => "Giỏ hàng rỗng."], 400);
            }

            foreach ($items as $item) {
                $orderItems[] = [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['final_price'],
                    'discount_code' => $item['discount_code']
                ];
                $total += $item['final_price'] * $item['quantity'];

                if (!empty($item['discount_code'])) {
                    $discount = $this->cartModel->getValidDiscount($item['product_id'], $item['discount_code']);
                    if ($discount) {
                        $this->cartModel->decreaseDiscountQuantity($discount['id']);
                    }
                }
            }
        }

        $orderId = $this->orderModel->createOrder($userId, $total, $orderItems);

        if ($type === 'from_cart') {
            $cartId = $this->cartModel->getPendingCartIdByUser($userId);
            if ($cartId) {
                $this->orderModel->clearCart($cartId);
            }
        }

        Utils::respond([
            "success" => true,
            "message" => "Đặt hàng thành công!",
            "order_id" => $orderId
        ], 200);
    }

    public function handleGetOrderHistory(): void
    {
        $user = AuthMiddleware::isUser();
        $userId = $user['user_id'];

        $orders = $this->orderModel->getOrdersByUser($userId);
        $data = [];

        foreach ($orders as $order) {
            $items = $this->orderModel->getOrderItems($order['id']);
            $order['items'] = $items;
            $data[] = $order;
        }

        Utils::respond([
            "success" => true,
            "message" => "Lấy lịch sử đơn hàng thành công.",
            "orders" => $data
        ], 200);
    }

    public function handleAdminUpdateOrderStatus(): void
    {
        AuthMiddleware::isAdmin();

        $data = json_decode(file_get_contents("php://input"), true);
        $orderId = (int)($data['order_id'] ?? 0);
        $status = $data['status'] ?? '';

        if ($orderId <= 0 || !in_array($status, ['pending', 'paid', 'delivered', 'completed', 'cancelled'])) {
            Utils::respond(["success" => false, "message" => "Dữ liệu không hợp lệ."], 400);
        }

        $success = $this->orderModel->updateOrderStatus($orderId, $status);
        if ($success) {
            Utils::respond(["success" => true, "message" => "Cập nhật trạng thái đơn hàng thành công."], 200);
        } else {
            Utils::respond(["success" => false, "message" => "Cập nhật thất bại."], 500);
        }
    }

    public function handleAdminListAllOrders(): void
    {
        AuthMiddleware::isAdmin();

        $orders = $this->orderModel->getAllOrders();
        $data = [];

        foreach ($orders as $order) {
            $items = $this->orderModel->getOrderItems($order['id']);
            $order['items'] = $items;
            $data[] = $order;
        }

        Utils::respond([
            "success" => true,
            "message" => "Lấy danh sách tất cả đơn hàng thành công.",
            "orders" => $data
        ], 200);
    }
}