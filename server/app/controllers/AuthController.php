<?php
require_once __DIR__ . "/../models/AuthModel.php";

class AuthController
{
    private AuthModel $authModel;

    public function __construct()
    {
        $this->authModel = new AuthModel();
    }

    // register user controller
    public function handleRegister(): void
    {
        // get data from request
        $data = json_decode(file_get_contents("php://input"), true);
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';
        $password_confirm = $data['password_confirm'] ?? '';
        $full_name = $data['full_name'] ?? '';
        $email = $data['email'] ?? '';
        $phone_number = $data['phone_number'] ?? '';
        $address = $data['address'] ?? '';
        $role = $data['role'] ?? 'user';

        // validate input not null
        if (empty($username)) {
            echo json_encode(["success" => false, "message" => "Username is required"]);
            exit();
        } elseif (empty($password)) {
            echo json_encode(["success" => false, "message" => "Password is required"]);
            exit();
        } elseif (empty($full_name)) {
            echo json_encode(["success" => false, "message" => "Full name is required"]);
            exit();
        }

        // validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(["success" => false, "message" => "Invalid email address"]);
            exit();
        }

        // validate password length (minimum 6 characters), at least one uppercase letter, one lowercase letter, one number and one special character
        if (
            !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9]).{6,}$/', $password)
        ) {
            echo json_encode(["success" => false, "message" => "Password must be at least 6 characters, at least one uppercase letter, one lowercase letter, one number and one special character"]);
            exit();
        }

        // verify phone number in Vietnamese format (optional) && minimum 10 characters
        if (
            !empty($phone_number) &&
            (!preg_match('/^0[0-9]{9,10}$/', $phone_number) || strlen($phone_number) < 10)
        ) {
            echo json_encode(["success" => false, "message" => "Invalid phone number"]);
            exit();
        }

        // validate password confirm
        if ($password !== $password_confirm) {
            echo json_encode(["success" => false, "message" => "Password confirm not match"]);
            exit();
        }

        // hash password
        $password = password_hash($password, PASSWORD_DEFAULT, ['cost' => 10]);

        // fake avatar url
        $avatar_url = "https://picsum.photos/seed/picsum/200/300";

        // add user to db and show notification
        $result = $this->authModel->register(
            $username,
            $password,
            $full_name,
            $email,
            $phone_number,
            $address,
            $avatar_url,
            $role
        );

        if ($result['success'] === false) {
            http_response_code(400);
        } else {
            http_response_code(200);
        }
        echo json_encode($result);
    }

    // login user controller
    public function handleLogin(): void
    {
        // get data from request
        $data = json_decode(file_get_contents("php://input"), true);
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        // validate input not null
        if (empty($username)) {
            echo json_encode(["success" => false, "message" => "Username is required"]);
            exit();
        } elseif (empty($password)) {
            echo json_encode(["success" => false, "message" => "Password is required"]);
            exit();
        }

        // get user from db and show notification
        $result = $this->authModel->login($username, $password);
        if ($result['success'] === false) {
            http_response_code(400);
        } else {
            http_response_code(200);
        }
        echo json_encode($result);
    }
}
