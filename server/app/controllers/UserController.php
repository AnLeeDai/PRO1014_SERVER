<?php

require_once __DIR__ . "/../models/UserModel.php";
require_once __DIR__ . "/../../helper/utils.php";

class UserController
{
  private UserModel $userModel;
  private Utils $utils;

  public function __construct()
  {
    $this->userModel = new UserModel();
    $this->utils = new Utils();
  }

  // Xử lý lấy danh sách user
  public function handleGetAllUser(): void
  {
    // Lấy dữ liệu từ query params 
    $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
    $limitPerPage = filter_input(INPUT_GET, 'limitPerPage', FILTER_VALIDATE_INT) ?: 10;


    // Lấy dữ liệu từ query params 
    $search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
    $sort_by = strtolower($_GET['sort_by'] ?? 'desc');

    // Đảm bảo sort_by chỉ nhận giá trị hợp lệ
    if (!in_array($sort_by, ['asc', 'desc'])) {
      $sort_by = 'desc';
    }

    // Gọi model để lấy danh sách user
    $users = $this->userModel->getAllUser($page, $limitPerPage, $sort_by, $search);

    $this->utils->respond($users, $users['success'] ? 200 : 400);

    // Trả về JSON response
    echo json_encode($users);
  }
}
