<?php

class CartController
{
    private CartModel $cartModel;

    public function __construct()
    {
        $this->cartModel = new CartModel();
    }

    public function handleGetCartItems(): void
    {
        $userData = AuthMiddleware::isUser();
        $userId = $userData['user_id'];

        $items = $this->cartModel->getCartItemsByUser($userId);

        Utils::respond([
            'success' => true,
            'message' => 'Lấy giỏ hàng thành công.',
            'cart_items' => $items
        ], 200);
    }

    public function handleAddToCart(): void
    {
        $user = AuthMiddleware::isUser();
        $userId = $user['user_id'];

        $data = json_decode(file_get_contents("php://input"), true);

        $rules = [
            'product_id' => 'ID sản phẩm không được để trống',
            'quantity' => 'Số lượng không được để trống'
        ];
        $errors = Utils::validateBasicInput($data, $rules);
        if (!empty($errors)) {
            Utils::respond(["success" => false, "message" => "Thiếu thông tin.", "errors" => $errors], 400);
        }

        $productId = (int)$data['product_id'];
        $quantity = (int)$data['quantity'];
        $discountCode = trim($data['discount_code'] ?? '');

        if ($productId <= 0 || $quantity <= 0) {
            Utils::respond(["success" => false, "message" => "ID sản phẩm hoặc số lượng không hợp lệ."], 400);
        }

        $cartModel = new CartModel();

        // Lấy thông tin sản phẩm
        $product = $cartModel->getProductStockAndPrice($productId);
        if (!$product) {
            Utils::respond(["success" => false, "message" => "Sản phẩm không tồn tại hoặc đã bị ẩn."], 404);
        }

        $inStock = (int)$product['in_stock'];
        $originalPrice = (float)$product['price'];
        $finalPrice = $originalPrice;

        // Nếu có mã giảm giá, xử lý kiểm tra
        if ($discountCode !== '') {
            $discountInfo = $cartModel->getValidDiscount($productId, $discountCode);
            if (!$discountInfo) {
                Utils::respond(["success" => false, "message" => "Mã giảm giá không hợp lệ hoặc hết hạn."], 400);
            }

            // Kiểm tra số lượng mã giảm còn lại
            if ((int)$discountInfo['quantity'] <= 0) {
                Utils::respond(["success" => false, "message" => "Mã giảm giá đã được sử dụng hết."], 400);
            }

            // Áp dụng phần trăm giảm
            $discountPercent = (float)$discountInfo['percent_value'];
            $finalPrice = $originalPrice * (1 - $discountPercent / 100);
        }

        // Lấy hoặc tạo giỏ hàng
        $cartId = $cartModel->getPendingCartIdByUser($userId) ?: $cartModel->createCartForUser($userId);
        if (!$cartId) {
            Utils::respond(["success" => false, "message" => "Không thể tạo giỏ hàng."], 500);
        }

        $currentQtyInCart = $cartModel->getQuantityInCart($cartId, $productId);
        if ($quantity + $currentQtyInCart > $inStock) {
            Utils::respond(["success" => false, "message" => "Vượt quá số lượng tồn kho. Hiện tại còn {$inStock} sản phẩm."], 400);
        }

        // Thêm hoặc cập nhật vào giỏ hàng
        $success = $cartModel->addOrUpdateCartItem($cartId, $productId, $quantity, $finalPrice);
        if ($success) {
            // Nếu có discount thì trừ số lượng
            if (!empty($discountInfo)) {
                $cartModel->decreaseDiscountQuantity($discountInfo['id']);
            }

            Utils::respond(["success" => true, "message" => "Thêm vào giỏ hàng thành công!"], 200);
        } else {
            Utils::respond(["success" => false, "message" => "Lỗi khi thêm vào giỏ hàng."], 500);
        }
    }
}
