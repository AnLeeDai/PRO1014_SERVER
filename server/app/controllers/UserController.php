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
  public function handleGetAllUser()
  {
    $users = $this->userModel->getAllUser();
    echo json_encode($users);
  }
}
