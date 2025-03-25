<?php

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helper/middleware.php";
require_once __DIR__ . "/../../helper/cors.php";
require_once __DIR__ . "/../../helper/utils.php";

class CategoryModel
{
  private ?PDO $conn;
  private static string $table_name = "categories";
  private Middleware $isAdmin;
  private Utils $utils;

  public function __construct()
  {
    $database = new Database();
    $this->conn = $database->getConnection();
    $this->isAdmin = new Middleware();
    $this->utils = new Utils();
  }

  // Lấy danh sách danh mục (có phân trang & sắp xếp)
  public function getAllCategory(
    int $page = 1,
    int $limit = 10,
    string $sort_by = 'desc',
    string $search = ''
  ) {
    try {
      // Chỉ admin mới được xem danh sách
      $this->isAdmin->IsAdmin();

      // Tính toán phân trang
      $offset = ($page - 1) * $limit;

      // Đảm bảo sort_by nhận giá trị hợp lệ
      $sort_by = strtolower(trim($sort_by));
      $allowedSortValues = ['asc', 'desc'];
      if (!in_array($sort_by, $allowedSortValues)) {
        $sort_by = 'desc';
      }

      // Chuẩn bị cho mệnh đề WHERE và bind param
      $whereConditions = [];
      $params = [];

      // Nếu có search
      if (!empty($search)) {
        $whereConditions[] = "category_name LIKE :search";
        $params[':search'] = '%' . $search . '%';
      }

      // Tạo whereClause nếu có điều kiện
      $whereClause = '';
      if (!empty($whereConditions)) {
        $whereClause = ' WHERE ' . implode(' AND ', $whereConditions);
      }

      // Đếm tổng số categories (để phục vụ phân trang)
      $countSql = "SELECT COUNT(*) FROM " . self::$table_name . $whereClause;
      $stmtCount = $this->conn->prepare($countSql);
      foreach ($params as $key => $value) {
        $stmtCount->bindValue($key, $value);
      }
      $stmtCount->execute();
      $totalCategories = $stmtCount->fetchColumn();

      // Lấy danh sách category theo trang + sort
      $sql = "SELECT * 
                    FROM " . self::$table_name .
        $whereClause .
        " ORDER BY category_id $sort_by 
                     LIMIT :limit 
                     OFFSET :offset";

      $stmt = $this->conn->prepare($sql);

      // Bind param search (nếu có)
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }

      // Bind limit & offset
      $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
      $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

      $stmt->execute();
      $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

      // Trả về data kèm thông tin phân trang
      return $this->utils->buildPaginatedResponse(
        true,
        "Lấy dữ liệu thành công",
        $categories,
        $page,
        $limit,
        $totalCategories,
        [
          "search"  => $search,
          "sort_by" => $sort_by
        ]
      );
    } catch (PDOException $e) {
      return $this->utils->buildPaginatedResponse(false, $e->getMessage());
    }
  }

  // Tạo category mới
  public function createCategory(array $data)
  {
    try {
      // Chỉ admin mới được thêm
      $this->isAdmin->IsAdmin();

      // Lấy tên danh mục từ mảng $data
      $category_name = $data['category_name'] ?? '';

      $sql = "INSERT INTO " . self::$table_name . " (category_name)
                    VALUES (:category_name)";

      $stmt = $this->conn->prepare($sql);
      $stmt->bindParam(':category_name', $category_name);
      $stmt->execute();

      return $this->utils->buildPaginatedResponse(
        true,
        "Thêm danh mục thành công",
      );
    } catch (PDOException $e) {
      return $this->utils->buildPaginatedResponse(false, $e->getMessage());
    }
  }

  // edit category
  public function editCategory(int $category_id, array $data)
  {
    try {
      // Chỉ admin mới được sửa
      $this->isAdmin->IsAdmin();

      // Lấy tên danh mục từ mảng $data
      $category_name = $data['category_name'] ?? '';

      // Câu lệnh UPDATE
      $sql = "UPDATE " . self::$table_name . "
                SET category_name = :category_name
                WHERE category_id = :category_id";

      $stmt = $this->conn->prepare($sql);
      $stmt->bindParam(':category_name', $category_name);
      $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
      $stmt->execute();

      if ($stmt->rowCount() === 0) {
        return $this->utils->buildPaginatedResponse(
          false,
          "Không tìm thấy hoặc không có thay đổi cho category_id = $category_id"
        );
      }

      // Thành công
      return $this->utils->buildPaginatedResponse(true, "Cập nhật danh mục thành công");
    } catch (PDOException $e) {
      return $this->utils->buildPaginatedResponse(false, $e->getMessage());
    }
  }

  // delete category
  public function deleteCategory(int $category_id)
  {
    try {
      // Chỉ admin mới được xóa
      $this->isAdmin->IsAdmin();

      // Bắt đầu transaction
      $this->conn->beginTransaction();

      // 1) Kiểm tra số lượng sản phẩm thuộc danh mục này
      $sqlCheck = "SELECT COUNT(*) FROM products WHERE category_id = :category_id";
      $stmtCheck = $this->conn->prepare($sqlCheck);
      $stmtCheck->bindValue(':category_id', $category_id, PDO::PARAM_INT);
      $stmtCheck->execute();
      $countProducts = $stmtCheck->fetchColumn();

      // 2) Nếu còn sản phẩm, xóa hết
      if ($countProducts > 0) {
        $sqlDeleteProducts = "DELETE FROM products WHERE category_id = :category_id";
        $stmtDeleteProducts = $this->conn->prepare($sqlDeleteProducts);
        $stmtDeleteProducts->bindValue(':category_id', $category_id, PDO::PARAM_INT);
        $stmtDeleteProducts->execute();
      }

      // 3) Xóa danh mục
      $sql = "DELETE FROM " . self::$table_name . " WHERE category_id = :category_id";
      $stmt = $this->conn->prepare($sql);
      $stmt->bindValue(':category_id', $category_id, PDO::PARAM_INT);
      $stmt->execute();

      if ($stmt->rowCount() === 0) {
        // Không xóa được => rollback
        $this->conn->rollBack();

        return $this->utils->buildPaginatedResponse(
          false,
          "Không tìm thấy danh mục hoặc đã bị xóa trước đó"
        );
      }

      $this->conn->commit();

      // Thành công
      return $this->utils->buildPaginatedResponse(
        true,
        "Xóa danh mục (và các sản phẩm liên quan) thành công"
      );
    } catch (PDOException $e) {
      // Nếu có lỗi, rollback để đảm bảo dữ liệu không bị xóa dang dở
      $this->conn->rollBack();
      return $this->utils->buildPaginatedResponse(false, $e->getMessage());
    }
  }
}
