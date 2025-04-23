<?php

class ProductController
{
    private ProductModel $productModel;

    public function __construct()
    {
        $this->productModel = new ProductModel();
    }

    /* ========== GET PRODUCT BY ID ========== */
    public function handleGetProductById(): void
    {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id || $id <= 0) {
            Utils::respond(["success" => false, "message" => "ID sản phẩm không hợp lệ."], 400);
        }

        $product = $this->productModel->getProductById($id, true);
        if (!$product) {
            Utils::respond(["success" => false, "message" => "Không tìm thấy sản phẩm."], 404);
        }

        Utils::respond([
            "success" => true,
            "message" => "Lấy thông tin sản phẩm thành công.",
            "product" => $product
        ], 200);
    }

    /* ========== LIST PRODUCTS ========== */
    public function handleListProducts(): void
    {
        $page       = filter_input(INPUT_GET, 'page',       FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
        $limit      = filter_input(INPUT_GET, 'limit',      FILTER_VALIDATE_INT, ['options' => ['default' => 10, 'min_range' => 1, 'max_range' => 100]]);
        $sortBy     = filter_input(INPUT_GET, 'sort_by',    FILTER_SANITIZE_SPECIAL_CHARS) ?: 'created_at';
        $search     = filter_input(INPUT_GET, 'search',     FILTER_SANITIZE_SPECIAL_CHARS) ?: '';
        $categoryId = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT);
        $minPrice   = filter_input(INPUT_GET, 'min_price',  FILTER_VALIDATE_FLOAT);
        $maxPrice   = filter_input(INPUT_GET, 'max_price',  FILTER_VALIDATE_FLOAT);
        $brand      = filter_input(INPUT_GET, 'brand',      FILTER_SANITIZE_SPECIAL_CHARS);

        $result = $this->productModel->getProductsPaginated(
            $page,
            $limit,
            $sortBy,
            $search,
            false,
            $categoryId,
            $minPrice,
            $maxPrice,
            $brand
        );

        Utils::respond(Utils::buildPaginatedResponse(
            true,
            "Lấy danh sách sản phẩm thành công.",
            $result['products'],
            $page,
            $limit,
            $result['total'],
            [
                'sort_by'     => $sortBy,
                'search'      => $search,
                'category_id' => $categoryId,
                'min_price'   => $minPrice,
                'max_price'   => $maxPrice,
                'brand'       => $brand,
            ]
        ), 200);
    }

    /* ========== CREATE PRODUCT ========== */
    public function handleCreateProduct(): void
    {
        AuthMiddleware::isAdmin();

        $data  = $_POST;
        $files = $_FILES;

        $required = [
            'product_name'      => 'Tên sản phẩm không được để trống',
            'price'             => 'Giá sản phẩm không được để trống',
            'short_description' => 'Mô tả ngắn không được để trống',
            'full_description'  => 'Mô tả chi tiết không được để trống',
            'category_id'       => 'Danh mục không được để trống',
            'in_stock'          => 'Số lượng tồn kho không được để trống',
        ];
        $errors = Utils::validateBasicInput($data, $required);
        if ($errors) {
            Utils::respond(["success" => false, "message" => "Thiếu thông tin.", "errors" => $errors], 400);
        }

        $productName = trim($data['product_name']);
        if ($this->productModel->findProductByName($productName)) {
            Utils::respond(["success" => false, "message" => "Sản phẩm đã tồn tại."], 409);
        }

        if (!isset($files['thumbnail'])) {
            Utils::respond(["success" => false, "message" => "Thiếu ảnh thumbnail."], 400);
        }

        $thumb = Utils::uploadImage($files['thumbnail'], 'product_thumb', $productName);
        if (!$thumb['success']) {
            Utils::respond(['success' => false, 'message' => 'Lỗi upload thumbnail: ' . $thumb['message']], 400);
        }
        $data['thumbnail'] = $thumb['url'];
        $data['in_stock']  = (int)$data['in_stock'];

        $productId = $this->productModel->createProduct($data);

        if ($productId && isset($files['gallery'])) {
            $this->productModel->uploadGalleryImages($productId, $files['gallery'], $productName);
        }

        if ($productId) {
            $product = $this->productModel->getProductById($productId, true);
            Utils::respond(["success" => true, "message" => "Tạo sản phẩm thành công.", "product" => $product], 201);
        } else {
            Utils::respond(["success" => false, "message" => "Lỗi khi tạo sản phẩm."], 500);
        }
    }

    /* ========== UPDATE PRODUCT ========== */
    public function handleUpdateProduct(): void
    {
        AuthMiddleware::isAdmin();

        $data  = $_POST;   // text fields
        $files = $_FILES;  // file fields

        /* ---------- validate cơ bản ---------- */
        if (
            empty($data['product_id']) ||
            !filter_var($data['product_id'], FILTER_VALIDATE_INT)
        ) {
            Utils::respond(["success" => false, "message" => "ID sản phẩm không hợp lệ."], 400);
        }

        $required = [
            'product_id'        => 'ID sản phẩm không được để trống',
            'product_name'      => 'Tên sản phẩm không được để trống',
            'price'             => 'Giá không được để trống',
            'category_id'       => 'Danh mục không được để trống',
            'in_stock'          => 'Số lượng tồn kho không được để trống',
            'short_description' => 'Mô tả ngắn không được để trống',
            'full_description'  => 'Mô tả chi tiết không được để trống',
            'extra_info'        => 'Thông tin thêm không được để trống',
        ];
        if ($errs = Utils::validateBasicInput($data, $required)) {
            Utils::respond(["success" => false, "errors" => $errs], 400);
        }

        $productId = (int) $data['product_id'];
        $existing  = $this->productModel->getProductById($productId, true);
        if (!$existing) {
            Utils::respond(["success" => false, "message" => "Không tìm thấy sản phẩm."], 404);
        }

        /* ---------- kiểm tra tên trùng ---------- */
        if (
            $data['product_name'] !== $existing['product_name'] &&
            $this->productModel->findProductByName($data['product_name'])
        ) {
            Utils::respond(["success" => false, "message" => "Tên sản phẩm đã tồn tại."], 409);
        }

        /*************** THUMBNAIL (file OR url) ***************/
        if (!empty($files['thumbnail']['name'])) {
            $up = Utils::uploadImage($files['thumbnail'], 'product_thumb', $data['product_name']);
            if (!$up['success']) {
                Utils::respond(["success" => false, "message" => "Lỗi upload thumbnail: " . $up['message']], 400);
            }
            $data['thumbnail'] = $up['url'];
        } elseif (
            !empty($data['thumbnail_url']) &&
            filter_var($data['thumbnail_url'], FILTER_VALIDATE_URL)
        ) {
            $data['thumbnail'] = trim($data['thumbnail_url']);
        }

        /*************** GALLERY (file và/hoặc url) ***************/
        $mode = $data['gallery_mode'] ?? 'replace';  // append | replace

        $hasGalleryFile = !empty($files['gallery']['name'][0]);
        $hasGalleryUrl  = !empty($data['gallery_urls']);

        /* URL có thể gửi 1 hoặc nhiều -> ép thành mảng */
        $galleryUrls = [];
        if ($hasGalleryUrl) {
            $galleryUrls = is_array($data['gallery_urls'])
                ? $data['gallery_urls']
                : [$data['gallery_urls']];
        }

        /* --- xử lý replace (xoá hết cũ rồi add mới) --- */
        if ($mode === 'replace' && ($hasGalleryFile || $hasGalleryUrl)) {
            // xoá toàn bộ gallery cũ
            $this->productModel->replaceGalleryImages(
                $productId,
                $hasGalleryFile ? $files['gallery'] : ['name' => [], 'type' => [], 'tmp_name' => [], 'error' => [], 'size' => []],
                $data['product_name']
            );
            // thêm url mới (nếu có)
            if ($hasGalleryUrl) {
                $this->productModel->addGalleryUrls($productId, $galleryUrls);
            }
        }

        /* --- append (giữ ảnh cũ, thêm mới) --- */
        if ($mode === 'append') {
            if ($hasGalleryFile) {
                $this->productModel->uploadGalleryImages($productId, $files['gallery'], $data['product_name']);
            }
            if ($hasGalleryUrl) {
                $this->productModel->addGalleryUrls($productId, $galleryUrls);
            }
        }

        /*************** UPDATE các field còn lại ***************/
        $updated = $this->productModel->updateProduct($productId, $data);

        if ($updated) {
            $product = $this->productModel->getProductById($productId, true);
            Utils::respond([
                "success" => true,
                "message" => "Cập nhật sản phẩm thành công.",
                "product" => $product
            ], 200);
        }

        Utils::respond([
            "success" => false,
            "message" => "Lỗi khi cập nhật hoặc không có thay đổi."
        ], 500);
    }

    /* ========== HIDE / UNHIDE PRODUCT ========== */
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

        $ok = $this->productModel->hideProductById($productId);
        Utils::respond(
            $ok
                ? ["success" => true, "message" => "Đã ẩn sản phẩm thành công."]
                : ["success" => false, "message" => "Lỗi khi ẩn sản phẩm hoặc đã bị ẩn trước đó."],
            $ok ? 200 : 500
        );
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

        $ok = $this->productModel->unhideProductById($productId);
        Utils::respond(
            $ok
                ? ["success" => true, "message" => "Đã mở khóa sản phẩm thành công.", "product" => $this->productModel->getProductById($productId, true)]
                : ["success" => false, "message" => "Không thể mở khóa sản phẩm."],
            $ok ? 200 : 500
        );
    }
}
