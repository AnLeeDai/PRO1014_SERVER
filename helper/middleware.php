<?php

class Middleware
{
    // check user or admin
    public function IsAdmin()
    {
        // check if user is logged in
        if (!isset($_SESSION['user'])) {
            http_response_code(401);
            echo json_encode([
                "success" => false,
                "message" => "Unauthorized: You must login first."
            ]);
            exit();
        }

        // check if user is not admin
        if ($_SESSION['user']['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode([
                "success" => false,
                "message" => "Forbidden: You do not have permission to access this resource."
            ]);
            exit();
        }
    }
}
