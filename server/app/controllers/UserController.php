<?php
require_once __DIR__ . "/../models/User.php";

class UserController
{
    public function getUsers()
    {
        $user = new User();
        $users = $user->getUsers();

        http_response_code(200);
        echo json_encode(["success" => true, "data" => $users]);
    }

    public function createUser()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!isset($data['name']) || !isset($data['email'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Missing required fields"]);
            return;
        }

        $user = new User();
        $result = $user->createUser($data['name'], $data['email']);

        http_response_code($result['success'] ? 201 : 500);
        echo json_encode($result);
    }

    public function updateUser()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!isset($data['id']) || !isset($data['name']) || !isset($data['email'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Missing required fields"]);
            return;
        }

        $user = new User();
        $result = $user->updateUser($data['id'], $data['name'], $data['email']);

        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result);
    }

    public function deleteUser()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!isset($data['id'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Missing required fields"]);
            return;
        }

        $user = new User();
        $result = $user->deleteUser($data['id']);

        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result);
    }
}
