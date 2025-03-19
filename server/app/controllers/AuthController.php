<?php
require_once __DIR__ . "/../models/AuthModel.php";
require_once __DIR__ . "/../../helper/utils.php";

class AuthController
{
    private AuthModel $authModel;
    private Utils $utils;

    public function __construct()
    {
        $this->authModel = new AuthModel();
        $this->utils = new Utils();
    }

//    handle register user
    public function handleRegister(): void
    {
//        get request body
        $data = json_decode(file_get_contents("php://input"), true);

//       get data from request body
        $username = trim($data['username']);
        $password = trim($data['password']);
        $password_confirm = trim($data['password_confirm'] ?? '');
        $email = trim($data['email'] ?? '');
        $phone_number = trim($data['phone_number'] ?? '');
        $role = trim($data['role'] ?? 'user');

//        validate input
        $this->utils->validateInput($data, [
            'username' => 'Username is required',
            'password' => 'Password is required',
            'full_name' => 'Full name is required'
        ]);

//        validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->utils->respond(["success" => false, "message" => "Invalid email address"], 400);
        }

//        validate role
        if (!in_array($role, ['user', 'admin'])) {
            $this->utils->respond(["success" => false, "message" => "Invalid role"], 400);
        }

//        validate username
        if (!preg_match('/^[a-zA-Z0-9]{6,}$/', $username)) {
            $this->utils->respond(["success" => false, "message" => "Username must be at least 6 characters and not contain special characters"], 400);
        }

//        validate password
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9]).{6,}$/', $password)) {
            $this->utils->respond(["success" => false, "message" => "Password must be at least 6 characters, at least one uppercase letter, one lowercase letter, one number and one special character"], 400);
        }

//        validate phone number
        if (!empty($phone_number) && (!preg_match('/^0[0-9]{9,10}$/', $phone_number) || strlen($phone_number) < 10)) {
            $this->utils->respond(["success" => false, "message" => "Invalid phone number"], 400);
        }

//        validate password confirm
        if ($password !== $password_confirm) {
            $this->utils->respond(["success" => false, "message" => "Password confirm not match"], 400);
        }

//        hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT, ['cost' => 10]);

//        set default avatar for user
        $avatar_url = "https://picsum.photos/seed/picsum/200/300";

//        call register function from model
        $result = $this->authModel->register(
            $username,
            $hashed_password,
            trim($data['full_name']),
            $email,
            $phone_number,
            trim($data['address'] ?? ''),
            $avatar_url,
            $role
        );

//        return response
        $this->utils->respond($result, $result['success'] ? 200 : 400);
    }

//    handle login user
    public function handleLogin(): void
    {
//        get request body
        $data = json_decode(file_get_contents("php://input"), true);

//        validate input
        $this->utils->validateInput($data, [
            'username' => 'Username is required',
            'password' => 'Password is required'
        ]);

//        call login function from model
        $result = $this->authModel->login(trim($data['username']), trim($data['password']));

//        return response
        $this->utils->respond($result, $result['success'] ? 200 : 400);
    }

    public function handleChangePassword(): void
    {
//        get request body
        $data = json_decode(file_get_contents("php://input"), true);

//        validate input
        $this->utils->validateInput($data, [
            'username' => 'Username is required',
            'old_password' => 'Old password is required',
            'new_password' => 'New password is required'
        ]);

//        validate new password
        $new_password = trim($data['new_password']);
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9]).{6,}$/', $new_password)) {
            $this->utils->respond(["success" => false, "message" => "Password must be at least 6 characters, at least one uppercase letter, one lowercase letter, one number and one special character"], 400);
        }

//        hash new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT, ['cost' => 10]);

//        call change password function from model
        $result = $this->authModel->changePassword(
            trim($data['username']),
            trim($data['old_password']),
            $hashed_password
        );

//        return response
        $this->utils->respond($result, $result['success'] ? 200 : 400);
    }
}
