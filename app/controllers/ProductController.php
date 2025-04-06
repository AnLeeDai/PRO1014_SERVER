<?php

class ProductController
{
    private ProductModel $productModel;

    public function __construct()
    {
        $this->productModel = new ProductModel();
    }

    public function handleRestoreProduct(): void
    {
        AuthMiddleware::isAdmin();

        // Đọc JSON
        $raw = file_get_contents("php://input");
        $body = json_decode($raw, true);
        $productId = $body['product_id'] ?? 0;

        if (!$productId || !is_numeric($productId)) {
            Utils::respond(['success' => false, 'message' => 'ID sản phẩm không hợp lệ.'], 400);
        }

        $product = $this->productModel->getProductById((int)$productId);
        if (!$product) {
            Utils::respond(['success' => false, 'message' => 'Không tìm thấy sản phẩm.'], 404);
        }

        if (isset($product['is_active']) && $product['is_active'] == 1) {
            Utils::respond(['success' => false, 'message' => 'Sản phẩm này đang hoạt động.'], 409);
        }

        $restored = $this->productModel->restoreProduct((int)$productId);

        if ($restored) {
            Utils::respond(['success' => true, 'message' => 'Mở khóa sản phẩm thành công.'], 200);
        } else {
            Utils::respond(['success' => false, 'message' => 'Không thể mở khóa sản phẩm.'], 500);
        }
    }

    public function handleHideProduct(): void
    {
        AuthMiddleware::isAdmin();

        // Đọc body JSON
        $rawInput = file_get_contents("php://input");
        $body = json_decode($rawInput, true);
        $productId = $body['product_id'] ?? 0;

        if (!$productId || !is_numeric($productId)) {
            Utils::respond(['success' => false, 'message' => 'ID sản phẩm không hợp lệ.'], 400);
        }

        // Lấy sản phẩm
        $product = $this->productModel->getProductById((int)$productId);
        if (!$product) {
            Utils::respond(['success' => false, 'message' => 'Không tìm thấy sản phẩm.'], 404);
        }

        // Nếu đã ẩn thì không cho ẩn tiếp
        if (isset($product['is_active']) && $product['is_active'] == 0) {
            Utils::respond(['success' => false, 'message' => 'Sản phẩm này đã bị ẩn trước đó.'], 409);
        }

        // Thực hiện ẩn
        $result = $this->productModel->hideProduct((int)$productId);

        if ($result) {
            Utils::respond(['success' => true, 'message' => 'Ẩn sản phẩm thành công.'], 200);
        } else {
            Utils::respond(['success' => false, 'message' => 'Không thể ẩn sản phẩm.'], 500);
        }
    }

    public function handleUpdateProduct(): void
    {
        AuthMiddleware::isAdmin();

        $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        if (!$productId || $productId <= 0) {
            Utils::respond(['success' => false, 'message' => 'ID sản phẩm không hợp lệ.'], 400);
        }

        $productName = $_POST['product_name'] ?? '';
        $price = $_POST['price'] ?? 0;
        $shortDesc = $_POST['short_description'] ?? '';
        $fullDesc = $_POST['full_description'] ?? '';
        $extraInfo = $_POST['extra_info'] ?? '';
        $brand = $_POST['brand'] ?? '';

        if (!$productName || !$price || !$shortDesc || !$fullDesc) {
            Utils::respond(['success' => false, 'message' => 'Thiếu thông tin bắt buộc.'], 400);
        }

        // Upload thumbnail nếu có
        $thumbnailUrl = null;
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === 0) {
            $upload = Utils::uploadImage($_FILES['thumbnail'], 'products', $productName);
            if (!$upload['success']) {
                Utils::respond(['success' => false, 'message' => 'Lỗi upload ảnh thumbnail: ' . $upload['message']], 400);
            }
            $thumbnailUrl = $upload['url'];
        }

        $updated = $this->productModel->updateProduct($productId, [
            'product_name' => $productName,
            'price' => $price,
            'short_description' => $shortDesc,
            'full_description' => $fullDesc,
            'extra_info' => $extraInfo,
            'brand' => $brand,
            'thumbnail' => $thumbnailUrl,
        ]);

        if (!$updated) {
            Utils::respond(['success' => false, 'message' => 'Không thể cập nhật sản phẩm.'], 500);
        }

        // Nếu có ảnh phụ mới, thêm vào
        if (!empty($_FILES['gallery']['name'][0])) {
            $total = count($_FILES['gallery']['name']);
            for ($i = 0; $i < $total; $i++) {
                $file = [
                    'name' => $_FILES['gallery']['name'][$i],
                    'type' => $_FILES['gallery']['type'][$i],
                    'tmp_name' => $_FILES['gallery']['tmp_name'][$i],
                    'error' => $_FILES['gallery']['error'][$i],
                    'size' => $_FILES['gallery']['size'][$i],
                ];
                $upload = Utils::uploadImage($file, 'product-gallery', $productName);
                if ($upload['success']) {
                    $this->productModel->insertProductImage($productId, $upload['url']);
                }
            }
        }

        Utils::respond([
            'success' => true,
            'message' => 'Cập nhật sản phẩm thành công.'
        ], 200);
    }

    public function handleGetProductById(): void
    {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id || $id <= 0) {
            Utils::respond(['success' => false, 'message' => 'ID sản phẩm không hợp lệ.'], 400);
        }

        $product = $this->productModel->getProductById($id);

        if (!$product) {
            Utils::respond(['success' => false, 'message' => 'Không tìm thấy sản phẩm.'], 404);
        }

        Utils::respond([
            'success' => true,
            'message' => 'Lấy chi tiết sản phẩm thành công.',
            'data' => $product
        ], 200);
    }

    public function handleListProducts(): void
    {
        $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
        $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT, ['options' => ['default' => 10, 'min_range' => 1, 'max_range' => 100]]);
        $sortBy = filter_input(INPUT_GET, 'sort_by', FILTER_SANITIZE_SPECIAL_CHARS) ?: 'created_at';
        $search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS) ?: '';
        $brand = filter_input(INPUT_GET, 'brand', FILTER_SANITIZE_SPECIAL_CHARS) ?: '';
        $priceRange = filter_input(INPUT_GET, 'price_range', FILTER_SANITIZE_SPECIAL_CHARS) ?: '';

        $result = $this->productModel->getProductsPaginatedWithGallery($page, $limit, $sortBy, $search, $brand, $priceRange);

        $filters = [
            'sort_by' => $sortBy,
            'search' => $search,
            'brand' => $brand,
            'price_range' => $priceRange
        ];

        Utils::respond(Utils::buildPaginatedResponse(
            true,
            "Lấy danh sách sản phẩm thành công.",
            $result['products'],
            $page,
            $limit,
            $result['total'],
            $filters
        ), 200);
    }

    public function handleCreateProduct(): void
    {
        AuthMiddleware::isAdmin();

        $productName = $_POST['product_name'] ?? '';
        $price = $_POST['price'] ?? 0;
        $shortDesc = $_POST['short_description'] ?? '';
        $fullDesc = $_POST['full_description'] ?? '';
        $extraInfo = $_POST['extra_info'] ?? '';
        $brand = $_POST['brand'] ?? '';

        if (!$brand || !$productName || !$price || !$shortDesc || !$fullDesc || !isset($_FILES['thumbnail'])) {
            Utils::respond(['success' => false, 'message' => 'Thiếu thông tin bắt buộc.'], 400);
        }

        $thumbUpload = Utils::uploadImage($_FILES['thumbnail'], 'products', $productName);
        if (!$thumbUpload['success']) {
            Utils::respond(['success' => false, 'message' => 'Lỗi upload ảnh thumbnail: ' . $thumbUpload['message']], 400);
        }
        $thumbnailUrl = $thumbUpload['url'];

        $productId = $this->productModel->createProduct([
            'product_name' => $productName,
            'price' => $price,
            'thumbnail' => $thumbnailUrl,
            'short_description' => $shortDesc,
            'full_description' => $fullDesc,
            'extra_info' => $extraInfo,
            'brand' => $brand
        ]);

        if (!$productId) {
            Utils::respond(['success' => false, 'message' => 'Không thể tạo sản phẩm.'], 500);
        }

        if (!empty($_FILES['gallery']['name'][0])) {
            $total = count($_FILES['gallery']['name']);
            for ($i = 0; $i < $total; $i++) {
                $file = [
                    'name' => $_FILES['gallery']['name'][$i],
                    'type' => $_FILES['gallery']['type'][$i],
                    'tmp_name' => $_FILES['gallery']['tmp_name'][$i],
                    'error' => $_FILES['gallery']['error'][$i],
                    'size' => $_FILES['gallery']['size'][$i],
                ];
                $upload = Utils::uploadImage($file, 'product-gallery', $productName);
                if ($upload['success']) {
                    $this->productModel->insertProductImage($productId, $upload['url']);
                }
            }
        }

        Utils::respond([
            'success' => true,
            'message' => 'Tạo sản phẩm thành công.',
            'data' => ['product_id' => $productId]
        ], 201);
    }
}
