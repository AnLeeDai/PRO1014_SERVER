<?php

require_once __DIR__ . "/../models/UserModel.php";

class UserController
{
  private UserModel $userModel;

  public function __construct()
  {
    $this->userModel = new UserModel();
  }

  // get all user controller (có phân trang)
  public function handleGetAllUser(): void
  {
    // get page from query params
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

    // set limit for pagination
    $limitPerPage = isset($_GET['limitPerPage']) ? (int)$_GET['limitPerPage'] : 10;

    // call the model function to get all user
    $users = $this->userModel->getAllUser($page, $limitPerPage);

    // return the response
    echo json_encode($users);
  }
}
