<?php

class CategoryController
{
    private CategoryModel $categoryModel;

    public function __construct()
    {
        $this->categoryModel = new CategoryModel();
    }

    public function handleListCategories(): void
    {
        $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
        $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT, ['options' => ['default' => 10, 'min_range' => 1, 'max_range' => 100]]);
        $sortBy = filter_input(INPUT_GET, 'sort_by', FILTER_SANITIZE_SPECIAL_CHARS) ?: 'category_name';
        $search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS) ?: '';

        if ($limit === 0) {
            $page = 1;
            $limit = PHP_INT_MAX;
        }

        $result = $this->categoryModel->getCategoriesPaginated($page, $limit, $sortBy, $search, true);

        $filters = ['sort_by' => $sortBy, 'search' => $search];

        Utils::respond(Utils::buildPaginatedResponse(
            true,
            "Lấy danh sách danh mục thành công.",
            $result['categories'] ?? [],
            $page,
            $limit,
            $result['total'] ?? 0,
            $filters
        ), 200);
    }

    public function handleCreateCategory(): void
    {
        $adminData = AuthMiddleware::isAdmin();

        $data = json_decode(file_get_contents("php://input"), true);
        $basicRules = ['category_name' => 'Tên danh mục không được để trống'];
        $basicErrors = Utils::validateBasicInput($data, $basicRules);
        if (!empty($basicErrors)) {
            Utils::respond(["success" => false, "message" => "Thiếu tên danh mục.", "errors" => $basicErrors], 400);
        }

        $categoryName = trim($data['category_name']);
        $description = isset($data['description']) ? trim($data['description']) : null;

        $formatErrors = [];
        if (empty($categoryName)) {
            $formatErrors['category_name'] = 'Tên danh mục không được để trống sau khi trim.';
        } elseif (mb_strlen($categoryName, 'UTF-8') > 100) {
            $formatErrors['category_name'] = 'Tên danh mục quá dài.';
        }
        if ($description !== null && mb_strlen($description, 'UTF-8') > 65535) {
            $formatErrors['description'] = 'Mô tả quá dài.';
        }
        if (!empty($formatErrors)) {
            Utils::respond(["success" => false, "message" => "Dữ liệu không hợp lệ.", "errors" => $formatErrors], 400);
        }

        if ($this->categoryModel->findCategoryByName($categoryName)) {
            Utils::respond(["success" => false, "message" => "Tên danh mục này đã tồn tại."], 409);
        }

        $newCategoryId = $this->categoryModel->createCategory($categoryName, $description);

        if ($newCategoryId !== false) {
            $createdCategoryData = $this->categoryModel->getCategoryById($newCategoryId, true);
            if ($createdCategoryData) {
                Utils::respond(["success" => true, "message" => "Tạo danh mục thành công!", 'category' => $createdCategoryData], 201);
            } else {
                error_log("Failed to retrieve category data after creation for ID: " . $newCategoryId);
                Utils::respond(["success" => true, "message" => "Tạo danh mục thành công nhưng lỗi lấy chi tiết.", "category_id" => $newCategoryId], 201);
            }
        } else {
            Utils::respond(["success" => false, "message" => "Lỗi khi tạo danh mục."], 500);
        }
    }

    public function handleUpdateCategory(): void
    {
        $adminData = AuthMiddleware::isAdmin();

        $data = json_decode(file_get_contents("php://input"), true);
        $basicRules = ['category_id' => 'ID danh mục không được để trống', 'category_name' => 'Tên danh mục không được để trống'];
        $basicErrors = Utils::validateBasicInput($data, $basicRules);
        if (!empty($basicErrors)) {
            Utils::respond(["success" => false, "message" => "Thiếu thông tin.", "errors" => $basicErrors], 400);
        }

        $categoryId = filter_var($data['category_id'], FILTER_VALIDATE_INT);
        $categoryName = trim($data['category_name']);
        $description = isset($data['description']) ? trim($data['description']) : null;
        $formatErrors = [];
        if ($categoryId === false || $categoryId <= 0) {
            $formatErrors['category_id'] = 'ID danh mục không hợp lệ.';
        }
        if (empty($categoryName)) {
            $formatErrors['category_name'] = 'Tên danh mục không được trống.';
        } elseif (mb_strlen($categoryName, 'UTF-8') > 100) {
            $formatErrors['category_name'] = 'Tên danh mục quá dài.';
        }
        if ($description !== null && mb_strlen($description, 'UTF-8') > 65535) {
            $formatErrors['description'] = 'Mô tả quá dài.';
        }
        if (!empty($formatErrors)) {
            Utils::respond(["success" => false, "message" => "Dữ liệu không hợp lệ.", "errors" => $formatErrors], 400);
        }

        $existingCategory = $this->categoryModel->getCategoryById($categoryId, true);
        if (!$existingCategory) {
            Utils::respond(["success" => false, "message" => "Không tìm thấy danh mục với ID [{$categoryId}]."], 404);
        }

        if ($categoryName !== $existingCategory['category_name']) {
            if ($this->categoryModel->findCategoryByNameAndNotId($categoryName, $categoryId)) {
                Utils::respond(["success" => false, "message" => "Tên danh mục '{$categoryName}' đã tồn tại."], 409);
            }
        }

        $updateSuccess = $this->categoryModel->updateCategory($categoryId, $categoryName, $description);

        if ($updateSuccess) {
            $updatedCategoryData = $this->categoryModel->getCategoryById($categoryId, true);
            Utils::respond(["success" => true, "message" => "Cập nhật danh mục thành công!", "category" => $updatedCategoryData ?: ['category_id' => $categoryId]], 200);
        } else {
            if ($categoryName !== $existingCategory['category_name'] && $this->categoryModel->findCategoryByName($categoryName)) {
                Utils::respond(["success" => false, "message" => "Lỗi cập nhật: Tên danh mục '{$categoryName}' đã tồn tại."], 409);
            } else {
                Utils::respond(["success" => false, "message" => "Lỗi khi cập nhật danh mục hoặc không có thay đổi."], 500);
            }
        }
    }

    public function handleHideCategory(): void
    {
        $adminData = AuthMiddleware::isAdmin();

        $data = json_decode(file_get_contents("php://input"), true);
        $basicRules = ['category_id' => 'ID danh mục không được để trống'];
        $basicErrors = Utils::validateBasicInput($data, $basicRules);
        if (!empty($basicErrors)) {
            Utils::respond(["success" => false, "message" => "Thiếu ID danh mục.", "errors" => $basicErrors], 400);
        }
        $categoryId = filter_var($data['category_id'], FILTER_VALIDATE_INT);
        if ($categoryId === false || $categoryId <= 0) {
            Utils::respond(["success" => false, "message" => "ID danh mục không hợp lệ."], 400);
        }

        $category = $this->categoryModel->getCategoryById($categoryId, false);
        if (!$category) {
            Utils::respond(["success" => false, "message" => "Không tìm thấy danh mục đang hoạt động với ID [{$categoryId}]."], 404);
        }

        $hideSuccess = $this->categoryModel->hideCategoryById($categoryId);

        if ($hideSuccess) {
            Utils::respond(["success" => true, "message" => "Đã ẩn danh mục ID [{$categoryId}] thành công."], 200);
        } else {
            Utils::respond(["success" => false, "message" => "Lỗi khi ẩn danh mục hoặc danh mục đã bị ẩn."], 500);
        }
    }

    public function handleUnhideCategory(): void
    {
        $adminData = AuthMiddleware::isAdmin();

        $data = json_decode(file_get_contents("php://input"), true);
        $basicRules = ['category_id' => 'ID danh mục không được để trống'];
        $basicErrors = Utils::validateBasicInput($data, $basicRules);
        if (!empty($basicErrors)) {
            Utils::respond(["success" => false, "message" => "Thiếu ID danh mục.", "errors" => $basicErrors], 400);
        }
        $categoryId = filter_var($data['category_id'], FILTER_VALIDATE_INT);
        if ($categoryId === false || $categoryId <= 0) {
            Utils::respond(["success" => false, "message" => "ID danh mục không hợp lệ."], 400);
        }

        $category = $this->categoryModel->getCategoryById($categoryId, true);
        if (!$category) {
            Utils::respond(["success" => false, "message" => "Không tìm thấy danh mục với ID [{$categoryId}]."], 404);
        }
        if ($category['is_active'] == 1) {
            Utils::respond(["success" => false, "message" => "Danh mục ID [{$categoryId}] không bị ẩn."], 400);
        }

        $unhideSuccess = $this->categoryModel->unhideCategoryById($categoryId);

        if ($unhideSuccess) {
            $unhiddenCategoryData = $this->categoryModel->getCategoryById($categoryId, true);
            Utils::respond(["success" => true, "message" => "Đã hiện lại danh mục thành công.", 'category' => $unhiddenCategoryData], 200);
        } else {
            Utils::respond(["success" => false, "message" => "Lỗi khi hiện lại danh mục."], 500);
        }
    }
}
