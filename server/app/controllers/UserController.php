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
//      call the model function to get all user
    $users = $this->userModel->getAllUser();

//    return the response
    echo json_encode($users);
  }
}
