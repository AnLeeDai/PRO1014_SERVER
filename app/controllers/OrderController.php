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

        $shippingAddress = trim($data['shipping_address'] ?? '');
        $paymentMethod = trim($data['payment_method'] ?? 'bank_transfer');
        $validMethods = ['bank_transfer', 'visa', 'cash'];

        if (!in_array($paymentMethod, $validMethods)) {
            $paymentMethod = 'bank_transfer';
        }

        if (!in_array($type, ['buy_now', 'from_cart'])) {
            Utils::respond([
                "success" => false,
                "message" => "Loại thanh toán không hợp lệ."
            ], 400);
        }

        $orderItems = [];
        $total = 0;

        if ($type === 'buy_now') {
            $productId = (int)($data['product_id'] ?? 0);
            $quantity = (int)($data['quantity'] ?? 0);

            if ($productId <= 0 || $quantity <= 0) {
                Utils::respond(["success" => false, "message" => "Thông tin sản phẩm không hợp lệ."], 400);
            }

            $product = $this->cartModel->getProductStockAndPrice($productId);
            if (!$product) {
                Utils::respond(["success" => false, "message" => "Sản phẩm không tồn tại."], 400);
            }

            $finalPrice = (float)$product['price'];

            $orderItems[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'price' => $finalPrice
            ];
            $total += $finalPrice * $quantity;
        } else if ($type === 'from_cart') {
            if (empty($shippingAddress)) {
                Utils::respond([
                    "success" => false,
                    "message" => "Vui lòng nhập địa chỉ giao hàng."
                ], 400);
            }

            $items = $this->cartModel->getCartItemsByUser($userId);
            if (empty($items)) {
                Utils::respond(["success" => false, "message" => "Giỏ hàng rỗng."], 400);
            }

            foreach ($items as $item) {
                $orderItems[] = [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['original_price']
                ];
                $total += $item['original_price'] * $item['quantity'];
            }
        }

        // check product in stock
        foreach ($orderItems as $item) {
            $product = $this->cartModel->getProductStockAndPrice($item['product_id']);

            if (!$product) {
                Utils::respond([
                    "success" => false,
                    "message" => "Sản phẩm không tồn tại."
                ], 400);
            }


            if ($item['quantity'] > (int)$product['in_stock']) {
                Utils::respond([
                    "success" => false,
                    "message" => "Sản phẩm '{$product['product_name']}' không còn đủ số lượng trong kho."
                ], 400);
            }
        }

        $orderId = $this->orderModel->createOrder(
            $userId,
            $total,
            $orderItems,
            $shippingAddress,
            $paymentMethod
        );

        if (!$orderId) {
            Utils::respond([
                "success" => false,
                "message" => "Đặt hàng thất bại."
            ], 500);
        }

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

        if (!$this->orderModel->orderExists($orderId)) {
            Utils::respond(["success" => false, "message" => "Đơn hàng không tồn tại."], 404);
        }

        $success = $this->orderModel->updateOrderStatus($orderId, $status);
        if ($success) {
            Utils::respond(["success" => true, "message" => "Cập nhật trạng thái đơn hàng thành công."], 200);
        } else {
            Utils::respond(["success" => false, "message" => "Cập nhật thất bại."], 500);
        }
    }

    public function handleAdminListOrdersPaginated(): void
    {
        AuthMiddleware::isAdmin();

        $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
        $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT, ['options' => ['default' => 10, 'min_range' => 1]]);
        $sortBy = filter_input(INPUT_GET, 'sort_by', FILTER_SANITIZE_SPECIAL_CHARS) ?: 'created_at';
        $status = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_SPECIAL_CHARS) ?: '';
        $search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS) ?: '';

        $result = $this->orderModel->getOrdersPaginated($page, $limit, $sortBy, $status, $search);

        Utils::respond(Utils::buildPaginatedResponse(
            true,
            "Lấy danh sách đơn hàng thành công.",
            $result['orders'] ?? [],
            $page,
            $limit,
            $result['total'] ?? 0,
            ['status' => $status, 'sort_by' => $sortBy, 'search' => $search]
        ), 200);
    }
}
