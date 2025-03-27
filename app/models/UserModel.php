<?php

require_once __DIR__ . "/../controllers/AuthController.php";
require_once __DIR__ . "/../../helper/middleware.php";
require_once __DIR__ . "/../../helper/utils.php";

class UserModel
{
  private ?PDO $conn;
  private static string $table_name = "users";
  private Middleware $isAdmin;
  private Utils $utils;

  public function __construct()
  {
    $database = new Database();
    $this->conn = $database->getConnection();
    $this->isAdmin = new Middleware();
    $this->utils = new Utils();
  }

  // Lấy danh sách người dùng có phân trang và sắp xếp
  public function getAllUser(
    int $page = 1,
    int $limit = 10,
        $sort_by = 'desc',
        $search = ''
  ): array
  {
    try {
      // Kiểm tra quyền admin
      $this->isAdmin->IsAdmin();

      // Xác định offset cho phân trang
      $offset = ($page - 1) * $limit;

      // Kiểm tra giá trị sort_by hợp lệ (chỉ cho phép 'asc' hoặc 'desc')
      $sort_by = strtolower(trim($sort_by));
      $allowedSortValues = ['asc', 'desc'];
      if (!in_array($sort_by, $allowedSortValues)) {
        $sort_by = 'desc'; // Mặc định giảm dần
      }

      // Xây dựng điều kiện WHERE
      $whereConditions = ["role != 'admin'"];
      $params = [];

      if (!empty($search)) {
        $whereConditions[] = "username LIKE :search";
        $params[':search'] = '%' . $search . '%';
      }

      // Ghép các điều kiện lại thành câu lệnh SQL
      $whereClause = " WHERE " . implode(" AND ", $whereConditions);

      // Lấy tổng số lượng người dùng
      $countSql = "SELECT COUNT(*) FROM " . self::$table_name . $whereClause;
      $stmtCount = $this->conn->prepare($countSql);

      foreach ($params as $key => $value) {
        $stmtCount->bindValue($key, $value, PDO::PARAM_STR);
      }
      $stmtCount->execute();
      $totalItems = $stmtCount->fetchColumn();

      // Lấy danh sách người dùng theo trang, có sắp xếp
      $query = "SELECT * FROM " . self::$table_name . $whereClause;
      $query .= " ORDER BY user_id " . strtoupper($sort_by);
      $query .= " LIMIT :limit OFFSET :offset";

      $stmt = $this->conn->prepare($query);
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
      }
      $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
      $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
      $stmt->execute();
      $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

      // Xóa trường password trước khi trả về
      foreach ($users as $key => $user) {
        unset($users[$key]['password']);
      }

      // Trả về kết quả JSON
      return $this->utils->buildResponse(
        true,
        "Lấy dữ liệu thành công",
        $users,
        $page,
        $limit,
        (int)$totalItems,
        [
          "search" => $search,
          "sort_by" => $sort_by
        ]
      );
    } catch (PDOException $e) {
      return $this->utils->buildResponse(
        false,
        "Database error: " . $e->getMessage()
      );
    }
  }

  // Lấy thông tin người dùng theo ID
  public function getUserById(int $user_id): array
  {
    try {
      // Trước hết kiểm tra xem đã đăng nhập chưa
      if (!isset($_SESSION['user']['user_id'])) {
        return $this->utils->buildResponse(false, "Bạn chưa đăng nhập");
      }

      // Thực hiện truy vấn lấy thông tin người dùng
      $query = "SELECT * FROM " . self::$table_name . " WHERE user_id = :user_id";
      $stmt = $this->conn->prepare($query);
      $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
      $stmt->execute();

      $user = $stmt->fetch(PDO::FETCH_ASSOC);

      // Nếu không tìm thấy người dùng trong DB
      if (!$user) {
        return $this->utils->buildResponse(false, "Không tìm thấy người dùng");
      }

      // Kiểm tra nếu user_id đang request khác với user_id trong session
      if ($_SESSION['user']['user_id'] !== $user_id) {
        return $this->utils->buildResponse(false, "Bạn không có quyền truy cập thông tin người dùng này");
      }

      // Xoá trường password trước khi trả về
      unset($user['password']);
      unset($user['role']);
      unset($user['user_id']);

      // Trả về kết quả JSON
      return $this->utils->buildResponse(true, "Lấy dữ liệu thành công", $user);

    } catch (PDOException $e) {
      return $this->utils->buildResponse(false, "Database error: " . $e->getMessage());
    }
  }
}