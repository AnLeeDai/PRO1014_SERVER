<?php

require_once __DIR__ . "/../models/CategoryModel.php";
require_once __DIR__ . "/../../helper/utils.php";

class CategoryController
{
  private CategoryModel $categoryModel;
  private Utils $utils;

  public function __construct()
  {
    $this->categoryModel = new CategoryModel();
    $this->utils = new Utils();
  }

  // Xử lý lấy danh sách category
  public function handleGetAllCategory(): void
  {
    // Lấy dữ liệu từ query params
    $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
    $limitPerPage = filter_input(INPUT_GET, 'limitPerPage', FILTER_VALIDATE_INT) ?: 10;

    // Lấy giá trị sort_by, search
    $search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
    $sort_by = strtolower($_GET['sort_by'] ?? 'desc');
    if (!in_array($sort_by, ['asc', 'desc'])) {
      $sort_by = 'desc';
    }

    // Gọi model để lấy danh sách category
    $categories = $this->categoryModel->getAllCategory($page, $limitPerPage, $sort_by, $search);

    $this->utils->respond($categories, $categories['success'] ? 200 : 400);
    echo json_encode($categories);
  }

  // Xử lý thêm mới category (có validate)
  public function handleAddCategory(): void
  {
    // Lấy dữ liệu JSON từ body
    $data = json_decode(file_get_contents('php://input'), true);

    // 1) Kiểm tra xem có trường category_name không
    if (!isset($data['category_name'])) {
      $error = $this->utils->buildResponse(false, "Thiếu trường category_name");
      $this->utils->respond($error, 400);
      echo json_encode($error);
      return;
    }

    // 2) Kiểm tra nội dung không được rỗng
    if (empty(trim($data['category_name']))) {
      $error = $this->utils->buildResponse(false, "category_name không được để trống");
      $this->utils->respond($error, 400);
      echo json_encode($error);
      return;
    }

    // Tùy chọn: Kiểm tra độ dài, ký tự đặc biệt...
    // if (mb_strlen($data['category_name']) > 255) { ... }

    // Gọi model để thêm mới category
    $result = $this->categoryModel->createCategory($data);

    $this->utils->respond($result, $result['success'] ? 200 : 400);
    echo json_encode($result);
  }

  // Xử lý cập nhật category (có validate)
  public function handleEditCategory(): void
  {
    // Nhận category_id từ URL (/categories/edit?id=1)
    $category_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    // Kiểm tra category_id có hợp lệ không
    if (empty($category_id) || $category_id <= 0) {
      $error = $this->utils->buildResponse(false, "Thiếu hoặc sai định dạng category_id");
      $this->utils->respond($error, 400);
      echo json_encode($error);
      return;
    }

    // Lấy dữ liệu JSON từ body
    $data = json_decode(file_get_contents('php://input'), true);

    // 1) Kiểm tra trường category_name (có thể tuỳ ý bắt buộc hay không)
    if (!isset($data['category_name'])) {
      $error = $this->utils->buildResponse(false, "Thiếu trường category_name");
      $this->utils->respond($error, 400);
      echo json_encode($error);
      return;
    }

    // 2) Kiểm tra nội dung không được rỗng
    if (empty(trim($data['category_name']))) {
      $error = $this->utils->buildResponse(false, "category_name không được để trống");
      $this->utils->respond($error, 400);
      echo json_encode($error);
      return;
    }

    // Gọi model để sửa category
    $result = $this->categoryModel->editCategory($category_id, $data);

    $this->utils->respond($result, $result['success'] ? 200 : 400);
    echo json_encode($result);
  }

  // Xử lý xóa category 
  public function handleDeleteCategory(): void
  {
    $category_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$category_id || $category_id <= 0) {
      $error = $this->utils->buildResponse(false, "Thiếu hoặc sai định dạng category_id");
      $this->utils->respond($error, 400);
      echo json_encode($error);
      return;
    }

    // Gọi model xóa
    $result = $this->categoryModel->deleteCategory($category_id);

    $this->utils->respond($result, $result['success'] ? 200 : 400);
    echo json_encode($result);
  }
}
