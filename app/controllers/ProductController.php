<?php

class ProductController
{
    private ProductModel $productModel;

    public function __construct()
    {
        $this->productModel = new ProductModel();
    }

    public function handleGetProductById(): void
    {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id || $id <= 0) {
            Utils::respond(["success" => false, "message" => "ID sản phẩm không hợp lệ."], 400);
        }

        $product = $this->productModel->getProductById($id, true); // true để lấy cả sản phẩm bị ẩn nếu cần
        if (!$product) {
            Utils::respond(["success" => false, "message" => "Không tìm thấy sản phẩm."], 404);
        }

        Utils::respond([
            "success" => true,
            "message" => "Lấy thông tin sản phẩm thành công.",
            "product" => $product
        ], 200);
    }

    public function handleListProducts(): void
    {
        $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
        $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT, ['options' => ['default' => 10, 'min_range' => 1, 'max_range' => 100]]);
        $sortBy = filter_input(INPUT_GET, 'sort_by', FILTER_SANITIZE_SPECIAL_CHARS) ?: 'created_at';
        $search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS) ?: '';

        $result = $this->productModel->getProductsPaginated($page, $limit, $sortBy, $search, false);

        $filters = ['sort_by' => $sortBy, 'search' => $search];

        Utils::respond(Utils::buildPaginatedResponse(
            true,
            "Lấy danh sách sản phẩm thành công.",
            $result['products'] ?? [],
            $page,
            $limit,
            $result['total'] ?? 0,
            $filters
        ), 200);
    }

    public function handleCreateProduct(): void
    {
        AuthMiddleware::isAdmin();

        $data = $_POST;
        $files = $_FILES;

        $requiredFields = [
            'product_name' => 'Tên sản phẩm không được để trống',
            'price' => 'Giá sản phẩm không được để trống',
            'short_description' => 'Mô tả ngắn không được để trống',
            'full_description' => 'Mô tả chi tiết không được để trống'
        ];
        $errors = Utils::validateBasicInput($data, $requiredFields);
        if (!empty($errors)) {
            Utils::respond(["success" => false, "message" => "Thiếu thông tin.", "errors" => $errors], 400);
        }

        $productName = trim($data['product_name']);
        if ($this->productModel->findProductByName($productName)) {
            Utils::respond(["success" => false, "message" => "Sản phẩm đã tồn tại."], 409);
        }

        // Upload thumbnail
        if (!isset($files['thumbnail'])) {
            Utils::respond(["success" => false, "message" => "Thiếu ảnh thumbnail."], 400);
        }

        $uploadThumbnail = Utils::uploadImage($files['thumbnail'], 'product_thumb', $productName);
        if (!$uploadThumbnail['success']) {
            Utils::respond(['success' => false, 'message' => 'Lỗi upload thumbnail: ' . $uploadThumbnail['message']], 400);
        }
        $data['thumbnail'] = $uploadThumbnail['url'];

        // Tạo sản phẩm
        $productId = $this->productModel->createProduct($data);

        // Upload gallery nếu có
        if ($productId !== false && isset($files['gallery'])) {
            $this->productModel->uploadGalleryImages($productId, $files['gallery'], $productName);
        }

        if ($productId !== false) {
            $product = $this->productModel->getProductById($productId, true);
            Utils::respond(["success" => true, "message" => "Tạo sản phẩm thành công.", "product" => $product], 201);
        } else {
            Utils::respond(["success" => false, "message" => "Lỗi khi tạo sản phẩm."], 500);
        }
    }

    public function handleUpdateProduct(): void
    {
        AuthMiddleware::isAdmin();

        $data = $_POST;
        $files = $_FILES;

        if (!isset($data['product_id']) || !filter_var($data['product_id'], FILTER_VALIDATE_INT)) {
            Utils::respond(["success" => false, "message" => "ID sản phẩm không hợp lệ."], 400);
        }

        $requiredFields = [
            'product_id' => 'ID sản phẩm không được để trống',
            'product_name' => 'Tên sản phẩm không được để trống',
            'price' => 'Giá không được để trống'
        ];
        $errors = Utils::validateBasicInput($data, $requiredFields);
        if (!empty($errors)) {
            Utils::respond(["success" => false, "message" => "Thiếu thông tin.", "errors" => $errors], 400);
        }

        $productId = (int)$data['product_id'];
        $existing = $this->productModel->getProductById($productId, true);
        if (!$existing) {
            Utils::respond(["success" => false, "message" => "Không tìm thấy sản phẩm."], 404);
        }

        if ($data['product_name'] !== $existing['product_name']) {
            if ($this->productModel->findProductByName($data['product_name'])) {
                Utils::respond(["success" => false, "message" => "Tên sản phẩm đã tồn tại."], 409);
            }
        }

        // Nếu có ảnh thumbnail mới thì upload
        if (isset($files['thumbnail'])) {
            $uploadThumb = Utils::uploadImage($files['thumbnail'], 'product_thumb', $data['product_name']);
            if (!$uploadThumb['success']) {
                Utils::respond(["success" => false, "message" => "Lỗi upload thumbnail: " . $uploadThumb['message']], 400);
            }
            $data['thumbnail'] = $uploadThumb['url'];
        }

        $updated = $this->productModel->updateProduct($productId, $data);
        if ($updated) {
            $product = $this->productModel->getProductById($productId, true);
            Utils::respond(["success" => true, "message" => "Cập nhật sản phẩm thành công.", "product" => $product], 200);
        } else {
            Utils::respond(["success" => false, "message" => "Lỗi khi cập nhật hoặc không có thay đổi."], 500);
        }
    }

    public function handleHideProduct(): void
    {
        AuthMiddleware::isAdmin();
        $data = json_decode(file_get_contents("php://input"), true);
        $productId = (int)($data['product_id'] ?? 0);
        if ($productId <= 0) {
            Utils::respond(["success" => false, "message" => "ID sản phẩm không hợp lệ."], 400);
        }

        $product = $this->productModel->getProductById($productId, false);
        if (!$product) {
            Utils::respond(["success" => false, "message" => "Sản phẩm không hoạt động hoặc không tồn tại."], 404);
        }

        $hideSuccess = $this->productModel->hideProductById($productId);
        if ($hideSuccess) {
            Utils::respond(["success" => true, "message" => "Đã ẩn sản phẩm thành công."], 200);
        } else {
            Utils::respond(["success" => false, "message" => "Lỗi khi ẩn sản phẩm hoặc đã bị ẩn trước đó."], 500);
        }
    }

    public function handleUnhideProduct(): void
    {
        AuthMiddleware::isAdmin();
        $data = json_decode(file_get_contents("php://input"), true);
        $productId = (int)($data['product_id'] ?? 0);
        if ($productId <= 0) {
            Utils::respond(["success" => false, "message" => "ID sản phẩm không hợp lệ."], 400);
        }

        $product = $this->productModel->getProductById($productId, true);
        if (!$product) {
            Utils::respond(["success" => false, "message" => "Không tìm thấy sản phẩm."], 404);
        }

        if ((int)$product['is_active'] === 1) {
            Utils::respond(["success" => false, "message" => "Sản phẩm này đã đang hoạt động."], 400);
        }

        $unhideSuccess = $this->productModel->unhideProductById($productId);
        if ($unhideSuccess) {
            $product = $this->productModel->getProductById($productId, true);
            Utils::respond(["success" => true, "message" => "Đã mở khóa sản phẩm thành công.", "product" => $product], 200);
        } else {
            Utils::respond(["success" => false, "message" => "Không thể mở khóa sản phẩm."], 500);
        }
    }
}
