<?php

class CartController
{
    private CartModel $cartModel;

    public function __construct()
    {
        $this->cartModel = new CartModel();
    }

    public function handleDeleteCartItem(): void
    {
        $user = AuthMiddleware::isUser();
        $userId = $user['user_id'];

        $data = json_decode(file_get_contents("php://input"), true);
        $productId = (int)($data['product_id'] ?? 0);

        if ($productId <= 0) {
            Utils::respond(["success" => false, "message" => "ID sản phẩm không hợp lệ."], 400);
        }

        $cartId = $this->cartModel->getPendingCartIdByUser($userId);
        if (!$cartId) {
            Utils::respond(["success" => false, "message" => "Không tìm thấy giỏ hàng."], 404);
        }

        $result = $this->cartModel->deleteCartItem($cartId, $productId);

        if ($result === 0) {
            Utils::respond(["success" => false, "message" => "Sản phẩm không tồn tại trong giỏ hàng."], 404);
        } elseif ($result === 1) {
            Utils::respond(["success" => true, "message" => "Xóa sản phẩm khỏi giỏ hàng thành công."], 200);
        } else {
            Utils::respond(["success" => false, "message" => "Lỗi khi xóa sản phẩm khỏi giỏ hàng."], 500);
        }
    }

    public function handleUpdateCartItem(): void
    {
        $user = AuthMiddleware::isUser();
        $userId = $user['user_id'];

        $data = json_decode(file_get_contents("php://input"), true);
        $productId = (int)($data['product_id'] ?? 0);
        $newQty = (int)($data['quantity'] ?? 0);

        if ($productId <= 0 || $newQty <= 0) {
            Utils::respond(["success" => false, "message" => "ID sản phẩm hoặc số lượng không hợp lệ."], 400);
        }

        $cartId = $this->cartModel->getPendingCartIdByUser($userId);
        if (!$cartId) {
            Utils::respond(["success" => false, "message" => "Không tìm thấy giỏ hàng."], 404);
        }

        $oldItem = $this->cartModel->getCartItem($cartId, $productId);
        $oldQty = (int)($oldItem['quantity'] ?? 0);

        $product = $this->cartModel->getProductStockAndPrice($productId);
        if (!$product) {
            Utils::respond(["success" => false, "message" => "Sản phẩm không tồn tại hoặc bị ẩn."], 404);
        }
        $inStock = (int)$product['in_stock'];
        $originalPrice = (float)$product['price'];

        if ($newQty > $inStock) {
            Utils::respond(["success" => false, "message" => "Vượt quá tồn kho, còn {$inStock} sản phẩm."], 400);
        }

        $success = $this->cartModel->updateCartItemQuantity(
            $cartId,
            $productId,
            $newQty,
            $originalPrice,
            ''
        );

        if (!$success) {
            Utils::respond(["success" => false, "message" => "Lỗi khi cập nhật giỏ hàng."], 500);
        }

        Utils::respond(["success" => true, "message" => "Cập nhật giỏ hàng thành công!"], 200);
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

        if ($productId <= 0 || $quantity <= 0) {
            Utils::respond(["success" => false, "message" => "ID sản phẩm hoặc số lượng không hợp lệ."], 400);
        }

        $product = $this->cartModel->getProductStockAndPrice($productId);
        if (!$product) {
            Utils::respond(["success" => false, "message" => "Sản phẩm không tồn tại hoặc đã bị ẩn."], 404);
        }

        $inStock = (int)$product['in_stock'];
        $originalPrice = (float)$product['price'];

        $cartId = $this->cartModel->getPendingCartIdByUser($userId) ?: $this->cartModel->createCartForUser($userId);
        if (!$cartId) {
            Utils::respond(["success" => false, "message" => "Không thể tạo giỏ hàng."], 500);
        }

        $currentQtyInCart = $this->cartModel->getQuantityInCart($cartId, $productId);
        if ($quantity + $currentQtyInCart > $inStock) {
            Utils::respond(["success" => false, "message" => "Vượt quá số lượng tồn kho. Còn {$inStock} sản phẩm."], 400);
        }

        $success = $this->cartModel->addOrUpdateCartItem($cartId, $productId, $quantity, $originalPrice, '');

        if ($success) {
            Utils::respond(["success" => true, "message" => "Thêm vào giỏ hàng thành công!"], 200);
        } else {
            Utils::respond(["success" => false, "message" => "Lỗi khi thêm vào giỏ hàng."], 500);
        }
    }
}
