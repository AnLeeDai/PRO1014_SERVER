<?php

class DiscountController
{
    private DiscountModel $discountModel;

    public function __construct()
    {
        $this->discountModel = new DiscountModel();
    }

    /**
     * [API] Xóa hẳn 1 mã giảm giá (dành cho admin)
     */
    public function handleDeleteDiscount(): void
    {
        AuthMiddleware::isAdmin();

        // Lấy discount_id từ JSON body
        $input = json_decode(file_get_contents("php://input"), true);
        $discountId = isset($input['discount_id']) ? (int)$input['discount_id'] : 0;

        if ($discountId <= 0) {
            Utils::respond(['success' => false, 'message' => 'ID mã giảm giá không hợp lệ.'], 400);
        }

        $discount = $this->discountModel->getDiscountById($discountId);
        if (!$discount) {
            Utils::respond(['success' => false, 'message' => 'Mã giảm giá không tồn tại.'], 404);
        }

        $deleted = $this->discountModel->deleteDiscount($discountId);
        if ($deleted) {
            Utils::respond(['success' => true, 'message' => 'Xóa mã giảm giá thành công.'], 200);
        } else {
            Utils::respond(['success' => false, 'message' => 'Không thể xóa mã giảm giá.'], 500);
        }
    }

    /**
     * [API] Lấy danh sách mã giảm giá (admin)
     */
    public function handleListDiscounts(): void
    {
        $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
        $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT, ['options' => ['default' => 10, 'min_range' => 1]]);
        $sortBy = filter_input(INPUT_GET, 'sort_by', FILTER_SANITIZE_SPECIAL_CHARS) ?: 'created_at';
        $search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS) ?: '';

        $result = $this->discountModel->getDiscountsPaginated($page, $limit, $sortBy, $search);

        $filters = ['sort_by' => $sortBy, 'search' => $search];

        Utils::respond(Utils::buildPaginatedResponse(
            true,
            "Lấy danh sách mã giảm giá thành công.",
            $result['discounts'],
            $page,
            $limit,
            $result['total'],
            $filters
        ), 200);
    }

    /**
     * [API] Tạo mới mã giảm giá (admin)
     */
    public function handleCreateDiscount(): void
    {
        AuthMiddleware::isAdmin();

        $data = json_decode(file_get_contents("php://input"), true);

        $requiredFields = ['discount_code', 'percent_value', 'product_id', 'quantity', 'start_date', 'end_date'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                Utils::respond(['success' => false, 'message' => "Thiếu trường: $field"], 400);
            }
        }

        $discountCode = strtoupper(trim($data['discount_code']));
        $percentValue = (float)$data['percent_value'];
        $productId = (int)$data['product_id'];
        $quantity = (int)$data['quantity'];
        $totalQuantity = $quantity;
        $startDate = $data['start_date'];
        $endDate = $data['end_date'];

        // Validate logic
        if ($percentValue <= 0 || $percentValue > 100) {
            Utils::respond(['success' => false, 'message' => "Phần trăm giảm giá phải trong khoảng 1-100"], 400);
        }
        if ($quantity <= 0) {
            Utils::respond(['success' => false, 'message' => "Số lượng phải lớn hơn 0"], 400);
        }

        $startTime = strtotime($startDate);
        $endTime = strtotime($endDate);
        if ($endTime < $startTime) {
            Utils::respond([
                'success' => false,
                'message' => "Ngày kết thúc không được nhỏ hơn ngày bắt đầu."
            ], 400);
        }

        $newId = $this->discountModel->createDiscount([
            'discount_code' => $discountCode,
            'percent_value' => $percentValue,
            'product_id' => $productId,
            'quantity' => $quantity,
            'total_quantity' => $totalQuantity,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);

        if ($newId === false) {
            Utils::respond(['success' => false, 'message' => "Mã giảm giá đã tồn tại"], 409);
        } else {
            Utils::respond([
                'success' => true,
                'message' => "Tạo mã giảm giá thành công.",
                'data' => [
                    'id' => $newId,
                    'discount_code' => $discountCode
                ]
            ], 201);
        }
    }

    /**
     * [API] Lấy danh sách mã giảm giá còn hạn, còn quantity cho 1 sản phẩm
     */
    public function handleGetAvailableDiscountsForProduct(): void
    {
        AuthMiddleware::isUser();
        $productId = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT, ['options' => ['default' => 0, 'min_range' => 1]]);
        if ($productId <= 0) {
            Utils::respond(["success" => false, "message" => "ID sản phẩm không hợp lệ."], 400);
        }

        $discounts = $this->discountModel->getAvailableDiscountsForProduct($productId);
        Utils::respond([
            'success' => true,
            'message' => 'Lấy mã giảm giá khả dụng thành công.',
            'data' => $discounts
        ], 200);
    }

    /**
     * [API] Hủy mã giảm giá đang dùng cho 1 sản phẩm
     */
    public function handleRemoveDiscountUsage(): void
    {
        $user = AuthMiddleware::isUser();
        $userId = $user['user_id'];

        $data = json_decode(file_get_contents("php://input"), true);
        $productId = (int)($data['product_id'] ?? 0);
        $discountCode = trim($data['discount_code'] ?? '');

        if ($productId <= 0 || $discountCode === '') {
            Utils::respond(["success" => false, "message" => "Thiếu product_id hoặc discount_code."], 400);
        }

        // Tìm discount
        $discount = $this->discountModel->getDiscountByCodeAndProduct($discountCode, $productId);
        if (!$discount) {
            Utils::respond(["success" => false, "message" => "Mã giảm giá không tồn tại hoặc không hợp lệ."], 400);
        }

        // Cập nhật cart_item => gỡ discount_code = NULL
        $cartId = $this->discountModel->getPendingCartIdByUser($userId);
        if (!$cartId) {
            Utils::respond(["success" => false, "message" => "Không tìm thấy giỏ hàng."], 404);
        }

        $ok = $this->discountModel->removeDiscountFromCartItem($cartId, $productId, $discountCode);
        if (!$ok) {
            Utils::respond(["success" => false, "message" => "Không thể xóa mã giảm giá trong giỏ hàng."], 500);
        }

        // Xóa usage (nếu có track usage)
        $this->discountModel->removeDiscountUsage($userId, (int)$discount['id'], $productId);

        // Optional: Tùy logic => increaseDiscountQuantity($discount['id'], X) để cộng lại lượt discount
        // ...

        Utils::respond(["success" => true, "message" => "Hủy mã giảm giá cho sản phẩm {$productId} thành công."], 200);
    }
}
