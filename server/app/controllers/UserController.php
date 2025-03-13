<?php
require_once __DIR__ . "/../models/UserModel.php";

class UserController
{
        public function getUsers()
        {
                $user = new UserModel();
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

                $user = new UserModel();
                $result = $user->createUser($data['name'], $data['email']);

                http_response_code($result['success'] ? 201 : 500);
                echo json_encode($result);
        }

        public function updateUser()
        {
                $id = isset($_GET['id']) ? $_GET['id'] : null;
                $data = json_decode(file_get_contents("php://input"), true);

                if (!$id || !isset($data['name']) || !isset($data['email'])) {
                        http_response_code(400);
                        echo json_encode(["success" => false, "message" => "Missing required fields"]);
                        return;
                }

                $user = new UserModel();
                $result = $user->updateUser($id, $data['name'], $data['email']);

                http_response_code($result['success'] ? 200 : 500);
                echo json_encode($result);
        }

        public function deleteUser()
        {
                $id = isset($_GET['id']) ? $_GET['id'] : null;

                if (!$id) {
                        http_response_code(400);
                        echo json_encode(["success" => false, "message" => "Missing required fields"]);
                        return;
                }

                $user = new UserModel();
                $result = $user->deleteUser($id);

                http_response_code($result['success'] ? 200 : 500);
                echo json_encode($result);
        }
}
