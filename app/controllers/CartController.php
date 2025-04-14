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

    /**
     * Cập nhật số lượng và (có thể) discount code cho cart item.
     * Nếu user bỏ discount hoặc giảm quantity => cộng lại discount cũ.
     * Nếu user thêm discount hoặc tăng quantity => trừ discount mới.
     */
    public function handleUpdateCartItem(): void
    {
        $user = AuthMiddleware::isUser();
        $userId = $user['user_id'];

        $data = json_decode(file_get_contents("php://input"), true);
        $productId = (int)($data['product_id'] ?? 0);
        $newQty = (int)($data['quantity'] ?? 0);
        $newDiscountCode = trim($data['discount_code'] ?? '');

        if ($productId <= 0 || $newQty <= 0) {
            Utils::respond(["success" => false, "message" => "ID sản phẩm hoặc số lượng không hợp lệ."], 400);
        }

        // Lấy cart ID
        $cartId = $this->cartModel->getPendingCartIdByUser($userId);
        if (!$cartId) {
            Utils::respond(["success" => false, "message" => "Không tìm thấy giỏ hàng."], 404);
        }

        // Lấy discount code cũ và số lượng cũ
        $oldItem = $this->cartModel->getCartItem($cartId, $productId);
        $oldDiscountCode = $oldItem['discount_code'] ?? '';
        $oldQty = (int)($oldItem['quantity'] ?? 0);

        // Lấy thông tin sản phẩm
        $product = $this->cartModel->getProductStockAndPrice($productId);
        if (!$product) {
            Utils::respond(["success" => false, "message" => "Sản phẩm không tồn tại hoặc bị ẩn."], 404);
        }
        $inStock = (int)$product['in_stock'];
        $originalPrice = (float)$product['price'];

        // Tính finalPrice cho discount mới
        $newFinalPrice = $originalPrice;
        $newDiscountInfo = null;
        if ($newDiscountCode !== '') {
            $newDiscountInfo = $this->cartModel->getValidDiscount($productId, $newDiscountCode);
            if (!$newDiscountInfo) {
                Utils::respond(["success" => false, "message" => "Mã giảm giá không hợp lệ hoặc hết hạn."], 400);
            }
            $discountPercent = (float)$newDiscountInfo['percent_value'];
            $newFinalPrice = round($originalPrice * (1 - $discountPercent / 100), 2);
        }

        // Kiểm tra tồn kho
        if ($newQty > $inStock) {
            Utils::respond(["success" => false, "message" => "Vượt quá tồn kho, còn {$inStock} sản phẩm."], 400);
        }

        // ======== 1) Cộng lại discount cũ nếu cần ========
        // Trường hợp user bỏ discount (oldDiscountCode != '', newDiscountCode == '')
        if (!empty($oldDiscountCode) && $newDiscountCode === '') {
            $oldDiscount = $this->cartModel->getDiscountByCode($oldDiscountCode, $productId);
            if ($oldDiscount) {
                // Cộng lại cũ (phần cũ = oldQty)
                $this->cartModel->increaseDiscountQuantity((int)$oldDiscount['id'], $oldQty);
            }
        } // Hoặc user đổi sang discount khác (oldDiscountCode != newDiscountCode && cả 2 != '')
        else if (!empty($oldDiscountCode) && !empty($newDiscountCode) && $oldDiscountCode !== $newDiscountCode) {
            $oldDiscount = $this->cartModel->getDiscountByCode($oldDiscountCode, $productId);
            if ($oldDiscount) {
                // Cộng lại cũ (phần cũ = oldQty)
                $this->cartModel->increaseDiscountQuantity((int)$oldDiscount['id'], $oldQty);
            }
        } // Hoặc user giảm số lượng (oldDiscountCode == newDiscountCode != '' && newQty < oldQty)
        else if (!empty($oldDiscountCode) && $oldDiscountCode === $newDiscountCode && $newQty < $oldQty) {
            $diff = $oldQty - $newQty;
            $oldDiscount = $this->cartModel->getDiscountByCode($oldDiscountCode, $productId);
            if ($oldDiscount) {
                // Cộng lại diff
                $this->cartModel->increaseDiscountQuantity((int)$oldDiscount['id'], $diff);
            }
        }

        // ======== 2) Update cart_item ========
        $success = $this->cartModel->updateCartItemQuantity(
            $cartId,
            $productId,
            $newQty,
            $newFinalPrice,
            $newDiscountCode
        );

        if (!$success) {
            Utils::respond(["success" => false, "message" => "Lỗi khi cập nhật giỏ hàng."], 500);
        }

        // ======== 3) Trừ discount mới nếu cần ========
        // a) User vừa thêm discount code (cũ='', new!='')
        if (empty($oldDiscountCode) && !empty($newDiscountCode)) {
            $this->cartModel->decreaseDiscountQuantity((int)$newDiscountInfo['id'], $newQty);
        } // b) User đổi discount (cũ!=new!= '')
        else if (!empty($oldDiscountCode) && !empty($newDiscountCode) && $oldDiscountCode !== $newDiscountCode) {
            $this->cartModel->decreaseDiscountQuantity((int)$newDiscountInfo['id'], $newQty);
        } // c) User tăng số lượng (cùng discount cũ => old==new !='')
        else if (!empty($oldDiscountCode) && $oldDiscountCode === $newDiscountCode && $newQty > $oldQty) {
            $diff = $newQty - $oldQty;
            $this->cartModel->decreaseDiscountQuantity((int)$newDiscountInfo['id'], $diff);
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
        $discountCode = trim($data['discount_code'] ?? '');

        if ($productId <= 0 || $quantity <= 0) {
            Utils::respond(["success" => false, "message" => "ID sản phẩm hoặc số lượng không hợp lệ."], 400);
        }

        $product = $this->cartModel->getProductStockAndPrice($productId);
        if (!$product) {
            Utils::respond(["success" => false, "message" => "Sản phẩm không tồn tại hoặc đã bị ẩn."], 404);
        }

        $inStock = (int)$product['in_stock'];
        $originalPrice = (float)$product['price'];
        $finalPrice = $originalPrice;

        $discountInfo = null;
        if ($discountCode !== '') {
            $discountInfo = $this->cartModel->getValidDiscount($productId, $discountCode);
            if (!$discountInfo) {
                Utils::respond(["success" => false, "message" => "Mã giảm giá không hợp lệ hoặc hết hạn."], 400);
            }

            if ((int)$discountInfo['quantity'] <= 0) {
                Utils::respond(["success" => false, "message" => "Mã giảm giá đã được sử dụng hết."], 400);
            }

            $discountPercent = (float)$discountInfo['percent_value'];
            $finalPrice = round($originalPrice * (1 - $discountPercent / 100), 2);
        }

        $cartId = $this->cartModel->getPendingCartIdByUser($userId) ?: $this->cartModel->createCartForUser($userId);
        if (!$cartId) {
            Utils::respond(["success" => false, "message" => "Không thể tạo giỏ hàng."], 500);
        }

        // Kiểm tra nếu thêm quantity mới + đã có cũ => không vượt tồn kho
        $currentQtyInCart = $this->cartModel->getQuantityInCart($cartId, $productId);
        if ($quantity + $currentQtyInCart > $inStock) {
            Utils::respond(["success" => false, "message" => "Vượt quá số lượng tồn kho. Còn {$inStock} sản phẩm."], 400);
        }

        $success = $this->cartModel->addOrUpdateCartItem($cartId, $productId, $quantity, $finalPrice, $discountCode);

        if ($success) {
            // Nếu user thêm discount => trừ discount
            if (!empty($discountInfo)) {
                $this->cartModel->decreaseDiscountQuantity($discountInfo['id'], $quantity);
            }

            Utils::respond(["success" => true, "message" => "Thêm vào giỏ hàng thành công!"], 200);
        } else {
            Utils::respond(["success" => false, "message" => "Lỗi khi thêm vào giỏ hàng."], 500);
        }
    }
}
