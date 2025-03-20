<?php

require_once __DIR__ . "/../models/UserModel.php";

class UserController
{
  private UserModel $userModel;

  public function __construct()
  {
    $this->userModel = new UserModel();
  }

  // get all user controller
  public function handleGetAllUser(): void
  {
    // get page from query params
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

    // set limit for pagination
    $limitPerPage = isset($_GET['limitPerPage']) ? (int)$_GET['limitPerPage'] : 10;

    // sort by desc or asc
    $sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'desc';

    // search user by name
    $search = isset($_GET['search']) ? $_GET['search'] : '';

    // call the model function to get all user
    $users = $this->userModel->getAllUser($page, $limitPerPage, $sort_by, $search);

    // return the response
    echo json_encode($users);
  }
}
